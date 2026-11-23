<?php
require __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

function responder(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
if (!usuario_logueado())                   responder(401, ['ok' => false, 'error' => 'Sesión vencida. Volvé a ingresar.']);
csrf_exigir_api();

$uid = (int) $_SESSION['usuario_id'];
$data = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
$nick = (is_array($data) && is_string($data['nickname'] ?? null))
    ? ltrim(strtolower(trim($data['nickname'])), '@') : '';

if (!preg_match('/^[a-z0-9._]{3,20}$/', $nick)) {
    responder(422, ['ok' => false, 'error' => 'Usá de 3 a 20 caracteres: minúsculas, números, punto o guion bajo.']);
}
if (eventos_recientes('cambio_nickname', 24 * 60, $uid) >= 5) {
    responder(429, ['ok' => false, 'error' => 'Cambiaste tu nickname muchas veces hoy. Probá mañana.']);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT nickname FROM usuarios WHERE id = ? FOR UPDATE');
    $q->execute([$uid]);
    $actual = $q->fetchColumn();
    if ($actual === false) { $pdo->rollBack(); responder(401, ['ok' => false, 'error' => 'Sesión vencida. Volvé a ingresar.']); }
    if ($actual === $nick) { $pdo->rollBack(); responder(200, ['ok' => true, 'nickname' => $nick]); }

    // Un nickname que alguien usó antes queda reservado para esa persona (evita que otro lo tome y se haga pasar por ella)
    if (!nickname_disponible($nick, $uid)) { $pdo->rollBack(); responder(409, ['ok' => false, 'error' => 'Ese nickname no está disponible.']); }

    $pdo->prepare('UPDATE usuarios SET nickname = ? WHERE id = ?')->execute([$nick, $uid]);
    $pdo->prepare('INSERT IGNORE INTO nickname_historial (usuario_id, nickname) VALUES (?, ?)')->execute([$uid, $actual]);
    $pdo->commit();
    evento_registrar('cambio_nickname', $uid, 'usuario', $uid, "$actual → $nick");
    responder(200, ['ok' => true, 'nickname' => $nick]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($e->getCode() === '23000') responder(409, ['ok' => false, 'error' => 'Ese nickname no está disponible.']);
    error_log('BEDM nickname: ' . $e->getMessage());
    responder(500, ['ok' => false, 'error' => 'No se pudo guardar. Intentá de nuevo.']);
}
