<?php
require __DIR__ . '/includes/auth.php';
$yo = requerir_login();   // sin sesión → redirige a login.php

// ── Modo de la página ───────────────────────────────────────────
// perfil.php          → mi perfil (editable)
// perfil.php?u=nick   → perfil público de otro alumno (solo lectura)
$nickPedido = isset($_GET['u']) ? strtolower(trim($_GET['u'], " \t\n\r@")) : null;

if ($nickPedido !== null && !preg_match('/^[a-z0-9._]{3,20}$/', $nickPedido)) {
    http_response_code(404);
    exit('Perfil no encontrado');
}

$esPropio = ($nickPedido === null || $nickPedido === $yo['nickname']);

// Invitaciones para figurar como coautor/a que todavía no respondió (solo se ven en el perfil propio)
$pedidos = [];
if ($esPropio) {
    $q = db()->prepare("SELECT t.id, t.titulo, CONCAT(u.apellido, ', ', u.nombre) AS dueno
                          FROM trabajo_autores a
                          JOIN trabajos t ON t.id = a.trabajo_id AND t.eliminado_en IS NULL
                          JOIN usuarios u ON u.id = t.usuario_id
                         WHERE a.usuario_id = ? AND a.estado = 'pendiente' ORDER BY a.id DESC");
    $q->execute([$yo['id']]);
    $pedidos = $q->fetchAll();
}
if ($esPropio) {
    $perfil = $yo;   // ya viene de la base (usuario_actual)
} else {
    $st = db()->prepare(
        'SELECT u.nombre, u.apellido, u.nickname, u.avatar_url, u.banner_url, u.bio, c.nombre AS carrera
           FROM usuarios u JOIN carreras c ON c.id = u.carrera_id
          WHERE u.nickname = ? AND u.activo = 1'
    );
    $st->execute([$nickPedido]);
    $perfil = $st->fetch();
    if (!$perfil) {
        // ¿Es un nickname que esa persona usó antes? Se la lleva a su perfil actual.
        $q = db()->prepare('SELECT u.nickname FROM nickname_historial hn JOIN usuarios u ON u.id = hn.usuario_id
                             WHERE hn.nickname = ? AND u.activo = 1');
        $q->execute([$nickPedido]);
        if ($actual = $q->fetchColumn()) { header('Location: perfil.php?u=' . rawurlencode($actual)); exit; }
        http_response_code(404);
        exit('Perfil no encontrado');
    }
}

$nick = $perfil['nickname'];
$nombreCompleto = trim($perfil['nombre'] . ' ' . $perfil['apellido']);   // el mismo nombre del registro
$iniciales = mb_strtoupper(mb_substr($perfil['nombre'], 0, 1) . mb_substr($perfil['apellido'], 0, 1));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEDM - <?= $esPropio ? 'Mi Perfil' : '@' . $h($nick) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <?= csrf_meta() ?>
</head>
<body class="inner-page" data-modo="<?= $esPropio ? 'propio' : 'publico' ?>" data-nick="<?= $h($nick) ?>">
    <?= $esPropio ? banner_verificacion($yo) : '' ?>

    <!-- SECCIÓN 1: PERFIL -->
    <div id="vista-perfil-principal">
        <main class="main-content">
            <div class="grid-container">

                <header class="profile-header col-4">
                    <div class="profile-banner" id="userBanner"<?= $perfil['banner_url'] ? ' style="background-image: url(\'' . $h($perfil['banner_url']) . '\');"' : '' ?>>
                        <?php if ($esPropio): ?>
                        <input type="file" id="bannerImageInput" accept="image/jpeg,image/png,image/webp" style="display: none;">
                        <?php endif; ?>
                    </div>

                    <div class="profile-info-grid">
                        <div class="profile-avatar-col">
                            <?php if ($perfil['avatar_url']): ?>
                            <img class="profile-pic-img" src="<?= $h($perfil['avatar_url']) ?>" alt="Foto de <?= $h($perfil['nombre']) ?>">
                            <?php else: ?>
                            <div class="profile-pic-img avatar-iniciales" aria-label="Sin foto de perfil"><?= $h($iniciales) ?></div>
                            <?php endif; ?>
                            <?php if ($esPropio): ?>
                            <input type="file" id="avatarImageInput" accept="image/jpeg,image/png,image/webp" style="display: none;">
                            <?php endif; ?>
                        </div>

                        <div class="profile-text-col">
                            <h2><?= $h($nombreCompleto) ?></h2>
                            <p class="profile-nick">
                                <span id="nickText">@<?= $h($nick) ?></span>
                            </p>
                            <p><?= $esPropio ? 'Legajo: ' . $h($yo['legajo'] ?: '—') . '<br>' : '' ?><?= $h($perfil['carrera']) ?></p>
                        </div>

                        <?php if ($esPropio): ?>
                        <div class="logout-col">
                            <button type="button" id="editProfileTrigger" class="logout-action-btn btn-edit-profile">Editar perfil</button>
                            <button type="button" id="logoutTrigger" class="logout-action-btn">Cerrar sesión</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($esPropio): ?>

                    <div class="form-card col-4 no-mb">
                        <label class="form-label" for="bioInput">Presentación (hasta 280 caracteres)</label>
                        <textarea id="bioInput" class="form-input" rows="3" maxlength="280" placeholder="Contá algo sobre vos…"><?= $h($yo['bio'] ?? '') ?></textarea>
                        <p class="form-error" id="bioError" role="alert"></p>
                        <p class="form-ok" id="bioOk" role="status"></p>
                        <button type="button" class="aviso-btn" id="btnGuardarBio" style="margin-left: 0;">Guardar presentación</button>
                    </div>

                    <?php if ($pedidos): ?>
                    <div class="aviso-invitacion col-4">
                        <p><b>Tenés <?= count($pedidos) ?> <?= count($pedidos) === 1 ? 'invitación' : 'invitaciones' ?> para figurar como coautor/a:</b></p>
                        <ul class="autores">
                            <?php foreach ($pedidos as $p): ?>
                            <li><a href="trabajo.php?id=<?= (int) $p['id'] ?>"><?= $h($p['titulo']) ?></a> <span class="nick">· de <?= $h($p['dueno']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <a href="crear.php" class="btn-add-job col-4">Añadir trabajo</a>

                    <div class="search-bar-rounded col-4">
                        <h3 class="filter-title">Mis Materias</h3>
                        <div class="filters-grid">
                            <div class="custom-select-wrapper select-white-blue">
                                <select class="custom-select" id="yearSelector">
                                    <option value="todos" selected>Todos los años</option>
                                    <option value="Año 1">1er Año</option>
                                    <option value="Año 2">2do Año</option>
                                    <option value="Año 3">3er Año</option>
                                    <option value="Año 4">4to Año</option>
                                    <option value="Año 5">5to Año</option>
                                </select>
                            </div>
                            <div class="custom-select-wrapper select-white-blue">
                                <select class="custom-select" id="termSelector">
                                    <option value="todos" selected>Todo el año</option>
                                    <option value="1er cuatrimestre">1er cuatrimestre</option>
                                    <option value="2do cuatrimestre">2do cuatrimestre</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="grid-container grid-flush col-4" id="materiasGrid"></div>

                <?php else: ?>

                    <?php if (!empty($perfil['bio'])): ?>
                    <div class="form-card col-4 no-mb">
                        <span class="form-label">Presentación</span>
                        <p class="trabajo-descripcion"><?= nl2br($h($perfil['bio'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Perfil público: trabajos publicados del alumno -->
                    <div class="section-title col-4">
                        <h3>Trabajos de @<?= $h($nick) ?></h3>
                    </div>
                    <div class="grid-container grid-flush col-4" id="trabajosUsuarioGrid"></div>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <?php if ($esPropio): ?>
    <!-- SECCIÓN 2: MIS TRABAJOS DE UNA MATERIA -->
    <div id="vista-trabajos" style="display: none;">
        <main class="main-content">
            <button class="back-btn" data-accion="cerrar-materia-perfil"><i class="fa-solid fa-arrow-left"></i> Volver a Mis Materias</button>
            <div class="section-title"><h3 id="tpTitle">Mis trabajos</h3></div>
            <a href="crear.php" id="btnAddMateria" class="btn-add-job">Publicar un trabajo en esta materia</a>
            <div class="grid-container" id="trabajosMateriaGrid" style="margin-top: var(--gutter);"></div>
        </main>
    </div>

    <!-- Modal: cerrar sesión -->
    <div id="logoutModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <p>¿Estás seguro que deseas cerrar la sesión?</p>
            <div class="modal-actions">
                <button id="cancelLogout" class="modal-btn cancel">Cancelar</button>
                <!-- logout.php solo acepta POST con el código CSRF -->
                <form method="post" action="logout.php" style="display: contents;">
                    <?= csrf_campo() ?>
                    <button type="submit" class="modal-btn confirm">Confirmar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: editar perfil -->
    <div id="editProfileModal" class="modal-overlay" style="display: none;">
        <div class="modal-box edit-profile-modal">
            <h3>Editar perfil</h3>

            <span class="form-label">Foto de perfil</span>
            <div class="edit-media-row">
                <label for="avatarImageInput" class="aviso-btn" style="cursor: pointer; margin-left: 0;">Cambiar foto</label>
                <button type="button" class="aviso-btn aviso-btn-sec" data-quitar-imagen="avatar"<?= $yo['avatar_url'] ? '' : ' disabled' ?>>Quitar foto</button>
            </div>

            <span class="form-label">Banner</span>
            <div class="edit-media-row">
                <label for="bannerImageInput" class="aviso-btn" style="cursor: pointer; margin-left: 0;">Cambiar banner</label>
                <button type="button" class="aviso-btn aviso-btn-sec" data-quitar-imagen="banner"<?= $yo['banner_url'] ? '' : ' disabled' ?>>Quitar banner</button>
            </div>

            <label class="form-label" for="nickInput">Nickname</label>
            <input type="text" id="nickInput" class="form-input" maxlength="20" value="<?= $h($nick) ?>" autocomplete="off">

            <label class="form-label" for="legajoInput">Legajo (solo lo ves vos)</label>
            <input type="text" id="legajoInput" class="form-input" maxlength="12" value="<?= $h($yo['legajo'] ?? '') ?>" autocomplete="off">

            <p class="form-error" id="cuentaError" role="alert" style="margin-top: 10px;"></p>
            <p class="form-ok" id="cuentaOk" role="status" style="margin-top: 10px;"></p>

            <div class="modal-actions" style="margin-top: 16px;">
                <button type="button" id="cancelEditProfile" class="modal-btn cancel">Cancelar</button>
                <button type="button" id="btnGuardarPerfil" class="modal-btn confirm">Guardar</button>
            </div>

            <hr class="sep">
            <a href="baja.php" class="aviso-btn aviso-btn-peligro" style="display: block; text-align: center; text-decoration: none; margin-left: 0;">Dar de baja mi cuenta</a>
        </div>
    </div>
    <?php endif; ?>

    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item" aria-label="Inicio"><i class="fas fa-home"></i></a>
        <a href="buscar.php" class="nav-item" aria-label="Buscar"><i class="fas fa-search"></i></a>
        <a href="perfil.php" class="nav-item<?= $esPropio ? ' active' : '' ?>" aria-label="Usuario"><i class="fas fa-user"></i></a>
    </nav>

    <script src="script.js"></script>
    <?php if ($esPropio): ?><script src="script-cuenta.js"></script><?php endif; ?>
</body>
</html>