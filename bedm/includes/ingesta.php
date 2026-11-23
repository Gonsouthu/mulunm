<?php
// ══════════════════════════════════════════════════════════════
// INGESTIÓN: link de la nube → imágenes de previsualización
//
// Regla del proyecto: NO se guarda el archivo original. El servidor lo descarga
// un momento, genera las imágenes (JPG de baja resolución), calcula su huella
// SHA-256 y lo borra. Solo quedan las imágenes y los datos (link, huella, tamaño).
// ══════════════════════════════════════════════════════════════

class IngestaError extends RuntimeException {
    public string $codigo;
    public function __construct(string $codigo, string $mensaje) {
        parent::__construct($mensaje);
        $this->codigo = $codigo;
    }
}

// ── Configuración (se lee de config/config.local.php, ver config.example.php) ──
function ingesta_cfg(string $clave, $defecto = null) {
    global $cfg;
    return (is_array($cfg ?? null) && array_key_exists($clave, $cfg) && $cfg[$clave] !== '') ? $cfg[$clave] : $defecto;
}

function ingesta_opciones(array $extra = []): array {
    return $extra + [
        'max_bytes'           => (int) ingesta_cfg('ingesta_max_mb', 40) * 1024 * 1024,
        'max_paginas'         => (int) ingesta_cfg('ingesta_max_paginas', 20),
        'ancho_px'            => (int) ingesta_cfg('ingesta_ancho_px', 1000),
        'timeout_descarga'    => (int) ingesta_cfg('ingesta_timeout_descarga', 45),
        'timeout_render'      => (int) ingesta_cfg('ingesta_timeout_render', 60),
        // Solo para pruebas automáticas (nunca se activan desde la configuración):
        'permitir_http'       => false,
        'permitir_cualquier_host' => false,
        'permitir_ip_locales' => false,
    ];
}

// ── Carpeta temporal de previsualizaciones (fuera del alcance de la web) ──
function ingesta_dir_base(): string {
    $dir = ingesta_cfg('storage_dir', __DIR__ . '/../storage') . DIRECTORY_SEPARATOR . 'tmp_previews';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = dirname($dir) . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_dir(dirname($dir)) && !is_file($ht)) {
        @file_put_contents($ht, "# Nadie entra por web a esta carpeta: las imágenes se sirven con api/preview_img.php\nRequire all denied\n");
    }
    return $dir;
}

function ingesta_dir_token(string $token): ?string {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    return ingesta_dir_base() . DIRECTORY_SEPARATOR . $token;
}

function ingesta_leer_meta(string $token): ?array {
    $dir = ingesta_dir_token($token);
    if ($dir === null || !is_file($dir . DIRECTORY_SEPARATOR . 'meta.json')) return null;
    $m = json_decode((string) @file_get_contents($dir . DIRECTORY_SEPARATOR . 'meta.json'), true);
    return is_array($m) ? $m : null;
}

function ingesta_borrar_dir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $ruta = $dir . DIRECTORY_SEPARATOR . $f;
        is_dir($ruta) && !is_link($ruta) ? ingesta_borrar_dir($ruta) : @unlink($ruta);
    }
    @rmdir($dir);
}

// Borra previsualizaciones temporales viejas (las que nadie confirmó)
function ingesta_limpiar_tmp(int $segundos = 7200): void {
    $base = ingesta_dir_base();
    foreach (scandir($base) ?: [] as $f) {
        if (!preg_match('/^[a-f0-9]{32}$/', $f)) continue;
        $ruta = $base . DIRECTORY_SEPARATOR . $f;
        if (is_dir($ruta) && filemtime($ruta) < time() - $segundos) ingesta_borrar_dir($ruta);
    }
}

