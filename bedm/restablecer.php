<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/plantilla_auth.php';
redirigir_si_logueado();

$token = (string) ($_POST['t'] ?? $_GET['t'] ?? '');
$uid = token_consultar($token, 'recuperar_password');
$error = '';

if ($uid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['confirmPassword'] ?? '');
    $q = db()->prepare('SELECT email, nickname FROM usuarios WHERE id = ? AND activo = 1');
    $q->execute([$uid]);
    $cuenta = $q->fetch();

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La página venció. Volvé a intentar.';
    } elseif (!$cuenta) {
        $uid = null;
    } elseif ($e = validar_password($pass, $cuenta['email'], $cuenta['nickname'])) {
        $error = $e;
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $dueno = token_consumir($token, 'recuperar_password');   // se gasta recién ahora que la contraseña es válida
        if ($dueno === null) {
            $pdo->rollBack();
            $uid = null;
        } else {
            // sesion_version + 1: cierra todas las sesiones abiertas con la contraseña vieja.
            // Entrar por el enlace del email también confirma que el correo es suyo.
            $pdo->prepare('UPDATE usuarios SET password_hash = ?, email_verificado = 1,
                                  sesion_version = IF(sesion_version >= 65000, 1, sesion_version + 1) WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $dueno]);
            $pdo->prepare("UPDATE tokens_usuario SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL")->execute([$dueno]);
            $pdo->commit();
            evento_registrar('password_restablecida', $dueno);
            header('Location: login.php?ok=reset'); exit;
        }
    }
}

auth_inicio('Nueva contraseña', 'Elegí tu nueva contraseña');
if (!$uid) {
    auth_mensaje('Este enlace no es válido o ya venció. Pedí uno nuevo.');
    auth_fin('<a href="olvide.php">Pedir un enlace nuevo</a>');
    exit;
}
auth_mensaje($error);
?>
            <form method="post" action="restablecer.php">
                <?= csrf_campo() ?>
                <input type="hidden" name="t" value="<?= h($token) ?>">
                <div class="input-group">
                    <label for="password">Contraseña nueva</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="input-group">
                    <label for="confirmPassword">Repetí la contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="••••••••" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>
                <p class="texto-ayuda">Mínimo 8 caracteres. Al cambiarla, se cierran las sesiones abiertas en otros dispositivos.</p>
                <button type="submit" class="login-btn">Guardar contraseña</button>
            </form>
<?php auth_fin('<a href="login.php">Volver a iniciar sesión</a>'); ?>
