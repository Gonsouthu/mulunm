<?php
// Devuelve las materias de la carrera del usuario logueado, en el formato que usa script.js.
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!usuario_logueado()) { http_response_code(401); echo '[]'; exit; }

$st = db()->prepare(
    'SELECT m.id, m.nombre, m.anio_carrera, m.cuatrimestre, m.categoria, m.icono, m.es_electiva
       FROM materias m
       JOIN usuarios u ON u.carrera_id = m.carrera_id
      WHERE u.id = ?
      ORDER BY m.anio_carrera, m.cuatrimestre, m.id'
);
$st->execute([$_SESSION['usuario_id']]);

$materias = array_map(fn($m) => [
    'id'           => (int) $m['id'],
    'nombre'       => $m['nombre'],
    'anio'         => 'Año ' . $m['anio_carrera'],
    'cuatrimestre' => $m['cuatrimestre'] == 1 ? '1er cuatrimestre' : '2do cuatrimestre',
    'categoria'    => $m['categoria'],
    'icon'         => $m['icono'],
    'electiva'     => (bool) $m['es_electiva'],
], $st->fetchAll());

echo json_encode($materias, JSON_UNESCAPED_UNICODE);
