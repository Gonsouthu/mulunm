<?php
// Pruebas del módulo de cuentas que no necesitan base de datos. Uso:  php tests/cuentas_test.php
$cfg = ['storage_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedm_cuentas_test_' . getmypid()];
require __DIR__ . '/../includes/cuentas.php';

$ok = 0; $mal = 0;
function chequear(string $n, bool $c): void { global $ok, $mal; if ($c) { $ok++; echo "  [ok]   $n\n"; } else { $mal++; echo "  [FALLA] $n\n"; } }
function lanza(callable $f): string { try { $f(); return 'SIN_ERROR'; } catch (CuentaError $e) { return $e->codigo; } }

echo "\n== Nombres de archivo\n";
chequear('quita tildes y símbolos', slug_archivo('Diseño Multimedial: ¡TP1!') === 'Diseno-Multimedial-TP1');
chequear('mayúsculas con tilde', slug_archivo('ÁÉÍÓÚ Ñandú') === 'AEIOU-Nandu');
chequear('vacío → "trabajo"', slug_archivo('***') === 'trabajo');
chequear('se acota al largo pedido', strlen(slug_archivo(str_repeat('a', 100), 30)) === 30);
chequear('no deja ../ ni barras', !preg_match('#[./\\\\]#', slug_archivo('../../etc/passwd')));

echo "\n== Presentación y legajo\n";
chequear('presentación vacía → null', perfil_validar_bio('  ') === null);
chequear('presentación normaliza espacios', perfil_validar_bio("  hola   mundo\n") === 'hola mundo');
chequear('presentación de 280 → válida', mb_strlen((string) perfil_validar_bio(str_repeat('ñ', 280))) === 280);
chequear('presentación de 281 → error', lanza(fn() => perfil_validar_bio(str_repeat('a', 281))) === 'bio');
chequear('legajo vacío → null', perfil_validar_legajo('') === null);
chequear('legajo válido', perfil_validar_legajo('UNM-1234') === 'UNM-1234');
foreach (['ab', str_repeat('1', 13), 'con espacio', 'ñandú1', '<b>1</b>', "12'; DROP"] as $l) chequear('legajo inválido: ' . json_encode($l), lanza(fn() => perfil_validar_legajo($l)) === 'legajo');

