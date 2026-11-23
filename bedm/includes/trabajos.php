<?php
// ══════════════════════════════════════════════════════════════
// TRABAJOS: publicar, ver, listar, editar y eliminar.
//
// Recordatorio del proyecto: el archivo original NUNCA se guarda. Cada trabajo conserva solo
// el link que indicó su autor/a, imágenes de previsualización (con un sello de autoría al pie)
// y la huella digital SHA-256 del archivo.
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/ingesta.php';

class TrabajoError extends RuntimeException {
    public string $codigo;
    public function __construct(string $codigo, string $mensaje) {
        parent::__construct($mensaje);
        $this->codigo = $codigo;
    }
}

const TRABAJO_LICENCIAS = [
    'todos_los_derechos' => 'Todos los derechos reservados',
    'cc_by'              => 'Creative Commons BY (atribución)',
    'cc_by_nc'           => 'Creative Commons BY-NC (atribución, uso no comercial)',
    'cc_by_nc_nd'        => 'Creative Commons BY-NC-ND (atribución, no comercial, sin obras derivadas)',
];
const TRABAJO_MAX_AUTORES = 8;
const TRABAJO_BANDA_PX = 30;   // alto de la franja con el sello de autoría

// ── Carpetas (fuera del alcance de la web: las imágenes se sirven con api/trabajo_img.php) ──
function trabajos_dir(): string {
    $dir = dirname(ingesta_dir_base()) . DIRECTORY_SEPARATOR . 'trabajos';   // ingesta_dir_base() ya protege storage/ con .htaccess
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function trabajo_dir(int $id): string {
    return trabajos_dir() . DIRECTORY_SEPARATOR . $id;
}

// ── Nombres ──
function nombre_apellido_nombre(array $u): string {
    return trim($u['apellido']) . ', ' . trim($u['nombre']);
}

function trabajo_materia_texto(string $materia, ?string $electiva): string {
    return ($electiva !== null && $electiva !== '') ? "$materia: $electiva" : $materia;
}

// Autores ACEPTADOS, en orden (el que publica primero). Los que aún no aceptaron no figuran.
function trabajo_autores(int $trabajoId, bool $todos = false): array {
    $st = db()->prepare(
        "SELECT a.id AS autor_id, a.usuario_id, a.nombre_externo, a.estado, a.orden,
                u.nombre, u.apellido, u.nickname
           FROM trabajo_autores a LEFT JOIN usuarios u ON u.id = a.usuario_id
          WHERE a.trabajo_id = ?" . ($todos ? '' : " AND a.estado = 'aceptado'") . "
          ORDER BY a.orden, a.id"
    );
    $st->execute([$trabajoId]);
    return $st->fetchAll();
}

function autor_texto(array $a): string {
    return $a['usuario_id'] ? nombre_apellido_nombre($a) : trim((string) $a['nombre_externo']);
}

function autores_texto(array $autores): string {
    $nombres = array_map('autor_texto', $autores);
    if (count($nombres) <= 1) return $nombres[0] ?? '';
    if (count($nombres) === 2) return $nombres[0] . ' y ' . $nombres[1];
    return implode('; ', $nombres);
}

// «Apellido, Nombre — Materia — 2025 — Título»: la autoría queda en el nombre del trabajo
function trabajo_nombre_canonico(array $t, array $autoresAceptados): string {
    return autores_texto($autoresAceptados) . ' — ' . trabajo_materia_texto($t['materia'], $t['electiva_nombre'] ?? null)
         . ' — ' . $t['ciclo_lectivo'] . ' — ' . $t['titulo'];
}

// ── Carga y permisos ──
function trabajo_cargar(int $id): ?array {
    $st = db()->prepare(
        'SELECT t.*, m.nombre AS materia, m.anio_carrera, m.cuatrimestre, m.es_electiva
           FROM trabajos t JOIN materias m ON m.id = t.materia_id
          WHERE t.id = ? AND t.eliminado_en IS NULL'
    );
    $st->execute([$id]);
    $t = $st->fetch();
    return $t ?: null;
}

// 'dueno' (quien lo publicó) · 'autor' (coautor/a aceptado/a) · 'pendiente' (invitado/a, aún sin responder) · 'visitante'
function trabajo_rol(array $t, array $autoresTodos, int $uid): string {
    if ((int) $t['usuario_id'] === $uid) return 'dueno';
    foreach ($autoresTodos as $a) {
        if ((int) $a['usuario_id'] === $uid) return $a['estado'] === 'aceptado' ? 'autor' : ($a['estado'] === 'pendiente' ? 'pendiente' : 'visitante');
    }
    return 'visitante';
}

function trabajo_visible(array $t, string $rol): bool {
    if ($t['estado'] === 'publicado') return true;
    return in_array($rol, ['dueno', 'autor', 'pendiente'], true);   // oculto: solo quienes participan
}

// ══════════════════════════════════════════════════════════════
// IMÁGENES: sello de autoría + miniatura de portada
// ══════════════════════════════════════════════════════════════
function trabajo_gd_disponible(): bool {
    return extension_loaded('gd') && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
}

// Agrega una franja al pie con el texto del sello. No tapa nada de la imagen: la franja es adicional.
function trabajo_sellar_imagen(string $origen, string $destino, string $texto): void {
    $im = @imagecreatefromjpeg($origen);
    if (!$im) throw new TrabajoError('imagen', 'No se pudo procesar una de las imágenes de la previsualización.');
    $w = imagesx($im); $h = imagesy($im);
    $out = imagecreatetruecolor($w, $h + TRABAJO_BANDA_PX);
    imagefill($out, 0, 0, imagecolorallocate($out, 241, 243, 245));
    imagecopy($out, $im, 0, 0, 0, 0, $w, $h);
    imageline($out, 0, $h, $w, $h, imagecolorallocate($out, 206, 212, 218));
    $t = mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');             // la fuente incorporada de GD es Latin-1 (alcanza para español)
    $max = max(10, intdiv($w - 20, 9));                                    // 9 px por carácter
    if (strlen($t) > $max) $t = substr($t, 0, $max - 3) . '...';
    imagestring($out, 5, 10, $h + 7, $t, imagecolorallocate($out, 73, 80, 87));
    imagejpeg($out, $destino, 82);
    imagedestroy($im); imagedestroy($out);
}

// Miniatura de portada: 480 px de ancho, recortada arriba (donde suele estar el título del trabajo)
function trabajo_hacer_portada(string $origen, string $destino): void {
    $im = @imagecreatefromjpeg($origen);
    if (!$im) throw new TrabajoError('imagen', 'No se pudo procesar la portada.');
    $w = imagesx($im); $h = imagesy($im);
    $nw = 480; $nh = (int) round($h * $nw / $w);
    $esc = imagescale($im, $nw, $nh, IMG_BICUBIC);
    $alto = min($nh, 300);
    $out = imagecreatetruecolor($nw, $alto);
    imagecopy($out, $esc, 0, 0, 0, 0, $nw, $alto);
    imagejpeg($out, $destino, 80);
    imagedestroy($im); imagedestroy($esc); imagedestroy($out);
}

// Pasa las imágenes de la carpeta temporal a la definitiva, con sello y portada.
function trabajo_procesar_imagenes(string $origen, array $paginas, string $destino, string $sello): void {
    if (!is_dir($destino) && !@mkdir($destino, 0775, true)) throw new TrabajoError('imagen', 'No se pudo preparar la carpeta del trabajo.');
    $gd = trabajo_gd_disponible();
    foreach ($paginas as $n) {
        $n = (int) $n;
        $src = $origen . DIRECTORY_SEPARATOR . $n . '.jpg';
        $dst = $destino . DIRECTORY_SEPARATOR . $n . '.jpg';
        if ($gd) trabajo_sellar_imagen($src, $dst, $sello);
        elseif (!@copy($src, $dst)) throw new TrabajoError('imagen', 'No se pudo guardar una de las imágenes.');
    }
    $primera = $destino . DIRECTORY_SEPARATOR . ((int) $paginas[0]) . '.jpg';
    $portada = $destino . DIRECTORY_SEPARATOR . 'portada.jpg';
    if ($gd) trabajo_hacer_portada($primera, $portada); else @copy($primera, $portada);
}

// ══════════════════════════════════════════════════════════════
// VALIDACIÓN
// ══════════════════════════════════════════════════════════════
function trabajo_limpiar_texto(string $s, bool $multilinea = false): string {
    $s = str_replace("\r\n", "\n", $s);
    $s = preg_replace($multilinea ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $s) ?? '';   // quita caracteres de control
    if (!$multilinea) $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    else $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';
    return trim($s);
}

function trabajo_validar_nota($nota): ?string {
    if ($nota === null || trim((string) $nota) === '') return null;
    $n = str_replace(',', '.', trim((string) $nota));
    if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $n) || (float) $n < 0 || (float) $n > 10) {
        throw new TrabajoError('nota', 'La nota tiene que ser un número entre 0 y 10 (por ejemplo 8 o 8,5).');
    }
    return number_format((float) $n, 2, '.', '');
}

