<?php
// ══════════════════════════════════════════════════════════════
// INTERACCIÓN: «me gusta» y comentarios.
// Reglas: hace falta el correo verificado; no se puede dar «me gusta» al propio trabajo;
// los comentarios los puede borrar quien los escribió o quien publicó el trabajo.
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/trabajos.php';

const COMENTARIO_MAX = 1000;

// Establece (no alterna) si la persona le da «me gusta» al trabajo. Devuelve el total actualizado.
function trabajo_like_establecer(array $yo, array $t, string $rol, bool $meGusta): int {
    $uid = (int) $yo['id'];
    if (empty($yo['email_verificado'])) throw new TrabajoError('no_verificado', 'Confirmá tu correo electrónico para dar «me gusta» (mirá el aviso de arriba).');
    if ($t['estado'] !== 'publicado' || !trabajo_visible($t, $rol)) throw new TrabajoError('no_encontrado', 'No encontramos ese trabajo.');
    if ($rol === 'dueno' || $rol === 'autor' || $rol === 'pendiente') throw new TrabajoError('propio', 'No podés dar «me gusta» a un trabajo en el que figurás como autor/a.');
    if (eventos_recientes('like', 60, $uid) >= 200) throw new TrabajoError('limite', 'Diste muchos «me gusta» seguidos. Probá en un rato.');

    $pdo = db();
    $pdo->beginTransaction();
    if ($meGusta) {
        $pdo->prepare('INSERT IGNORE INTO votos (usuario_id, trabajo_id, valor) VALUES (?, ?, 1)')->execute([$uid, $t['id']]);
    } else {
        $pdo->prepare('DELETE FROM votos WHERE usuario_id = ? AND trabajo_id = ?')->execute([$uid, $t['id']]);
    }
    // El total se recalcula contando (no sumando 1): así nunca se desfasa aunque lleguen dos pedidos a la vez
    $pdo->prepare('UPDATE trabajos SET likes_count = (SELECT COUNT(*) FROM votos WHERE trabajo_id = ? AND valor = 1) WHERE id = ?')->execute([$t['id'], $t['id']]);
    $n = $pdo->prepare('SELECT likes_count FROM trabajos WHERE id = ?');
    $n->execute([$t['id']]);
    $total = (int) $n->fetchColumn();
    $pdo->commit();
    evento_registrar('like', $uid, 'trabajo', (int) $t['id'], $meGusta ? '+1' : '-1');
    return $total;
}

function trabajo_comentarios(int $trabajoId, int $limite = 100): array {
    $st = db()->prepare(
        'SELECT c.id, c.usuario_id, c.texto, c.creado_en, u.nombre, u.apellido, u.nickname
           FROM comentarios c JOIN usuarios u ON u.id = c.usuario_id
          WHERE c.trabajo_id = ? ORDER BY c.creado_en, c.id LIMIT ' . max(1, min(500, $limite))
    );
    $st->execute([$trabajoId]);
    return $st->fetchAll();
}

function trabajo_comentarios_total(int $trabajoId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM comentarios WHERE trabajo_id = ?');
    $st->execute([$trabajoId]);
    return (int) $st->fetchColumn();
}

function trabajo_comentar(array $yo, array $t, string $rol, $texto): int {
    $uid = (int) $yo['id'];
    if (empty($yo['email_verificado'])) throw new TrabajoError('no_verificado', 'Confirmá tu correo electrónico para comentar (mirá el aviso de arriba).');
    if (!trabajo_visible($t, $rol)) throw new TrabajoError('no_encontrado', 'No encontramos ese trabajo.');
    $texto = trabajo_limpiar_texto((string) $texto, true);
    if ($texto === '') throw new TrabajoError('texto', 'Escribí algo para comentar.');
    if (mb_strlen($texto) > COMENTARIO_MAX) throw new TrabajoError('texto', 'El comentario puede tener hasta ' . COMENTARIO_MAX . ' caracteres.');
    if (eventos_recientes('comentar', 1, $uid) >= 3 || eventos_recientes('comentar', 60, $uid) >= 30) {
        throw new TrabajoError('limite', 'Estás comentando muy rápido. Esperá un momento y probá de nuevo.');
    }
    $st = db()->prepare('INSERT INTO comentarios (trabajo_id, usuario_id, texto) VALUES (?, ?, ?)');
    $st->execute([$t['id'], $uid, $texto]);
    $id = (int) db()->lastInsertId();
    evento_registrar('comentar', $uid, 'trabajo', (int) $t['id']);
    return $id;
}

// Borra un comentario si lo escribió la persona o si es quien publicó el trabajo
function trabajo_comentario_borrar(array $yo, int $comentarioId): void {
    $uid = (int) $yo['id'];
    $st = db()->prepare('SELECT c.id, c.usuario_id, c.trabajo_id, t.usuario_id AS dueno
                           FROM comentarios c JOIN trabajos t ON t.id = c.trabajo_id AND t.eliminado_en IS NULL WHERE c.id = ?');
    $st->execute([$comentarioId]);
    $c = $st->fetch();
    if (!$c) throw new TrabajoError('no_encontrado', 'No encontramos ese comentario.');
    if ((int) $c['usuario_id'] !== $uid && (int) $c['dueno'] !== $uid) throw new TrabajoError('prohibido', 'No podés borrar ese comentario.');
    db()->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$comentarioId]);
    evento_registrar('comentario_borrado', $uid, 'trabajo', (int) $c['trabajo_id'], 'comentario ' . $comentarioId);
}
