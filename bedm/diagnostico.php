<?php
// Página de diagnóstico: revisa si el servidor está listo para generar previsualizaciones.
// Solo se puede abrir desde la misma computadora donde corre el sitio (localhost).
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Esta página solo se puede abrir desde el mismo equipo donde está instalado el sitio.');
}
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/ingesta.php';
require __DIR__ . '/includes/mailer.php';

$filas = [];
function fila(string $titulo, bool $ok, string $detalle, string $ayuda = ''): void {
    global $filas;
    $filas[] = compact('titulo', 'ok', 'detalle', 'ayuda');
}

// 1. PHP y extensiones
fila('Versión de PHP', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION, 'Hace falta PHP 8.0 o superior (XAMPP reciente lo trae).');
foreach (['curl' => 'descargar los archivos de la nube', 'mbstring' => 'validar nombres', 'openssl' => 'conexiones seguras (https)', 'pdo_mysql' => 'conectarse a la base de datos'] as $ext => $para) {
    fila("Extensión $ext", extension_loaded($ext), extension_loaded($ext) ? 'activada' : 'NO activada',
        "Sirve para {$ext}: {$para}. En XAMPP: abrí php.ini (XAMPP Control Panel → Apache → Config → PHP (php.ini)), buscá «extension={$ext}», borrá el «;» del principio, guardá y reiniciá Apache.");
}
fila('Extensión gd (sello de autoría y miniaturas)', extension_loaded('gd') && function_exists('imagecreatefromjpeg'), extension_loaded('gd') ? 'activada' : 'NO activada',
    'Sin esta extensión los trabajos se publican igual, pero sin el sello de autoría al pie de las imágenes ni miniaturas. En XAMPP: php.ini → buscá «extension=gd», borrá el «;», guardá y reiniciá Apache.');
fila('Extensión zip (descargar historial y copias de seguridad)', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'activada' : 'NO activada',
    'Sin esta extensión no se puede descargar el historial ni la copia de seguridad. En XAMPP: php.ini → buscá «extension=zip», borrá el «;», guardá y reiniciá Apache.');
fila('Extensión exif (orientación de las fotos de celular)', true, function_exists('exif_read_data') ? 'activada' : 'no activada (opcional: las fotos sacadas de costado podrían verse giradas)', '');
fila('Ejecución de programas externos (proc_open)', function_exists('proc_open'), function_exists('proc_open') ? 'permitida' : 'bloqueada', 'Está bloqueada en php.ini (disable_functions). Hace falta para usar poppler.');

// 2. Configuración
global $cfg;
$dir = (string) ingesta_cfg('poppler_dir', '');
fila('Configuración de poppler_dir', $dir !== '' || PHP_OS_FAMILY !== 'Windows', $dir !== '' ? $dir : '(no configurado)',
    "En config/config.local.php agregá una línea como:  'poppler_dir' => 'C:/poppler/poppler-24.08.0/Library/bin',");

// 3. poppler
foreach (['pdftoppm', 'pdfinfo'] as $bin) {
    try {
        $r = ingesta_ejecutar([ingesta_bin($bin), '-v'], 10);
        $texto = trim(strtok($r['out'] . "\n" . $r['err'], "\n"));
        fila("poppler: $bin", $texto !== '' && stripos($texto, 'version') !== false, $texto ?: '(sin respuesta)', 'Revisá que poppler_dir apunte a la carpeta que contiene ' . $bin . '.exe');
    } catch (IngestaError $e) {
        fila("poppler: $bin", false, 'no se encontró', 'Instalá poppler y configurá poppler_dir (ver las instrucciones del paso a paso).');
    }
}

// 4. Carpeta de trabajo
$base = ingesta_dir_base();
$prueba = $base . DIRECTORY_SEPARATOR . 'prueba.tmp';
$escribible = @file_put_contents($prueba, 'ok') !== false;
@unlink($prueba);
fila('Carpeta temporal de previsualizaciones', $escribible, $base, 'El servidor no puede escribir en esa carpeta. Revisá los permisos de la carpeta del proyecto.');

// 5. Base de datos
if (empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) {
    fila('Base de datos', false, 'config/config.local.php no existe o está incompleto', 'Copiá config.example.php como config.local.php y completalo.');
} else {
    try {
        $pdo = db();
        fila('Base de datos', true, 'conexión correcta');
        $tablas = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
        $faltan = array_values(array_diff(['auditoria', 'trabajo_autores', 'materias'], array_map('strtolower', $tablas)));
        $col = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'sesion_version'")->fetchColumn();
        fila('Migración 003 aplicada (seguridad de cuentas)', (int) $col === 1, (int) $col === 1 ? 'columna sesion_version presente' : 'falta la columna sesion_version', 'Ejecutá database/migraciones/003_seguridad.sql desde phpMyAdmin (pestaña Importar).');
        $uq = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'trabajos' AND index_name = 'uq_trabajo_sha256'")->fetchColumn();
        fila('Migración 004 aplicada (publicación de trabajos)', (int) $uq > 0, (int) $uq > 0 ? 'huella única presente' : 'falta el índice uq_trabajo_sha256', 'Ejecutá database/migraciones/004_publicacion.sql desde phpMyAdmin (pestaña Importar).');
        fila('Migración 002 aplicada', !$faltan, $faltan ? 'faltan tablas: ' . implode(', ', $faltan) : 'tablas nuevas presentes', 'Ejecutá database/migraciones/002_base_v2.sql desde phpMyAdmin (pestaña Importar).');
    } catch (Throwable $e) {
        fila('Base de datos', false, 'no se pudo conectar', 'Revisá usuario y clave en config/config.local.php y que MySQL esté encendido en XAMPP.');
    }
}

