<?php
// Plantilla de las pantallas de acceso (olvidé mi contraseña, restablecer, verificar, términos).
// Usa los mismos estilos que login.php y register.php.
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function auth_inicio(string $titulo, string $subtitulo = '', string $claseCaja = ''): void { ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>BEDM - <?= h($titulo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box <?= h($claseCaja) ?>">
            <div class="login-header">
                <h1>BEDM</h1>
                <p><?= $subtitulo !== '' ? h($subtitulo) : 'Repositorio Curricular Situado<br>UNM' ?></p>
            </div>
<?php }

// $pieHtml es HTML propio del sitio (nunca texto del usuario)
function auth_fin(string $pieHtml = ''): void {
    if ($pieHtml !== '') echo '            <div class="login-footer">' . $pieHtml . "</div>\n";
    echo "        </div>\n    </div>\n</body>\n</html>\n";
}

function auth_mensaje(string $texto, string $tipo = 'error'): void {
    if ($texto === '') return;
    $icono = $tipo === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
    $clase = $tipo === 'error' ? 'error-message' : 'ok-message';
    echo '            <div class="' . $clase . ' show"><i class="fa-solid ' . $icono . '"></i> ' . h($texto) . "</div>\n";
}
