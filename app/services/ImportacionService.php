<?php
/**
 * Importación masiva de simpatizantes desde Excel (.xlsx) o CSV.
 *
 * analizar() NO escribe nada: revisa cada fila y la clasifica.
 *   ok         → lista para importar
 *   aviso      → se puede importar, pero conviene revisarla (posible duplicado, CURP inconsistente…)
 *   duplicado  → ya existe (en la base o antes en el archivo): se omite
 *   error      → le falta algo o tiene un formato inválido: se omite
 */

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Simpatizante.php';
require_once __DIR__ . '/HojaCalculoService.php';

class ImportacionService {
    const MAX_FILAS = 5000;

    /** Nombre en la plantilla => alias aceptados (comparados sin acentos, mayúsculas ni signos). */
    const ALIAS = [
        'nombre_completo'   => ['nombrecompleto', 'nombreyapellidos', 'nombreapellidos'],
        'nombre'            => ['nombre', 'nombres'],
        'apellido_paterno'  => ['apellidopaterno', 'paterno', 'primerapellido', 'apellido1'],
        'apellido_materno'  => ['apellidomaterno', 'materno', 'segundoapellido', 'apellido2'],
        'domicilio_completo'=> ['domiciliocompleto', 'domicilio', 'direccion'],
        'sexo'              => ['sexo', 'genero'],
        'ciudad'            => ['ciudad', 'municipio', 'localidad'],
        'clave_elector'     => ['claveelector', 'clavedeelector', 'claveine', 'cve', 'clave'],
        'curp'              => ['curp'],
        'fecha_nacimiento'  => ['fechanacimiento', 'fechadenacimiento', 'nacimiento', 'fnacimiento'],
        'seccion_electoral' => ['seccionelectoral', 'seccion', 'secc'],
        'telefono'          => ['telefono', 'tel', 'celular', 'movil'],
        'whatsapp'          => ['whatsapp', 'wa', 'whats'],
        'email'             => ['email', 'correo', 'correoelectronico', 'mail'],
        'ano_registro'      => ['anoregistro', 'anoderegistro', 'anodelregistro', 'anioregistro', 'aniodelregistro', 'registro'],
        'vigencia'          => ['vigencia'],
        'observaciones'     => ['observaciones', 'notas', 'comentarios'],
    ];

    /** Columnas de la plantilla, en orden. */
    const PLANTILLA = [
        'Nombre completo', 'Domicilio completo', 'Sección electoral', 'Clave de elector', 'CURP', 'Sexo',
        'Fecha de nacimiento', 'Ciudad', 'Teléfono', 'WhatsApp', 'Correo', 'Año de registro', 'Vigencia', 'Observaciones'
    ];

    public static function filasPlantilla() {
        return [
            self::PLANTILLA,
            ['María Fernanda López Ramírez', 'Calle Hidalgo 123, Col. Centro, Querétaro, Qro.', '0123', '', '', 'F',
             '15/03/1990', 'Querétaro', '4421234567', '4427654321', 'maria@example.com', '2010', '2030', 'Ejemplo: borre esta fila antes de importar'],
        ];
    }

    private static function clave($texto) {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', vd_sin_acentos($texto)));
    }

    /**
     * Relaciona las columnas del archivo con los campos del sistema.
     * @return array ['indices' => campo => posición, 'ignoradas' => [encabezados sin uso]]
     */
    public static function mapearEncabezados(array $encabezado) {
        $indices = [];
        $ignoradas = [];
        foreach ($encabezado as $i => $titulo) {
            $k = self::clave($titulo);
            if ($k === '') { continue; }
            $campo = null;
            foreach (self::ALIAS as $nombre => $aliases) {
                if (in_array($k, $aliases, true)) { $campo = $nombre; break; }
            }
            if ($campo !== null && !isset($indices[$campo])) {
                $indices[$campo] = $i;
            } else {
                $ignoradas[] = $titulo;
            }
        }
        return ['indices' => $indices, 'ignoradas' => $ignoradas];
    }

