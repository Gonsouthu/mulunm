<?php
// Publica un trabajo a partir de una previsualización ya generada (api/previsualizar.php).
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';

$yo = api_post_con_sesion();
$datos = api_cuerpo_json();
session_write_close();   // la publicación procesa imágenes y puede tardar: no se retiene la sesión
set_time_limit(120);

try {
    $id = trabajo_publicar($yo, $datos);
    api_json(200, ['ok' => true, 'id' => $id, 'url' => 'trabajo.php?id=' . $id]);
} catch (TrabajoError $e) {
    $http = ['no_verificado' => 403, 'limite' => 429, 'interno' => 500][$e->codigo] ?? 422;
    api_json($http, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM trabajo_crear: ' . $e);
    api_json(500, ['ok' => false, 'codigo' => 'interno', 'error' => 'Ocurrió un error inesperado. Probá de nuevo.']);
}
