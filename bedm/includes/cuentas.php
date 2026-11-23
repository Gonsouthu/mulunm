<?php
// ══════════════════════════════════════════════════════════════
// CUENTAS: foto de perfil y portada, exportación del historial, baja, reactivación y purga.
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/interaccion.php';

class CuentaError extends RuntimeException {
    public string $codigo;
    public function __construct(string $codigo, string $mensaje) {
        parent::__construct($mensaje);
        $this->codigo = $codigo;
    }
}

const CUENTA_DIAS_REACTIVAR = 30;

// ══════════════════════════════════════════════════════════════
// FOTO DE PERFIL Y PORTADA
// La imagen subida NO se guarda tal cual: se decodifica y se vuelve a codificar como JPEG del tamaño
// justo. Así se descarta cualquier dato oculto en el archivo (ubicación GPS, programas incrustados, etc.).
// ══════════════════════════════════════════════════════════════
const PERFIL_IMG = [
    'avatar' => ['ancho' => 400,  'alto' => 400, 'min_ancho' => 100, 'min_alto' => 100, 'max_bytes' => 3 * 1048576],
    'banner' => ['ancho' => 1200, 'alto' => 400, 'min_ancho' => 300, 'min_alto' => 100, 'max_bytes' => 5 * 1048576],
];
const PERFIL_IMG_MAX_PIXELES = 16000000;   // 16 megapíxeles: evita imágenes gigantes que agotan la memoria

function perfiles_dir(): string {
    $dir = dirname(ingesta_dir_base()) . DIRECTORY_SEPARATOR . 'perfiles';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function perfil_img_ruta(int $uid, string $tipo): string {
    return perfiles_dir() . DIRECTORY_SEPARATOR . $uid . '_' . $tipo . '.jpg';
}

// Procesa un archivo de imagen y escribe el JPEG final en $destino. No depende de $_FILES (así se puede probar).
function perfil_imagen_procesar(string $tmp, string $tipo, string $destino): void {
    $cfg = PERFIL_IMG[$tipo] ?? null;
    if (!$cfg) throw new CuentaError('tipo', 'Tipo de imagen no válido.');
    if (!trabajo_gd_disponible()) throw new CuentaError('sin_gd', 'El servidor todavía no puede procesar imágenes (falta la extensión gd de PHP).');
    if (!is_file($tmp)) throw new CuentaError('subida', 'No se recibió el archivo.');
    if (filesize($tmp) > $cfg['max_bytes']) throw new CuentaError('tamano', 'La imagen pesa demasiado (máximo ' . round($cfg['max_bytes'] / 1048576) . ' MB).');

    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new CuentaError('formato', 'Solo se aceptan imágenes JPG, PNG o WebP.');
    }
    $mime = class_exists('finfo') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
    if ($mime !== '' && !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new CuentaError('formato', 'Solo se aceptan imágenes JPG, PNG o WebP.');
    }
    [$w, $h] = $info;
    if ($w * $h > PERFIL_IMG_MAX_PIXELES) throw new CuentaError('dimensiones', 'La imagen es demasiado grande (más de 16 megapíxeles). Achicala y probá de nuevo.');
    if ($w < $cfg['min_ancho'] || $h < $cfg['min_alto']) throw new CuentaError('pequena', 'La imagen es demasiado chica (mínimo ' . $cfg['min_ancho'] . '×' . $cfg['min_alto'] . ' píxeles).');

    @ini_set('memory_limit', '256M');
    $im = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        default        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
    };
    if (!$im) throw new CuentaError('imagen_danada', 'No pudimos leer esa imagen. Probá con otra.');

    // Las fotos de celular traen la orientación en un dato aparte (EXIF): se corrige para que no queden de costado
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro !== 0 && ($rot = imagerotate($im, $giro, 0))) { imagedestroy($im); $im = $rot; }
    }

    // Recorte centrado a la proporción pedida y reducción al tamaño final
    $sw = imagesx($im); $sh = imagesy($im);
    $proporcion = $cfg['ancho'] / $cfg['alto'];
    if ($sw / $sh > $proporcion) { $cw = (int) round($sh * $proporcion); $ch = $sh; $cx = intdiv($sw - $cw, 2); $cy = 0; }
    else                         { $cw = $sw; $ch = (int) round($sw / $proporcion); $cx = 0; $cy = intdiv($sh - $ch, 2); }
    $out = imagecreatetruecolor($cfg['ancho'], $cfg['alto']);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // las transparencias (PNG) quedan sobre fondo blanco
    imagecopyresampled($out, $im, 0, 0, $cx, $cy, $cfg['ancho'], $cfg['alto'], $cw, $ch);

    $provisorio = $destino . '.tmp';
    $ok = imagejpeg($out, $provisorio, 86);
    imagedestroy($im); imagedestroy($out);
    if (!$ok || !@rename($provisorio, $destino)) { @unlink($provisorio); throw new CuentaError('guardado', 'No se pudo guardar la imagen.'); }
}

