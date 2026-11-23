<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuario_logueado()) { http_response_code(401); echo '[]'; exit; }
$uid = (int) $_SESSION['usuario_id'];
session_write_close();
echo json_encode(trabajos_listar(['limite' => 6], $uid), JSON_UNESCAPED_UNICODE);
