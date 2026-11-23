<?php
// Pruebas de las funciones de trabajos que no necesitan base de datos. Uso:  php tests/trabajos_test.php
$cfg = ['storage_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedm_trabajos_test_' . getmypid()];
require __DIR__ . '/../includes/trabajos.php';

$ok = 0; $mal = 0;
function chequear(string $n, bool $c): void { global $ok, $mal; if ($c) { $ok++; echo "  [ok]   $n\n"; } else { $mal++; echo "  [FALLA] $n\n"; } }
function lanza(callable $f): string { try { $f(); return 'SIN_ERROR'; } catch (TrabajoError $e) { return $e->codigo; } }

echo "\n== Nombres\n";
$a1 = ['usuario_id' => 1, 'nombre' => 'Ana', 'apellido' => 'Álvarez', 'nombre_externo' => null];
$a2 = ['usuario_id' => 2, 'nombre' => 'Beto', 'apellido' => 'Benítez', 'nombre_externo' => null];
$a3 = ['usuario_id' => null, 'nombre' => null, 'apellido' => null, 'nombre_externo' => 'Lucía Pérez'];
chequear('un autor: «Apellido, Nombre»', autores_texto([$a1]) === 'Álvarez, Ana');
chequear('dos autores: se unen con «y»', autores_texto([$a1, $a2]) === 'Álvarez, Ana y Benítez, Beto');
chequear('tres autores: separados con «;»', autores_texto([$a1, $a2, $a3]) === 'Álvarez, Ana; Benítez, Beto; Lucía Pérez');
chequear('sin autores: vacío', autores_texto([]) === '');
chequear('materia común', trabajo_materia_texto('Dibujo', null) === 'Dibujo');
chequear('materia electiva con su nombre', trabajo_materia_texto('Asignatura Electiva', 'Fotografía') === 'Asignatura Electiva: Fotografía');
$t = ['materia' => 'Taller de Diseño I', 'electiva_nombre' => null, 'ciclo_lectivo' => 2025, 'titulo' => 'TP1'];
chequear('nombre canónico completo', trabajo_nombre_canonico($t, [$a1]) === 'Álvarez, Ana — Taller de Diseño I — 2025 — TP1');

echo "\n== Limpieza de texto\n";
chequear('espacios repetidos y bordes', trabajo_limpiar_texto("  hola    mundo \n  ") === 'hola mundo');
chequear('caracteres de control fuera', trabajo_limpiar_texto("ho\x00la\x07") === 'hola');
chequear('multilínea: conserva saltos, limita a 2 seguidos', trabajo_limpiar_texto("a\n\n\n\nb", true) === "a\n\nb");
chequear('multilínea: normaliza \\r\\n', trabajo_limpiar_texto("a\r\nb", true) === "a\nb");

echo "\n== Validaciones\n";
chequear('nota vacía → null', trabajo_validar_nota('') === null && trabajo_validar_nota(null) === null);
chequear('nota 8 → 8.00', trabajo_validar_nota('8') === '8.00');
chequear('nota con coma 8,5 → 8.50', trabajo_validar_nota('8,5') === '8.50');
chequear('nota 10 válida', trabajo_validar_nota('10') === '10.00');
foreach (['10.5', '11', '-1', 'abc', '8,555', '1e1', ' ; '] as $n) chequear("nota inválida «{$n}»", lanza(fn() => trabajo_validar_nota($n)) === 'nota');
chequear('título válido normaliza espacios', trabajo_validar_titulo('  TP1   Marca ') === 'TP1 Marca');
chequear('título de 2 caracteres → error', lanza(fn() => trabajo_validar_titulo('ab')) === 'titulo');
chequear('título de 100 caracteres → válido', mb_strlen(trabajo_validar_titulo(str_repeat('ñ', 100))) === 100);
chequear('título de 101 caracteres → error', lanza(fn() => trabajo_validar_titulo(str_repeat('a', 101))) === 'titulo');
chequear('descripción vacía → null', trabajo_validar_descripcion('  ') === null);
chequear('descripción de 2000 → válida', mb_strlen((string) trabajo_validar_descripcion(str_repeat('a', 2000))) === 2000);
chequear('descripción de 2001 → error', lanza(fn() => trabajo_validar_descripcion(str_repeat('a', 2001))) === 'descripcion');
foreach (array_keys(TRABAJO_LICENCIAS) as $l) chequear("licencia permitida: $l", trabajo_validar_licencia($l) === $l);
foreach (['', 'gpl', null, 5, ['cc_by']] as $l) chequear('licencia inválida: ' . json_encode($l), lanza(fn() => trabajo_validar_licencia($l)) === 'licencia');
chequear('coautor sin cuenta: nombre válido', trabajo_validar_externo("María José O'Brien-Núñez") === "María José O'Brien-Núñez");
foreach (['<b>x</b>', '1234', 'A', 'x;drop table', 'http://malo.com'] as $n) chequear("coautor sin cuenta inválido: $n", lanza(fn() => trabajo_validar_externo($n)) === 'coautor');
chequear('lista de coautores vacía → vacía', trabajo_resolver_coautores([], 1) === []);
chequear('coautores externos: quedan aceptados y sin duplicados (ignora mayúsculas)', array_column(trabajo_resolver_coautores([['nombre' => 'Lucía Pérez'], ['nombre' => 'lucía pérez']], 1), 'estado') === ['aceptado']);
chequear('más de 8 autores → error', lanza(fn() => trabajo_resolver_coautores(array_fill(0, 8, ['nombre' => 'Ana Lopez']), 1)) === 'coautor');
chequear('formato inválido de la lista → error', lanza(fn() => trabajo_resolver_coautores('basura', 1)) === 'coautor');

