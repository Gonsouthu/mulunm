<?php
// Las credenciales NO viven en el repositorio. Se leen, en este orden, de:
//   1) variables de entorno: BEDM_DB_HOST, BEDM_DB_NAME, BEDM_DB_USER, BEDM_DB_PASS
//   2) config/config.local.php (ignorado por git; copiá config.example.php y editalo)
$cfg = [
    'host' => getenv('BEDM_DB_HOST') ?: null,
    'name' => getenv('BEDM_DB_NAME') ?: null,
    'user' => getenv('BEDM_DB_USER') ?: null,
    'pass' => getenv('BEDM_DB_PASS') !== false ? getenv('BEDM_DB_PASS') : null,
];
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $cfg = array_merge(array_filter($cfg, fn($v) => $v !== null), (array) require $local);
}

function db(): PDO {
    global $cfg;
    static $pdo = null;
    if ($pdo === null) {
        foreach (['host', 'name', 'user'] as $k) {
            if (empty($cfg[$k])) {
                error_log('BEDM: falta configurar la base de datos (config/config.local.php o variables BEDM_DB_*).');
                http_response_code(500);
                exit('El servidor no está configurado correctamente.');
            }
        }
        try {
            $pdo = new PDO(
                'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4',
                $cfg['user'], $cfg['pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('BEDM: no se pudo conectar a la base: ' . $e->getMessage());   // el detalle va al log, no al navegador
            http_response_code(500);
            exit('No se pudo conectar con la base de datos.');
        }
    }
    return $pdo;
}
