<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cuentas.php';
require __DIR__ . '/includes/plantilla_auth.php';
$yo = requerir_login();
$uid = (int) $yo['id'];

$q = db()->prepare("SELECT COUNT(*) FROM trabajos WHERE usuario_id = ? AND eliminado_en IS NULL");
$q->execute([$uid]);
$propios = (int) $q->fetchColumn();
$q = db()->prepare("SELECT COUNT(DISTINCT a.trabajo_id) FROM trabajo_autores a JOIN trabajos t ON t.id = a.trabajo_id AND t.eliminado_en IS NULL
                     WHERE t.usuario_id = ? AND a.estado = 'aceptado' AND a.usuario_id IS NOT NULL AND a.usuario_id <> ?");
$q->execute([$uid, $uid]);
$grupales = (int) $q->fetchColumn();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modo = (string) ($_POST['modo'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La página venció. Volvé a intentar.';
    } elseif (($_POST['entiendo'] ?? '') !== '1') {
        $error = 'Tenés que tildar que entendés lo que va a pasar.';
    } elseif (!in_array($modo, ['ocultar', 'eliminar'], true)) {
        $error = 'Elegí qué hacer con tus trabajos.';
    } elseif (login_demasiados_intentos($yo['email'])) {
        $error = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos y probá de nuevo.';
    } else {
        // Se pide la contraseña: así nadie con la sesión abierta (un celular prestado, por ejemplo) puede dar de baja la cuenta
        $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$uid]);
        if (strlen($pass) > 200 || !password_verify($pass, (string) $st->fetchColumn())) {
            login_registrar($yo['email'], false);
            $error = 'La contraseña no es correcta.';
        } else {
            login_registrar($yo['email'], true);
            try {
                cuenta_dar_de_baja($yo, $modo);
                cerrar_sesion();
                header('Location: login.php?ok=baja'); exit;
            } catch (Throwable $e) {
                error_log('BEDM baja: ' . $e);
                $error = 'No pudimos completar la baja. Probá de nuevo.';
            }
        }
    }
}

auth_inicio('Dar de baja mi cuenta', 'Dar de baja mi cuenta', 'legal-box');
auth_mensaje($error);
?>
            <div class="legal-text" style="max-height: none;">
                <p>Vas a dar de baja la cuenta de <b><?= h(nombre_apellido_nombre($yo)) ?></b> (@<?= h($yo['nickname']) ?>).</p>
                <h2>Qué va a pasar</h2>
                <ul class="lista-baja">
                    <li>Tu perfil deja de verse y no vas a poder ingresar.</li>
                    <li>Se borran tus <b>comentarios</b> y tus <b>«me gusta»</b>.</li>
                    <li>Dejás de figurar como coautor/a en trabajos de otras personas.</li>
                    <li>Tenés <b><?= CUENTA_DIAS_REACTIVAR ?> días</b> para arrepentirte: ingresando con tu contraseña podés reactivar la cuenta.</li>
                    <li>Pasado ese plazo, tus datos personales (nombre, correo, legajo, fotos) se <b>anonimizan</b> y ya no se puede recuperar la cuenta.</li>
                </ul>
                <p>Tenés <b><?= $propios ?></b> trabajo(s) publicados.
                <?php if ($grupales > 0): ?><b>Atención:</b> <?= $grupales ?> de ellos tienen coautores; al ocultarse o eliminarse, <b>también dejan de verse para tus coautores</b> (las imágenes llevan tu nombre en el sello y no se pueden modificar).<?php endif; ?></p>
                <p>Antes de seguir, podés <b>descargar tu historial</b> desde tu perfil («Mi cuenta»).</p>
            </div>
            <form method="post" action="baja.php">
                <?= csrf_campo() ?>
                <span class="form-label" style="display:block;margin-bottom:6px;">¿Qué hacemos con tus trabajos?</span>
                <label class="chk" style="margin-bottom:6px;"><input type="radio" name="modo" value="ocultar" checked> <span><b>Ocultarlos</b>: se conservan (si reactivás, podés volver a mostrarlos).</span></label>
                <label class="chk" style="margin-bottom:12px;"><input type="radio" name="modo" value="eliminar"> <span><b>Eliminarlos</b>: se borran las imágenes y no se pueden recuperar.</span></label>
                <div class="input-group">
                    <label for="password">Tu contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" maxlength="200" required>
                    </div>
                </div>
                <label class="terms-label"><input type="checkbox" name="entiendo" value="1" required> <span>Entiendo lo que va a pasar con mi cuenta y mis trabajos.</span></label>
                <button type="submit" class="login-btn" style="background:#B42318;">Dar de baja mi cuenta</button>
            </form>
<?php auth_fin('<a href="perfil.php">Cancelar y volver a mi perfil</a>'); ?>