function trabajo_validar_titulo($t): string {
    $t = trabajo_limpiar_texto((string) $t);
    if (mb_strlen($t) < 3 || mb_strlen($t) > 100) throw new TrabajoError('titulo', 'El nombre del trabajo debe tener entre 3 y 100 caracteres (por ejemplo «TP1 - Manual de marca»).');
    return $t;
}

function trabajo_validar_descripcion($d): ?string {
    $d = trabajo_limpiar_texto((string) $d, true);
    if (mb_strlen($d) > 2000) throw new TrabajoError('descripcion', 'La descripción puede tener hasta 2000 caracteres.');
    return $d === '' ? null : $d;
}

function trabajo_validar_licencia($l): string {
    if (!is_string($l) || !isset(TRABAJO_LICENCIAS[$l])) throw new TrabajoError('licencia', 'Elegí una licencia de la lista.');
    return $l;
}

function trabajo_validar_externo($n): string {
    $n = trabajo_limpiar_texto((string) $n);
    if (!preg_match('/^\p{L}[\p{L}\p{M} .\'’-]{1,79}$/u', $n)) {
        throw new TrabajoError('coautor', 'El nombre de un coautor/a sin cuenta solo puede tener letras, espacios, puntos y guiones (2 a 80 caracteres).');
    }
    return $n;
}

