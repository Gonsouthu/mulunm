<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cuentas.php';
require __DIR__ . '/includes/plantilla_auth.php';

// Solo se llega acá después de ingresar con la contraseña correcta de una cuenta dada de baja hace menos de 30 días
$uid = (int) ($_SESSION['reactivar_uid'] ?? 0);
if ($uid <= 0) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido($_POST['csrf'] ?? null)) {
    if (($_POST['accion'] ?? '') === 'reactivar' && cuenta_reactivar($uid)) {
        $st = db()->prepare('SELECT sesion_version FROM usuarios WHERE id = ?');
        $st->execute([$uid]);
        iniciar_sesion($uid, (int) $st->fetchColumn());
        header('Location: perfil.php'); exit;
    }
    cerrar_sesion();
    header('Location: login.php'); exit;
}
auth_inicio('Reactivar cuenta', 'Reactivar mi cuenta');
?>
            <div class="legal-text" style="max-height:none;">
                <p>Tu cuenta está <b>dada de baja</b>. Podés reactivarla ahora.</p>
                <p>Si la reactivás, vuelve a verse tu perfil. Tus comentarios y «me gusta» no se recuperan, y tus trabajos siguen <b>ocultos</b> (si no los eliminaste): podés volver a mostrarlos desde cada trabajo.</p>
            </div>
            <form method="post" action="reactivar.php">
                <?= csrf_campo() ?>
                <button type="submit" name="accion" value="reactivar" class="login-btn">Reactivar mi cuenta</button>
                <button type="submit" name="accion" value="cancelar" class="login-btn" style="background:#fff;color:#004E9F;border:1.5px solid #004E9F;margin-top:10px;">No, salir</button>
            </form>
<?php auth_fin(); ?>
