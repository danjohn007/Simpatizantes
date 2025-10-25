<?php
/**
 * Servicio OCR para Extracción de Datos de INE
 * Utiliza OCR.space API (gratuita con límites)
 */

class OCRService {
    private $apiKey;
    private $apiUrl;
    private $habilitado;
    
    public function __construct() {
        $db = Database::getInstance();
        
        // Obtener configuración
        $config = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('ocr_api_key', 'ocr_api_url', 'ocr_habilitado')");
        
        foreach ($config as $item) {
            switch ($item['clave']) {
                case 'ocr_api_key':
                    $this->apiKey = $item['valor'];
                    break;
                case 'ocr_api_url':
                    $this->apiUrl = $item['valor'];
                    break;
                case 'ocr_habilitado':
                    $this->habilitado = $item['valor'] === 'true';
                    break;
            }
        }
    }
    
    /**
     * Verifica si el servicio OCR está habilitado y configurado
     */
    public function estaDisponible() {
        return $this->habilitado && !empty($this->apiKey);
    }
    
    /**
     * Procesa una imagen y extrae texto usando OCR
     */
    public function procesarImagen($rutaImagen) {
        if (!$this->estaDisponible()) {
            return ['error' => 'Servicio OCR no disponible o no configurado'];
        }
        
        if (!file_exists($rutaImagen)) {
            return ['error' => 'Archivo no encontrado'];
        }
        
        // Preparar datos para la API
        $postData = [
            'apikey' => $this->apiKey,
            'language' => 'spa',
            'isOverlayRequired' => false,
            'detectOrientation' => true,
            'scale' => true,
            'OCREngine' => 2,
            'file' => new CURLFile($rutaImagen)
        ];
        
        // Hacer petición a la API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['error' => 'Error al conectar con el servicio OCR'];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['ParsedResults'])) {
            return ['error' => 'Error al procesar la imagen'];
        }
        
        if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
            return ['error' => $result['ErrorMessage'][0] ?? 'Error desconocido'];
        }
        
        $texto = $result['ParsedResults'][0]['ParsedText'] ?? '';
        
        return ['success' => true, 'texto' => $texto];
    }
    
    /**
     * Extrae datos específicos del INE del texto OCR
     */
    public function extraerDatosINE($texto) {
        $datos = [
            'nombre_completo' => '',
            'curp' => '',
            'clave_elector' => '',
            'domicilio_completo' => '',
            'seccion_electoral' => '',
            'vigencia' => '',
            'sexo' => ''
        ];
        
        // Normalizar texto
        $texto = strtoupper($texto);
        $lineas = explode("\n", $texto);
        
        // Extraer CURP (18 caracteres alfanuméricos)
        if (preg_match('/([A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]{2})/', $texto, $matches)) {
            $datos['curp'] = $matches[1];
            
            // Extraer sexo del CURP (posición 11)
            if (strlen($matches[1]) >= 11) {
                $sexoCurp = substr($matches[1], 10, 1);
                $datos['sexo'] = $sexoCurp === 'H' ? 'M' : ($sexoCurp === 'M' ? 'F' : '');
            }
        }
        
        // Extraer Clave de Elector (18 caracteres alfanuméricos)
        if (preg_match('/([A-Z]{6}\d{8}[HM]\d{3})/', $texto, $matches)) {
            $datos['clave_elector'] = $matches[1];
        }
        
        // Extraer nombre (buscar después de "NOMBRE" o antes de CURP)
        foreach ($lineas as $i => $linea) {
            if (strpos($linea, 'NOMBRE') !== false && isset($lineas[$i + 1])) {
                $nombreLinea = trim($lineas[$i + 1]);
                if (strlen($nombreLinea) > 5 && strlen($nombreLinea) < 100) {
                    $datos['nombre_completo'] = ucwords(strtolower($nombreLinea));
                }
                break;
            }
        }
        
        // Extraer domicilio (buscar después de "DOMICILIO" o "DIRECCION")
        foreach ($lineas as $i => $linea) {
            if (strpos($linea, 'DOMICILIO') !== false || strpos($linea, 'DIRECCION') !== false) {
                if (isset($lineas[$i + 1])) {
                    $domicilioLinea = trim($lineas[$i + 1]);
                    if (strlen($domicilioLinea) > 10) {
                        $datos['domicilio_completo'] = ucwords(strtolower($domicilioLinea));
                    }
                }
                break;
            }
        }
        
        // Extraer sección electoral (4 dígitos)
        if (preg_match('/SECCION[:\s]*(\d{4})/', $texto, $matches)) {
            $datos['seccion_electoral'] = $matches[1];
        } elseif (preg_match('/SEC[:\s]*(\d{4})/', $texto, $matches)) {
            $datos['seccion_electoral'] = $matches[1];
        }
        
        // Extraer vigencia (4 dígitos de año)
        if (preg_match('/VIGENCIA[:\s]*(20\d{2})/', $texto, $matches)) {
            $datos['vigencia'] = $matches[1];
        } elseif (preg_match('/VIG[:\s]*(20\d{2})/', $texto, $matches)) {
            $datos['vigencia'] = $matches[1];
        }
        
        return $datos;
    }
    
    /**
     * Procesa imagen de INE y retorna datos extraídos
     */
    public function procesarINE($rutaImagen) {
        $resultado = $this->procesarImagen($rutaImagen);
        
        if (isset($resultado['error'])) {
            return $resultado;
        }
        
        $datosExtraidos = $this->extraerDatosINE($resultado['texto']);
        
        return [
            'success' => true,
            'datos' => $datosExtraidos,
            'texto_completo' => $resultado['texto']
        ];
    }
}