// 5b. Seguridad y emails
fila('Conexión del sitio', true, es_https() ? 'HTTPS (cookie de sesión con marca "secure")' : 'HTTP (normal en localhost; en un servidor real usá HTTPS)');
fila('Política de seguridad de contenido (CSP)', cfg_valor('csp', 'bloquear') !== 'no', 'modo: ' . cfg_valor('csp', 'bloquear'), "Está desactivada ('csp' => 'no'). Dejala activa salvo que algo no cargue.");
$smtp = (string) cfg_valor('smtp_host', '');
fila('Envío de emails', true, $smtp !== '' ? "SMTP real: $smtp" : 'modo PRUEBA: los emails se guardan como archivos, no se envían a nadie');
$base = url_base();
fila("Dirección del sitio para los enlaces de los emails ('url_base')", $base !== null, $base ?? '(no configurada)', "Agregá en config/config.local.php:  'url_base' => 'https://tu-sitio.edu.ar/BEDM',");
if ($smtp === '') {
    $dirMails = rtrim((string) cfg_valor('storage_dir', __DIR__ . '/storage'), '/\\') . DIRECTORY_SEPARATOR . 'mails';
    $archivos = is_dir($dirMails) ? (glob($dirMails . DIRECTORY_SEPARATOR . '*.txt') ?: []) : [];
    rsort($archivos);
    $textoMails = [];
    foreach (array_slice($archivos, 0, 5) as $f) {
        $c = (string) file_get_contents($f);
        preg_match('/^Para: (.+)$/m', $c, $mp); preg_match('/^Asunto: (.+)$/m', $c, $ma); preg_match('#https?://\S+#', $c, $ml);
        $textoMails[] = [trim($mp[1] ?? ''), trim($ma[1] ?? ''), $ml[0] ?? ''];
    }
    $GLOBALS['mails_prueba'] = $textoMails;
}

// 6. Salida a internet por HTTPS (certificados)
if (extension_loaded('curl')) {
    $ch = curl_init('https://drive.google.com/');
    $o = [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true];
    if ($ca = ingesta_cfg('ca_bundle')) $o[CURLOPT_CAINFO] = $ca;
    curl_setopt_array($ch, $o);
    curl_exec($ch);
    $errno = curl_errno($ch); $err = curl_error($ch); $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $ayuda = in_array($errno, [35, 51, 58, 60, 77], true)
        ? "Falta el archivo de certificados. Descargá «cacert.pem» desde https://curl.se/docs/caextract.html, guardalo (por ejemplo en C:/xampp/php/extras/ssl/cacert.pem) y agregá en config/config.local.php:  'ca_bundle' => 'C:/xampp/php/extras/ssl/cacert.pem',"
        : 'Revisá la conexión a internet de esta computadora.';
    fila('Conexión segura a Google Drive', $errno === 0, $errno === 0 ? "correcta (respuesta $http)" : "error $errno: $err", $ayuda);
}

$todoOk = !in_array(false, array_column($filas, 'ok'), true);
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>BEDM · Diagnóstico</title>
<style>
 body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;color:#1c2733}
 h1{font-size:1.4rem} .res{padding:12px 14px;border-radius:10px;font-weight:700;margin:12px 0}
 .res.ok{background:#e3f6e8;color:#14532d} .res.mal{background:#fde8e8;color:#7f1d1d}
 .f{border:1px solid #d9e0e8;border-radius:10px;padding:10px 14px;margin:8px 0}
 .f b{display:block} .ok-i{color:#15803d} .mal-i{color:#b91c1c} small{color:#475569;display:block;margin-top:4px}
 code{background:#eef2f6;padding:1px 5px;border-radius:4px}
</style></head><body>
<h1>BEDM · Diagnóstico del servidor</h1>
<div class="res <?= $todoOk ? 'ok' : 'mal' ?>"><?= $todoOk ? '✔ Todo listo: ya se pueden generar previsualizaciones.' : '✘ Hay cosas por arreglar (marcadas abajo).' ?></div>
<?php foreach ($filas as $f): ?>
 <div class="f"><b><span class="<?= $f['ok'] ? 'ok-i' : 'mal-i' ?>"><?= $f['ok'] ? '✔' : '✘' ?></span> <?= htmlspecialchars($f['titulo']) ?></b>
  <?= htmlspecialchars($f['detalle']) ?>
  <?php if (!$f['ok'] && $f['ayuda']): ?><small><?= htmlspecialchars($f['ayuda']) ?></small><?php endif; ?></div>
<?php endforeach; ?>
<?php if (!empty($GLOBALS['mails_prueba'])): ?>
 <div class="f"><b>Últimos emails de prueba (no se enviaron: hacé clic en el enlace para simular que abrís el email)</b>
 <?php foreach ($GLOBALS['mails_prueba'] as [$para, $asunto, $enlace]): ?>
  <small><?= htmlspecialchars($para) ?> · <?= htmlspecialchars($asunto) ?>
   <?php if ($enlace && preg_match('#^https?://(localhost|127\.0\.0\.1)#', $enlace)): ?> · <a href="<?= htmlspecialchars($enlace) ?>">abrir enlace</a><?php endif; ?></small>
 <?php endforeach; ?></div>
<?php endif; ?>
<p><small>Esta página solo se abre desde el mismo equipo donde corre el sitio. Podés borrar <code>diagnostico.php</code> cuando ya no la necesites.</small></p>
</body></html>
