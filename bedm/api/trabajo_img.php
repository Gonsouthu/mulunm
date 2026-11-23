<?php
// Entrega una imagen de un trabajo (n = 0: portada; n >= 1: página). Solo a personas con sesión, respetando
// si el trabajo está oculto.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';

function sin_imagen(): void { http_response_code(404); exit; }

if (!usuario_logueado()) { http_response_code(401); exit; }

// Bloquea el acceso directo por URL en una pestaña nueva o desde sitios externos
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host    = $_SERVER['HTTP_HOST'] ?? '';
if ($referer === '' || parse_url($referer, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    exit;
}

$uid = (int) $_SESSION['usuario_id'];
session_write_close();

$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$n  = ctype_digit((string) ($_GET['n'] ?? '')) ? (int) $_GET['n'] : -1;
$t = $id ? trabajo_cargar($id) : null;
if (!$t || $n < 0) sin_imagen();
if (!trabajo_visible($t, trabajo_rol($t, trabajo_autores($id, true), $uid))) sin_imagen();

$archivo = trabajo_dir($id) . DIRECTORY_SEPARATOR . ($n === 0 ? 'portada' : $n) . '.jpg';
if (!is_file($archivo)) sin_imagen();

header('Content-Type: image/jpeg');
header('Content-Disposition: inline');
header('Content-Length: ' . filesize($archivo));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($archivo);