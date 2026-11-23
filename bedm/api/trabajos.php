<?php
// Listado y búsqueda de trabajos. Parámetros opcionales: materia (id), ciclo (año), usuario (nickname), anio, cuatri, categoria, q (texto), limite.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';
header('Content-Type: application/json; charset=utf-8');
if (!usuario_logueado()) { http_response_code(401); echo '[]'; exit; }
$uid = (int) $_SESSION['usuario_id'];
session_write_close();

$f = [
    'materia' => ctype_digit((string) ($_GET['materia'] ?? '')) ? (int) $_GET['materia'] : 0,
    'ciclo'   => ctype_digit((string) ($_GET['ciclo'] ?? '')) ? (int) $_GET['ciclo'] : 0,
    'usuario' => preg_match('/^@?[a-z0-9._]{3,20}$/', strtolower((string) ($_GET['usuario'] ?? ''))) ? strtolower($_GET['usuario']) : '',
    'limite'  => ctype_digit((string) ($_GET['limite'] ?? '')) ? (int) $_GET['limite'] : 60,
    'anio'    => in_array($_GET['anio'] ?? '', ['1', '2', '3', '4', '5'], true) ? (int) $_GET['anio'] : 0,
    'cuatri'  => in_array($_GET['cuatri'] ?? '', ['1', '2'], true) ? (int) $_GET['cuatri'] : 0,
    'categoria' => in_array($_GET['categoria'] ?? '', ['taller', 'tecnología', 'sistemas'], true) ? $_GET['categoria'] : '',
    'q'       => is_string($_GET['q'] ?? null) ? $_GET['q'] : '',
];
echo json_encode(trabajos_listar($f, $uid), JSON_UNESCAPED_UNICODE);
