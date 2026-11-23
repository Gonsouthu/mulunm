<?php
// Recibe {"url": "..."} y devuelve la previsualización (imágenes temporales) de un PDF de la nube.
// No guarda el archivo original: solo imágenes. Ver includes/ingesta.php.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/ingesta.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $http, array $datos): void {
    http_response_code($http);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!usuario_logueado()) responder(401, ['ok' => false, 'codigo' => 'sin_sesion', 'error' => 'Iniciá sesión para continuar.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'codigo' => 'metodo', 'error' => 'Método no permitido.']);
csrf_exigir_api();   // el código CSRF viaja en el encabezado X-CSRF-Token

$usuarioId = (int) $_SESSION['usuario_id'];
session_write_close();   // no bloquea otras pestañas del usuario mientras se procesa (puede tardar)

$crudo = file_get_contents('php://input', false, null, 0, 8192);
$datos = json_decode((string) $crudo, true);
$url = is_array($datos) && isset($datos['url']) && is_string($datos['url']) ? $datos['url'] : '';

try {
    $norm = ingesta_normalizar_url($url);   // valida el link (sin tocar la red)

    // Límite de uso: cada previsualización consume mucho servidor
    $limite = (int) ingesta_cfg('ingesta_max_por_10min', 8);
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM auditoria WHERE usuario_id = ? AND accion = 'previsualizar' AND creado_en > (NOW() - INTERVAL 10 MINUTE)");
        $st->execute([$usuarioId]);
        if ((int) $st->fetchColumn() >= $limite) {
            responder(429, ['ok' => false, 'codigo' => 'demasiados_intentos', 'error' => 'Hiciste muchas previsualizaciones seguidas. Esperá unos minutos y probá de nuevo.']);
        }
        $ip = @inet_pton($_SERVER['REMOTE_ADDR'] ?? '') ?: null;
        $ins = db()->prepare("INSERT INTO auditoria (usuario_id, accion, entidad, detalle, ip) VALUES (?, 'previsualizar', 'link', ?, ?)");
        $ins->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $ins->bindValue(2, $norm['proveedor'], PDO::PARAM_STR);
        $ins->bindValue(3, $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $ins->execute();
    } catch (PDOException $e) {
        error_log('BEDM previsualizar: no se pudo usar la tabla auditoria (¿falta la migración 002?): ' . $e->getMessage());
    }

    set_time_limit(120);
    $meta = ingesta_procesar($norm, $usuarioId);

    responder(200, [
        'ok'            => true,
        'token'         => $meta['token'],
        'proveedor'     => $meta['proveedor'],
        'bytes'         => $meta['bytes'],
        'total_paginas' => $meta['total_paginas'],
        'paginas'       => count($meta['paginas']),
        'truncado'      => count($meta['paginas']) < $meta['total_paginas'],
        'imagenes'      => array_map(fn($n) => 'api/preview_img.php?t=' . $meta['token'] . '&n=' . $n, $meta['paginas']),
    ]);
} catch (IngestaError $e) {
    responder($e->codigo === 'sin_poppler' ? 503 : 422, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM previsualizar: ' . $e);
    responder(500, ['ok' => false, 'codigo' => 'interno', 'error' => 'Ocurrió un error inesperado. Probá de nuevo.']);
}
