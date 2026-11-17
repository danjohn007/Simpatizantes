<?php
/**
 * Controlador de Simpatizantes
 * Maneja operaciones CRUD de simpatizantes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../models/Simpatizante.php';
require_once __DIR__ . '/../models/Campo.php';
require_once __DIR__ . '/../models/LogAuditoria.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../helpers/validacion.php';

class SimpatizanteController {
    private $model;
    private $logModel;
    private $auth;
    
    public function __construct() {
        $this->model = new Simpatizante();
        $this->logModel = new LogAuditoria();
        $this->auth = new AuthController();
    }
    
    /**
     * Lista simpatizantes con filtros
     */
    public function listar($filtros = [], $page = 1) {
        $this->auth->requiereAutenticacion();
        
        // Filtrar por campaña si no puede ver todas las campañas
        if (!$this->auth->puedeVerTodasLasCampanas()) {
            $filtros['campana_id'] = $this->auth->obtenerCampanaId();
        }
        
        // Filtrar por capturista si es el rol actual
        if ($this->auth->obtenerRol() === 'capturista') {
            $filtros['capturista_id'] = $this->auth->obtenerUsuarioId();
        }
        
        $simpatizantes = $this->model->obtenerTodos($filtros, $page);
        $total = $this->model->contarTotal($filtros);
        $totalPaginas = ceil($total / RECORDS_PER_PAGE);
        
        return [
            'simpatizantes' => $simpatizantes,
            'total' => $total,
            'pagina_actual' => $page,
            'total_paginas' => $totalPaginas,
            'por_estatus' => $this->model->contarPorEstatus($filtros)
        ];
    }
    
    /**
     * Obtiene un simpatizante por ID
     */
    public function obtener($id) {
        $this->auth->requiereAutenticacion();

        $simpatizante = $this->model->obtenerPorId($id);

        if ($simpatizante && !$this->puedeAcceder($simpatizante)) {
            return ['error' => 'No tiene permisos para ver este simpatizante'];
        }

        return $simpatizante;
    }

    /**
     * ¿Puede el usuario actual ver/modificar este registro?
     * - Capturista: solo los que capturó.
     * - Admin y super admin: todos.
     * - Resto (coordinador, candidato): solo los de su campaña.
     */
    public function puedeAcceder($simpatizante) {
        if ($this->auth->puedeVerTodasLasCampanas()) {
            return true;
        }
        if ($this->auth->obtenerRol() === 'capturista') {
            return (int)$simpatizante['capturista_id'] === (int)$this->auth->obtenerUsuarioId();
        }
        $campana = $this->auth->obtenerCampanaId();
        return $campana && (int)$simpatizante['campana_id'] === (int)$campana;
    }

    /**
     * Normaliza los datos capturados: espacios, mayúsculas en CURP/clave, teléfonos a 10 dígitos, sección a 4 dígitos.
     */
    public function normalizar($datos) {
        return vd_normalizar_simpatizante($datos);
    }

    /**
     * Aviso si un capturista registra fuera de las secciones que se le asignaron.
     * Sin zonas asignadas (o para otros roles) no hay restricción ni aviso.
     */
    public function avisoZona($seccion) {
        $seccion = vd_normalizar_seccion($seccion);
        if ($this->auth->obtenerRol() !== 'capturista' || !preg_match('/^\d{4}$/', (string)$seccion)) {
            return null;
        }
        $zonas = (new Campo())->seccionesDe($this->auth->obtenerUsuarioId());
        if ($zonas && !in_array($seccion, $zonas, true)) {
            $lista = count($zonas) > 6 ? implode(', ', array_slice($zonas, 0, 6)) . '…' : implode(', ', $zonas);
            return 'La sección ' . $seccion . ' está fuera de sus zonas asignadas (' . $lista . '). Se guarda, pero avise a su coordinador si es correcto.';
        }
        return null;
    }

    /**
     * Revisa duplicados y consistencia de un registro (para el aviso en pantalla mientras se captura).
     * Quien no puede ver todo el padrón recibe el nombre enmascarado.
     */
    public function revisarCalidad($datos, $excludeId = null) {
        $this->auth->requiereAutenticacion();
        $datos = $this->normalizar($datos);

        $avisos = [];
        $fecha = vd_normalizar_fecha($datos['fecha_nacimiento'] ?? '');
        $sexo = $datos['sexo'] ?? '';
        $curp = vd_validar_curp($datos['curp'] ?? '', $fecha, $sexo);
        $clave = vd_validar_clave_elector($datos['clave_elector'] ?? '', $fecha, $sexo);
        $errores = [];
        if ($curp['errores']) { $errores['curp'] = $curp['errores'][0]; }
        if ($clave['errores']) { $errores['clave_elector'] = $clave['errores'][0]; }
        foreach ($curp['avisos'] as $a) { $avisos[] = ['campo' => 'curp', 'texto' => $a]; }
        foreach ($clave['avisos'] as $a) { $avisos[] = ['campo' => 'clave_elector', 'texto' => $a]; }
        if (!vd_telefono_valido($datos['telefono'] ?? '')) { $errores['telefono'] = 'El teléfono debe tener 10 dígitos'; }
        if (!vd_telefono_valido($datos['whatsapp'] ?? '')) { $errores['whatsapp'] = 'El WhatsApp debe tener 10 dígitos'; }

        if ($zona = $this->avisoZona($datos['seccion_electoral'] ?? '')) {
            $avisos[] = ['campo' => 'seccion_electoral', 'texto' => $zona];
        }

        $verTodo = $this->auth->puedeVerTodasLasCampanas();
        $duplicados = [];
        foreach ($this->model->buscarDuplicados($datos, $excludeId) as $d) {
            $mio = $this->puedeAcceder($d);
            $duplicados[] = [
                'tipo' => $d['tipo'],
                'campos' => $d['campos'],
                'texto' => 'Ya existe un registro con ' . Simpatizante::describirCampos($d['campos']),
                'id' => $mio ? (int)$d['id'] : null,
                'nombre' => ($verTodo || $mio) ? $d['nombre_completo'] : vd_enmascarar_nombre($d['nombre_completo']),
                'seccion' => $d['seccion_electoral'],
                'fecha' => $d['created_at'] ? date('d/m/Y', strtotime($d['created_at'])) : null,
            ];
        }

        return ['errores' => $errores, 'avisos' => $avisos, 'duplicados' => $duplicados];
    }

    /** Temas de colonia de un simpatizante al que el usuario tiene acceso: [clave => etiqueta]. */
    public function temas($id) {
        $this->auth->requiereAutenticacion();
        $simp = $this->model->obtenerPorId($id);
        if (!$simp || !$this->puedeAcceder($simp)) { return []; }
        $r = [];
        foreach ($this->model->temasDe($id) as $t) { $r[$t] = Simpatizante::TEMAS[$t] ?? $t; }
        return $r;
    }

    /** Historial de cambios de estado de un simpatizante al que el usuario tiene acceso. */
    public function historialValidacion($id) {
        $this->auth->requiereAutenticacion();
        $simp = $this->model->obtenerPorId($id);
        return ($simp && $this->puedeAcceder($simp)) ? $this->model->historialValidacion($id) : [];
    }

    /**
     * Cambia el estado de validación (pendiente / validado / rechazado). Solo admin, super admin y coordinador.
     */
    public function cambiarEstatus($id, $estatus, $motivo = '') {
        $this->auth->requiereAutenticacion();
        if (!$this->auth->tieneRol(['super_admin', 'admin', 'coordinador'])) {
            return ['error' => 'No tiene permisos para cambiar el estado de validación'];
        }
        $simp = $this->model->obtenerPorId($id);
        if (!$simp) {
            return ['error' => 'Simpatizante no encontrado'];
        }
        if (!$this->puedeAcceder($simp)) {
            return ['error' => 'No tiene permisos sobre este simpatizante'];
        }

        $res = $this->model->cambiarEstatus($id, $estatus, $motivo, $this->auth->obtenerUsuarioId());
        if (!empty($res['success'])) {
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(), 'validacion_simpatizante', 'simpatizantes', $id,
                ['estatus' => $res['anterior']], ['estatus' => $estatus, 'motivo' => $motivo !== '' ? $motivo : null]
            );
        }
        return $res;
    }

    /**
     * Crea un nuevo simpatizante
     * @param array $datos Datos del simpatizante
     * @param bool $esRegistroPublico Indica si es un registro público (no requiere autenticación)
     */
    public function crear($datos, $esRegistroPublico = false) {
        // Si no es registro público, requiere autenticación
        if (!$esRegistroPublico) {
            $this->auth->requiereAutenticacion();
        }
        
        $datos = $this->normalizar($datos);
        
        // Validaciones
        $errores = $this->validar($datos, null, $esRegistroPublico);
        if (!empty($errores)) {
            return ['error' => 'Errores de validación', 'errores' => $errores];
        }
        
        // Asignar capturista actual si no está asignado y no es registro público
        if (empty($datos['capturista_id']) && !$esRegistroPublico) {
            $datos['capturista_id'] = $this->auth->obtenerUsuarioId();
        }
        
        $result = $this->model->crear($datos);
        
        if (isset($result['success'])) {
            // Registrar en log solo si hay un usuario autenticado
            if ($this->auth->estaAutenticado()) {
                $this->logModel->registrar(
                    $this->auth->obtenerUsuarioId(),
                    'crear_simpatizante',
                    'simpatizantes',
                    $result['id'],
                    null,
                    $datos
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Actualiza un simpatizante
     */
    public function actualizar($id, $datos) {
        $this->auth->requiereAutenticacion();
        
        // Obtener datos anteriores para log
        $datosAnteriores = $this->model->obtenerPorId($id);
        
        if (!$datosAnteriores) {
            return ['error' => 'Simpatizante no encontrado'];
        }
        
        // Verificar permisos
        if (!$this->puedeAcceder($datosAnteriores)) {
            return ['error' => 'No tiene permisos para editar este simpatizante'];
        }

        $datos = $this->normalizar($datos);

        // Validaciones
        $errores = $this->validar($datos, $id);
        if (!empty($errores)) {
            return ['error' => 'Errores de validación', 'errores' => $errores];
        }

        $result = $this->model->actualizar($id, $datos);
        
        if ($result) {
            // Registrar en log
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'actualizar_simpatizante',
                'simpatizantes',
                $id,
                $datosAnteriores,
                $datos
            );
            
            return ['success' => true];
        }
        
        return ['error' => Database::getInstance()->describirError('actualizar el simpatizante')];
    }
    
    /**
     * Elimina un simpatizante
     */
    public function eliminar($id) {
        $this->auth->requiereRol(['super_admin', 'admin']);
        
        $datosAnteriores = $this->model->obtenerPorId($id);
        
        if (!$datosAnteriores) {
            return ['error' => 'Simpatizante no encontrado'];
        }
        
        $result = $this->model->eliminar($id);
        
        if ($result) {
            // Registrar en log
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'eliminar_simpatizante',
                'simpatizantes',
                $id,
                $datosAnteriores,
                null
            );
            
            return ['success' => true];
        }
        
        return ['error' => 'Error al eliminar simpatizante'];
    }
    
    /**
     * Valida los datos de un simpatizante (ya normalizados)
     */
    private function validar($datos, $excludeId = null, $esRegistroPublico = false) {
        $errores = [];

        // Campos obligatorios
        if (empty($datos['nombre_completo'])) {
            $errores['nombre_completo'] = 'El nombre completo es obligatorio';
        } elseif (mb_strlen($datos['nombre_completo']) < 5 || strpos($datos['nombre_completo'], ' ') === false) {
            $errores['nombre_completo'] = 'Escriba el nombre completo (nombre y apellido)';
        }

        if (empty($datos['domicilio_completo'])) {
            $errores['domicilio_completo'] = 'El domicilio completo es obligatorio';
        }

        if (empty($datos['seccion_electoral'])) {
            $errores['seccion_electoral'] = 'La sección electoral es obligatoria';
        } elseif (!preg_match('/^[0-9]{4}$/', $datos['seccion_electoral'])) {
            $errores['seccion_electoral'] = 'La sección electoral debe tener exactamente 4 dígitos';
        }

        // Solo validar campaña si NO es registro público
        if (!$esRegistroPublico && empty($datos['campana_id'])) {
            $errores['campana_id'] = 'Debe seleccionar una campaña';
        }

        // Fecha de nacimiento: real y no futura
        $fecha = null;
        if (!empty($datos['fecha_nacimiento'])) {
            $fecha = vd_normalizar_fecha($datos['fecha_nacimiento']);
            if ($fecha === null) {
                $errores['fecha_nacimiento'] = 'La fecha de nacimiento no es válida';
            }
        }
        $sexo = $datos['sexo'] ?? '';

        // CURP y clave de elector: formato y fecha. Las diferencias con otros datos son solo avisos.
        $curp = vd_validar_curp($datos['curp'] ?? '', $fecha, $sexo);
        if ($curp['errores']) {
            $errores['curp'] = $curp['errores'][0];
        }
        $clave = vd_validar_clave_elector($datos['clave_elector'] ?? '', $fecha, $sexo);
        if ($clave['errores']) {
            $errores['clave_elector'] = $clave['errores'][0];
        }

        // Teléfonos: 10 dígitos
        if (!vd_telefono_valido($datos['whatsapp'] ?? '')) {
            $errores['whatsapp'] = 'El WhatsApp debe tener exactamente 10 dígitos';
        }
        if (!vd_telefono_valido($datos['telefono'] ?? '')) {
            $errores['telefono'] = 'El teléfono debe tener exactamente 10 dígitos';
        }

        // Email
        if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Formato de email inválido';
        }

        // Coordenadas dentro de rango
        if (isset($datos['latitud']) && $datos['latitud'] !== '' && (!is_numeric($datos['latitud']) || abs($datos['latitud']) > 90)) {
            $errores['latitud'] = 'La latitud no es válida';
        }
        if (isset($datos['longitud']) && $datos['longitud'] !== '' && (!is_numeric($datos['longitud']) || abs($datos['longitud']) > 180)) {
            $errores['longitud'] = 'La longitud no es válida';
        }

        // Duplicados "duros": clave de elector, CURP, WhatsApp y correo no pueden repetirse
        $mensajes = [
            'clave_elector' => 'Esta clave de elector ya está registrada',
            'curp' => 'Esta CURP ya está registrada',
            'whatsapp' => 'Este número de WhatsApp ya está registrado',
            'email' => 'Este correo electrónico ya está registrado',
        ];
        foreach ($this->model->buscarDuplicados($datos, $excludeId) as $d) {
            if ($d['tipo'] !== 'duro') { continue; }
            foreach ($d['campos'] as $campo) {
                if (isset($mensajes[$campo]) && !isset($errores[$campo])) {
                    $errores[$campo] = $mensajes[$campo];
                }
            }
        }

        return $errores;
    }

    /**
     * Procesa archivo subido
     */
    public function procesarArchivo($file, $tipo) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['error' => 'No se recibió ningún archivo'];
        }
        
        // Validar tamaño
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['error' => 'El archivo excede el tamaño máximo permitido'];
        }
        
        // Validar el contenido real del archivo (no el tipo que declara el navegador)
        require_once __DIR__ . '/../helpers/security.php';
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'Archivo no válido'];
        }
        $extension = seguridad_extension_imagen($file['tmp_name']);
        if ($extension === null) {
            return ['error' => 'Tipo de archivo no permitido. Solo se aceptan imágenes JPG o PNG'];
        }
        
        // Crear directorio si no existe
        $uploadDir = UPLOAD_PATH . '/' . $tipo;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generar nombre único
        $nombreArchivo = bin2hex(random_bytes(12)) . '_' . time() . '.' . $extension;
        $rutaDestino = $uploadDir . '/' . $nombreArchivo;
        
        if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
            return ['success' => true, 'archivo' => 'uploads/' . $tipo . '/' . $nombreArchivo];
        }
        
        return ['error' => 'Error al subir el archivo'];
    }
    
    /**
     * Procesa firma digital en formato base64
     */
    public function procesarFirmaBase64($base64Data) {
        if (empty($base64Data)) {
            return ['error' => 'No se recibió firma digital'];
        }
        
        // Extraer datos de la imagen base64
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $type = strtolower($type[1]);
            
            if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                return ['error' => 'Formato de imagen no válido'];
            }
            
            $base64Data = base64_decode($base64Data);
            
            if ($base64Data === false) {
                return ['error' => 'Error al decodificar imagen'];
            }
        } else {
            return ['error' => 'Formato de datos inválido'];
        }
        
        // La firma debe ser una imagen real y no exceder el tamaño máximo
        if (strlen($base64Data) > MAX_FILE_SIZE || @getimagesizefromstring($base64Data) === false) {
            return ['error' => 'La firma no es una imagen válida'];
        }
        
        // Crear directorio si no existe
        $uploadDir = UPLOAD_PATH . '/firmas';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generar nombre único
        $nombreArchivo = bin2hex(random_bytes(12)) . '_' . time() . '.png';
        $rutaDestino = $uploadDir . '/' . $nombreArchivo;
        
        if (file_put_contents($rutaDestino, $base64Data)) {
            return ['success' => true, 'archivo' => 'uploads/firmas/' . $nombreArchivo];
        }
        
        return ['error' => 'Error al guardar la firma'];
    }
}
