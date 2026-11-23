<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/mailer.php';
redirigir_si_logueado();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$error = '';
$v = ['nombre' => '', 'apellido' => '', 'nickname' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['nombre']   = trim($_POST['nombre'] ?? '');
    $v['apellido'] = trim($_POST['apellido'] ?? '');
    $v['nickname'] = ltrim(strtolower(trim($_POST['nickname'] ?? '')), '@');
    $v['email']    = strtolower(trim($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['confirmPassword'] ?? '');
    $terminos = ($_POST['terminos'] ?? '') === '1';

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'La página venció. Volvé a intentar.';
    } elseif (eventos_recientes('registro', 60) >= (int) cfg_valor('registro_max_por_hora', 10)) {
        $error = 'Se crearon muchas cuentas desde tu conexión. Probá de nuevo en una hora.';
    } elseif ($v['nombre'] === '' || $v['apellido'] === '' || mb_strlen($v['nombre']) > 60 || mb_strlen($v['apellido']) > 60) {
        $error = 'Completá nombre y apellido (hasta 60 caracteres).';
    } elseif (!preg_match('/^[a-z0-9._]{3,20}$/', $v['nickname'])) {
        $error = 'El nickname debe tener 3 a 20 caracteres: letras, números, punto o guion bajo.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 120) {
        $error = 'Ingresá un correo electrónico válido.';
    } elseif ($e = validar_password($pass, $v['email'], $v['nickname'])) {
        $error = $e;
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!$terminos) {
        $error = 'Para crear la cuenta tenés que aceptar los términos y condiciones.';
    } elseif (!nickname_disponible($v['nickname'])) {
        $error = 'Ese nickname ya está en uso.';
    } else {
        evento_registrar('registro');   // cuenta contra el límite por conexión (se cuenta cada intento válido)
        try {
            $pdo = db();
            $st = $pdo->prepare(
                'INSERT INTO usuarios (carrera_id, nombre, apellido, nickname, email, password_hash, anio_ingreso, terminos_aceptados_en)
                 VALUES (1, ?, ?, ?, ?, ?, NULL, NOW())'
            );
            $st->execute([
                $v['nombre'], $v['apellido'], $v['nickname'], $v['email'],
                password_hash($pass, PASSWORD_DEFAULT),
            ]);
            $nuevoId = (int) $pdo->lastInsertId();
            mail_verificacion($nuevoId, $v['email'], $v['nombre']);   // si falla el envío, la cuenta igual se crea (se puede reenviar)
            iniciar_sesion($nuevoId, 1);   // queda logueado
            header('Location: index.php'); exit;
        } catch (PDOException $e) {
            $m = $e->getMessage();
            if ($e->getCode() === '23000' && strpos($m, 'nickname') !== false) {
                $error = 'Ese nickname ya está en uso.';
            } elseif ($e->getCode() === '23000' && strpos($m, 'email') !== false) {
                $error = 'Ese correo ya está registrado.';
            } else {
                error_log('BEDM registro: ' . $m);
                $error = 'No pudimos crear la cuenta. Intentá de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEDM - Crear Cuenta</title>
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
                <p>Creá tu cuenta en el Repositorio</p>
            </div>

            <form id="registerForm" method="post" action="register.php" novalidate>
                <?= csrf_campo() ?>
                <!-- Se muestra si PHP devuelve un error (clase "show") o si el JS detecta uno -->
                <div id="errorMessage" class="error-message<?= $error ? ' show' : '' ?>">
                    <i class="fa-solid fa-circle-exclamation"></i> <span id="errorText"><?= $h($error ?: 'Las contraseñas no coinciden.') ?></span>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="nombre">Nombre</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" id="nombre" name="nombre" maxlength="60" value="<?= $h($v['nombre']) ?>" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="apellido">Apellido</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-address-card input-icon"></i>
                            <input type="text" id="apellido" name="apellido" maxlength="60" value="<?= $h($v['apellido']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label for="nickname">Nickname</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-at input-icon"></i>
                        <input type="text" id="nickname" name="nickname" maxlength="20" placeholder="tu.nickname" value="<?= $h($v['nickname']) ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Alumno@gmail.com" value="<?= $h($v['email']) ?>" maxlength="120" autocomplete="email" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirmPassword">Confirmar Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="••••••••" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>

                <label class="terms-label">
                    <input type="checkbox" id="terminos" name="terminos" value="1" required>
                    <span>Acepto los <a href="terminos.php" target="_blank" rel="noopener">términos y condiciones</a> y declaro que soy autor/a de los trabajos que publique (o que cuento con el permiso de mis coautores).</span>
                </label>

                <button type="submit" class="login-btn">Crear cuenta</button>
            </form>

            <div class="login-footer">
                ¿Ya tenés cuenta? <a href="login.php">Ingresá acá</a>
            </div>

        </div>
    </div>
    <script src="script-auth.js"></script>
</body>
</html>