// ══════════════════════════════════════════════════════════════
// 1) NORMALIZAR EL LINK: reconoce el proveedor y arma el link de descarga
// ══════════════════════════════════════════════════════════════
function ingesta_normalizar_url(string $entrada): array {
    $url = trim($entrada);
    if ($url === '') throw new IngestaError('url_vacia', 'Pegá el link de tu archivo.');
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
        throw new IngestaError('url_invalida', 'Ese link no es válido. Copiá la dirección completa desde la barra del navegador.');
    }
    $p = parse_url($url);
    if ($p === false || empty($p['host'])) {
        throw new IngestaError('url_invalida', 'Ese texto no parece un link. Copiá la dirección completa, empezando con https://');
    }
    if (strtolower($p['scheme'] ?? '') !== 'https') {
        throw new IngestaError('url_invalida', 'El link tiene que empezar con https://');
    }
    if (isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
        throw new IngestaError('url_invalida', 'Ese link no es válido.');
    }

    $host = strtolower(rtrim($p['host'], '.'));
    $path = $p['path'] ?? '/';
    parse_str($p['query'] ?? '', $q);
    $ID = '[A-Za-z0-9_-]{10,200}';

    // ── Google Drive (archivos) ──
    if ($host === 'drive.google.com') {
        if (preg_match('#^/(?:drive/)?(?:u/\d+/)?folders/#', $path)) {
            throw new IngestaError('carpeta', 'Ese link es de una carpeta. Compartí el archivo PDF directamente, no la carpeta.');
        }
        if (preg_match("#^/file/(?:u/\d+/)?d/($ID)#", $path, $m)) {
            $id = $m[1];
        } elseif (in_array($path, ['/open', '/uc'], true) && isset($q['id']) && is_string($q['id']) && preg_match("#^$ID$#", $q['id'])) {
            $id = $q['id'];
        } else {
            throw new IngestaError('link_no_reconocido', 'No reconocimos ese link de Google Drive. Usá «Compartir → Copiar enlace» sobre el archivo.');
        }
        return [
            'proveedor'    => 'drive',
            'url_descarga' => 'https://drive.usercontent.google.com/download?id=' . $id . '&export=download&confirm=t',
            'url_original' => $url,
        ];
    }

    // ── Google Docs / Presentaciones / Hojas (se exportan a PDF) ──
    if ($host === 'docs.google.com' && preg_match("#^/(document|presentation|spreadsheets)/(?:u/\d+/)?d/($ID)#", $path, $m)) {
        [$tipo, $id] = [$m[1], $m[2]];
        $dl = $tipo === 'presentation'
            ? "https://docs.google.com/presentation/d/$id/export/pdf"
            : "https://docs.google.com/$tipo/d/$id/export?format=pdf";
        return ['proveedor' => 'drive', 'url_descarga' => $dl, 'url_original' => $url];
    }

    // ── Dropbox ──
    if ($host === 'dropbox.com' || $host === 'www.dropbox.com') {
        if (preg_match('#^/(sh|scl/fo)/#', $path)) {
            throw new IngestaError('carpeta', 'Ese link es de una carpeta. Compartí el archivo PDF directamente, no la carpeta.');
        }
        if (!preg_match('#^/(s|scl/fi)/#', $path)) {
            throw new IngestaError('link_no_reconocido', 'No reconocimos ese link de Dropbox. Usá «Compartir → Crear enlace» sobre el archivo.');
        }
        unset($q['dl'], $q['raw']);
        $q['dl'] = '1';
        return [
            'proveedor'    => 'dropbox',
            'url_descarga' => 'https://www.dropbox.com' . $path . '?' . http_build_query($q),
            'url_original' => $url,
        ];
    }
    if ($host === 'dl.dropboxusercontent.com') {
        return ['proveedor' => 'dropbox', 'url_descarga' => $url, 'url_original' => $url];
    }

    // ── OneDrive (personal: se usa la API pública de enlaces compartidos) ──
    if ($host === '1drv.ms' || $host === 'onedrive.live.com') {
        $b64 = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        return [
            'proveedor'    => 'onedrive',
            'url_descarga' => 'https://api.onedrive.com/v1.0/shares/u!' . $b64 . '/root/content',
            'url_original' => $url,
        ];
    }
    // ── OneDrive / SharePoint de la universidad o de trabajo ──
    if (str_ends_with($host, '.sharepoint.com')) {
        $q['download'] = '1';
        return [
            'proveedor'    => 'onedrive',
            'url_descarga' => 'https://' . $host . $path . '?' . http_build_query($q),
            'url_original' => $url,
        ];
    }

    throw new IngestaError(
        'proveedor_no_soportado',
        'Por ahora aceptamos links de Google Drive (y Docs/Presentaciones), Dropbox y OneDrive.'
    );
}

// ══════════════════════════════════════════════════════════════
// 2) DESCARGA SEGURA (anti-SSRF): solo hosts conocidos, solo IPs públicas,
//    cada redirección se vuelve a validar, tamaño y tiempo limitados.
// ══════════════════════════════════════════════════════════════
const INGESTA_SUFIJOS_PERMITIDOS = [
    'google.com', 'googleusercontent.com',
    'dropbox.com', 'dropboxusercontent.com',
    'onedrive.com', 'live.com', '1drv.ms', '1drv.com', 'sharepoint.com', 'microsoftpersonalcontent.com',
];

function ingesta_host_permitido(string $host): bool {
    $host = strtolower(rtrim($host, '.'));
    foreach (INGESTA_SUFIJOS_PERMITIDOS as $s) {
        if ($host === $s || str_ends_with($host, '.' . $s)) return true;
    }
    return false;
}

