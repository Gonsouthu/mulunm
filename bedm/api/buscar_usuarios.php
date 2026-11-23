<?php
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuario_logueado()) { http_response_code(401); echo '[]'; exit; }

$q = ltrim(strtolower(trim($_GET['q'] ?? '')), '@');
$esc = addcslashes($q, '%_\\');

$st = db()->prepare(
    "SELECT nickname, nombre, apellido FROM usuarios
      WHERE activo = 1 AND (nickname LIKE ? OR CONCAT(nombre, ' ', apellido) LIKE ?)
      ORDER BY nickname LIMIT 20"
);
$st->execute([$esc . '%', '%' . $esc . '%']);
echo json_encode($st->fetchAll(), JSON_UNESCAPED_UNICODE);