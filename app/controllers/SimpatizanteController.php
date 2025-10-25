<?php
/**
 * Controlador de Simpatizantes
 * Maneja operaciones CRUD de simpatizantes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../models/Simpatizante.php';
require_once __DIR__ . '/../models/LogAuditoria.php';
require_once __DIR__ . '/AuthController.php';

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
            'total_paginas' => $totalPaginas
        ];
    }
    
    /**
     * Obtiene un simpatizante por ID
     */
    public function obtener($id) {
        $this->auth->requiereAutenticacion();
        
        $simpatizante = $this->model->obtenerPorId($id);
        
        // Verificar permisos
        if ($this->auth->obtenerRol() === 'capturista' && 
            $simpatizante['capturista_id'] != $this->auth->obtenerUsuarioId()) {
            return ['error' => 'No tiene permisos para ver este simpatizante'];
        }
        
        return $simpatizante;
    }
    
    /**
     * Crea un nuevo simpatizante
     */
    public function crear($datos) {
        $this->auth->requiereAutenticacion();
        
        // Validaciones
        $errores = $this->validar($datos);
        if (!empty($errores)) {
            return ['error' => 'Errores de validación', 'errores' => $errores];
        }
        
        // Asignar capturista actual si no está asignado
        if (empty($datos['capturista_id'])) {
            $datos['capturista_id'] = $this->auth->obtenerUsuarioId();
        }
        
        $result = $this->model->crear($datos);
        
        if (isset($result['success'])) {
            // Registrar en log
            $this->logModel->registrar(
                $this->auth->obtenerUsuarioId(),
                'crear_simpatizante',
                'simpatizantes',
                $result['id'],
                null,
                $datos
            );
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
        if ($this->auth->obtenerRol() === 'capturista' && 
            $datosAnteriores['capturista_id'] != $this->auth->obtenerUsuarioId()) {
            return ['error' => 'No tiene permisos para editar este simpatizante'];
        }
        
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
        
        return ['error' => 'Error al actualizar simpatizante'];
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
     * Valida los datos de un simpatizante
     */
    private function validar($datos, $excludeId = null) {
        $errores = [];
        
        // Campos obligatorios
        if (empty($datos['nombre_completo'])) {
            $errores['nombre_completo'] = 'El nombre completo es obligatorio';
        }
        
        if (empty($datos['domicilio_completo'])) {
            $errores['domicilio_completo'] = 'El domicilio completo es obligatorio';
        }
        
        if (empty($datos['seccion_electoral'])) {
            $errores['seccion_electoral'] = 'La sección electoral es obligatoria';
        }
        
        if (empty($datos['campana_id'])) {
            $errores['campana_id'] = 'Debe seleccionar una campaña';
        }
        
        // Validar formato CURP si se proporciona
        if (!empty($datos['curp'])) {
            if (!$this->validarCURP($datos['curp'])) {
                $errores['curp'] = 'Formato de CURP inválido';
            }
        }
        
        // Validar formato de Clave de Elector si se proporciona
        if (!empty($datos['clave_elector'])) {
            if (!$this->validarClaveElector($datos['clave_elector'])) {
                $errores['clave_elector'] = 'Formato de Clave de Elector inválido';
            }
        }
        
        // Validar WhatsApp (10 dígitos)
        if (!empty($datos['whatsapp'])) {
            if (!preg_match('/^[0-9]{10}$/', $datos['whatsapp'])) {
                $errores['whatsapp'] = 'El WhatsApp debe tener exactamente 10 dígitos';
            } else {
                // Verificar unicidad de WhatsApp
                if ($this->model->existeWhatsApp($datos['whatsapp'], $excludeId)) {
                    $errores['whatsapp'] = 'Este número de WhatsApp ya está registrado';
                }
            }
        }
        
        // Validar sección electoral (4 dígitos)
        if (!empty($datos['seccion_electoral'])) {
            if (!preg_match('/^[0-9]{4}$/', $datos['seccion_electoral'])) {
                $errores['seccion_electoral'] = 'La sección electoral debe tener exactamente 4 dígitos';
            }
        }
        
        // Validar email si se proporciona
        if (!empty($datos['email'])) {
            if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                $errores['email'] = 'Formato de email inválido';
            } else {
                // Verificar unicidad de email
                if ($this->model->existeEmail($datos['email'], $excludeId)) {
                    $errores['email'] = 'Este correo electrónico ya está registrado';
                }
            }
        }
        
        return $errores;
    }
    
    /**
     * Valida formato de CURP
     */
    private function validarCURP($curp) {
        $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/';
        return preg_match($pattern, strtoupper($curp));
    }
    
    /**
     * Valida formato de Clave de Elector
     */
    private function validarClaveElector($clave) {
        $pattern = '/^[A-Z]{6}[0-9]{8}[HM][0-9]{3}$/';
        return preg_match($pattern, strtoupper($clave));
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
        
        // Validar tipo
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            return ['error' => 'Tipo de archivo no permitido'];
        }
        
        // Crear directorio si no existe
        $uploadDir = UPLOAD_PATH . '/' . $tipo;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
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
        
        // Crear directorio si no existe
        $uploadDir = UPLOAD_PATH . '/firmas';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generar nombre único
        $nombreArchivo = uniqid() . '_' . time() . '.png';
        $rutaDestino = $uploadDir . '/' . $nombreArchivo;
        
        if (file_put_contents($rutaDestino, $base64Data)) {
            return ['success' => true, 'archivo' => 'uploads/firmas/' . $nombreArchivo];
        }
        
        return ['error' => 'Error al guardar la firma'];
    }
}
