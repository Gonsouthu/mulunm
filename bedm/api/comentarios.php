<?php
// Comentarios: {accion: 'crear', trabajo_id, texto} · {accion: 'borrar', id}
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/interaccion.php';

$yo = api_post_con_sesion();
$d = api_cuerpo_json();
$accion = (string) ($d['accion'] ?? '');

try {
    if ($accion === 'crear') {
        $t = trabajo_cargar((int) ($d['trabajo_id'] ?? 0));
        if (!$t) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);
        $rol = trabajo_rol($t, trabajo_autores((int) $t['id'], true), (int) $yo['id']);
        $id = trabajo_comentar($yo, $t, $rol, $d['texto'] ?? '');
        api_json(200, ['ok' => true, 'id' => $id]);
    }
    if ($accion === 'borrar') {
        trabajo_comentario_borrar($yo, (int) ($d['id'] ?? 0));
        api_json(200, ['ok' => true]);
    }
    api_json(400, ['ok' => false, 'error' => 'Acción no válida.']);
} catch (TrabajoError $e) {
    $http = ['no_encontrado' => 404, 'no_verificado' => 403, 'prohibido' => 403, 'limite' => 429][$e->codigo] ?? 422;
    api_json($http, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM comentarios: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos completar la acción. Probá de nuevo.']);
}
