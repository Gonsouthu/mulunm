<?php
// Pruebas automáticas del motor de ingestión. Uso:  php tests/ingesta_test.php
// Necesita poppler (pdfinfo y pdftoppm) y la extensión curl de PHP.
$cfg = ['storage_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bedm_test_storage_' . getmypid()];
require __DIR__ . '/../includes/ingesta.php';

$ok = 0; $mal = 0;
function chequear(string $nombre, bool $cond, string $detalle = ''): void {
    global $ok, $mal;
    if ($cond) { $ok++; echo "  [ok]   $nombre\n"; } else { $mal++; echo "  [FALLA] $nombre" . ($detalle ? "  → $detalle" : '') . "\n"; }
}
function codigo_de(callable $f): string {
    try { $f(); return 'SIN_ERROR'; } catch (IngestaError $e) { return $e->codigo; } catch (Throwable $e) { return 'EXCEPCION: ' . $e->getMessage(); }
}
function espera_error(string $nombre, string $esperado, callable $f): void {
    $c = codigo_de($f); chequear("$nombre → $esperado", $c === $esperado, "devolvió $c");
}

// ════════ 1) Normalización de links ════════
echo "\n== Normalización de links\n";
$ID = 'AbCdEfGhIjKlMnOpQrStUvWxYz012345';
$r = ingesta_normalizar_url("https://drive.google.com/file/d/$ID/view?usp=sharing");
chequear('Drive: /file/d/ID/view', $r['proveedor'] === 'drive' && $r['url_descarga'] === "https://drive.usercontent.google.com/download?id=$ID&export=download&confirm=t");
$r = ingesta_normalizar_url("https://drive.google.com/open?id=$ID");
chequear('Drive: open?id=', $r['proveedor'] === 'drive' && str_contains($r['url_descarga'], "id=$ID"));
$r = ingesta_normalizar_url("https://drive.google.com/uc?id=$ID&export=download");
chequear('Drive: uc?id=', $r['proveedor'] === 'drive' && str_contains($r['url_descarga'], "id=$ID"));
$r = ingesta_normalizar_url("https://drive.google.com/file/u/1/d/$ID/view");
chequear('Drive: con /u/1/', str_contains($r['url_descarga'], "id=$ID"));
$r = ingesta_normalizar_url("https://docs.google.com/document/d/$ID/edit?usp=sharing");
chequear('Docs: documento → export pdf', $r['url_descarga'] === "https://docs.google.com/document/d/$ID/export?format=pdf");
$r = ingesta_normalizar_url("https://docs.google.com/presentation/d/$ID/edit#slide=id.p");
chequear('Docs: presentación → export/pdf', $r['url_descarga'] === "https://docs.google.com/presentation/d/$ID/export/pdf");
$r = ingesta_normalizar_url("https://docs.google.com/spreadsheets/d/$ID/edit");
chequear('Docs: hoja de cálculo → export pdf', $r['url_descarga'] === "https://docs.google.com/spreadsheets/d/$ID/export?format=pdf");
$r = ingesta_normalizar_url('https://www.dropbox.com/s/abc123/tp1.pdf?dl=0');
chequear('Dropbox: /s/ dl=0 → dl=1', $r['proveedor'] === 'dropbox' && $r['url_descarga'] === 'https://www.dropbox.com/s/abc123/tp1.pdf?dl=1');
$r = ingesta_normalizar_url('https://www.dropbox.com/scl/fi/xyz/tp.pdf?rlkey=k123&st=ab&dl=0');
chequear('Dropbox: /scl/fi/ conserva rlkey', str_contains($r['url_descarga'], 'rlkey=k123') && str_contains($r['url_descarga'], 'dl=1') && !str_contains($r['url_descarga'], 'dl=0'));
$r = ingesta_normalizar_url('https://dropbox.com/s/abc123/tp1.pdf?raw=1');
chequear('Dropbox: sin www y raw=1', str_starts_with($r['url_descarga'], 'https://www.dropbox.com/s/abc123/tp1.pdf') && !str_contains($r['url_descarga'], 'raw'));
$r = ingesta_normalizar_url('https://dl.dropboxusercontent.com/s/abc/tp.pdf');
chequear('Dropbox: dl.dropboxusercontent.com', $r['proveedor'] === 'dropbox');
$orig = 'https://1drv.ms/b/s!AbCdEf123';
$r = ingesta_normalizar_url($orig);
$b64 = substr($r['url_descarga'], strlen('https://api.onedrive.com/v1.0/shares/u!'), -strlen('/root/content'));
chequear('OneDrive: 1drv.ms → API de enlaces compartidos (base64 ida y vuelta)', $r['proveedor'] === 'onedrive' && base64_decode(strtr($b64, '-_', '+/')) === $orig);
$r = ingesta_normalizar_url('https://uni-my.sharepoint.com/:b:/g/personal/x/Eabc?e=Q1');
chequear('SharePoint: agrega download=1', $r['proveedor'] === 'onedrive' && str_contains($r['url_descarga'], 'download=1') && str_contains($r['url_descarga'], 'e=Q1'));