    /**
     * Revisa todas las filas del archivo (la primera es el encabezado).
     *
     * @return array [
     *   'error'    => string|null   (problema que impide importar, p. ej. faltan columnas),
     *   'filas'    => [ ['n' => nº de fila en el archivo, 'datos' => [...], 'estado' => ..., 'mensajes' => [...]] ],
     *   'resumen'  => ['total','ok','aviso','duplicado','error'],
     *   'ignoradas'=> [...]
     * ]
     */
    public static function analizar(Simpatizante $modelo, array $filas) {
        $vacio = ['error' => null, 'filas' => [], 'resumen' => ['total' => 0, 'ok' => 0, 'aviso' => 0, 'duplicado' => 0, 'error' => 0], 'ignoradas' => []];
        if (count($filas) < 2) {
            $vacio['error'] = 'El archivo no tiene datos: la primera fila debe ser el encabezado y debajo van los simpatizantes.';
            return $vacio;
        }

        $mapa = self::mapearEncabezados($filas[0]);
        $ix = $mapa['indices'];
        $vacio['ignoradas'] = $mapa['ignoradas'];

        $tieneNombre = isset($ix['nombre_completo']) || isset($ix['nombre']);
        $faltan = [];
        if (!$tieneNombre) { $faltan[] = 'Nombre completo'; }
        if (!isset($ix['domicilio_completo'])) { $faltan[] = 'Domicilio completo'; }
        if (!isset($ix['seccion_electoral'])) { $faltan[] = 'Sección electoral'; }
        if ($faltan) {
            $vacio['error'] = 'Faltan columnas obligatorias: ' . implode(', ', $faltan) . '. Descargue la plantilla para ver el formato.';
            return $vacio;
        }
        if (count($filas) - 1 > self::MAX_FILAS) {
            $vacio['error'] = 'El archivo tiene más de ' . number_format(self::MAX_FILAS) . ' filas. Divídalo en varios archivos.';
            return $vacio;
        }

        // 1) Normalizar cada fila
        $registros = [];
        foreach (array_slice($filas, 1, null, true) as $pos => $fila) {
            $get = function ($campo) use ($ix, $fila) {
                return isset($ix[$campo]) ? trim((string)($fila[$ix[$campo]] ?? '')) : '';
            };
            $nombre = $get('nombre_completo');
            if ($nombre === '') {
                $nombre = trim($get('nombre') . ' ' . $get('apellido_paterno') . ' ' . $get('apellido_materno'));
            }
            $crudos = [
                'nombre_completo'    => $nombre,
                'domicilio_completo' => $get('domicilio_completo'),
                'seccion_electoral'  => $get('seccion_electoral'),
                'clave_elector'      => $get('clave_elector'),
                'curp'               => $get('curp'),
                'telefono'           => $get('telefono'),
                'whatsapp'           => $get('whatsapp'),
                'email'              => $get('email'),
                'ciudad'             => $get('ciudad'),
                'observaciones'      => $get('observaciones'),
            ];
            $datos = vd_normalizar_simpatizante($crudos);
            $datos['sexo'] = vd_normalizar_sexo($get('sexo'));
            $datos['_fecha_cruda'] = $get('fecha_nacimiento');
            $datos['fecha_nacimiento'] = vd_normalizar_fecha($datos['_fecha_cruda']);
            $datos['ano_registro'] = $get('ano_registro');
            $datos['vigencia'] = $get('vigencia');
            $registros[] = ['n' => $pos + 1, 'datos' => $datos, 'estado' => 'ok', 'mensajes' => []];
        }

        // 2) Validación fila por fila (formato)
        foreach ($registros as &$r) {
            $d = $r['datos'];
            $errores = [];
            $avisos = [];

            if ($d['nombre_completo'] === '') { $errores[] = 'Falta el nombre'; }
            elseif (mb_strlen($d['nombre_completo']) < 5 || strpos($d['nombre_completo'], ' ') === false) { $errores[] = 'El nombre debe incluir nombre y apellido'; }
            elseif (mb_strlen($d['nombre_completo']) > 150) { $errores[] = 'El nombre excede 150 caracteres'; }

            if ($d['domicilio_completo'] === '') { $errores[] = 'Falta el domicilio'; }
            if ($d['seccion_electoral'] === '') { $errores[] = 'Falta la sección electoral'; }
            elseif (!preg_match('/^\d{4}$/', $d['seccion_electoral'])) { $errores[] = 'La sección debe tener 4 dígitos'; }

            if ($d['_fecha_cruda'] !== '' && $d['fecha_nacimiento'] === null) { $errores[] = 'Fecha de nacimiento no válida (use dd/mm/aaaa)'; }

            $curp = vd_validar_curp($d['curp'], $d['fecha_nacimiento'], $d['sexo']);
            $clave = vd_validar_clave_elector($d['clave_elector'], $d['fecha_nacimiento'], $d['sexo']);
            foreach (array_merge($curp['errores'], $clave['errores']) as $e) { $errores[] = $e; }
            foreach (array_merge($curp['avisos'], $clave['avisos']) as $a) { $avisos[] = $a; }

            if (!vd_telefono_valido($d['telefono'])) { $errores[] = 'El teléfono debe tener 10 dígitos'; }
            if (!vd_telefono_valido($d['whatsapp'])) { $errores[] = 'El WhatsApp debe tener 10 dígitos'; }
            if ($d['email'] !== '' && (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 100)) { $errores[] = 'Correo no válido'; }
            if (mb_strlen($d['ciudad']) > 100) { $errores[] = 'La ciudad excede 100 caracteres'; }
            foreach (['ano_registro' => [1900, 2100, 'Año de registro'], 'vigencia' => [2000, 2100, 'Vigencia']] as $campo => $cfg) {
                if ($d[$campo] !== '' && (!ctype_digit($d[$campo]) || (int)$d[$campo] < $cfg[0] || (int)$d[$campo] > $cfg[1])) {
                    $errores[] = $cfg[2] . ' no válido';
                }
            }

            if ($errores) { $r['estado'] = 'error'; $r['mensajes'] = $errores; }
            elseif ($avisos) { $r['estado'] = 'aviso'; $r['mensajes'] = $avisos; }
        }
        unset($r);

        // 3) Duplicados contra la base de datos (una consulta por lote, no por fila)
        $candidatos = array_filter($registros, function ($r) { return $r['estado'] !== 'error'; });
        $existentes = [];
        foreach (['clave_elector', 'curp', 'whatsapp', 'email'] as $campo) {
            $existentes[$campo] = $modelo->valoresExistentes($campo, array_map(function ($r) use ($campo) { return $r['datos'][$campo]; }, $candidatos));
        }
        $telefonos = $modelo->valoresExistentes('telefono', array_map(function ($r) { return $r['datos']['telefono']; }, $candidatos));
        $nombres = $modelo->nombresPorSeccion(array_map(function ($r) { return $r['datos']['seccion_electoral']; }, $candidatos));

        $etiquetas = ['clave_elector' => 'la misma clave de elector', 'curp' => 'la misma CURP', 'whatsapp' => 'el mismo WhatsApp', 'email' => 'el mismo correo'];
        $vistos = ['clave_elector' => [], 'curp' => [], 'whatsapp' => [], 'email' => [], 'telefono' => [], 'nombre' => []];

        foreach ($registros as &$r) {
            if ($r['estado'] === 'error') { continue; }
            $d = $r['datos'];
            $duros = [];
            $blandos = [];

            foreach ($etiquetas as $campo => $texto) {
                $v = strtoupper($d[$campo]);
                if ($v === '') { continue; }
                if (isset($existentes[$campo][$v])) {
                    $duros[] = 'Ya existe un simpatizante con ' . $texto . ' (registro #' . $existentes[$campo][$v] . ')';
                } elseif (isset($vistos[$campo][$v])) {
                    $duros[] = 'Repetido en el archivo: ' . $texto . ' que la fila ' . $vistos[$campo][$v];
                }
            }
            if ($d['telefono'] !== '') {
                if (isset($telefonos[$d['telefono']])) { $blandos[] = 'El teléfono ya está en el registro #' . $telefonos[$d['telefono']]; }
                elseif (isset($vistos['telefono'][$d['telefono']])) { $blandos[] = 'El teléfono se repite en la fila ' . $vistos['telefono'][$d['telefono']]; }
            }
            $kn = $d['seccion_electoral'] . '|' . vd_sin_acentos($d['nombre_completo']);
            if (isset($nombres[$kn])) { $blandos[] = 'Ya hay alguien con el mismo nombre en esa sección (registro #' . $nombres[$kn] . ')'; }
            elseif (isset($vistos['nombre'][$kn])) { $blandos[] = 'Mismo nombre y sección que la fila ' . $vistos['nombre'][$kn]; }

            // Solo se "reclama" el valor cuando la fila va a importarse
            if (!$duros) {
                foreach (array_keys($etiquetas) as $campo) {
                    $v = strtoupper($d[$campo]);
                    if ($v !== '') { $vistos[$campo][$v] = $r['n']; }
                }
                if ($d['telefono'] !== '') { $vistos['telefono'][$d['telefono']] = $r['n']; }
                $vistos['nombre'][$kn] = $r['n'];
            }

            if ($duros) { $r['estado'] = 'duplicado'; $r['mensajes'] = array_merge($duros, $blandos); }
            elseif ($blandos) { $r['estado'] = 'aviso'; $r['mensajes'] = array_merge($r['mensajes'], $blandos); }
        }
        unset($r);

        $resumen = ['total' => count($registros), 'ok' => 0, 'aviso' => 0, 'duplicado' => 0, 'error' => 0];
        foreach ($registros as $r) { $resumen[$r['estado']]++; }

        return ['error' => null, 'filas' => $registros, 'resumen' => $resumen, 'ignoradas' => $vacio['ignoradas']];
    }

