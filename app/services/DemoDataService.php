<?php
/**
 * Datos de demostración
 *
 * Genera simpatizantes SIMULADOS (nombres, domicilios y números inventados) con ubicaciones
 * reales de Querétaro, Guanajuato y Estado de México, fechas recientes y capturistas ficticios.
 *
 * Todo lo generado se puede identificar y borrar:
 *  - Simpatizantes:  observaciones empieza con "[DEMO]"
 *  - Capturistas:    correo termina en "@demo.example.com" (sin contraseña utilizable)
 *  - Campaña:        descripción empieza con "[DEMO]" (solo si no había ninguna activa)
 *
 * Los teléfonos tienen el formato de 10 dígitos pero comienzan el número local con 0,
 * por lo que no pueden corresponder a una línea real. Los correos usan example.com (reservado).
 */
class DemoDataService {
    const MARCA = '[DEMO]';
    const DOMINIO_CAPTURISTAS = 'demo.example.com';

    // estado, municipio, latitud, longitud, radio (km), peso, base de secciones
    private static $municipios = [
        ['Querétaro', 'Querétaro', 20.5888, -100.3899, 7.0, 14, 1],
        ['Querétaro', 'Corregidora', 20.5300, -100.4400, 4.5, 5, 41],
        ['Querétaro', 'El Marqués', 20.6500, -100.2800, 6.0, 5, 81],
        ['Querétaro', 'San Juan del Río', 20.3889, -99.9961, 5.5, 5, 121],
        ['Querétaro', 'Tequisquiapan', 20.5200, -99.8900, 3.5, 2, 161],
        ['Querétaro', 'Cadereyta de Montes', 20.6930, -99.8110, 3.5, 2, 201],
        ['Querétaro', 'Pedro Escobedo', 20.5000, -100.1400, 3.0, 2, 241],
        ['Querétaro', 'Amealco de Bonfil', 20.1900, -100.1450, 3.0, 1, 281],
        ['Guanajuato', 'León', 21.1250, -101.6860, 9.0, 10, 321],
        ['Guanajuato', 'Irapuato', 20.6767, -101.3563, 6.5, 6, 361],
        ['Guanajuato', 'Celaya', 20.5235, -100.8157, 6.5, 6, 401],
        ['Guanajuato', 'Salamanca', 20.5735, -101.1950, 5.0, 4, 441],
        ['Guanajuato', 'Guanajuato', 21.0190, -101.2574, 4.0, 3, 481],
        ['Guanajuato', 'San Miguel de Allende', 20.9144, -100.7452, 4.5, 4, 521],
        ['Guanajuato', 'Silao de la Victoria', 20.9437, -101.4279, 4.0, 3, 561],
        ['Estado de México', 'Toluca', 19.2826, -99.6557, 7.0, 6, 601],
        ['Estado de México', 'Ecatepec de Morelos', 19.6011, -99.0603, 7.0, 8, 641],
        ['Estado de México', 'Naucalpan de Juárez', 19.4786, -99.2396, 6.0, 5, 681],
        ['Estado de México', 'Nezahualcóyotl', 19.4006, -99.0148, 6.0, 6, 721],
        ['Estado de México', 'Tlalnepantla de Baz', 19.5400, -99.1950, 5.0, 4, 761],
        ['Estado de México', 'Cuautitlán Izcalli', 19.6430, -99.2120, 5.5, 4, 801],
        ['Estado de México', 'Texcoco', 19.5160, -98.8830, 4.0, 2, 841],
        ['Estado de México', 'Metepec', 19.2530, -99.6050, 4.0, 3, 881],
        ['Estado de México', 'Chalco', 19.2640, -98.8970, 4.5, 2, 921],
        ['Estado de México', 'Atizapán de Zaragoza', 19.5390, -99.2540, 5.0, 4, 961],
    ];

    private static $codigoEstado = ['Querétaro' => 'QT', 'Guanajuato' => 'GT', 'Estado de México' => 'MC'];
    private static $lada = ['Querétaro' => '442', 'Guanajuato' => '477', 'Estado de México' => '722'];
    private static $cp = ['Querétaro' => [76000, 76999], 'Guanajuato' => [36000, 38999], 'Estado de México' => [50000, 56999]];

