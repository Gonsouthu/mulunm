<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/plantilla_auth.php';
redirigir_si_logueado();

$error = '';
$enviado = false;
$pendiente = null;   // [id, email, nombre] si hay que mandar el email
$emailPrev = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailPrev = mb_substr(strtolower(trim($_POST['email'] ?? '')), 0, 120);

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La página venció. Volvé a intentar.';
    } elseif (!filter_var($emailPrev, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá un correo electrónico válido.';
    } elseif (eventos_recientes('olvide_password', 60) >= (int) cfg_valor('olvide_max_por_hora', 5)) {
        $error = 'Hiciste varios pedidos seguidos. Esperá un rato y probá de nuevo.';
    } else {
        $st = db()->prepare('SELECT id, nombre, email FROM usuarios WHERE email = ? AND activo = 1');
        $st->execute([$emailPrev]);
        $u = $st->fetch();
        evento_registrar('olvide_password', $u ? (int) $u['id'] : null);   // se registra siempre, exista o no el correo

        // Máximo 3 emails por hora para una misma cuenta (así nadie puede llenar de mails a otra persona)
        if ($u && eventos_recientes('olvide_password', 60, (int) $u['id']) <= 3) {
            $pendiente = [(int) $u['id'], $u['email'], $u['nombre']];
        }
        $enviado = true;   // la respuesta es la misma exista o no la cuenta
    }
}

auth_inicio('Recuperar contraseña', 'Recuperar contraseña');
if ($enviado) {
    auth_mensaje('Si ese correo está registrado, en unos minutos te llega un email con el enlace para elegir una contraseña nueva. Revisá también la carpeta de spam.', 'ok');
} else {
    auth_mensaje($error);
?>
            <form method="post" action="olvide.php">
                <?= csrf_campo() ?>
                <p class="texto-ayuda">Escribí el correo con el que te registraste y te mandamos un enlace para elegir una contraseña nueva.</p>
                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Alumno@gmail.com" value="<?= h($emailPrev) ?>" maxlength="120" autocomplete="email" required>
                    </div>
                </div>
                <button type="submit" class="login-btn">Enviar enlace</button>
            </form>
<?php
}
auth_fin('<a href="login.php">Volver a iniciar sesión</a>');

// El email se manda DESPUÉS de responder (si el servidor lo permite), para que la demora
// no delate si la cuenta existe.
if ($pendiente) {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    mail_recuperacion(...$pendiente);
}
