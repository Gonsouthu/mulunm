<?php
// Página de mantenimiento para quien administra el sitio: copia de seguridad de las imágenes y purga de bajas.
// Solo se abre desde la misma computadora donde corre el sitio (localhost).
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cuentas.php';
require __DIR__ . '/includes/plantilla_auth.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Esta página solo se puede abrir desde el mismo equipo donde está instalado el sitio.'); }

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $mensaje = 'La página venció. Recargala e intentá de nuevo.';
    } elseif (($_POST['accion'] ?? '') === 'purgar') {
        $n = cuenta_purgar_vencidas();
        $mensaje = $n === 0 ? 'No había cuentas con el plazo vencido.' : "Listo: se anonimizaron $n cuenta(s) con el plazo vencido.";
    } elseif (($_POST['accion'] ?? '') === 'copia') {
        historial_limpiar_tmp();
        set_time_limit(300);
        try {
            [$ruta, $nombre] = copia_imagenes_zip();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . filesize($ruta));
            header('Cache-Control: no-store');
            readfile($ruta); @unlink($ruta); exit;
        } catch (CuentaError $e) { $mensaje = $e->getMessage(); }
    }
}
$res = almacenamiento_resumen();
$bajas = cuentas_bajas_pendientes();
$mb = fn($b) => number_format($b / 1048576, 1, ',', '.') . ' MB';
$trabajos = (int) db()->query("SELECT COUNT(*) FROM trabajos WHERE eliminado_en IS NULL")->fetchColumn();
$usuarios = (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>BEDM · Mantenimiento</title>
<style>
 body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;color:#1c2733;line-height:1.5}
 h1{font-size:1.4rem}h2{font-size:1.05rem;color:#004E9F;margin-top:28px}.f{border:1px solid #d9e0e8;border-radius:10px;padding:12px 14px;margin:10px 0}
 button{background:#004E9F;color:#fff;border:none;border-radius:8px;padding:9px 14px;font-weight:700;cursor:pointer}.msg{background:#e3f6e8;color:#14532d;padding:10px 12px;border-radius:8px;font-weight:700}
 small{color:#475569}table{border-collapse:collapse;width:100%;font-size:.85rem}td,th{border-bottom:1px solid #e5e7eb;padding:6px 8px;text-align:left}
</style></head><body>
<h1>BEDM · Mantenimiento</h1>
<?php if ($mensaje): ?><p class="msg"><?= h($mensaje) ?></p><?php endif; ?>

<h2>Estado</h2>
<div class="f">
 <?= $usuarios ?> cuenta(s) activa(s) · <?= $trabajos ?> trabajo(s) publicados u ocultos<br>
 Imágenes de trabajos: <?= (int) $res['trabajos_archivos'] ?> archivo(s), <?= h($mb($res['trabajos_bytes'])) ?><br>
 Fotos de perfil y portadas: <?= (int) $res['perfiles_archivos'] ?> archivo(s), <?= h($mb($res['perfiles_bytes'])) ?>
</div>

<h2>Copia de seguridad</h2>
<div class="f">
 <p><b>Las imágenes son lo único que BEDM conserva de cada trabajo</b> (los archivos originales no se guardan). Si se pierden, no hay forma de regenerarlas si el alumno ya borró su archivo de la nube.</p>
 <form method="post"><?= csrf_campo() ?><button name="accion" value="copia">Descargar copia de todas las imágenes (ZIP)</button></form>
 <p><small>La base de datos no está en ese ZIP: exportala aparte desde phpMyAdmin (bedm → Exportar). Guardá las dos copias juntas, con la misma fecha.</small></p>
</div>

<h2>Bajas de cuenta</h2>
<div class="f">
 <?php if (!$bajas): ?><p>No hay cuentas dadas de baja esperando el plazo.</p><?php else: ?>
 <table><tr><th>Cuenta</th><th>Baja</th><th>Días</th><th>Estado</th></tr>
 <?php foreach ($bajas as $b): ?><tr><td>@<?= h($b['nickname']) ?></td><td><?= h(substr($b['baja_en'], 0, 10)) ?></td><td><?= (int) $b['dias'] ?></td><td><?= $b['dias'] >= CUENTA_DIAS_REACTIVAR ? '<b>plazo vencido</b>' : 'puede reactivar (' . (CUENTA_DIAS_REACTIVAR - (int) $b['dias']) . ' días)' ?></td></tr><?php endforeach; ?></table>
 <?php endif; ?>
 <p>Pasados <?= CUENTA_DIAS_REACTIVAR ?> días de la baja se anonimizan los datos personales (nombre, correo, legajo, fotos) y se eliminan los trabajos que hubieran quedado ocultos.</p>
 <form method="post" onsubmit="return confirm('¿Anonimizar ahora las cuentas con el plazo vencido? No se puede deshacer.')"><?= csrf_campo() ?><button name="accion" value="purgar">Anonimizar las bajas con plazo vencido</button></form>
</div>
<p><small>Esta página solo se abre desde esta computadora. Podés borrar <code>mantenimiento.php</code> si no la necesitás.</small></p>
</body></html>