    private static $hombres = ['José', 'Juan', 'Luis', 'Carlos', 'Miguel', 'Jorge', 'Francisco', 'Alejandro', 'Daniel', 'Ricardo', 'Fernando', 'Roberto',
        'Eduardo', 'Javier', 'Manuel', 'Sergio', 'Pedro', 'Raúl', 'Héctor', 'Arturo', 'Óscar', 'Alberto', 'Rafael', 'Mario', 'Gerardo', 'Ángel',
        'Diego', 'Andrés', 'Emilio', 'Iván', 'Gabriel', 'Hugo', 'Enrique', 'Salvador', 'Armando', 'Cristian', 'Adrián', 'Julio', 'Marco', 'Rodrigo'];
    private static $mujeres = ['María', 'Guadalupe', 'Ana', 'Laura', 'Patricia', 'Verónica', 'Alejandra', 'Gabriela', 'Fernanda', 'Mariana', 'Karla', 'Claudia',
        'Leticia', 'Rosa', 'Sofía', 'Daniela', 'Paola', 'Lucía', 'Elena', 'Adriana', 'Carmen', 'Silvia', 'Diana', 'Martha', 'Sandra', 'Irma',
        'Teresa', 'Beatriz', 'Mónica', 'Isabel', 'Rocío', 'Araceli', 'Norma', 'Yolanda', 'Brenda', 'Valeria', 'Jimena', 'Regina', 'Ximena', 'Andrea'];
    private static $apellidos = ['Hernández', 'García', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Cruz', 'Flores', 'Gómez',
        'Morales', 'Vázquez', 'Jiménez', 'Reyes', 'Díaz', 'Torres', 'Gutiérrez', 'Ruiz', 'Mendoza', 'Aguilar', 'Ortiz', 'Moreno', 'Castillo', 'Romero',
        'Álvarez', 'Chávez', 'Rivera', 'Juárez', 'Ramos', 'Domínguez', 'Herrera', 'Medina', 'Castro', 'Vargas', 'Guerrero', 'Estrada', 'Muñoz', 'Salazar',
        'Delgado', 'Pacheco', 'Rangel', 'Ibarra', 'Valencia', 'Cervantes', 'Luna', 'Ríos', 'Montes', 'Navarro', 'Trejo', 'Ugalde', 'Olvera', 'Villegas'];
    private static $calles = ['Av. Hidalgo', 'Calle Morelos', 'Av. Juárez', 'Calle 5 de Mayo', 'Av. Reforma', 'Calle Allende', 'Calle Guerrero', 'Av. Universidad',
        'Calle Independencia', 'Calle Aldama', 'Av. de las Torres', 'Calle Pino Suárez', 'Calle Madero', 'Calle Zaragoza', 'Av. Insurgentes', 'Calle Matamoros',
        'Calle Constitución', 'Av. del Parque', 'Calle Jacarandas', 'Calle Los Pinos'];
    private static $colonias = ['Centro', 'Jardines', 'Las Palmas', 'San José', 'Santa María', 'Valle Dorado', 'La Loma', 'Lomas del Sur', 'El Mirador',
        'Villas del Parque', 'Benito Juárez', 'Emiliano Zapata', 'Reforma', 'Los Pinos', 'San Pedro', 'Del Valle', 'Cumbres', 'Residencial Norte', 'Francisco Villa', 'Plan de Ayala'];

    /** Cuántos registros de ejemplo hay actualmente. */
    public static function contar() {
        $db = Database::getInstance();
        $r = $db->queryOne("SELECT COUNT(*) AS c FROM simpatizantes WHERE observaciones LIKE ?", [self::MARCA . '%']);
        return $r ? (int)$r['c'] : 0;
    }