echo "\n== Texto de búsqueda\n";
chequear('un solo carácter → sin búsqueda', trabajos_terminos_busqueda('a') === []);
chequear('vacío → sin búsqueda', trabajos_terminos_busqueda('   ') === []);
chequear('dos caracteres alcanzan', trabajos_terminos_busqueda('ab') === ['ab']);
chequear('separa palabras y ignora espacios de más', trabajos_terminos_busqueda('  ana    manual ') === ['ana', 'manual']);
chequear('máximo 5 palabras', count(trabajos_terminos_busqueda('a1 b2 c3 d4 e5 f6 g7')) === 5);
chequear('cada palabra se acota a 40 caracteres', mb_strlen(trabajos_terminos_busqueda(str_repeat('x', 70))[0]) === 40);
chequear('un texto larguísimo se acota a 80 antes de separar', array_sum(array_map('mb_strlen', trabajos_terminos_busqueda(str_repeat('abcde ', 100)))) <= 80);
chequear('quita caracteres de control', trabajos_terminos_busqueda("ma\x00nual") === ['manual']);
chequear('conserva tildes y ñ', trabajos_terminos_busqueda('diseño álvarez') === ['diseño', 'álvarez']);

echo "\n== Permisos\n";
$trab = ['usuario_id' => 10, 'estado' => 'publicado'];
$aut = [['usuario_id' => 10, 'estado' => 'aceptado'], ['usuario_id' => 20, 'estado' => 'aceptado'], ['usuario_id' => 30, 'estado' => 'pendiente'], ['usuario_id' => 40, 'estado' => 'rechazado']];
chequear('quien publicó → dueno', trabajo_rol($trab, $aut, 10) === 'dueno');
chequear('coautor aceptado → autor', trabajo_rol($trab, $aut, 20) === 'autor');
chequear('invitado → pendiente', trabajo_rol($trab, $aut, 30) === 'pendiente');
chequear('quien rechazó → visitante', trabajo_rol($trab, $aut, 40) === 'visitante');
chequear('cualquier otra persona → visitante', trabajo_rol($trab, $aut, 99) === 'visitante');
chequear('publicado: lo ve cualquiera', trabajo_visible($trab, 'visitante'));
$oculto = ['estado' => 'oculto'];
chequear('oculto: no lo ve un visitante', !trabajo_visible($oculto, 'visitante'));
chequear('oculto: lo ven dueño, autor e invitado', trabajo_visible($oculto, 'dueno') && trabajo_visible($oculto, 'autor') && trabajo_visible($oculto, 'pendiente'));

echo "\n== Imágenes (sello y portada)\n";
if (!trabajo_gd_disponible()) {
    echo "  (se omiten: la extensión gd no está activada; los trabajos se publicarían sin sello)\n";
} else {
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedm_img_' . getmypid(); mkdir($tmp);
    $im = imagecreatetruecolor(1000, 1400); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255)); imagejpeg($im, "$tmp/1.jpg", 85);
    trabajo_procesar_imagenes($tmp, [1], "$tmp/out", 'Publicado por Álvarez, Ana | Dibujo | 2025 | BEDM #1');
    [$w, $h] = getimagesize("$tmp/out/1.jpg"); [$pw, $ph] = getimagesize("$tmp/out/portada.jpg");
    chequear('la imagen sellada conserva el ancho y suma la franja al alto', $w === 1000 && $h === 1400 + TRABAJO_BANDA_PX);
    chequear('la portada mide 480 px de ancho y hasta 300 de alto', $pw === 480 && $ph === 300);
    $largo = str_repeat('Texto larguísimo ', 40);
    trabajo_sellar_imagen("$tmp/1.jpg", "$tmp/largo.jpg", $largo);
    chequear('un sello demasiado largo no rompe la imagen', is_array(getimagesize("$tmp/largo.jpg")));
    chequear('una imagen dañada da un error controlado', lanza(function () use ($tmp) { file_put_contents("$tmp/roto.jpg", 'no soy un jpg'); trabajo_sellar_imagen("$tmp/roto.jpg", "$tmp/x.jpg", 'x'); }) === 'imagen');
    ingesta_borrar_dir($tmp);
}
ingesta_borrar_dir($cfg['storage_dir']);

echo "\n════════ Resultado: $ok correctas, $mal con falla ════════\n";
exit($mal ? 1 : 0);
