<?php
// «Me gusta» a un trabajo: {id, me_gusta: true|false}. Devuelve el total actualizado.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/interaccion.php';

$yo = api_post_con_sesion();
$d = api_cuerpo_json();
$t = trabajo_cargar((int) ($d['id'] ?? 0));
if (!$t) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);
$rol = trabajo_rol($t, trabajo_autores((int) $t['id'], true), (int) $yo['id']);

try {
    $total = trabajo_like_establecer($yo, $t, $rol, !empty($d['me_gusta']));
    api_json(200, ['ok' => true, 'likes' => $total, 'me_gusta' => !empty($d['me_gusta'])]);
} catch (TrabajoError $e) {
    $http = ['no_encontrado' => 404, 'no_verificado' => 403, 'propio' => 403, 'limite' => 429][$e->codigo] ?? 422;
    api_json($http, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM like: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos registrar tu «me gusta». Probá de nuevo.']);
}
