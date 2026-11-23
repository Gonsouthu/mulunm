<?php
// ═══ PROTECCIÓN DE LA PÁGINA ═════════════════════════════════
// Sin sesión iniciada → redirige a login.php (ver includes/auth.php)
require __DIR__ . '/includes/auth.php';
$yo = requerir_login();

// ═══ SKETCH P5.JS DEL HERO ═══════════════════════════════════
$p5Url         = 'https://editor.p5js.org/Gonso.u/full/y-X3tht6r';
$p5Opacidad    = 1;    // ← EDITÁ ACÁ: 0 (invisible) a 1 (opaco)
$p5Interactivo = false;  // ← true si querés poder tocar el sketch (en celular bloquea el scroll sobre el hero)
$p5Ancho       = 1080;   // ← ancho del lienzo del sketch, en píxeles
$p5Alto        = 1920;   // ← alto del lienzo del sketch, en píxeles
$p5RecorteSuperior = 48; // ← píxeles de arriba que se esconden (el cartel con el título del proyecto). Subí o bajá este número hasta que desaparezca el cartel
$p5Ajuste      = 'cover'; // ← 'cover' = el sketch llena todo el banner (puede recortar los bordes) · 'contain' = se ve entero, con bordes azules a los costados
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MULUNM - Inicio</title>
    <!-- Tipografía Roboto Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <!-- Íconos FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>

    <!-- Pantalla de inicio (Hero) -->
    <header class="hero" id="heroSection">
        <!-- Capa de fondo: sketch p5.js (la opacidad se edita arriba, en $p5Opacidad) -->
        <div class="hero-p5-stage" id="p5Stage"
             data-ancho="<?= (int)$p5Ancho ?>" data-alto="<?= (int)$p5Alto ?>"
             data-ajuste="<?= $p5Ajuste === 'contain' ? 'contain' : 'cover' ?>"
             style="width: <?= (int)$p5Ancho ?>px; height: <?= (int)$p5Alto ?>px; opacity: <?= number_format($p5Opacidad, 2, '.', '') ?>;<?= $p5Interactivo ? ' pointer-events: auto;' : '' ?>">
            <iframe class="hero-p5"
                    src="<?= htmlspecialchars($p5Url, ENT_QUOTES, 'UTF-8') ?>"
                    title="Proyecto p5.js de fondo" aria-hidden="true" tabindex="-1"
                    scrolling="no"
                    sandbox="allow-scripts allow-same-origin"
                    style="top: -<?= (int)$p5RecorteSuperior ?>px; width: <?= (int)$p5Ancho ?>px; height: <?= (int)$p5Alto + (int)$p5RecorteSuperior ?>px;"></iframe>
        </div>

        <!-- Capa de texto, por encima del sketch -->
         <div class="hero-content">
            <!-- El style inline define las variables para calcular el font-size perfecto -->
             <div class="hero-text-block" style="--title-chars: 5; --slogan-chars: 43;">
                <h1 class="hero-title">MULUNM</h1>
                <p class="hero-slogan">Repositorio Académico de Diseño Multimedial</p>
            </div>
        </div>
    </header>

    <!-- Cuerpo principal -->
    <main class="main-content">
        <div class="grid-container">
            <!-- Cartel de sección (mismo estilo que .btn-add-job) -->
            <div class="section-banner col-4">Últimos trabajos subidos</div>

            <!-- script.js inyecta las tarjetas acá (desde api/ultimos_trabajos.php) -->
            <div class="grid-container grid-flush col-4" id="communityGrid"></div>
        </div>
    </main>

    <!-- Barra de navegación inferior -->
    <nav class="bottom-nav hidden" id="bottomNav">
        <a href="index.php" class="nav-item active" aria-label="Inicio">
            <i class="fas fa-home"></i>
        </a>
        <a href="buscar.php" class="nav-item" aria-label="Buscar">
            <i class="fas fa-search"></i>
        </a>
        <a href="perfil.php" class="nav-item" aria-label="Usuario">
            <i class="fas fa-user"></i>
        </a>
    </nav>

    <script src="script.js"></script>
</body>
</html>