echo "\n== Links inválidos o peligrosos\n";
espera_error('vacío', 'url_vacia', fn() => ingesta_normalizar_url('   '));
espera_error('texto suelto', 'url_invalida', fn() => ingesta_normalizar_url('mi trabajo final'));
espera_error('http (sin s)', 'url_invalida', fn() => ingesta_normalizar_url("http://drive.google.com/file/d/$ID/view"));
espera_error('file://', 'url_invalida', fn() => ingesta_normalizar_url('file:///etc/passwd'));
espera_error('javascript:', 'url_invalida', fn() => ingesta_normalizar_url('javascript:alert(1)'));
espera_error('ftp://', 'url_invalida', fn() => ingesta_normalizar_url('ftp://drive.google.com/x'));
espera_error('usuario@ en el link (drive.google.com@evil.com)', 'url_invalida', fn() => ingesta_normalizar_url("https://drive.google.com@evil.com/file/d/$ID/view"));
espera_error('puerto raro', 'url_invalida', fn() => ingesta_normalizar_url("https://drive.google.com:8443/file/d/$ID/view"));
espera_error('espacio dentro del link', 'url_invalida', fn() => ingesta_normalizar_url("https://drive.google.com/file/d/$ID /view"));
espera_error('salto de línea', 'url_invalida', fn() => ingesta_normalizar_url("https://drive.google.com/file/d/$ID/view\nhttps://evil.com"));
espera_error('link larguísimo', 'url_invalida', fn() => ingesta_normalizar_url('https://drive.google.com/file/d/' . str_repeat('a', 3000)));
espera_error('sitio cualquiera', 'proveedor_no_soportado', fn() => ingesta_normalizar_url('https://example.com/tp.pdf'));
espera_error('dominio engañoso drive.google.com.evil.com', 'proveedor_no_soportado', fn() => ingesta_normalizar_url("https://drive.google.com.evil.com/file/d/$ID/view"));
espera_error('dominio engañoso notdropbox.com', 'proveedor_no_soportado', fn() => ingesta_normalizar_url('https://notdropbox.com/s/abc/tp.pdf'));
espera_error('Drive: carpeta', 'carpeta', fn() => ingesta_normalizar_url("https://drive.google.com/drive/folders/$ID"));
espera_error('Drive: carpeta con /u/0/', 'carpeta', fn() => ingesta_normalizar_url("https://drive.google.com/drive/u/0/folders/$ID"));
espera_error('Dropbox: carpeta /sh/', 'carpeta', fn() => ingesta_normalizar_url('https://www.dropbox.com/sh/abc/xyz'));
espera_error('Dropbox: carpeta /scl/fo/', 'carpeta', fn() => ingesta_normalizar_url('https://www.dropbox.com/scl/fo/abc/xyz?rlkey=1'));
espera_error('Drive: id demasiado corto', 'link_no_reconocido', fn() => ingesta_normalizar_url('https://drive.google.com/file/d/corto/view'));
espera_error('Drive: página principal', 'link_no_reconocido', fn() => ingesta_normalizar_url('https://drive.google.com/drive/my-drive'));
espera_error('Dropbox: página principal', 'link_no_reconocido', fn() => ingesta_normalizar_url('https://www.dropbox.com/home'));