// Convierte la lista que manda el formulario en filas para trabajo_autores. $propioId = quien publica.
function trabajo_resolver_coautores($lista, int $propioId, int $yaExistentes = 1): array {
    if ($lista === null || $lista === []) return [];
    if (!is_array($lista)) throw new TrabajoError('coautor', 'La lista de coautores no es válida.');
    if ($yaExistentes + count($lista) > TRABAJO_MAX_AUTORES) throw new TrabajoError('coautor', 'Un trabajo puede tener hasta ' . TRABAJO_MAX_AUTORES . ' autores.');
    $vistos = []; $filas = [];
    foreach ($lista as $c) {
        if (!is_array($c)) throw new TrabajoError('coautor', 'La lista de coautores no es válida.');
        if (isset($c['nickname']) && is_string($c['nickname']) && $c['nickname'] !== '') {
            $nick = ltrim(strtolower(trim($c['nickname'])), '@');
            $st = db()->prepare('SELECT id FROM usuarios WHERE nickname = ? AND activo = 1');
            $st->execute([$nick]);
            $id = $st->fetchColumn();
            if ($id === false) throw new TrabajoError('coautor', "No encontramos a @$nick en BEDM.");
            $id = (int) $id;
            if ($id === $propioId) throw new TrabajoError('coautor', 'Vos ya figurás como autor/a.');
            if (isset($vistos["u$id"])) continue;
            $vistos["u$id"] = true;
            $filas[] = ['usuario_id' => $id, 'nombre_externo' => null, 'estado' => 'pendiente'];
        } elseif (isset($c['nombre'])) {
            $nombre = trabajo_validar_externo($c['nombre']);
            if (isset($vistos['n' . mb_strtolower($nombre)])) continue;
            $vistos['n' . mb_strtolower($nombre)] = true;
            $filas[] = ['usuario_id' => null, 'nombre_externo' => $nombre, 'estado' => 'aceptado'];   // sin cuenta: no puede aceptar
        } else {
            throw new TrabajoError('coautor', 'La lista de coautores no es válida.');
        }
    }
    return $filas;
}