function ingesta_ip_es_publica(string $ip): bool {
    // IPv6 "mapeada" a IPv4 (::ffff:1.2.3.4): se evalúa la IPv4 de adentro
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    if (strlen($bin) === 16 && substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
        $ip = inet_ntop(substr($bin, 12));
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach (['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3'] as $cidr) {
            [$red, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($n & $mask) === (ip2long($red) & $mask)) return false;
        }
    }
    return true;
}

// Valida UN destino (se usa en el link inicial y en cada redirección). Devuelve host, puerto, ip y si es https.
function ingesta_validar_destino(string $url, array $op): array {
    $p = parse_url($url);
    if ($p === false || empty($p['host'])) throw new IngestaError('host_no_permitido', 'El link lleva a una dirección no válida.');
    $esq = strtolower($p['scheme'] ?? '');
    if (!($esq === 'https' || ($op['permitir_http'] && $esq === 'http'))) {
        throw new IngestaError('host_no_permitido', 'El link lleva a una dirección no segura.');
    }
    if (isset($p['user']) || isset($p['pass'])) throw new IngestaError('host_no_permitido', 'El link lleva a una dirección no válida.');
    $host = strtolower(rtrim($p['host'], '.'));
    $host = trim($host, '[]');
    $puerto = (int) ($p['port'] ?? ($esq === 'https' ? 443 : 80));

    if (!$op['permitir_cualquier_host']) {
        if (!ingesta_host_permitido($host) || $puerto !== 443) {
            throw new IngestaError('host_no_permitido', 'El link lleva a un sitio que no está permitido.');
        }
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = @gethostbynamel($host) ?: [];
        if (!$ips) throw new IngestaError('dns', 'No se pudo encontrar el servidor de ese link.');
    }
    if (!$op['permitir_ip_locales']) {
        foreach ($ips as $ip) {
            if (!ingesta_ip_es_publica($ip)) {
                throw new IngestaError('host_no_permitido', 'El link lleva a una dirección no permitida.');
            }
        }
    }
    return ['host' => $host, 'puerto' => $puerto, 'ip' => $ips[0], 'https' => $esq === 'https'];
}

function ingesta_resolver_ubicacion(string $base, string $loc): string {
    if (preg_match('#^https?://#i', $loc)) return $loc;
    $p = parse_url($base);
    $origen = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($loc, '//')) return $p['scheme'] . ':' . $loc;
    if (str_starts_with($loc, '/')) return $origen . $loc;
    $dir = rtrim(dirname($p['path'] ?? '/'), '/');
    return $origen . $dir . '/' . $loc;
}