echo "\n== Procesamiento de imágenes\n";
if (!trabajo_gd_disponible()) { echo "  (se omiten: falta la extensión gd)\n"; }
else {
    $d = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedm_imgs_' . getmypid(); mkdir($d);
    $jpg = function (int $w, int $h, array $rgb = [30, 120, 200]) use ($d) { $im = imagecreatetruecolor($w, $h); imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb)); $f = "$d/o_" . uniqid() . '.jpg'; imagejpeg($im, $f, 90); return $f; };
    $f = $jpg(1000, 600);
    perfil_imagen_procesar($f, 'avatar', "$d/a.jpg"); [$w, $h, $t] = getimagesize("$d/a.jpg");
    chequear('foto de perfil: 400×400 y JPEG', $w === 400 && $h === 400 && $t === IMAGETYPE_JPEG);
    perfil_imagen_procesar($f, 'banner', "$d/b.jpg"); [$w, $h] = getimagesize("$d/b.jpg");
    chequear('portada: 1200×400', $w === 1200 && $h === 400);
    chequear('no quedan archivos provisorios', glob("$d/*.tmp") === []);
    // PNG con transparencia → fondo blanco
    $im = imagecreatetruecolor(300, 300); imagealphablending($im, false); imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 127)); imagesavealpha($im, true); imagepng($im, "$d/t.png");
    perfil_imagen_procesar("$d/t.png", 'avatar', "$d/t.jpg"); $c = imagecolorat(imagecreatefromjpeg("$d/t.jpg"), 200, 200);
    chequear('PNG transparente: la transparencia queda en blanco', (($c >> 16) & 255) > 240 && (($c >> 8) & 255) > 240 && ($c & 255) > 240);
    // formatos y tamaños rechazados
    file_put_contents("$d/x.txt", 'no soy una imagen');
    chequear('un archivo de texto → rechazado', lanza(fn() => perfil_imagen_procesar("$d/x.txt", 'avatar', "$d/n.jpg")) === 'formato');
    file_put_contents("$d/php.jpg", '<?php system($_GET["c"]); ?>' . str_repeat('A', 500));
    chequear('código PHP disfrazado de .jpg → rechazado', lanza(fn() => perfil_imagen_procesar("$d/php.jpg", 'avatar', "$d/n.jpg")) === 'formato');
    $gif = imagecreatetruecolor(300, 300); imagegif($gif, "$d/a.gif");
    chequear('GIF → rechazado', lanza(fn() => perfil_imagen_procesar("$d/a.gif", 'avatar', "$d/n.jpg")) === 'formato');
    file_put_contents("$d/svg.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    chequear('SVG (puede llevar scripts) → rechazado', lanza(fn() => perfil_imagen_procesar("$d/svg.svg", 'avatar', "$d/n.jpg")) === 'formato');
    chequear('imagen muy chica → rechazada', lanza(fn() => perfil_imagen_procesar($jpg(50, 50), 'avatar', "$d/n.jpg")) === 'pequena');
    chequear('portada demasiado baja → rechazada', lanza(fn() => perfil_imagen_procesar($jpg(1000, 60), 'banner', "$d/n.jpg")) === 'pequena');
    $big = imagecreatetruecolor(5000, 4000); imagepng($big, "$d/big.png", 9);
    chequear('más de 16 megapíxeles → rechazada', lanza(fn() => perfil_imagen_procesar("$d/big.png", 'avatar', "$d/n.jpg")) === 'dimensiones');
    copy($f, "$d/pesada.jpg"); file_put_contents("$d/pesada.jpg", str_repeat("\0", 4 * 1048576), FILE_APPEND);
    chequear('archivo de más de 3 MB (foto de perfil) → rechazado', lanza(fn() => perfil_imagen_procesar("$d/pesada.jpg", 'avatar', "$d/n.jpg")) === 'tamano');
    file_put_contents("$d/roto.jpg", "\xff\xd8\xff\xe0" . random_bytes(3000));
    chequear('JPEG dañado → rechazado sin romper', in_array(lanza(fn() => perfil_imagen_procesar("$d/roto.jpg", 'avatar', "$d/n.jpg")), ['formato', 'imagen_danada'], true));
    chequear('tipo desconocido → error', lanza(fn() => perfil_imagen_procesar($f, 'fondo', "$d/n.jpg")) === 'tipo');
    chequear('ningún rechazo dejó un archivo de salida', !file_exists("$d/n.jpg"));
    // Orientación EXIF y privacidad (el archivo trae orientación «girar 90°» y coordenadas GPS)
    if (!function_exists('exif_read_data')) { echo "  (se omite la prueba de EXIF: falta la extensión exif)\n"; }
    else {
        $origen = __DIR__ . '/fixtures/exif_giro6_gps.jpg';
        $e0 = @exif_read_data($origen);
        chequear('la foto de prueba trae GPS y orientación 6 (punto de partida)', isset($e0['GPSLatitude']) && ($e0['Orientation'] ?? 0) === 6);
        perfil_imagen_procesar($origen, 'avatar', "$d/exif.jpg");
        $e1 = @exif_read_data("$d/exif.jpg");
        chequear('el resultado NO conserva las coordenadas GPS ni la marca de la cámara', !isset($e1['GPSLatitude']) && !isset($e1['Make']));
        $im = imagecreatefromjpeg("$d/exif.jpg");
        $arriba = imagecolorat($im, 200, 40); $abajo = imagecolorat($im, 200, 360);
        chequear('la orientación se corrigió (la foto no queda de costado)', (($arriba >> 16) & 255) > 200 && ($abajo & 255) > 200);
    }
    ingesta_borrar_dir($d);
}
ingesta_borrar_dir($cfg['storage_dir']);

echo "\n════════ Resultado: $ok correctas, $mal con falla ════════\n";
exit($mal ? 1 : 0);