// ══════════════════════════════════════════════════════════════
// PUBLICAR
// ══════════════════════════════════════════════════════════════
// $yo = fila de usuario_actual(). $d = datos del formulario. Devuelve el id del trabajo creado.
function trabajo_publicar(array $yo, array $d): int {
    $pdo = db();
    $uid = (int) $yo['id'];

    if (empty($d['declaracion'])) throw new TrabajoError('declaracion', 'Tenés que declarar que sos autor/a del trabajo (o que tenés el permiso de tus coautores).');
    if (empty($yo['email_verificado'])) throw new TrabajoError('no_verificado', 'Confirmá tu correo electrónico antes de publicar (te enviamos un email al registrarte).');
    if (eventos_recientes('publicar', 1440, $uid) >= (int) cfg_valor('publicar_max_por_dia', 10)) {
        throw new TrabajoError('limite', 'Publicaste muchos trabajos hoy. Probá de nuevo mañana.');
    }

    // Previsualización generada en el paso anterior (api/previsualizar.php)
    $token = (string) ($d['token'] ?? '');
    $meta = ingesta_leer_meta($token);
    if (!$meta || (int) $meta['usuario_id'] !== $uid) {
        throw new TrabajoError('preview', 'La previsualización venció. Pegá el link de nuevo para generarla otra vez.');
    }
    $dirTmp = ingesta_dir_token($token);
    foreach ($meta['paginas'] as $n) {
        if (!is_file($dirTmp . DIRECTORY_SEPARATOR . ((int) $n) . '.jpg')) throw new TrabajoError('preview', 'La previsualización venció. Pegá el link de nuevo.');
    }

    // Materia y ciclo (mismas reglas que el disparador de la base de datos)
    $st = $pdo->prepare(
        'SELECT m.id, m.nombre, m.anio_carrera, m.es_electiva, c.anio_inicio
           FROM materias m JOIN usuarios u ON u.carrera_id = m.carrera_id JOIN carreras c ON c.id = m.carrera_id
          WHERE m.id = ? AND u.id = ?'
    );
    $st->execute([(int) ($d['materia_id'] ?? 0), $uid]);
    $mat = $st->fetch();
    if (!$mat) throw new TrabajoError('materia', 'Elegí una materia de la lista.');
    $ciclo = (int) ($d['ciclo'] ?? 0);
    $min = (int) $mat['anio_inicio'] + (int) $mat['anio_carrera'] - 1;
    if ($ciclo < $min || $ciclo > (int) date('Y')) throw new TrabajoError('ciclo', "El ciclo lectivo de esta materia va de $min a " . date('Y') . '.');

    $electiva = null;
    if ($mat['es_electiva']) {
        $electiva = trabajo_limpiar_texto((string) ($d['electiva'] ?? ''));
        if (mb_strlen($electiva) < 3 || mb_strlen($electiva) > 80) throw new TrabajoError('electiva', 'Indicá el nombre de la electiva que cursaste (3 a 80 caracteres).');
    }

    $titulo = trabajo_validar_titulo($d['titulo'] ?? '');
    $descripcion = trabajo_validar_descripcion($d['descripcion'] ?? '');
    $licencia = trabajo_validar_licencia($d['licencia'] ?? '');
    $nota = trabajo_validar_nota($d['nota'] ?? null);
    $coautores = trabajo_resolver_coautores($d['coautores'] ?? [], $uid);

    // Mismo archivo ya publicado: la huella digital es única
    $dup = $pdo->prepare('SELECT usuario_id FROM trabajos WHERE sha256 = ?');
    $dup->execute([$meta['sha256']]);
    if (($dueno = $dup->fetchColumn()) !== false) {
        evento_registrar('duplicado_bloqueado', $uid, 'trabajo', null, 'sha256 ' . substr($meta['sha256'], 0, 16));
        throw new TrabajoError(((int) $dueno === $uid) ? 'duplicado_propio' : 'duplicado_ajeno',
            ((int) $dueno === $uid)
                ? 'Ya publicaste este mismo archivo en BEDM.'
                : 'Este mismo archivo ya fue publicado en BEDM. Si es un trabajo grupal, pedile a quien lo publicó que te agregue como coautor/a. Si creés que es un error o que copiaron tu trabajo, avisá a los administradores.');
    }

    // ── Todo OK: se guarda (si algo falla a mitad de camino no queda nada) ──
    // El separador es «|» porque el «·» no se dibuja bien con la fuente incorporada de GD
    $sello = 'Publicado por ' . nombre_apellido_nombre($yo) . ' | ' . trabajo_materia_texto($mat['nombre'], $electiva) . ' | ' . $ciclo;
    $id = 0;
    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare(
            "INSERT INTO trabajos (usuario_id, materia_id, ciclo_lectivo, electiva_nombre, titulo, descripcion, origen_url, origen_tipo,
                                   proveedor, estado_proceso, sha256, tamano_bytes, procesado_en, licencia, nota_final, nota_publica, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pdf', ?, 'listo', ?, ?, NOW(), ?, ?, ?, 'publicado')"
        );
        $ins->execute([
            $uid, (int) $mat['id'], $ciclo, $electiva, $titulo, $descripcion, $meta['url_original'],
            $meta['proveedor'], $meta['sha256'], (int) $meta['bytes'], $licencia, $nota, empty($d['nota_publica']) ? 0 : 1,
        ]);
        $id = (int) $pdo->lastInsertId();

        $aut = $pdo->prepare('INSERT INTO trabajo_autores (trabajo_id, usuario_id, nombre_externo, orden, estado) VALUES (?, ?, ?, ?, ?)');
        $aut->execute([$id, $uid, null, 1, 'aceptado']);
        $orden = 2;
        foreach ($coautores as $c) $aut->execute([$id, $c['usuario_id'], $c['nombre_externo'], $orden++, $c['estado']]);

        trabajo_procesar_imagenes($dirTmp, $meta['paginas'], trabajo_dir($id), $sello . ' | BEDM #' . $id);

        $pg = $pdo->prepare('INSERT INTO trabajo_paginas (trabajo_id, numero, imagen_url) VALUES (?, ?, ?)');
        foreach ($meta['paginas'] as $n) $pg->execute([$id, (int) $n, 'trabajos/' . $id . '/' . (int) $n . '.jpg']);
        $pdo->prepare('UPDATE trabajos SET portada_url = ? WHERE id = ?')->execute(['api/trabajo_img.php?id=' . $id . '&n=0', $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($id) ingesta_borrar_dir(trabajo_dir($id));
        if ($e instanceof TrabajoError) throw $e;
        if ($e instanceof PDOException) {
            $m = $e->getMessage();
            if ($e->getCode() === '23000' && str_contains($m, 'uq_trabajo_sha256')) {
                throw new TrabajoError('duplicado_ajeno', 'Este mismo archivo ya fue publicado en BEDM. Si es un trabajo grupal, pedile a quien lo publicó que te agregue como coautor/a.');
            }
            if ($e->getCode() === '45000') throw new TrabajoError('ciclo', 'El ciclo lectivo no corresponde a esa materia.');
        }
        error_log('BEDM publicar: ' . $e);
        throw new TrabajoError('interno', 'No pudimos publicar el trabajo. Probá de nuevo.');
    }

    ingesta_borrar_dir($dirTmp);   // la previsualización temporal ya cumplió su función
    evento_registrar('publicar', $uid, 'trabajo', $id, mb_substr($titulo, 0, 100));
    return $id;
}

// ══════════════════════════════════════════════════════════════
// ELIMINAR (se borran las imágenes y se libera la huella; queda un registro mínimo para auditoría)
// ══════════════════════════════════════════════════════════════
function trabajo_eliminar(array $t, int $uid): void {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE trabajos SET eliminado_en = NOW(), estado = 'oculto', sha256 = NULL, portada_url = NULL, descripcion = NULL, origen_url = '' WHERE id = ?")->execute([$t['id']]);
    $pdo->prepare('DELETE FROM trabajo_paginas WHERE trabajo_id = ?')->execute([$t['id']]);
    $pdo->commit();
    ingesta_borrar_dir(trabajo_dir((int) $t['id']));
    evento_registrar('eliminar_trabajo', $uid, 'trabajo', (int) $t['id'], mb_substr($t['titulo'], 0, 80) . ' | sha ' . substr((string) $t['sha256'], 0, 16));
}

// ══════════════════════════════════════════════════════════════
// LISTADOS (tarjetas) Y BÚSQUEDA
// ══════════════════════════════════════════════════════════════
// Filtros: materia (id), ciclo (año), usuario (nickname: trabajos donde figura como autor/a),
//          anio (1-5), cuatri (1|2), categoria (taller|tecnología|sistemas), q (texto libre), limite.
// Un trabajo oculto solo aparece para quienes participan en él.
function trabajos_listar(array $f, int $uid): array {
    $sql = "SELECT t.id, t.titulo, t.estado, t.ciclo_lectivo, t.electiva_nombre, t.portada_url, t.likes_count, m.nombre AS materia
              FROM trabajos t JOIN materias m ON m.id = t.materia_id
             WHERE t.eliminado_en IS NULL
               AND (t.estado = 'publicado' OR EXISTS (SELECT 1 FROM trabajo_autores v WHERE v.trabajo_id = t.id AND v.usuario_id = ? AND v.estado = 'aceptado'))";
    $p = [$uid];
    if (!empty($f['materia']))   { $sql .= ' AND t.materia_id = ?';       $p[] = (int) $f['materia']; }
    if (!empty($f['ciclo']))     { $sql .= ' AND t.ciclo_lectivo = ?';    $p[] = (int) $f['ciclo']; }
    if (!empty($f['anio']))      { $sql .= ' AND m.anio_carrera = ?';     $p[] = (int) $f['anio']; }
    if (!empty($f['cuatri']))    { $sql .= ' AND m.cuatrimestre = ?';     $p[] = (int) $f['cuatri']; }
    if (!empty($f['categoria'])) { $sql .= ' AND m.categoria = ?';        $p[] = (string) $f['categoria']; }
    if (!empty($f['usuario'])) {
        $sql .= " AND EXISTS (SELECT 1 FROM trabajo_autores a JOIN usuarios u ON u.id = a.usuario_id
                               WHERE a.trabajo_id = t.id AND a.estado = 'aceptado' AND u.nickname = ?)";
        $p[] = ltrim(strtolower((string) $f['usuario']), '@');
    }
    // Texto libre: cada palabra tiene que aparecer en alguno de los campos (título, descripción, materia, ciclo o autores)
    foreach (trabajos_terminos_busqueda((string) ($f['q'] ?? '')) as $term) {
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $sql .= " AND (t.titulo LIKE ? OR t.descripcion LIKE ? OR m.nombre LIKE ? OR t.electiva_nombre LIKE ? OR CAST(t.ciclo_lectivo AS CHAR) LIKE ?
                       OR EXISTS (SELECT 1 FROM trabajo_autores b LEFT JOIN usuarios w ON w.id = b.usuario_id
                                   WHERE b.trabajo_id = t.id AND b.estado = 'aceptado'
                                     AND (w.nombre LIKE ? OR w.apellido LIKE ? OR w.nickname LIKE ? OR b.nombre_externo LIKE ?)))";
        array_push($p, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    $limite = max(1, min(60, (int) ($f['limite'] ?? 60)));
    $sql .= ' ORDER BY t.creado_en DESC, t.id DESC LIMIT ' . $limite;
    $st = db()->prepare($sql);
    $st->execute($p);
    $filas = $st->fetchAll();
    if (!$filas) return [];

    // Autores aceptados de todos los trabajos, en una sola consulta
    $ids = array_map(fn($r) => (int) $r['id'], $filas);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sa = db()->prepare("SELECT a.trabajo_id, a.usuario_id, a.nombre_externo, u.nombre, u.apellido
                           FROM trabajo_autores a LEFT JOIN usuarios u ON u.id = a.usuario_id
                          WHERE a.estado = 'aceptado' AND a.trabajo_id IN ($in) ORDER BY a.orden, a.id");
    $sa->execute($ids);
    $porTrabajo = [];
    foreach ($sa->fetchAll() as $a) $porTrabajo[(int) $a['trabajo_id']][] = $a;

    return array_map(fn($r) => [
        'id'      => (int) $r['id'],
        'tp'      => $r['titulo'],
        'autor'   => autores_texto($porTrabajo[(int) $r['id']] ?? []),
        'materia' => trabajo_materia_texto($r['materia'], $r['electiva_nombre']),
        'ciclo'   => (int) $r['ciclo_lectivo'],
        'img'     => $r['portada_url'],
        'oculto'  => $r['estado'] !== 'publicado',
        'likes'   => (int) $r['likes_count'],
    ], $filas);
}

// Palabras de la búsqueda: hasta 5, de 1 a 40 caracteres, sin símbolos de control
function trabajos_terminos_busqueda(string $q): array {
    $q = trabajo_limpiar_texto(mb_substr($q, 0, 80));
    if (mb_strlen($q) < 2) return [];
    $terms = array_values(array_filter(explode(' ', $q), fn($t) => $t !== ''));
    return array_map(fn($t) => mb_substr($t, 0, 40), array_slice($terms, 0, 5));
}

// ── Respuesta JSON común a las APIs de trabajos ──
function api_json(int $http, array $datos): void {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Lee el cuerpo JSON de un pedido POST (tamaño limitado)
function api_cuerpo_json(int $max = 20000): array {
    $d = json_decode((string) file_get_contents('php://input', false, null, 0, $max), true);
    return is_array($d) ? $d : [];
}

// Inicio común de las APIs que modifican datos: método POST, sesión, CSRF. Devuelve el usuario.
function api_post_con_sesion(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') api_json(405, ['ok' => false, 'error' => 'Método no permitido.']);
    if (!usuario_logueado()) api_json(401, ['ok' => false, 'codigo' => 'sin_sesion', 'error' => 'Tu sesión venció. Volvé a ingresar.']);
    csrf_exigir_api();
    $yo = usuario_actual();
    if (!$yo) api_json(401, ['ok' => false, 'codigo' => 'sin_sesion', 'error' => 'Tu sesión venció. Volvé a ingresar.']);
    return $yo;
}
