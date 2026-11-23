<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/trabajos.php';
$yo = requerir_login();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Materias de la carrera del usuario (el selector se arma acá, sin depender de JavaScript)
$st = db()->prepare(
    'SELECT m.id, m.nombre, m.anio_carrera, m.cuatrimestre, m.es_electiva, c.anio_inicio
       FROM materias m JOIN usuarios u ON u.carrera_id = m.carrera_id JOIN carreras c ON c.id = m.carrera_id
      WHERE u.id = ? ORDER BY m.anio_carrera, m.cuatrimestre, m.id'
);
$st->execute([$yo['id']]);
$grupos = [];
foreach ($st->fetchAll() as $m) $grupos[$m['anio_carrera'] . '|' . $m['cuatrimestre']][] = $m;
$preMateria = ctype_digit((string) ($_GET['materia'] ?? '')) ? (int) $_GET['materia'] : 0;
$verificado = !empty($yo['email_verificado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEDM - Publicar trabajo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <?= csrf_meta() ?>
</head>
<body class="inner-page" data-yo-nick="<?= $h($yo['nickname']) ?>">
    <?= banner_verificacion($yo) ?>

    <main class="main-content" style="padding-bottom: 120px;">
        <button class="back-btn" data-accion="volver">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </button>

        <form id="trabajoForm" novalidate data-actual="<?= (int) date('Y') ?>" data-autor="<?= $h(nombre_apellido_nombre($yo)) ?>">

            <h2 class="form-titulo">Publicar un trabajo</h2>
            <p class="form-ayuda">Publicás como <b><?= $h(nombre_apellido_nombre($yo)) ?></b>. Tu nombre va a figurar en el nombre del trabajo y en cada imagen.</p>

            <!-- 1. Materia y ciclo -->
            <section class="form-card">
                <label class="form-label" for="materiaSelect">Materia</label>
                <select id="materiaSelect" class="form-input" required>
                    <option value="">Elegí la materia…</option>
                    <?php foreach ($grupos as $clave => $lista): [$anio, $cuat] = explode('|', $clave); ?>
                    <optgroup label="Año <?= (int) $anio ?> · <?= $cuat == 1 ? '1er' : '2do' ?> cuatrimestre">
                        <?php foreach ($lista as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" data-anio="<?= (int) $m['anio_carrera'] ?>" data-inicio="<?= (int) $m['anio_inicio'] ?>" data-electiva="<?= (int) $m['es_electiva'] ?>"<?= $preMateria === (int) $m['id'] ? ' selected' : '' ?>><?= $h($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>

                <label class="form-label" for="cicloSelect">Ciclo lectivo (año en que cursaste)</label>
                <select id="cicloSelect" class="form-input" required></select>

                <div id="electivaBloque" style="display: none;">
                    <label class="form-label" for="electivaInput">¿Qué electiva cursaste?</label>
                    <input type="text" id="electivaInput" class="form-input" maxlength="80" placeholder="Nombre de la electiva" autocomplete="off">
                </div>
            </section>

            <!-- 2. Nombre y descripción -->
            <section class="form-card">
                <label class="form-label" for="tituloInput">Nombre del trabajo</label>
                <input type="text" id="tituloInput" class="form-input" maxlength="100" placeholder="Ej.: TP1 - Manual de marca" autocomplete="off" required>
                <p class="nombre-canonico" id="nombreCanonico" aria-live="polite"></p>

                <label class="form-label" for="descripcionInput">Descripción (opcional)</label>
                <textarea id="descripcionInput" class="form-input" rows="4" maxlength="2000" placeholder="Contá de qué trata el proyecto, herramientas que usaste, consigna…"></textarea>
            </section>

            <!-- 3. Link + previsualización -->
            <div class="post-evaluation-box">
                <div class="grid-container" style="padding: 0; grid-template-columns: 1fr;">
                    <div class="search-input-wrapper" style="margin-bottom: 0;">
                        <i class="fa-solid fa-link search-icon"></i>
                        <input type="url" id="pdfUrlInput" class="search-input" placeholder="Pegá el link de tu PDF" autocomplete="off">
                    </div>
                    <!-- Estado de la previsualización + token que se usa al publicar -->
                    <p class="preview-info" id="previewInfo">Link de Google Drive, Dropbox u OneDrive. El archivo tiene que estar compartido como «Cualquier persona con el enlace».</p>
                    <input type="hidden" id="previewToken" value="">
                    <div class="create-pdf-viewer" id="pdfViewer"></div>
                </div>
            </div>
            <p class="form-ayuda" style="margin-top: 8px;">BEDM <b>no guarda tu archivo</b>: solo el link, imágenes de baja resolución y una huella digital. El archivo sigue en tu nube.</p>

            <!-- 4. Licencia -->
            <section class="form-card">
                <label class="form-label" for="licenciaSelect">Licencia</label>
                <select id="licenciaSelect" class="form-input">
                    <?php foreach (TRABAJO_LICENCIAS as $k => $nombre): ?>
                    <option value="<?= $h($k) ?>"><?= $h($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-ayuda">Con «todos los derechos reservados» nadie más puede reutilizar tu trabajo. Con una licencia Creative Commons, las demás personas también verán el link a tu archivo original.</p>
            </section>

            <!-- 5. Coautores -->
            <section class="form-card">
                <span class="form-label">Coautores (trabajos grupales)</span>
                <p class="form-ayuda">Quienes tengan cuenta reciben una invitación y figuran cuando aceptan.</p>
                <div id="coChips" class="chips"></div>
                <input type="text" id="coInput" class="form-input" placeholder="Buscá por nombre o @nickname" autocomplete="off">
                <div id="coResultados" class="co-resultados"></div>
                <div class="co-externo">
                    <input type="text" id="coExterno" class="form-input" maxlength="80" placeholder="¿Sin cuenta? Escribí su nombre y apellido" autocomplete="off">
                    <button type="button" id="coExternoBtn" class="aviso-btn">Agregar</button>
                </div>
            </section>

            <!-- 6. Nota (opcional, privada por defecto) -->
            <section class="form-card">
                <label class="form-label" for="notaInput">Nota final (opcional)</label>
                <div class="nota-fila">
                    <input type="text" id="notaInput" class="form-input nota-input" inputmode="decimal" maxlength="5" placeholder="—" autocomplete="off">
                    <span class="grade-total" style="color: var(--blue-top);">/10</span>
                    <label class="chk"><input type="checkbox" id="notaPublica"> Mostrarla a las demás personas</label>
                </div>
                <p class="form-ayuda">Por defecto la nota solo la ves vos.</p>
            </section>

            <!-- 7. Declaración y botón -->
            <label class="chk chk-declaracion">
                <input type="checkbox" id="declaracion">
                <span>Declaro que soy autor/a de este trabajo, o que cuento con el permiso de mis coautores y de los titulares del material de terceros que usé.</span>
            </label>

            <p class="form-error" id="formError" role="alert"></p>
            <button type="submit" class="btn-add-job btn-publicar" id="btnPublicar"<?= $verificado ? '' : ' disabled' ?>>Publicar trabajo</button>
            <?php if (!$verificado): ?>
            <p class="form-ayuda" style="text-align: center;">Para publicar tenés que confirmar tu correo (mirá el aviso de arriba).</p>
            <?php endif; ?>
        </form>
    </main>

    <nav class="bottom-nav" id="bottomNav">
        <a href="index.php" class="nav-item" aria-label="Inicio"><i class="fas fa-home"></i></a>
        <a href="buscar.php" class="nav-item" aria-label="Buscar"><i class="fas fa-search"></i></a>
        <a href="perfil.php" class="nav-item" aria-label="Usuario"><i class="fas fa-user"></i></a>
    </nav>

    <script src="script.js"></script>
    <script src="script-trabajo.js"></script>
</body>
</html>
