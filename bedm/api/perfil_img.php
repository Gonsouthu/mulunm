<?php
// Entrega la foto de perfil o la portada de una persona (solo con sesión y solo si la cuenta está activa).
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/cuentas.php';

if (!usuario_logueado()) { http_response_code(401); exit; }
session_write_close();

$u = ctype_digit((string) ($_GET['u'] ?? '')) ? (int) $_GET['u'] : 0;
$t = (string) ($_GET['t'] ?? '');
if (!$u || !isset(PERFIL_IMG[$t])) { http_response_code(404); exit; }
$st = db()->prepare('SELECT 1 FROM usuarios WHERE id = ? AND activo = 1');
$st->execute([$u]);
$ruta = perfil_img_ruta($u, $t);
if (!$st->fetchColumn() || !is_file($ruta)) { http_response_code(404); exit; }

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