    /**
     * Inserta las filas "ok" y "aviso" del análisis.
     * Cada inserción vuelve a verificar duplicados (por si alguien capturó lo mismo mientras tanto).
     *
     * @return array ['importados' => n, 'fallidos' => [ ['n' => fila, 'mensajes' => [...]] ]]
     */
    public static function importar(Simpatizante $modelo, array $analisis, $campanaId, $usuarioId, $nombreArchivo) {
        $importados = 0;
        $fallidos = [];
        $nota = 'Importado de «' . mb_substr($nombreArchivo, 0, 80) . '» el ' . date('d/m/Y H:i');

        foreach ($analisis['filas'] as $r) {
            if ($r['estado'] !== 'ok' && $r['estado'] !== 'aviso') { continue; }
            $d = $r['datos'];
            unset($d['_fecha_cruda']);
            foreach ($d as $k => $v) { if ($v === '') { $d[$k] = null; } }
            $d['observaciones'] = trim(($d['observaciones'] ? $d['observaciones'] . "\n" : '') . $nota);
            $d['campana_id'] = $campanaId;
            $d['capturista_id'] = $usuarioId;
            $d['metodo_captura'] = 'manual';
            $d['validado'] = 0;

            $res = $modelo->crear($d);
            if (!empty($res['success'])) {
                $importados++;
            } else {
                $fallidos[] = ['n' => $r['n'], 'mensajes' => [$res['error'] ?? 'No se pudo guardar']];
            }
        }
        return ['importados' => $importados, 'fallidos' => $fallidos];
    }

    /** Filas con problemas (todo lo que no es "ok"), listas para descargar como reporte. */
    public static function filasReporte(array $analisis, array $fallidos = []) {
        $etiqueta = ['aviso' => 'Importable con aviso', 'duplicado' => 'Duplicado (omitido)', 'error' => 'Error (omitido)', 'ok' => 'Importado'];
        $salida = [['Fila', 'Resultado', 'Nombre', 'Sección', 'Clave de elector', 'CURP', 'Motivo']];
        $falla = [];
        foreach ($fallidos as $f) { $falla[$f['n']] = $f['mensajes']; }
        foreach ($analisis['filas'] as $r) {
            $mensajes = $r['mensajes'];
            $estado = $r['estado'];
            if (isset($falla[$r['n']])) { $estado = 'error'; $mensajes = $falla[$r['n']]; }
            if ($estado === 'ok') { continue; }
            $d = $r['datos'];
            $salida[] = [$r['n'], $etiqueta[$estado] ?? $estado, $d['nombre_completo'], $d['seccion_electoral'], $d['clave_elector'], $d['curp'], implode(' · ', $mensajes)];
        }
        return $salida;
    }
}