    /**
     * Genera $cantidad simpatizantes simulados. Devuelve un resumen.
     */
    public static function generar($cantidad, $dias = 60) {
        $cantidad = max(1, min(5000, (int)$cantidad));
        @set_time_limit(180);
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        // Columnas disponibles en esta base (la estructura puede estar desactualizada)
        $cols = [];
        foreach ($db->query("SHOW COLUMNS FROM simpatizantes") ?: [] as $c) { $cols[$c['Field']] = $c['Type']; }
        if (!isset($cols['nombre_completo'], $cols['seccion_electoral'], $cols['observaciones'])) {
            throw new RuntimeException('La tabla simpatizantes no tiene la estructura mínima. Ejecute database/migracion_servidor.sql.');
        }

        // Método de captura: solo valores que la base acepta
        $metodos = ['manual'];
        if (isset($cols['metodo_captura'])) {
            foreach (['app', 'web'] as $m) { if (strpos($cols['metodo_captura'], "'$m'") !== false) { $metodos[] = $m; } }
        }

        $pdo->beginTransaction();
        try {
            [$campanas, $campanaCreada] = self::campanasDisponibles($db);
            $capturistas = self::capturistasDisponibles($db, $pdo);

            $usadosClave = [];
            $filas = [];
            $porEstado = ['Querétaro' => 0, 'Guanajuato' => 0, 'Estado de México' => 0];
            $pesoTotal = array_sum(array_column(self::$municipios, 5));

            for ($i = 0; $i < $cantidad; $i++) {
                $mun = self::elegirMunicipio($pesoTotal);
                [$estado, $municipio, $lat0, $lng0, $radio, , $baseSec] = $mun;
                $porEstado[$estado]++;

                $sexo = (mt_rand(1, 100) <= 52) ? 'F' : 'M';
                $nombres = $sexo === 'F' ? self::$mujeres : self::$hombres;
                $nombre = $nombres[array_rand($nombres)];
                if (mt_rand(1, 100) <= 28) { $nombre .= ' ' . $nombres[array_rand($nombres)]; }
                $ap1 = self::$apellidos[array_rand(self::$apellidos)];
                $ap2 = self::$apellidos[array_rand(self::$apellidos)];
                $completo = "$nombre $ap1 $ap2";

                $edad = self::edadAleatoria();
                $nacimiento = new DateTime('-' . $edad . ' years -' . mt_rand(0, 364) . ' days');
                $registroAnio = (int)date('Y') - mt_rand(0, min(12, max(0, $edad - 18)));

                [$curp, $clave] = self::documentos($ap1, $ap2, $nombre, $sexo, $nacimiento, $estado, $usadosClave);
                [$lat, $lng] = self::puntoCercano($lat0, $lng0, $radio);
                $seccion = str_pad((string)($baseSec + mt_rand(0, 39)), 4, '0', STR_PAD_LEFT);

                $creado = self::fechaReciente($dias);
                $lada = self::$lada[$estado];
                $whatsapp = $lada . '0' . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $slug = preg_replace('/[^a-z0-9]/', '', strtolower(self::sinAcentos($nombre . $ap1)));
                $email = (mt_rand(1, 100) <= 70) ? $slug . mt_rand(1, 999) . '@example.com' : null;

                $cp = mt_rand(self::$cp[$estado][0], self::$cp[$estado][1]);
                $domicilio = self::$calles[array_rand(self::$calles)] . ' #' . mt_rand(1, 980) . ', Col. ' . self::$colonias[array_rand(self::$colonias)]
                           . ", $municipio, $estado, C.P. $cp";

                $azar = mt_rand(1, 100);
                $estatus = $azar <= 62 ? 'validado' : ($azar <= 92 ? 'pendiente' : 'rechazado');
                $validado = $estatus === 'validado' ? 1 : 0;

                $fila = [
                    'nombre_completo' => $completo,
                    'domicilio_completo' => $domicilio,
                    'sexo' => $sexo,
                    'ciudad' => $municipio,
                    'clave_elector' => $clave,
                    'curp' => $curp,
                    'fecha_nacimiento' => $nacimiento->format('Y-m-d'),
                    'ano_registro' => $registroAnio,
                    'vigencia' => (int)date('Y') + mt_rand(1, 9),
                    'seccion_electoral' => $seccion,
                    'telefono' => $lada . '0' . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT),
                    'whatsapp' => $whatsapp,
                    'email' => $email,
                    'latitud' => number_format($lat, 8, '.', ''),
                    'longitud' => number_format($lng, 8, '.', ''),
                    'campana_id' => $campanas[array_rand($campanas)],
                    'capturista_id' => $capturistas ? $capturistas[array_rand($capturistas)] : null,
                    'metodo_captura' => $metodos[array_rand($metodos)],
                    'validado' => $validado,
                    'estatus_validacion' => $estatus,
                    'motivo_rechazo' => $estatus === 'rechazado' ? self::$motivosRechazo[array_rand(self::$motivosRechazo)] : null,
                    'observaciones' => self::MARCA . ' Registro simulado para demostración',
                    'created_at' => $creado,
                    'updated_at' => $creado,
                ];
                // Solo las columnas que existen
                $filas[] = array_intersect_key($fila, $cols);
            }

            self::insertarPorLotes($pdo, $filas);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }

