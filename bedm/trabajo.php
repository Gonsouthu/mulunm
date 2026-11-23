<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/interaccion.php';
$yo = requerir_login();
$uid = (int) $yo['id'];
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$t = $id ? trabajo_cargar($id) : null;
$todos = $t ? trabajo_autores($id, true) : [];
$rol = $t ? trabajo_rol($t, $todos, $uid) : 'visitante';

if (!$t || !trabajo_visible($t, $rol)) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BEDM - Trabajo no encontrado</title><link rel="stylesheet" href="styles.css"></head>
    <body class="inner-page"><main class="main-content"><button class="back-btn" data-accion="volver">← Volver</button>
    <p class="empty-state" style="grid-column: auto;">No encontramos ese trabajo. Puede que se haya eliminado o que esté oculto.</p></main>
    <script src="script.js"></script></body></html><?php
    exit;
}

$aceptados = array_values(array_filter($todos, fn($a) => $a['estado'] === 'aceptado'));
$nombreCanonico = trabajo_nombre_canonico($t, $aceptados);
$pg = db()->prepare('SELECT numero FROM trabajo_paginas WHERE trabajo_id = ? ORDER BY numero');
$pg->execute([$id]);
$paginas = $pg->fetchAll(PDO::FETCH_COLUMN);

$esDueno = $rol === 'dueno';
$notaVisible = $t['nota_final'] !== null && ($esDueno || $t['nota_publica']);
$fecha = (new DateTime($t['creado_en']))->format('d/m/Y');
$oculto = $t['estado'] !== 'publicado';

