<?php
/**
 * Alta idempotente de un simpatizante capturado desde un celular (PWA o app nativa).
 *
 * El celular manda un `uuid` por captura; si llega repetido (reintento tras perder señal) se responde
 * éxito sin crear otro registro. Aplica las mismas reglas que el formulario web (normalización,
 * CURP/clave, duplicados, alcance por rol) porque pasa por SimpatizanteController.
 */

require_once __DIR__ . '/../controllers/SimpatizanteController.php';
require_once __DIR__ . '/../models/Campana.php';

class CapturaService {
    /** Campos que se aceptan del celular. */
    const CAMPOS = ['nombre_completo', 'domicilio_completo', 'seccion_electoral', 'telefono', 'whatsapp', 'email', 'clave_elector',
                    'curp', 'sexo', 'fecha_nacimiento', 'ciudad', 'latitud', 'longitud', 'observaciones', 'consentimiento',
                    'colonia', 'comentario_colonia'];

    /**
     * @return array [código HTTP, cuerpo de la respuesta]
     *   200 {ok:true, id, repetido?, avisos[]}            → guardada
     *   422 {ok:false, permanente:true, error, errores{}} → datos inválidos o duplicado: no reintentar
     *   503 {ok:false, permanente:false, error}           → falla temporal: reintentar después
     */
    public static function procesar(AuthController $auth, array $in, $metodoCaptura = 'app') {
        $uuid = isset($in['uuid']) && is_string($in['uuid']) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $in['uuid'])
            ? strtolower($in['uuid']) : null;
        if (!$uuid) {
            return [422, ['ok' => false, 'permanente' => true, 'error' => 'Falta el identificador de la captura.']];
        }

        $controller = new SimpatizanteController();
        $modelo = new Simpatizante();

        // Reintento de algo que ya se guardó
        if ($previo = $modelo->obtenerPorUuid($uuid)) {
            return [200, ['ok' => true, 'id' => (int)$previo['id'], 'repetido' => true, 'avisos' => []]];
        }

        $datos = [];
        foreach (self::CAMPOS as $c) {
            $v = $in[$c] ?? '';
            $datos[$c] = is_scalar($v) ? mb_substr((string)$v, 0, 500) : '';
        }

        // Campaña: quien está ligado a una campaña solo captura en ella
        if ($auth->puedeVerTodasLasCampanas()) {
            $campana = (int)($in['campana_id'] ?? 0);
            $validas = array_map(function ($c) { return (int)$c['id']; }, (new Campana())->obtenerTodas(1) ?: []);
            if (!in_array($campana, $validas, true)) {
                return [422, ['ok' => false, 'permanente' => true, 'error' => 'Seleccione una campaña válida.']];
            }
        } else {
            $campana = (int)$auth->obtenerCampanaId();
            if (!$campana) {
                return [422, ['ok' => false, 'permanente' => true, 'error' => 'Su usuario no tiene una campaña asignada. Avise a su coordinador.']];
            }
        }
        $datos['campana_id'] = $campana;
        $datos['metodo_captura'] = $metodoCaptura;
        $datos['uuid_cliente'] = $uuid;

        // Si se capturó sin señal, se deja constancia de cuándo
        if (!empty($in['capturado_en']) && is_string($in['capturado_en']) && ($t = strtotime($in['capturado_en'])) && $t < time() - 600 && $t > time() - 86400 * 60) {
            $nota = 'Capturado sin conexión el ' . date('d/m/Y H:i', $t);
            $datos['observaciones'] = trim($datos['observaciones'] . ($datos['observaciones'] !== '' ? "\n" : '') . $nota);
        }

        try {
            $res = $controller->crear($datos);
        } catch (Throwable $e) {
            error_log('CapturaService: ' . $e->getMessage());
            return [503, ['ok' => false, 'permanente' => false, 'error' => 'Falla temporal del servidor.']];
        }

        if (!empty($res['success'])) {
            // Temas que la persona quiere mejorar en su colonia
            if (!empty($in['temas']) && is_array($in['temas'])) {
                $modelo->guardarTemas((int)$res['id'], $in['temas']);
            }
            $avisos = [];
            if ($zona = $controller->avisoZona($datos['seccion_electoral'])) { $avisos[] = $zona; }
            return [200, ['ok' => true, 'id' => (int)$res['id'], 'avisos' => $avisos]];
        }

        // Carrera: otro reintento guardó el mismo uuid entre la consulta y el alta
        if ($previo = $modelo->obtenerPorUuid($uuid)) {
            return [200, ['ok' => true, 'id' => (int)$previo['id'], 'repetido' => true, 'avisos' => []]];
        }

        $errores = $res['errores'] ?? [];
        $mensaje = $errores ? implode(' · ', array_values($errores)) : ($res['error'] ?? 'No se pudo guardar.');
        // Un error de base de datos puede ser temporal; una validación o un duplicado no
        $temporal = !$errores && stripos($mensaje, 'Ya existe') === false;
        return [$temporal ? 503 : 422, ['ok' => false, 'permanente' => !$temporal, 'error' => $mensaje, 'errores' => $errores]];
    }
}
