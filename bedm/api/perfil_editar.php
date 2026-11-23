<?php
// Edita la presentación y el legajo de la persona. Solo cambia lo que se envía.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/cuentas.php';

$yo = api_post_con_sesion();
$d = api_cuerpo_json();
if (eventos_recientes('perfil_editar', 60, (int) $yo['id']) >= 30) api_json(429, ['ok' => false, 'error' => 'Hiciste muchos cambios seguidos. Probá en un rato.']);

try {
    $set = []; $par = [];
    if (array_key_exists('bio', $d))    { $set[] = 'bio = ?';    $par[] = perfil_validar_bio($d['bio']); }
    if (array_key_exists('legajo', $d)) { $set[] = 'legajo = ?'; $par[] = perfil_validar_legajo($d['legajo']); }
    if (!$set) api_json(200, ['ok' => true]);
    $par[] = (int) $yo['id'];
    try {
        db()->prepare('UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($par);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') throw new CuentaError('legajo', 'No pudimos guardar ese legajo. Revisá que sea el tuyo (puede estar en uso en otra cuenta).');
        throw $e;
    }
    evento_registrar('perfil_editar', (int) $yo['id'], 'usuario', (int) $yo['id'], implode(',', array_map(fn($s) => explode(' ', $s)[0], $set)));
    api_json(200, ['ok' => true]);
} catch (CuentaError $e) {
    api_json(422, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM perfil_editar: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos guardar los cambios. Probá de nuevo.']);
}
