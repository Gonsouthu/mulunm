<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/plantilla_auth.php';

$uid = token_consumir((string) ($_GET['t'] ?? ''), 'verificar_email');
if ($uid !== null) {
    db()->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id = ?')->execute([$uid]);
    evento_registrar('email_verificado', $uid);
}
$destino = usuario_logueado() ? '<a href="index.php">Ir al inicio</a>' : '<a href="login.php">Iniciar sesión</a>';

auth_inicio('Confirmar correo', 'Confirmación de correo');
if ($uid !== null) {
    auth_mensaje('¡Listo! Tu correo quedó confirmado.', 'ok');
    auth_fin($destino);
} else {
    auth_mensaje('Este enlace no es válido o ya venció. Iniciá sesión y pedí un email nuevo desde tu perfil.');
    auth_fin($destino);
}