// Descarga $url en $destino. Devuelve ['bytes', 'url_final', 'content_type'].
function ingesta_descargar(string $url, string $destino, array $op): array {
    $actual = $url;
    $max = $op['max_bytes'];
    $maxSaltos = 5;

    for ($salto = 0; $salto <= $maxSaltos; $salto++) {
        $dest = ingesta_validar_destino($actual, $op);

        $fh = fopen($destino, 'wb');
        if (!$fh) throw new IngestaError('render_fallo', 'No se pudo preparar la carpeta temporal del servidor.');
        $escrito = 0; $demasiado = false; $loc = ''; $tipo = '';

        $ch = curl_init($actual);
        $protos = $dest['https'] ? CURLPROTO_HTTPS : (CURLPROTO_HTTPS | CURLPROTO_HTTP);
        $opts = [
            CURLOPT_FOLLOWLOCATION => false,                 // las redirecciones las seguimos nosotros, validándolas
            CURLOPT_PROTOCOLS      => $protos,
            CURLOPT_RESOLVE        => [$dest['host'] . ':' . $dest['puerto'] . ':' . $dest['ip']],  // evita que el DNS cambie entre la validación y la descarga
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $op['timeout_descarga'],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; BEDM-Previews/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($c, $linea) use (&$loc, &$tipo, &$demasiado, $max) {
                $n = strlen($linea);
                if (stripos($linea, 'location:') === 0) $loc = trim(substr($linea, 9));
                elseif (stripos($linea, 'content-type:') === 0) $tipo = strtolower(trim(substr($linea, 13)));
                elseif (stripos($linea, 'content-length:') === 0 && (int) trim(substr($linea, 15)) > $max) { $demasiado = true; return 0; }
                return $n;
            },
            CURLOPT_WRITEFUNCTION  => function ($c, $datos) use ($fh, &$escrito, &$demasiado, $max) {
                $escrito += strlen($datos);
                if ($escrito > $max) { $demasiado = true; return 0; }
                return fwrite($fh, $datos);
            },
        ];
        if ($ca = ingesta_cfg('ca_bundle')) $opts[CURLOPT_CAINFO] = $ca;
        curl_setopt_array($ch, $opts);

        curl_exec($ch);
        $errno  = curl_errno($ch);
        $error  = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fh);

        if ($demasiado) {
            @unlink($destino);
            throw new IngestaError('demasiado_grande', 'El archivo pesa más de ' . round($max / 1048576) . ' MB. Probá comprimir el PDF o dividirlo.');
        }
        if ($errno) {
            @unlink($destino);
            error_log("BEDM ingesta: curl $errno ($error) en $actual");
            if ($errno === 28) throw new IngestaError('timeout', 'El servidor del link tardó demasiado en responder. Probá de nuevo en un rato.');
            if (in_array($errno, [35, 51, 58, 60, 77, 83], true)) throw new IngestaError('ssl', 'El servidor no pudo verificar la conexión segura con el sitio del link (certificados). Avisale a quien administra el sitio.');
            throw new IngestaError('red', 'No se pudo descargar el archivo desde ese link.');
        }

        if (in_array($codigo, [301, 302, 303, 307, 308], true)) {
            if ($loc === '') throw new IngestaError('http_error', 'El link no devolvió el archivo.');
            $actual = ingesta_resolver_ubicacion($actual, $loc);
            continue;
        }

        if ($codigo === 404) { @unlink($destino); throw new IngestaError('no_encontrado', 'No encontramos ese archivo. Revisá que el link sea correcto y que el archivo exista.'); }
        if ($codigo === 401 || $codigo === 403) { @unlink($destino); throw new IngestaError('no_publico', 'No pudimos acceder al archivo. Compartilo como «Cualquier persona con el enlace» y probá de nuevo.'); }
        if ($codigo === 429) { @unlink($destino); throw new IngestaError('cuota', 'El sitio nos pidió esperar (demasiadas descargas de ese archivo). Probá de nuevo en un rato.'); }
        if ($codigo >= 500) { @unlink($destino); throw new IngestaError('servidor_remoto', 'El sitio del link tuvo un problema. Probá de nuevo en unos minutos.'); }
        if ($codigo < 200 || $codigo >= 300) { @unlink($destino); throw new IngestaError('http_error', 'No se pudo descargar el archivo (error ' . $codigo . ').'); }
        if (str_starts_with($tipo, 'text/html')) {
            // Páginas de inicio de sesión, de cuota excedida o de "pedir acceso"
            @unlink($destino);
            throw new IngestaError('no_publico', 'El link no lleva directamente al archivo. Compartilo como «Cualquier persona con el enlace» y probá de nuevo.');
        }
        return ['bytes' => $escrito, 'url_final' => $actual, 'content_type' => $tipo];
    }

    @unlink($destino);
    throw new IngestaError('demasiadas_redirecciones', 'El link da demasiadas vueltas y no llega al archivo.');
}

// ══════════════════════════════════════════════════════════════
// 3) PDF → IMÁGENES (poppler: pdfinfo + pdftoppm)
// ══════════════════════════════════════════════════════════════
function ingesta_es_pdf(string $ruta): bool {
    $f = @fopen($ruta, 'rb');
    if (!$f) return false;
    $cab = fread($f, 1024);
    fclose($f);
    return $cab !== false && strpos($cab, '%PDF-') !== false;
}

function ingesta_bin(string $nombre): string {
    $exe = $nombre . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    $dir = (string) ingesta_cfg('poppler_dir', '');
    if ($dir !== '') {
        $ruta = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $exe;
        if (!is_file($ruta)) {
            error_log("BEDM ingesta: no se encontró $ruta (revisá 'poppler_dir' en config.local.php)");
            throw new IngestaError('sin_poppler', 'El servidor todavía no tiene instalada la herramienta que genera las previsualizaciones.');
        }
        return $ruta;
    }
    return $nombre;   // se busca en el PATH del sistema
}

// Ejecuta un programa SIN pasar por la consola (los argumentos no se pueden "inyectar") y con tiempo máximo.
function ingesta_ejecutar(array $cmd, int $timeout): array {
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        throw new IngestaError('sin_poppler', 'El servidor todavía no tiene instalada la herramienta que genera las previsualizaciones.');
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = ''; $err = ''; $inicio = microtime(true); $vencio = false;
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        if (!proc_get_status($proc)['running']) break;
        if (microtime(true) - $inicio > $timeout) { proc_terminate($proc, 9); $vencio = true; break; }
        usleep(50000);
    }
    $out .= (string) stream_get_contents($pipes[1]);
    $err .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $codigo = proc_close($proc);
    return ['codigo' => $codigo, 'out' => substr($out, 0, 20000), 'err' => substr($err, 0, 20000), 'vencio' => $vencio];
}