function perfil_imagen_guardar(int $uid, string $tipo, array $archivo): string {
    if (!isset(PERFIL_IMG[$tipo])) throw new CuentaError('tipo', 'Tipo de imagen no válido.');
    $err = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new CuentaError('subida', in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'La imagen pesa demasiado (máximo ' . round(PERFIL_IMG[$tipo]['max_bytes'] / 1048576) . ' MB).'
            : 'No se pudo subir la imagen. Probá de nuevo.');
    }
    if (!is_uploaded_file((string) $archivo['tmp_name'])) throw new CuentaError('subida', 'No se pudo subir la imagen. Probá de nuevo.');
    perfil_imagen_procesar($archivo['tmp_name'], $tipo, perfil_img_ruta($uid, $tipo));
    $url = 'api/perfil_img.php?u=' . $uid . '&t=' . $tipo . '&v=' . time();    // v= evita que el navegador muestre la imagen vieja
    db()->prepare('UPDATE usuarios SET ' . ($tipo === 'avatar' ? 'avatar_url' : 'banner_url') . ' = ? WHERE id = ?')->execute([$url, $uid]);
    return $url;
}

function perfil_imagen_quitar(int $uid, string $tipo): void {
    if (!isset(PERFIL_IMG[$tipo])) throw new CuentaError('tipo', 'Tipo de imagen no válido.');
    @unlink(perfil_img_ruta($uid, $tipo));
    db()->prepare('UPDATE usuarios SET ' . ($tipo === 'avatar' ? 'avatar_url' : 'banner_url') . ' = NULL WHERE id = ?')->execute([$uid]);
}

// ══════════════════════════════════════════════════════════════
// DATOS DEL PERFIL
// ══════════════════════════════════════════════════════════════
function perfil_validar_bio($b): ?string {
    $b = trabajo_limpiar_texto((string) $b);
    if (mb_strlen($b) > 280) throw new CuentaError('bio', 'La presentación puede tener hasta 280 caracteres.');
    return $b === '' ? null : $b;
}
function perfil_validar_legajo($l): ?string {
    $l = trim((string) $l);
    if ($l === '') return null;
    if (!preg_match('/^[A-Za-z0-9-]{3,12}$/', $l)) throw new CuentaError('legajo', 'El legajo tiene de 3 a 12 caracteres: letras, números o guiones.');
    return $l;
}

// ══════════════════════════════════════════════════════════════
// EXPORTAR EL HISTORIAL (ZIP)
// ══════════════════════════════════════════════════════════════
// Nombre seguro para carpetas y archivos: solo letras, números y guiones, sin tildes
function slug_archivo(string $s, int $max = 50): string {
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','à'=>'a','è'=>'e','ç'=>'c']);
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s === '' ? 'trabajo' : substr($s, 0, $max);
}

