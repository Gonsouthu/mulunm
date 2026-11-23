<?php
// Reenvía el email para confirmar el correo (máximo 3 por hora por cuenta).
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';
header('Content-Type: application/json; charset=utf-8');

function responder(int $http, array $datos): void { http_response_code($http); echo json_encode($datos, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
if (!usuario_logueado()) responder(401, ['ok' => false, 'error' => 'Sesión vencida. Volvé a ingresar.']);
csrf_exigir_api();

$yo = usuario_actual();
if (!$yo) responder(401, ['ok' => false, 'error' => 'Sesión vencida. Volvé a ingresar.']);
if (!empty($yo['email_verificado'])) responder(200, ['ok' => true, 'mensaje' => 'Tu correo ya está confirmado.']);

if (eventos_recientes('reenvio_verificacion', 60, (int) $yo['id']) >= 3 || eventos_recientes('reenvio_verificacion', 60) >= 10) {
    responder(429, ['ok' => false, 'error' => 'Ya te enviamos varios emails. Revisá tu bandeja (y el spam) y probá más tarde.']);
}
evento_registrar('reenvio_verificacion', (int) $yo['id']);

if (!mail_verificacion((int) $yo['id'], $yo['email'], $yo['nombre'])) {
    responder(503, ['ok' => false, 'error' => 'No pudimos enviar el email ahora. Probá de nuevo más tarde.']);
}
responder(200, ['ok' => true, 'mensaje' => 'Listo, te enviamos un email nuevo.']);