// ════════ 2) Defensas anti-SSRF ════════
echo "\n== Defensas anti-SSRF (el servidor no debe poder ser usado para llegar a redes internas)\n";
$op = ingesta_opciones();
foreach ([
    'https://127.0.0.1/x', 'https://127.1.2.3/x', 'https://169.254.169.254/latest/meta-data/', 'https://10.0.0.5/x',
    'https://172.16.5.5/x', 'https://192.168.0.10/x', 'https://[::1]/x', 'https://0.0.0.0/x', 'https://100.64.0.1/x',
    'https://[::ffff:127.0.0.1]/x', 'https://localhost/x', 'https://evil.com/x', 'https://notgoogle.com/x',
    'https://google.com.evil.com/x', 'https://googleusercontent.com.evil.com/x', 'https://drive.google.com:8080/x',
    'http://drive.google.com/x', 'https://u:p@drive.google.com/x', 'ftp://drive.google.com/x', 'gopher://drive.google.com/x',
] as $u) {
    espera_error("bloquea $u", 'host_no_permitido', fn() => ingesta_validar_destino($u, $op));
}
echo "  -- segunda capa: aunque el dominio estuviera permitido, una IP interna se rechaza\n";
$sinLista = ['permitir_cualquier_host' => true] + $op;
foreach (['https://127.0.0.1/x', 'https://169.254.169.254/x', 'https://10.0.0.5/x', 'https://192.168.1.1/x', 'https://[::1]/x', 'https://localhost/x'] as $u) {
    espera_error("IP interna rechazada aun sin filtro de dominio: $u", 'host_no_permitido', fn() => ingesta_validar_destino($u, $sinLista));
}
foreach (['8.8.8.8', '1.1.1.1', '2001:4860:4860::8888'] as $ip) chequear("IP pública $ip permitida", ingesta_ip_es_publica($ip));
foreach (['127.0.0.1', '10.1.1.1', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1', 'no-es-ip'] as $ip) {
    chequear("IP no pública $ip rechazada", !ingesta_ip_es_publica($ip));
}
foreach (['drive.google.com', 'docs.google.com', 'lh3.googleusercontent.com', 'www.dropbox.com', 'uc123.dl.dropboxusercontent.com', 'api.onedrive.com', 'uni-my.sharepoint.com', 'x.files.1drv.com'] as $h) chequear("host permitido: $h", ingesta_host_permitido($h));
foreach (['evil.com', 'notgoogle.com', 'google.com.evil.com', 'dropbox.com.evil.net', 'localhost', ''] as $h) chequear("host NO permitido: '$h'", !ingesta_host_permitido($h));
echo "  -- redirecciones: cada salto se revalida\n";
$sig = ingesta_resolver_ubicacion('https://drive.google.com/a/b', 'http://169.254.169.254/latest');
espera_error('redirige a IP interna', 'host_no_permitido', fn() => ingesta_validar_destino($sig, $op));
$sig = ingesta_resolver_ubicacion('https://drive.google.com/a/b', '//evil.com/x');
espera_error('redirige con //evil.com', 'host_no_permitido', fn() => ingesta_validar_destino($sig, $op));
$sig = ingesta_resolver_ubicacion('https://drive.google.com/a/b', 'https://127.0.0.1:8080/admin');
espera_error('redirige a localhost:8080', 'host_no_permitido', fn() => ingesta_validar_destino($sig, $op));
chequear('ubicación relativa absoluta (/x)', ingesta_resolver_ubicacion('https://drive.google.com/a/b', '/x') === 'https://drive.google.com/x');
chequear('ubicación relativa (x)', ingesta_resolver_ubicacion('https://drive.google.com/a/b', 'x') === 'https://drive.google.com/a/x');

// ════════ 3) Token / carpeta ════════
echo "\n== Tokens\n";
foreach (['../../etc', '', 'abc', str_repeat('g', 32), str_repeat('a', 31), str_repeat('A', 32), '../' . str_repeat('a', 29)] as $t) {
    chequear("token inválido '" . substr($t, 0, 12) . "…' rechazado", ingesta_dir_token($t) === null && ingesta_leer_meta($t) === null);
}

// ════════ 4) Flujo completo contra un servidor falso ════════
echo "\n== Flujo completo (descarga → PDF → imágenes → borrar original)\n";
// Puerto libre elegido por el sistema (así no choca con otros programas)
$sock = stream_socket_server('tcp://127.0.0.1:0');
$puerto = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
fclose($sock);
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", __DIR__ . '/servidor_falso.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p);
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $puerto)) break; usleep(100000); }
$B = "http://127.0.0.1:$puerto";
$prueba = ['permitir_http' => true, 'permitir_cualquier_host' => true, 'permitir_ip_locales' => true];
$norm = fn(string $ruta) => ['proveedor' => 'drive', 'url_descarga' => $B . $ruta, 'url_original' => 'https://drive.google.com/file/d/TEST/view'];
$base = ingesta_dir_base();
$dirs = fn() => array_values(array_filter(scandir($base), fn($f) => preg_match('/^[a-f0-9]{32}$/', $f)));