function ingesta_info_pdf(string $pdf, array $op): int {
    $r = ingesta_ejecutar([ingesta_bin('pdfinfo'), $pdf], 20);
    if ($r['vencio']) throw new IngestaError('pdf_invalido', 'No pudimos leer el PDF (tardó demasiado).');
    if ($r['codigo'] !== 0) {
        if (stripos($r['err'], 'password') !== false) {
            throw new IngestaError('pdf_protegido', 'El PDF tiene contraseña. Subí una versión sin contraseña para poder previsualizarlo.');
        }
        throw new IngestaError('pdf_invalido', 'El archivo parece estar dañado o no es un PDF válido.');
    }
    if (!preg_match('/^Pages:\s+(\d+)/m', $r['out'], $m) || (int) $m[1] < 1) {
        throw new IngestaError('pdf_invalido', 'No pudimos leer las páginas del PDF.');
    }
    return (int) $m[1];
}

// Genera las imágenes {n}.jpg dentro de $dirSalida. Devuelve la lista de números de página generados.
function ingesta_renderizar(string $pdf, string $dirSalida, int $paginas, array $op): array {
    $hasta = min($paginas, $op['max_paginas']);
    $prefijo = $dirSalida . DIRECTORY_SEPARATOR . 'pagina';
    $r = ingesta_ejecutar([
        ingesta_bin('pdftoppm'), '-jpeg', '-jpegopt', 'quality=80',
        '-scale-to-x', (string) $op['ancho_px'], '-scale-to-y', '-1',
        '-f', '1', '-l', (string) $hasta, $pdf, $prefijo,
    ], $op['timeout_render']);
    if ($r['vencio']) throw new IngestaError('render_fallo', 'El PDF es demasiado pesado de procesar. Probá con una versión más liviana.');
    if ($r['codigo'] !== 0) {
        error_log('BEDM ingesta: pdftoppm falló: ' . $r['err']);
        throw new IngestaError('render_fallo', 'No pudimos generar la previsualización de ese PDF.');
    }
    $generadas = [];
    foreach (scandir($dirSalida) ?: [] as $f) {
        if (preg_match('/^pagina-(\d+)\.jpg$/', $f, $m)) {
            $n = (int) $m[1];
            rename($dirSalida . DIRECTORY_SEPARATOR . $f, $dirSalida . DIRECTORY_SEPARATOR . $n . '.jpg');
            $generadas[] = $n;
        }
    }
    sort($generadas);
    if (!$generadas) throw new IngestaError('render_fallo', 'No pudimos generar la previsualización de ese PDF.');
    return $generadas;
}

// ══════════════════════════════════════════════════════════════
// 4) ORQUESTADOR
// ══════════════════════════════════════════════════════════════
// $norm = resultado de ingesta_normalizar_url(). Devuelve los datos de la previsualización temporal.
function ingesta_procesar(array $norm, int $usuarioId, array $op = []): array {
    $op = ingesta_opciones($op);
    ingesta_limpiar_tmp();

    $token = bin2hex(random_bytes(16));
    $dir = ingesta_dir_token($token);
    if (!@mkdir($dir, 0775, true)) throw new IngestaError('render_fallo', 'No se pudo preparar la carpeta temporal del servidor.');
    $pdf = $dir . DIRECTORY_SEPARATOR . 'origen.tmp';

    try {
        $d = ingesta_descargar($norm['url_descarga'], $pdf, $op);
        if (!ingesta_es_pdf($pdf)) {
            throw new IngestaError('no_es_pdf', 'Ese link no lleva a un PDF. Por ahora solo podemos previsualizar archivos PDF.');
        }
        $sha = hash_file('sha256', $pdf);
        $total = ingesta_info_pdf($pdf, $op);
        $generadas = ingesta_renderizar($pdf, $dir, $total, $op);
    } catch (Throwable $e) {
        ingesta_borrar_dir($dir);      // ante cualquier fallo no queda nada
        throw $e;
    }
    @unlink($pdf);                      // el original NUNCA se conserva

    $meta = [
        'token'         => $token,
        'usuario_id'    => $usuarioId,
        'proveedor'     => $norm['proveedor'],
        'url_original'  => $norm['url_original'],
        'sha256'        => $sha,
        'bytes'         => $d['bytes'],
        'total_paginas' => $total,
        'paginas'       => $generadas,
        'creado'        => time(),
    ];
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
    return $meta;
}