function historial_dir_tmp(): string {
    $dir = dirname(ingesta_dir_base()) . DIRECTORY_SEPARATOR . 'tmp_export';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

// Arma el ZIP con todo el historial de la persona y devuelve [ruta_del_zip, nombre_sugerido].
function historial_exportar(array $yo): array {
    if (!class_exists('ZipArchive')) throw new CuentaError('sin_zip', 'El servidor todavía no puede crear archivos ZIP (falta la extensión zip de PHP).');
    $uid = (int) $yo['id'];
    $pdo = db();

    $cu = $pdo->prepare('SELECT u.nombre, u.apellido, u.nickname, u.email, u.legajo, u.bio, u.creado_en, u.terminos_aceptados_en, c.nombre AS carrera
                           FROM usuarios u JOIN carreras c ON c.id = u.carrera_id WHERE u.id = ?');
    $cu->execute([$uid]);
    $cuenta = $cu->fetch();

    // Trabajos en los que figura como autor/a (incluidos los ocultos)
    $st = $pdo->prepare(
        "SELECT t.*, m.nombre AS materia, m.es_electiva
           FROM trabajos t JOIN materias m ON m.id = t.materia_id
          WHERE t.eliminado_en IS NULL
            AND EXISTS (SELECT 1 FROM trabajo_autores a WHERE a.trabajo_id = t.id AND a.usuario_id = ? AND a.estado = 'aceptado')
          ORDER BY t.ciclo_lectivo DESC, t.creado_en DESC"
    );
    $st->execute([$uid]);
    $trabajos = $st->fetchAll();

    $nombreZip = 'BEDM_historial_' . preg_replace('/[^a-z0-9._]/', '', strtolower($yo['nickname'])) . '_' . date('Ymd') . '.zip';
    $ruta = historial_dir_tmp() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new CuentaError('zip', 'No se pudo preparar el archivo ZIP.');

    $export = [];
    foreach ($trabajos as $t) {
        $id = (int) $t['id'];
        $autores = trabajo_autores($id, true);
        $aceptados = array_values(array_filter($autores, fn($a) => $a['estado'] === 'aceptado'));
        $esDueno = (int) $t['usuario_id'] === $uid;
        $carpeta = 'trabajos/' . $t['ciclo_lectivo'] . '_' . slug_archivo($t['materia'], 30) . '_' . slug_archivo($t['titulo'], 40) . '_' . $id;

        $paginas = [];
        $pg = $pdo->prepare('SELECT numero FROM trabajo_paginas WHERE trabajo_id = ? ORDER BY numero');
        $pg->execute([$id]);
        foreach ($pg->fetchAll(PDO::FETCH_COLUMN) as $n) {
            $archivo = trabajo_dir($id) . DIRECTORY_SEPARATOR . ((int) $n) . '.jpg';
            if (!is_file($archivo)) continue;
            $dentro = $carpeta . '/pagina_' . sprintf('%02d', (int) $n) . '.jpg';
            $zip->addFile($archivo, $dentro);
            $zip->setCompressionName($dentro, ZipArchive::CM_STORE);   // los JPG ya están comprimidos
            $paginas[] = $dentro;
        }
        $coms = [];
        foreach (trabajo_comentarios($id, 500) as $c) {
            $coms[] = ['autor' => nombre_apellido_nombre($c) . ' (@' . $c['nickname'] . ')', 'fecha' => $c['creado_en'], 'texto' => $c['texto']];
        }
        // El link a la nube lo ve quien publicó; las demás personas autoras solo si la licencia es Creative Commons
        $verLink = $t['origen_url'] !== '' && ($esDueno || str_starts_with($t['licencia'], 'cc_'));
        $export[] = [
            'id'                => $id,
            'nombre_completo'   => trabajo_nombre_canonico(['materia' => $t['materia'], 'electiva_nombre' => $t['electiva_nombre'], 'ciclo_lectivo' => $t['ciclo_lectivo'], 'titulo' => $t['titulo']], $aceptados),
            'titulo'            => $t['titulo'],
            'descripcion'       => $t['descripcion'],
            'materia'           => trabajo_materia_texto($t['materia'], $t['electiva_nombre']),
            'ciclo_lectivo'     => (int) $t['ciclo_lectivo'],
            'licencia'          => TRABAJO_LICENCIAS[$t['licencia']] ?? $t['licencia'],
            'estado'            => $t['estado'] === 'publicado' ? 'publicado' : 'oculto',
            'publicado_el'      => $t['creado_en'],
            'huella_sha256'     => $t['sha256'],
            'link_archivo_original' => $verLink ? $t['origen_url'] : null,
            'nota_final'        => ($esDueno && $t['nota_final'] !== null) ? (float) $t['nota_final'] : null,   // la nota es de quien publicó
            'me_gusta'          => (int) $t['likes_count'],
            'autores'           => array_map(fn($a) => ['nombre' => autor_texto($a), 'nickname' => $a['nickname'], 'estado' => $a['estado']], $autores),
            'comentarios'       => $coms,
            'imagenes'          => $paginas,
        ];
    }

    $datosCuenta = [
        'nombre' => $cuenta['nombre'], 'apellido' => $cuenta['apellido'], 'nickname' => $cuenta['nickname'], 'email' => $cuenta['email'],
        'legajo' => $cuenta['legajo'], 'carrera' => $cuenta['carrera'], 'presentacion' => $cuenta['bio'],
        'cuenta_creada_el' => $cuenta['creado_en'], 'terminos_aceptados_el' => $cuenta['terminos_aceptados_en'],
    ];
    $zip->addFromString('LEEME.txt', historial_leeme($cuenta, count($export)));
    $zip->addFromString('cuenta.json', json_encode($datosCuenta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->addFromString('historial.json', json_encode(['exportado_el' => date('c'), 'trabajos' => $export], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->addFromString('historial.html', historial_html($cuenta, $export));
    if (!$zip->close()) { @unlink($ruta); throw new CuentaError('zip', 'No se pudo completar el archivo ZIP.'); }
    return [$ruta, $nombreZip];
}

function historial_leeme(array $cuenta, int $n): string {
    return "BEDM · Repositorio Curricular Situado (UNM)\n"
         . "Copia de tu historial · {$cuenta['nombre']} {$cuenta['apellido']} (@{$cuenta['nickname']}) · " . date('d/m/Y') . "\n\n"
         . "Este archivo contiene $n trabajo(s) en los que figurás como autor/a.\n\n"
         . "· historial.html   → abrilo con el navegador (doble clic): es tu historial para leer, con las imágenes.\n"
         . "· historial.json   → los mismos datos en formato de computadora (por si querés procesarlos).\n"
         . "· cuenta.json      → los datos de tu cuenta.\n"
         . "· carpeta trabajos → las imágenes de cada trabajo (con el sello de autoría).\n\n"
         . "IMPORTANTE: BEDM no guarda los archivos originales de los trabajos, solo imágenes de previsualización.\n"
         . "Los archivos originales siguen en tu nube (el link figura en cada trabajo). Guardá una copia propia de esos archivos.\n"
         . "La «huella SHA-256» sirve para comprobar que un archivo es idéntico al que se publicó.\n";
}

function historial_html(array $cuenta, array $trabajos): string {
    $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
          . '<title>Mi historial en BEDM</title><style>'
          . 'body{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:820px;margin:24px auto;padding:0 16px;color:#1c2733;line-height:1.5}'
          . 'h1{color:#004E9F;font-size:1.5rem}h2{color:#004E9F;border-bottom:2px solid #004E9F;padding-bottom:4px;margin-top:32px}'
          . '.t{border:1px solid #d9e0e8;border-radius:12px;padding:14px 16px;margin:16px 0}.t h3{margin:0 0 6px;font-size:1rem}'
          . '.m{font-size:.82rem;color:#475569}.imgs{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0}.imgs img{width:150px;border:1px solid #d9e0e8;border-radius:6px}'
          . '.c{background:#f4f7fa;border-radius:8px;padding:8px 10px;margin:6px 0;font-size:.85rem}code{font-size:.78rem;word-break:break-all}'
          . '</style></head><body>'
          . '<h1>Mi historial en BEDM</h1><p class="m">' . $e($cuenta['nombre'] . ' ' . $cuenta['apellido']) . ' (@' . $e($cuenta['nickname']) . ') · ' . $e($cuenta['carrera']) . ' · copia del ' . $e(date('d/m/Y')) . '</p>'
          . '<p class="m">BEDM no guarda los archivos originales: los links apuntan a tu nube. Las imágenes de esta copia llevan el sello de autoría.</p>';
    if (!$trabajos) $html .= '<p>Todavía no publicaste trabajos.</p>';
    $cicloActual = null;
    foreach ($trabajos as $t) {
        if ($t['ciclo_lectivo'] !== $cicloActual) { $cicloActual = $t['ciclo_lectivo']; $html .= '<h2>Ciclo lectivo ' . $e($cicloActual) . '</h2>'; }
        $html .= '<div class="t"><h3>' . $e($t['nombre_completo']) . '</h3>'
               . '<div class="m">' . $e($t['materia']) . ' · ' . $e($t['licencia']) . ' · publicado el ' . $e(substr((string) $t['publicado_el'], 0, 10)) . ($t['estado'] === 'oculto' ? ' · <b>oculto</b>' : '') . ' · ' . (int) $t['me_gusta'] . ' me gusta</div>';
        if ($t['descripcion']) $html .= '<p>' . nl2br($e($t['descripcion'])) . '</p>';
        if ($t['nota_final'] !== null) $html .= '<p class="m">Nota final: ' . $e(rtrim(rtrim(number_format($t['nota_final'], 2, ',', ''), '0'), ',')) . '/10</p>';
        $html .= '<div class="imgs">';
        foreach ($t['imagenes'] as $img) $html .= '<a href="' . $e($img) . '"><img src="' . $e($img) . '" alt="Página"></a>';
        $html .= '</div>';
        if ($t['link_archivo_original']) $html .= '<p class="m">Archivo original: <a href="' . $e($t['link_archivo_original']) . '">' . $e($t['link_archivo_original']) . '</a></p>';
        $html .= '<p class="m">Huella SHA-256: <code>' . $e($t['huella_sha256']) . '</code></p>';
        if ($t['comentarios']) {
            $html .= '<div class="m"><b>Comentarios recibidos (' . count($t['comentarios']) . ')</b></div>';
            foreach ($t['comentarios'] as $c) $html .= '<div class="c"><b>' . $e($c['autor']) . '</b> · ' . $e(substr($c['fecha'], 0, 16)) . '<br>' . nl2br($e($c['texto'])) . '</div>';
        }
        $html .= '</div>';
    }
    return $html . '</body></html>';
}

// ══════════════════════════════════════════════════════════════
// BAJA, REACTIVACIÓN Y PURGA
// ══════════════════════════════════════════════════════════════
// $modo: 'ocultar' (los trabajos propios quedan ocultos) o 'eliminar' (se borran las imágenes).
// En ambos casos la persona deja de figurar en trabajos ajenos y se borran sus comentarios y «me gusta».
function cuenta_dar_de_baja(array $yo, string $modo): void {
    if (!in_array($modo, ['ocultar', 'eliminar'], true)) throw new CuentaError('modo', 'Elegí qué hacer con tus trabajos.');
    $uid = (int) $yo['id'];
    $pdo = db();

    $propios = $pdo->prepare('SELECT id FROM trabajos WHERE usuario_id = ? AND eliminado_en IS NULL');
    $propios->execute([$uid]);
    $ids = $propios->fetchAll(PDO::FETCH_COLUMN);
    if ($modo === 'eliminar') {
        foreach ($ids as $tid) { $t = trabajo_cargar((int) $tid); if ($t) trabajo_eliminar($t, $uid); }
    } elseif ($ids) {
        $pdo->prepare("UPDATE trabajos SET estado = 'oculto' WHERE usuario_id = ? AND eliminado_en IS NULL")->execute([$uid]);
    }

    $pdo->beginTransaction();
    // Deja de figurar (o de estar invitado/a) en trabajos de otras personas
    $pdo->prepare("UPDATE trabajo_autores a JOIN trabajos t ON t.id = a.trabajo_id SET a.estado = 'rechazado' WHERE a.usuario_id = ? AND t.usuario_id <> ?")->execute([$uid, $uid]);
    // Sus «me gusta» y comentarios se borran (y los totales se recalculan)
    $liked = $pdo->prepare('SELECT DISTINCT trabajo_id FROM votos WHERE usuario_id = ?');
    $liked->execute([$uid]);
    $afectados = $liked->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare('DELETE FROM comentarios WHERE usuario_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM votos WHERE usuario_id = ?')->execute([$uid]);
    foreach ($afectados as $tid) {
        $pdo->prepare('UPDATE trabajos SET likes_count = (SELECT COUNT(*) FROM votos WHERE trabajo_id = ? AND valor = 1) WHERE id = ?')->execute([$tid, $tid]);
    }
    // La cuenta queda inactiva y todas sus sesiones dejan de valer
    $pdo->prepare('UPDATE usuarios SET activo = 0, baja_en = NOW(), sesion_version = IF(sesion_version >= 65000, 1, sesion_version + 1) WHERE id = ?')->execute([$uid]);
    $pdo->prepare('UPDATE tokens_usuario SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL')->execute([$uid]);
    $pdo->commit();
    evento_registrar('baja_cuenta', $uid, 'usuario', $uid, $modo . ' · ' . count($ids) . ' trabajo(s)');
}

// La cuenta se puede reactivar dentro del plazo. Devuelve true si se reactivó.
function cuenta_reactivar(int $uid): bool {
    $st = db()->prepare('UPDATE usuarios SET activo = 1, baja_en = NULL WHERE id = ? AND activo = 0 AND baja_en > (NOW() - INTERVAL ' . CUENTA_DIAS_REACTIVAR . ' DAY)');
    $st->execute([$uid]);
    if ($st->rowCount() === 1) { evento_registrar('reactivar_cuenta', $uid, 'usuario', $uid); return true; }
    return false;
}

// Cuentas dadas de baja hace más de 30 días y todavía sin anonimizar
function cuentas_bajas_pendientes(): array {
    $st = db()->query("SELECT id, nickname, baja_en, DATEDIFF(NOW(), baja_en) AS dias
                         FROM usuarios WHERE activo = 0 AND baja_en IS NOT NULL AND nickname NOT LIKE 'eliminado.%' ORDER BY baja_en");
    return $st->fetchAll();
}

// Anonimiza las cuentas cuyo plazo de reactivación venció. Devuelve cuántas procesó.
function cuenta_purgar_vencidas(): int {
    $pdo = db();
    $st = $pdo->query("SELECT id FROM usuarios WHERE activo = 0 AND baja_en IS NOT NULL AND baja_en <= (NOW() - INTERVAL " . CUENTA_DIAS_REACTIVAR . " DAY) AND nickname NOT LIKE 'eliminado.%'");
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $uid = (int) $uid;
        // Trabajos que hubieran quedado ocultos: se eliminan (imágenes incluidas)
        $q = $pdo->prepare('SELECT id FROM trabajos WHERE usuario_id = ? AND eliminado_en IS NULL');
        $q->execute([$uid]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $tid) { $t = trabajo_cargar((int) $tid); if ($t) trabajo_eliminar($t, $uid); }
        @unlink(perfil_img_ruta($uid, 'avatar'));
        @unlink(perfil_img_ruta($uid, 'banner'));
        $email = $pdo->prepare('SELECT email FROM usuarios WHERE id = ?');
        $email->execute([$uid]);
        $emailViejo = (string) $email->fetchColumn();

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE usuarios SET nombre = 'Cuenta', apellido = 'eliminada', nickname = ?, email = ?, legajo = NULL, bio = NULL,
                              avatar_url = NULL, banner_url = NULL, email_verificado = 0, password_hash = ? WHERE id = ?")
            ->execute(['eliminado.' . $uid, 'eliminado.' . $uid . '@eliminado.invalid', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $uid]);
        $pdo->prepare('DELETE FROM nickname_historial WHERE usuario_id = ?')->execute([$uid]);
        $pdo->prepare('UPDATE auditoria SET ip = NULL WHERE usuario_id = ?')->execute([$uid]);
        $pdo->prepare('DELETE FROM intentos_login WHERE email = ?')->execute([$emailViejo]);
        $pdo->prepare('DELETE FROM tokens_usuario WHERE usuario_id = ?')->execute([$uid]);
        $pdo->commit();
        evento_registrar('purga_cuenta', null, 'usuario', $uid);
        $n++;
    }
    return $n;
}

// ══════════════════════════════════════════════════════════════
// COPIA DE SEGURIDAD DE LAS IMÁGENES (para quien administra el sitio)
// ══════════════════════════════════════════════════════════════
function almacenamiento_resumen(): array {
    $tam = function (string $dir) use (&$tam): array {
        $bytes = 0; $archivos = 0;
        if (!is_dir($dir)) return [0, 0];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile()) { $bytes += $f->getSize(); $archivos++; } }
        return [$bytes, $archivos];
    };
    [$bt, $at] = $tam(trabajos_dir());
    [$bp, $ap] = $tam(perfiles_dir());
    return ['trabajos_bytes' => $bt, 'trabajos_archivos' => $at, 'perfiles_bytes' => $bp, 'perfiles_archivos' => $ap];
}

function copia_imagenes_zip(): array {
    if (!class_exists('ZipArchive')) throw new CuentaError('sin_zip', 'Falta la extensión zip de PHP.');
    $ruta = historial_dir_tmp() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new CuentaError('zip', 'No se pudo preparar el ZIP.');
    foreach (['trabajos' => trabajos_dir(), 'perfiles' => perfiles_dir()] as $nombre => $dir) {
        if (!is_dir($dir)) continue;
        $base = realpath($dir);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (!$f->isFile()) continue;
            $dentro = $nombre . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
            $zip->addFile($f->getPathname(), $dentro);
            $zip->setCompressionName($dentro, ZipArchive::CM_STORE);
        }
    }
    $zip->addFromString('LEEME.txt', "Copia de seguridad de las imágenes de BEDM · " . date('d/m/Y H:i') . "\n\nCarpeta trabajos/<id>/: páginas de cada trabajo publicado (con sello de autoría).\nCarpeta perfiles/: fotos de perfil y portadas.\nLa base de datos NO está incluida: exportala aparte desde phpMyAdmin.\nPara restaurar: copiá estas carpetas dentro de storage/ del sitio.\n");
    if (!$zip->close()) { @unlink($ruta); throw new CuentaError('zip', 'No se pudo completar el ZIP.'); }
    return [$ruta, 'BEDM_copia_imagenes_' . date('Ymd_His') . '.zip'];
}

// Borra los ZIP temporales viejos (más de 1 hora)
function historial_limpiar_tmp(): void {
    $dir = historial_dir_tmp();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $f) if (filemtime($f) < time() - 3600) @unlink($f);
}
