<?php
// Elimina un trabajo propio: se borran las imágenes y se libera la huella digital.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';

$yo = api_post_con_sesion();
$d = api_cuerpo_json();
$t = trabajo_cargar((int) ($d['id'] ?? 0));
if (!$t || (int) $t['usuario_id'] !== (int) $yo['id']) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);
try {
    trabajo_eliminar($t, (int) $yo['id']);
    api_json(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('BEDM trabajo_eliminar: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos eliminar el trabajo. Probá de nuevo.']);
}
