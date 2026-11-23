<?php
// Foto de perfil / portada. Subir: formulario con «tipo» (avatar|banner) y «archivo». Quitar: JSON {accion:'quitar', tipo}.
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/cuentas.php';

$yo = api_post_con_sesion();
$uid = (int) $yo['id'];
if (eventos_recientes('perfil_imagen', 60, $uid) >= 20) api_json(429, ['ok' => false, 'error' => 'Cambiaste tus imágenes muchas veces. Probá de nuevo en un rato.']);

try {
    if (isset($_FILES['archivo'])) {
        $tipo = (string) ($_POST['tipo'] ?? '');
        session_write_close();
        $url = perfil_imagen_guardar($uid, $tipo, $_FILES['archivo']);
        evento_registrar('perfil_imagen', $uid, 'usuario', $uid, 'subir ' . $tipo);
        api_json(200, ['ok' => true, 'url' => $url]);
    }
    $d = api_cuerpo_json();
    if (($d['accion'] ?? '') === 'quitar') {
        perfil_imagen_quitar($uid, (string) ($d['tipo'] ?? ''));
        evento_registrar('perfil_imagen', $uid, 'usuario', $uid, 'quitar ' . ($d['tipo'] ?? ''));
        api_json(200, ['ok' => true]);
    }
    api_json(400, ['ok' => false, 'error' => 'Pedido no válido.']);
} catch (CuentaError $e) {
    api_json($e->codigo === 'sin_gd' ? 503 : 422, ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('BEDM perfil_imagen: ' . $e);
    api_json(500, ['ok' => false, 'error' => 'No pudimos guardar la imagen. Probá de nuevo.']);
}
