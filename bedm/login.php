<?php
require __DIR__ . '/includes/auth.php';
redirigir_si_logueado();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$error = '';
$emailPrev = '';

// Mensajes que llegan desde otras pantallas (lista cerrada: nunca se muestra texto de la URL)
$avisos = [
    'reset' => 'Tu contraseña se actualizó. Ingresá con la nueva.',
    'baja'  => 'Tu cuenta fue dada de baja. Tenés 30 días para reactivarla ingresando con tu contraseña.',
];
$aviso = $avisos[$_GET['ok'] ?? ''] ?? '';

// Hash de relleno: si el correo no existe se verifica igual contra este hash, así tarda lo mismo
// y no se puede averiguar qué correos están registrados midiendo el tiempo de respuesta.
const HASH_RELLENO = '$2y$10$9O9hUGbyUkDuDbe5wTsFnejvj1SkPlj6RwSeNxwYFFZNNfZKgy/xq';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailPrev = mb_substr(strtolower(trim($_POST['email'] ?? '')), 0, 120);
    $pass = (string) ($_POST['password'] ?? '');

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La página venció. Volvé a intentar.';
    } elseif (login_demasiados_intentos($emailPrev)) {
        $error = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos o recuperá tu contraseña.';
    } else {
        $st = db()->prepare('SELECT id, password_hash, sesion_version, activo, (baja_en > (NOW() - INTERVAL 30 DAY)) AS reactivable FROM usuarios WHERE email = ?');
        $st->execute([$emailPrev]);
        $u = $st->fetch();

        $correcta = strlen($pass) <= 200 && password_verify($pass, $u ? $u['password_hash'] : HASH_RELLENO) && $u;
        // Cuenta dada de baja: con la contraseña correcta y dentro de los 30 días se ofrece reactivarla
        if ($correcta && !(int) $u['activo']) {
            if ($u['reactivable']) {
                login_registrar($emailPrev, true);
                session_regenerate_id(true);
                $_SESSION = ['reactivar_uid' => (int) $u['id'], 'inicio' => time(), 'ultimo_acceso' => time()];
                header('Location: reactivar.php'); exit;
            }
            $correcta = false;   // plazo vencido: se trata igual que una cuenta inexistente
        }
        if ($correcta) {
            login_registrar($emailPrev, true);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            iniciar_sesion((int) $u['id'], (int) $u['sesion_version']);
            header('Location: index.php'); exit;
        }
        login_registrar($emailPrev, false);
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEDM - Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">

            <div class="login-header">
                <h1>BEDM</h1>
                <p>Repositorio Curricular Situado<br>UNM</p>
            </div>

            <form id="loginForm" method="post" action="login.php">
                <?= csrf_campo() ?>
                <?php if ($aviso): ?>
                <div class="ok-message show"><i class="fa-solid fa-circle-check"></i> <?= $h($aviso) ?></div>
                <?php endif; ?>
                <div class="error-message<?= $error ? ' show' : '' ?>">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $h($error) ?>
                </div>

                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Alumno@gmail.com" value="<?= $h($emailPrev) ?>" maxlength="120" autocomplete="username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="forgot-password">
                    <a href="olvide.php">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="login-btn">Ingresar</button>
            </form>

            <div class="login-footer">
                ¿No tenés una cuenta? <a href="register.php">Registrate acá</a>
            </div>

        </div>
    </div>
</body>
</html>