// «Me gusta» y comentarios
$verificado = !empty($yo['email_verificado']);
$participa = in_array($rol, ['dueno', 'autor', 'pendiente'], true);
$mg = db()->prepare('SELECT 1 FROM votos WHERE usuario_id = ? AND trabajo_id = ? AND valor = 1');
$mg->execute([$uid, $id]);
$meGusta = (bool) $mg->fetchColumn();
$puedeLike = !$oculto && !$participa && $verificado;
$motivoLike = $oculto ? 'El trabajo está oculto.' : ($participa ? 'No podés dar «me gusta» a un trabajo en el que figurás como autor/a.' : (!$verificado ? 'Confirmá tu correo para dar «me gusta».' : ''));
$comentarios = trabajo_comentarios($id);
$totalComentarios = trabajo_comentarios_total($id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>BEDM - <?= $h($nombreCanonico) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <?= csrf_meta() ?>
</head>
<body class="inner-page" data-trabajo-id="<?= (int) $id ?>" data-yo-nick="<?= $h($yo['nickname']) ?>">
    <?= banner_verificacion($yo) ?>

    <main class="main-content" style="padding-bottom: 120px;">
        <button class="back-btn" data-accion="volver"><i class="fa-solid fa-arrow-left"></i> Volver</button>

        <?php if ($oculto): ?>
        <div class="aviso-oculto"><i class="fa-solid fa-eye-slash"></i> Este trabajo está <b>oculto</b>: solo lo ven quienes participan en él.</div>
        <?php endif; ?>

        <?php if ($rol === 'pendiente'): ?>
        <div class="aviso-invitacion" id="invitacion">
            <p><b><?= $h(nombre_apellido_nombre(array_values(array_filter($todos, fn($a) => (int) $a['usuario_id'] === (int) $t['usuario_id']))[0] ?? ['nombre' => '', 'apellido' => 'Quien publicó'])) ?></b> te agregó como coautor/a de este trabajo. Si aceptás, vas a figurar como autor/a y el trabajo va a aparecer en tu perfil.</p>
            <div class="aviso-botones">
                <button type="button" class="aviso-btn" data-coautoria="aceptar">Aceptar</button>
                <button type="button" class="aviso-btn aviso-btn-sec" data-coautoria="rechazar">Rechazar</button>
            </div>
        </div>
        <?php endif; ?>

        <h1 class="trabajo-nombre"><?= $h($nombreCanonico) ?></h1>

        <div class="trabajo-meta">
            <span class="badge"><?= $h(trabajo_materia_texto($t['materia'], $t['electiva_nombre'])) ?></span>
            <span class="badge">Ciclo <?= (int) $t['ciclo_lectivo'] ?></span>
            <span class="badge badge-claro"><?= $h(TRABAJO_LICENCIAS[$t['licencia']] ?? $t['licencia']) ?></span>
        </div>

        <section class="form-card">
            <span class="form-label"><?= count($aceptados) > 1 ? 'Autores' : 'Autor/a' ?></span>
            <ul class="autores">
                <?php foreach ($aceptados as $a): ?>
                <li>
                    <?php if ($a['usuario_id']): ?>
                        <a href="perfil.php?u=<?= rawurlencode($a['nickname']) ?>"><?= $h(nombre_apellido_nombre($a)) ?></a> <span class="nick">@<?= $h($a['nickname']) ?></span>
                    <?php else: ?>
                        <?= $h($a['nombre_externo']) ?> <span class="nick">(sin cuenta en BEDM, indicado/a por quien publicó)</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if ($t['descripcion']): ?>
        <section class="form-card"><p class="trabajo-descripcion"><?= nl2br($h($t['descripcion'])) ?></p></section>
        <?php endif; ?>

        <div class="post-evaluation-box">
            <div class="create-pdf-viewer" id="pdfViewer">
                <?php foreach ($paginas as $n): ?>
                <img src="api/trabajo_img.php?id=<?= (int) $id ?>&amp;n=<?= (int) $n ?>" alt="Página <?= (int) $n ?> de <?= $h($t['titulo']) ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="acciones-trabajo">
            <button type="button" class="like-btn<?= $meGusta ? ' activo' : '' ?>" id="btnLike" aria-pressed="<?= $meGusta ? 'true' : 'false' ?>"<?= $puedeLike ? '' : ' disabled' ?>>
                <span class="like-corazon" aria-hidden="true"><?= $meGusta ? '♥' : '♡' ?></span>
                <span id="likeCount"><?= (int) $t['likes_count'] ?></span>
                <span class="like-texto">Me gusta</span>
            </button>
            <?php if ($motivoLike): ?><p class="form-ayuda" style="margin: 6px 0 0;"><?= $h($motivoLike) ?></p><?php endif; ?>
        </div>

        <?php if ($notaVisible): ?>
        <p class="trabajo-dato"><b>Nota final:</b> <?= $h(rtrim(rtrim(number_format((float) $t['nota_final'], 2, ',', ''), '0'), ',')) ?>/10<?= (!$t['nota_publica'] && $esDueno) ? ' <span class="nick">(solo la ves vos)</span>' : '' ?></p>
        <?php endif; ?>
        

        <p class="trabajo-dato trabajo-huella">
            Publicado el <?= $h($fecha) ?> · Huella digital (SHA-256):
            <span title="<?= $h($t['sha256']) ?>"><?= $h(substr((string) $t['sha256'], 0, 16)) ?>…</span><br>
            <span class="nick">La huella permite comprobar que el archivo existía en esa fecha. El archivo original no está guardado en BEDM.</span>
        </p>

        <!-- ═════ Comentarios ═════ -->
        <section class="form-card" id="comentarios">
            <span class="form-label">Comentarios (<span id="comentariosTotal"><?= (int) $totalComentarios ?></span>)</span>
            <ul class="comentarios" id="comentariosLista">
                <?php foreach ($comentarios as $cm): ?>
                <li class="comentario" id="comentario-<?= (int) $cm['id'] ?>">
                    <div class="comentario-cab">
                        <a href="perfil.php?u=<?= rawurlencode($cm['nickname']) ?>"><?= $h(nombre_apellido_nombre($cm)) ?></a>
                        <span class="nick">· <?= $h((new DateTime($cm['creado_en']))->format('d/m/Y H:i')) ?></span>
                        <?php if ((int) $cm['usuario_id'] === $uid || $esDueno): ?>
                        <button type="button" class="comentario-borrar" data-borrar-comentario="<?= (int) $cm['id'] ?>">Borrar</button>
                        <?php endif; ?>
                    </div>
                    <p class="comentario-texto"><?= nl2br($h($cm['texto'])) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($totalComentarios > count($comentarios)): ?>
            <p class="form-ayuda">Se muestran los primeros <?= count($comentarios) ?> comentarios.</p>
            <?php endif; ?>
            <?php if ($verificado): ?>
            <textarea id="comentarioInput" class="form-input" rows="3" maxlength="1000" placeholder="Escribí un comentario…"></textarea>
            <p class="form-error" id="comentarioError" role="alert"></p>
            <button type="button" class="aviso-btn" id="btnComentar" style="margin-top: 8px;">Comentar</button>
            <?php else: ?>
            <p class="form-ayuda">Confirmá tu correo para poder comentar.</p>
            <?php endif; ?>
        </section>

        <?php if ($esDueno): ?>
        <!-- ═════ Panel de quien publicó ═════ -->
        <section class="form-card panel-dueno" id="panelDueno">
            <h3 class="form-label" style="font-size:1rem;">Administrar este trabajo</h3>

            <label class="form-label" for="editTitulo">Nombre del trabajo</label>
            <input type="text" id="editTitulo" class="form-input" maxlength="100" value="<?= $h($t['titulo']) ?>">
            <label class="form-label" for="editDescripcion">Descripción</label>
            <textarea id="editDescripcion" class="form-input" rows="4" maxlength="2000"><?= $h($t['descripcion'] ?? '') ?></textarea>
            <label class="form-label" for="editLicencia">Licencia</label>
            <select id="editLicencia" class="form-input">
                <?php foreach (TRABAJO_LICENCIAS as $k => $nombre): ?>
                <option value="<?= $h($k) ?>"<?= $t['licencia'] === $k ? ' selected' : '' ?>><?= $h($nombre) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label" for="editNota">Nota final</label>
            <div class="nota-fila">
                <input type="text" id="editNota" class="form-input nota-input" inputmode="decimal" maxlength="5" value="<?= $t['nota_final'] !== null ? $h(rtrim(rtrim(number_format((float) $t['nota_final'], 2, ',', ''), '0'), ',')) : '' ?>" placeholder="—">
                <span class="grade-total" style="color: var(--blue-top);">/10</span>
                <label class="chk"><input type="checkbox" id="editNotaPublica"<?= $t['nota_publica'] ? ' checked' : '' ?>> Mostrarla</label>
            </div>
            <p class="form-error" id="panelError" role="alert"></p>
            <button type="button" class="btn-add-job btn-publicar" id="btnGuardarEdicion">Guardar cambios</button>

            <hr class="sep">
            <span class="form-label">Coautores</span>
            <ul class="autores autores-admin">
                <?php foreach ($todos as $a): if ((int) $a['usuario_id'] === $uid) continue; ?>
                <li>
                    <span><?= $h(autor_texto($a)) ?> <span class="nick">· <?= $a['usuario_id'] ? ($a['estado'] === 'aceptado' ? 'aceptó' : ($a['estado'] === 'pendiente' ? 'invitación pendiente' : 'rechazó')) : 'sin cuenta' ?></span></span>
                    <button type="button" class="aviso-btn aviso-btn-sec" data-quitar="<?= (int) $a['autor_id'] ?>">Quitar</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <input type="text" id="coInput" class="form-input" placeholder="Invitar: buscá por nombre o @nickname" autocomplete="off">
            <div id="coResultados" class="co-resultados"></div>
            <div class="co-externo">
                <input type="text" id="coExterno" class="form-input" maxlength="80" placeholder="¿Sin cuenta? Nombre y apellido" autocomplete="off">
                <button type="button" id="coExternoBtn" class="aviso-btn">Agregar</button>
            </div>

            <hr class="sep">
            <div class="acciones-peligro">
                <button type="button" class="aviso-btn aviso-btn-sec" id="btnOcultar" data-estado="<?= $oculto ? 'publicado' : 'oculto' ?>"><?= $oculto ? 'Volver a mostrar' : 'Ocultar' ?></button>
                <button type="button" class="aviso-btn aviso-btn-peligro" id="btnEliminar">Eliminar trabajo</button>
            </div>
            <p class="form-ayuda">Ocultar es reversible. Eliminar borra las imágenes y no se puede deshacer (podés volver a publicar el mismo archivo).</p>
        </section>
        <?php elseif ($rol === 'autor'): ?>
        <p class="trabajo-dato"><button type="button" class="aviso-btn aviso-btn-sec" data-coautoria="rechazar">Dejar de figurar como autor/a</button></p>
        <?php endif; ?>
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
