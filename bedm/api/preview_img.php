<?php
// Entrega una imagen de previsualización temporal, solo a quien la generó.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/ingesta.php';

function no_encontrado(): void { http_response_code(404); exit; }

if (!usuario_logueado()) { http_response_code(401); exit; }
$usuarioId = (int) $_SESSION['usuario_id'];
session_write_close();   // las imágenes se piden en paralelo: no hay que retener la sesión

$token = (string) ($_GET['t'] ?? '');
$n = (int) ($_GET['n'] ?? 0);
$meta = ingesta_leer_meta($token);
if (!$meta || (int) $meta['usuario_id'] !== $usuarioId || !in_array($n, $meta['paginas'], true)) no_encontrado();

$ruta = ingesta_dir_token($token) . DIRECTORY_SEPARATOR . $n . '.jpg';
if (!is_file($ruta)) no_encontrado();

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