$m = ingesta_procesar($norm('/ok.pdf'), 7, $prueba);
$d = ingesta_dir_token($m['token']);
chequear('PDF de 3 páginas: total 3 y 3 imágenes', $m['total_paginas'] === 3 && $m['paginas'] === [1, 2, 3]);
chequear('sha256 coincide con el archivo real', $m['sha256'] === hash_file('sha256', __DIR__ . '/fixtures/tres_paginas.pdf'));
chequear('bytes coincide con el archivo real', $m['bytes'] === filesize(__DIR__ . '/fixtures/tres_paginas.pdf'));
chequear('el ORIGINAL se borró (solo quedan imágenes y meta.json)', scandir($d) === ['.', '..', '1.jpg', '2.jpg', '3.jpg', 'meta.json'], implode(',', scandir($d)));
$gi = getimagesize($d . '/1.jpg');
chequear('las imágenes son JPG válidos de ancho ≤ 1000 px', $gi && $gi['mime'] === 'image/jpeg' && $gi[0] <= 1000, json_encode($gi));
chequear('la meta guarda el dueño y el proveedor', ingesta_leer_meta($m['token'])['usuario_id'] === 7 && ingesta_leer_meta($m['token'])['proveedor'] === 'drive');
$m2 = ingesta_procesar($norm('/redir'), 7, $prueba);
chequear('sigue una redirección válida', $m2['total_paginas'] === 3);
$m2b = ingesta_procesar($norm('/redir-relativa'), 7, $prueba);
chequear('sigue una redirección relativa', $m2b['total_paginas'] === 3);
$m3 = ingesta_procesar($norm('/treinta.pdf'), 7, $prueba);
chequear('PDF de 30 páginas: se generan solo 20', $m3['total_paginas'] === 30 && count($m3['paginas']) === 20 && !is_file(ingesta_dir_token($m3['token']) . '/21.jpg'));
$antes = count($dirs());
espera_error('PDF con contraseña', 'pdf_protegido', fn() => ingesta_procesar($norm('/clave.pdf'), 7, $prueba));
espera_error('texto disfrazado de PDF', 'no_es_pdf', fn() => ingesta_procesar($norm('/falso.pdf'), 7, $prueba));
espera_error('PDF dañado', 'pdf_invalido', fn() => ingesta_procesar($norm('/roto.pdf'), 7, $prueba));
espera_error('página de login en vez de archivo', 'no_publico', fn() => ingesta_procesar($norm('/web'), 7, $prueba));
espera_error('403 (privado)', 'no_publico', fn() => ingesta_procesar($norm('/prohibido'), 7, $prueba));
espera_error('404', 'no_encontrado', fn() => ingesta_procesar($norm('/nada'), 7, $prueba));
espera_error('429 (cuota)', 'cuota', fn() => ingesta_procesar($norm('/cuota'), 7, $prueba));
espera_error('503 (sitio caído)', 'servidor_remoto', fn() => ingesta_procesar($norm('/caido'), 7, $prueba));
espera_error('redirecciones infinitas', 'demasiadas_redirecciones', fn() => ingesta_procesar($norm('/redir-loop'), 7, $prueba));
espera_error('archivo grande (Content-Length)', 'demasiado_grande', fn() => ingesta_procesar($norm('/grande'), 7, ['max_bytes' => 1048576] + $prueba));
espera_error('archivo grande (sin Content-Length)', 'demasiado_grande', fn() => ingesta_procesar($norm('/grande-chunked'), 7, ['max_bytes' => 1048576] + $prueba));
$t0 = microtime(true);
espera_error('servidor lento', 'timeout', fn() => ingesta_procesar($norm('/lento'), 7, ['timeout_descarga' => 2] + $prueba));
chequear('el timeout cortó a tiempo (< 4 s)', microtime(true) - $t0 < 4, round(microtime(true) - $t0, 1) . ' s');
chequear('tras 12 fallos NO quedó ninguna carpeta temporal huérfana', count($dirs()) === $antes, count($dirs()) . ' vs ' . $antes);
espera_error('el filtro anti-SSRF sigue activo sin las opciones de prueba', 'host_no_permitido', fn() => ingesta_procesar($norm('/ok.pdf'), 7));

echo "\n== Sin poppler instalado\n";
$cfg['poppler_dir'] = '/ruta/que/no/existe';
$antes = count($dirs());
espera_error('poppler_dir inválido', 'sin_poppler', fn() => ingesta_procesar($norm('/ok.pdf'), 7, $prueba));
chequear('no deja carpetas huérfanas', count($dirs()) === $antes);
unset($cfg['poppler_dir']);

echo "\n== Limpieza de temporales viejos\n";
$viejo = $base . '/' . str_repeat('b', 32); mkdir($viejo); file_put_contents("$viejo/1.jpg", 'x'); touch($viejo, time() - 10000);
$nuevo = $base . '/' . str_repeat('c', 32); mkdir($nuevo);
ingesta_limpiar_tmp(7200);
chequear('borra la carpeta de más de 2 horas', !is_dir($viejo));
chequear('conserva la reciente', is_dir($nuevo));

// limpieza final
proc_terminate($srv, 9);
ingesta_borrar_dir(dirname($base));
echo "\n════════ Resultado: $ok correctas, $mal con falla ════════\n";
exit($mal ? 1 : 0);
