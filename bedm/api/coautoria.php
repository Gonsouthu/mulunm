<?php
// Coautoría: aceptar o rechazar una invitación, salir de un trabajo, y (quien publicó) invitar o quitar coautores.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';

$yo = api_post_con_sesion();
$uid = (int) $yo['id'];
$d = api_cuerpo_json();
$t = trabajo_cargar((int) ($d['trabajo_id'] ?? 0));
$accion = (string) ($d['accion'] ?? '');
if (!$t) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);
$autores = trabajo_autores((int) $t['id'], true);
$rol = trabajo_rol($t, $autores, $uid);
if (!trabajo_visible($t, $rol)) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);
$pdo = db();

try {
    if ($accion === 'aceptar' || $accion === 'rechazar') {
        if ($rol !== 'pendiente' && !($accion === 'rechazar' && $rol === 'autor')) api_json(403, ['ok' => false, 'error' => 'No tenés una invitación pendiente en este trabajo.']);
        $pdo->prepare('UPDATE trabajo_autores SET estado = ? WHERE trabajo_id = ? AND usuario_id = ?')
            ->execute([$accion === 'aceptar' ? 'aceptado' : 'rechazado', $t['id'], $uid]);
        evento_registrar('coautoria_' . $accion, $uid, 'trabajo', (int) $t['id']);
        api_json(200, ['ok' => true]);
    }

    if ($rol !== 'dueno') api_json(403, ['ok' => false, 'error' => 'Solo quien publicó el trabajo puede hacer eso.']);

    if ($accion === 'agregar') {
        if (eventos_recientes('coautoria_invitar', 1440, $uid) >= 20) api_json(429, ['ok' => false, 'error' => 'Enviaste muchas invitaciones hoy. Probá mañana.']);
        $nuevos = trabajo_resolver_coautores([$d['coautor'] ?? null], $uid, count($autores));
        $ins = $pdo->prepare('INSERT INTO trabajo_autores (trabajo_id, usuario_id, nombre_externo, orden, estado) VALUES (?, ?, ?, ?, ?)');
        $orden = count($autores) + 1;
        foreach ($nuevos as $c) {
            $existe = null;
            foreach ($autores as $a) if ($c['usuario_id'] && (int) $a['usuario_id'] === $c['usuario_id']) $existe = $a;
            if ($existe && $existe['estado'] === 'rechazado') {   // volver a invitar a quien había rechazado
                $pdo->prepare("UPDATE trabajo_autores SET estado = 'pendiente' WHERE id = ?")->execute([$existe['autor_id']]);
            } elseif (!$existe) {
                $ins->execute([$t['id'], $c['usuario_id'], $c['nombre_externo'], $orden++, $c['estado']]);
            }
        }
        evento_registrar('coautoria_invitar', $uid, 'trabajo', (int) $t['id']);
        api_json(200, ['ok' => true]);
    }

    if ($accion === 'quitar') {
        $autorId = (int) ($d['autor_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM trabajo_autores WHERE id = ? AND trabajo_id = ? AND (usuario_id IS NULL OR usuario_id <> ?)');
        $del->execute([$autorId, $t['id'], $uid]);   // quien publicó no puede quitarse a sí mismo
        evento_registrar('coautoria_quitar', $uid, 'trabajo', (int) $t['id']);
        api_json(200, ['ok' => true]);
    }

    api_json(400, ['ok' => false, 'error' => 'Acción no válida.']);
} catch (TrabajoError $e) {
    api_json(422, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM coautoria: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos completar la acción. Probá de nuevo.']);
}