        return ['total' => $cantidad, 'por_estado' => $porEstado, 'campana_creada' => $campanaCreada, 'capturistas' => count($capturistas)];
    }

    /**
     * Elimina todo lo generado por este servicio.
     */
    public static function eliminar() {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare("DELETE FROM simpatizantes WHERE observaciones LIKE ?");
            $s->execute([self::MARCA . '%']);
            $simp = $s->rowCount();

            $u = $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?");
            $u->execute(['%@' . self::DOMINIO_CAPTURISTAS]);
            $usr = $u->rowCount();

            $c = $pdo->prepare("DELETE FROM campanas WHERE descripcion LIKE ?");
            $c->execute([self::MARCA . '%']);
            $camp = $c->rowCount();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        return ['simpatizantes' => $simp, 'capturistas' => $usr, 'campanas' => $camp];
    }

    /**
     * Crea (o renueva) un acceso de demostración por cada nivel de usuario, con contraseña aleatoria.
     * Las contraseñas se devuelven UNA sola vez; en la base solo queda su hash.
     * No crea super administradores.
     */
    public static function crearAccesos() {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $colsU = array_column($db->query("SHOW COLUMNS FROM usuarios") ?: [], 'Field');
        $colsS = array_column($db->query("SHOW COLUMNS FROM simpatizantes") ?: [], 'Field');
        $conCampana = in_array('campana_id', $colsU, true);

        $niveles = [
            ['demo_admin',       'admin',       'Administrador (demo)',  false, 'Todo menos Configuración: usuarios, auditoría, todas las campañas, simpatizantes, mapa y reportes.'],
            ['demo_coordinador', 'coordinador', 'Coordinador (demo)',    true,  'Solo su campaña: campañas, simpatizantes (puede editar y validar), mapa y reportes.'],
            ['demo_candidato',   'candidato',   'Candidato (demo)',      true,  'Solo su campaña: dashboard, simpatizantes, mapa y reportes (consulta).'],
            ['demo_capturista',  'capturista',  'Capturista (demo)',     true,  'Solo los registros que él capturó: dashboard propio y alta/edición de sus simpatizantes.'],
        ];

        $pdo->beginTransaction();
        try {
            [$campanas] = self::campanasDisponibles($db);
            $campanaId = min($campanas);
            $resultado = [];
            $capturistaId = null;

            foreach ($niveles as [$usuario, $rol, $nombre, $ligaCampana, $descripcion]) {
                $clave = self::contrasenaAleatoria();
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $correo = str_replace('demo_', 'demo.', $usuario) . '@' . self::DOMINIO_CAPTURISTAS;
                $campana = ($conCampana && $ligaCampana) ? $campanaId : null;

                $existe = $db->queryOne("SELECT id FROM usuarios WHERE email = ?", [$correo]);
                if ($existe) {
                    $id = (int)$existe['id'];
                    $set = ['password = ?', 'activo = 1'];
                    $par = [$hash];
                    if ($conCampana) { $set[] = 'campana_id = ?'; $par[] = $campana; }
                    if (in_array('intentos_fallidos', $colsU, true)) { $set[] = 'intentos_fallidos = 0'; }
                    if (in_array('bloqueado_hasta', $colsU, true)) { $set[] = 'bloqueado_hasta = NULL'; }
                    $par[] = $id;
                    $ok = $db->execute('UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id = ?', $par);
                } else {
                    $datos = ['username' => $usuario, 'email' => $correo, 'password' => $hash, 'nombre_completo' => $nombre, 'rol' => $rol, 'activo' => 1];
                    if ($conCampana) { $datos['campana_id'] = $campana; }
                    $datos = array_intersect_key($datos, array_flip($colsU));
                    $ok = $db->execute('INSERT INTO usuarios (`' . implode('`,`', array_keys($datos)) . '`) VALUES (' . implode(',', array_fill(0, count($datos), '?')) . ')', array_values($datos));
                    $id = (int)$db->lastInsertId();
                }
                if (!$ok) { throw new RuntimeException($db->describirError('crear el acceso ' . $usuario)); }

                if ($rol === 'capturista') { $capturistaId = $id; }
                $resultado[] = ['usuario' => $usuario, 'rol' => $rol, 'clave' => $clave, 've' => $descripcion];
            }

            // Para que el capturista de demostración tenga qué ver, se le asignan registros de ejemplo existentes
            $asignados = 0;
            if ($capturistaId && in_array('capturista_id', $colsS, true)) {
                $set = 'capturista_id = ?';
                $par = [$capturistaId];
                if (in_array('campana_id', $colsS, true)) { $set .= ', campana_id = ?'; $par[] = $campanaId; }
                $par[] = self::MARCA . '%';
                $db->execute("UPDATE simpatizantes SET $set WHERE observaciones LIKE ? ORDER BY RAND() LIMIT 150", $par);
                $r = $db->queryOne("SELECT COUNT(*) AS c FROM simpatizantes WHERE capturista_id = ? AND observaciones LIKE ?", [$capturistaId, self::MARCA . '%']);
                $asignados = $r ? (int)$r['c'] : 0;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        return ['accesos' => $resultado, 'registros_capturista' => $asignados];
    }

    private static function contrasenaAleatoria($largo = 12) {
        // Sin caracteres ambiguos (0/O, 1/l/I); garantiza minúscula, mayúscula y número
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $c = '';
            for ($i = 0; $i < $largo; $i++) { $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)]; }
        } while (!preg_match('/[a-z]/', $c) || !preg_match('/[A-Z]/', $c) || !preg_match('/\d/', $c));
        return $c;
    }

    // ------------------------------------------------------------------ internos

    private static function campanasDisponibles($db) {
        $ids = array_map('intval', array_column($db->query("SELECT id FROM campanas WHERE activa = 1") ?: [], 'id'));
        if ($ids) { return [$ids, false]; }
        $db->execute("INSERT INTO campanas (nombre, descripcion, fecha_inicio, fecha_fin, activa) VALUES (?, ?, ?, ?, 1)",
            ['Querétaro al Mando (demo)', self::MARCA . ' Campaña creada para la demostración', date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('+120 days'))]);
        $id = (int)$db->lastInsertId();
        if (!$id) { throw new RuntimeException('No se pudo crear una campaña para los datos de ejemplo.'); }
        return [[$id], true];
    }

    private static function capturistasDisponibles($db, $pdo) {
        // Capturistas ficticios (sin contraseña utilizable) para que los reportes por capturista tengan contenido
        $existentes = $db->query("SELECT id FROM usuarios WHERE email LIKE ?", ['%@' . self::DOMINIO_CAPTURISTAS]) ?: [];
        if (!$existentes) {
            $cols = array_column($db->query("SHOW COLUMNS FROM usuarios") ?: [], 'Field');
            $nombres = ['Ana Lucía Ríos', 'Carlos Mendoza', 'Daniela Olvera', 'Jorge Trejo', 'Mariana Estrada', 'Luis Ibarra', 'Paola Navarro', 'Ricardo Salazar'];
            foreach ($nombres as $n => $nombre) {
                $datos = [
                    'username' => 'demo_capturista' . ($n + 1),
                    'email' => 'capturista' . ($n + 1) . '@' . self::DOMINIO_CAPTURISTAS,
                    'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    'nombre_completo' => $nombre . ' (demo)',
                    'rol' => 'capturista',
                    'activo' => 1,
                ];
                $datos = array_intersect_key($datos, array_flip($cols));
                $sql = 'INSERT INTO usuarios (`' . implode('`,`', array_keys($datos)) . '`) VALUES (' . implode(',', array_fill(0, count($datos), '?')) . ')';
                $db->execute($sql, array_values($datos));
            }
        }
        $ids = array_map('intval', array_column($db->query("SELECT id FROM usuarios WHERE rol = 'capturista' AND activo = 1") ?: [], 'id'));
        return $ids;
    }

    private static function insertarPorLotes($pdo, $filas) {
        if (!$filas) { return; }
        $columnas = array_keys($filas[0]);
        $lista = '`' . implode('`,`', $columnas) . '`';
        $unaFila = '(' . implode(',', array_fill(0, count($columnas), '?')) . ')';
        foreach (array_chunk($filas, 100) as $lote) {
            $sql = "INSERT INTO simpatizantes ($lista) VALUES " . implode(',', array_fill(0, count($lote), $unaFila));
            $params = [];
            foreach ($lote as $f) { foreach ($columnas as $c) { $params[] = $f[$c] ?? null; } }
            $pdo->prepare($sql)->execute($params);
        }
    }

    private static function elegirMunicipio($pesoTotal) {
        $r = mt_rand(1, $pesoTotal);
        foreach (self::$municipios as $m) {
            $r -= $m[5];
            if ($r <= 0) { return $m; }
        }
        return self::$municipios[0];
    }

    /** Punto aleatorio dentro de un círculo de $radioKm alrededor del centro. */
    private static function puntoCercano($lat, $lng, $radioKm) {
        $r = $radioKm * sqrt(mt_rand() / mt_getrandmax());
        $a = 2 * M_PI * (mt_rand() / mt_getrandmax());
        return [$lat + ($r * sin($a)) / 111.32, $lng + ($r * cos($a)) / (111.32 * cos(deg2rad($lat)))];
    }

    /** Fecha reciente: más registros en los últimos días, en horario de 8:00 a 21:00, nunca en el futuro. */
    private static function fechaReciente($dias) {
        $offset = (int)floor(pow(mt_rand() / mt_getrandmax(), 1.7) * $dias);
        $ts = strtotime('today') - $offset * 86400 + mt_rand(8 * 3600, 21 * 3600);
        if ($ts > time()) { $ts = time() - mt_rand(60, 3 * 3600); }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function edadAleatoria() {
        $r = mt_rand(1, 100);
        if ($r <= 22) return mt_rand(18, 29);
        if ($r <= 55) return mt_rand(30, 44);
        if ($r <= 85) return mt_rand(45, 64);
        return mt_rand(65, 85);
    }

    private static function sinAcentos($t) {
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u']);
    }

    private static function consonante($texto) {
        $t = strtoupper(preg_replace('/[^A-Za-z]/', '', self::sinAcentos($texto)));
        $c = preg_replace('/[AEIOU]/', '', substr($t, 1));
        return $c !== '' ? $c[0] : 'X';
    }

    private static $motivosRechazo = ['INE ilegible', 'Datos incompletos', 'No reside en la sección', 'Teléfono no contesta', 'Registro duplicado en campo'];

    /** CURP y clave de elector sintéticas, con el formato correcto pero sin corresponder a una persona real. */
    private static function documentos($ap1, $ap2, $nombre, $sexo, DateTime $nac, $estado, &$usados) {
        $letra = function ($t) { $t = strtoupper(preg_replace('/[^A-Za-z]/', '', self::sinAcentos($t))); return $t !== '' ? $t[0] : 'X'; };
        $vocal = function ($t) { $t = strtoupper(preg_replace('/[^A-Za-z]/', '', self::sinAcentos($t))); return preg_match('/[AEIOU]/', substr($t, 1), $m) ? $m[0] : 'X'; };
        $primerNombre = explode(' ', $nombre)[0];
        $alfanum = 'ABCDEFGHIJKLMNPQRSTUVWXYZ0123456789';
        do {
            $homo = $alfanum[mt_rand(0, strlen($alfanum) - 1)] . mt_rand(0, 9);
            $curp = $letra($ap1) . $vocal($ap1) . $letra($ap2) . $letra($primerNombre) . $nac->format('ymd')
                  . ($sexo === 'F' ? 'M' : 'H') . self::$codigoEstado[$estado]
                  . self::consonante($ap1) . self::consonante($ap2) . self::consonante($primerNombre) . $homo;
        } while (isset($usados[$curp]));
        $usados[$curp] = true;

        $seis = '';
        foreach ([$ap1, $ap2, $primerNombre] as $t) { $seis .= $letra($t) . self::consonante($t); }
        $clave = $seis . $nac->format('ymd') . str_pad((string)mt_rand(1, 32), 2, '0', STR_PAD_LEFT) . ($sexo === 'F' ? 'M' : 'H') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        return [substr($curp, 0, 18), substr($clave, 0, 18)];
    }
}
