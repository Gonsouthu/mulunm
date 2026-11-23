<?php
// Edita los datos de un trabajo propio. Solo cambia lo que se envía.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/trabajos.php';

$yo = api_post_con_sesion();
$d = api_cuerpo_json();
$t = trabajo_cargar((int) ($d['id'] ?? 0));
if (!$t || (int) $t['usuario_id'] !== (int) $yo['id']) api_json(404, ['ok' => false, 'error' => 'No encontramos ese trabajo.']);

try {
    $set = []; $par = [];
    if (array_key_exists('titulo', $d))       { $set[] = 'titulo = ?';       $par[] = trabajo_validar_titulo($d['titulo']); }
    if (array_key_exists('descripcion', $d))  { $set[] = 'descripcion = ?';  $par[] = trabajo_validar_descripcion($d['descripcion']); }
    if (array_key_exists('licencia', $d))     { $set[] = 'licencia = ?';     $par[] = trabajo_validar_licencia($d['licencia']); }
    if (array_key_exists('nota', $d))         { $set[] = 'nota_final = ?';   $par[] = trabajo_validar_nota($d['nota']); }
    if (array_key_exists('nota_publica', $d)) { $set[] = 'nota_publica = ?'; $par[] = empty($d['nota_publica']) ? 0 : 1; }
    if (array_key_exists('estado', $d)) {
        if (!in_array($d['estado'], ['publicado', 'oculto'], true)) throw new TrabajoError('estado', 'Estado no válido.');
        $set[] = 'estado = ?'; $par[] = $d['estado'];
    }
    if (!$set) api_json(200, ['ok' => true]);
    $par[] = (int) $t['id'];
    db()->prepare('UPDATE trabajos SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($par);
    evento_registrar('editar_trabajo', (int) $yo['id'], 'trabajo', (int) $t['id'], implode(',', array_map(fn($s) => explode(' ', $s)[0], $set)));
    api_json(200, ['ok' => true]);
} catch (TrabajoError $e) {
    api_json(422, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
}
