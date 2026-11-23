<?php
// ══════════════════════════════════════════════════════════════
// SEGURIDAD BASE: sesión, cabeceras, CSRF, límites de uso, tokens de un solo uso.
// Lo carga includes/auth.php; no hace falta incluirlo a mano en las páginas.
// ══════════════════════════════════════════════════════════════

// Lee un valor de config/config.local.php (o devuelve el valor por defecto)
function cfg_valor(string $clave, $defecto = null) {
    global $cfg;
    return (is_array($cfg ?? null) && array_key_exists($clave, $cfg) && $cfg[$clave] !== '') ? $cfg[$clave] : $defecto;
}

function es_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    // Detrás de un proxy (hosting): solo se confía en este encabezado si se activa 'confiar_proxy'
    return cfg_valor('confiar_proxy', false) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// ── Sesión ────────────────────────────────────────────────────
function iniciar_sesion_segura(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');     // rechaza identificadores de sesión inventados por un atacante
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('BEDMSESSID');                  // nombre propio: en localhost conviven otros sitios con PHPSESSID
    session_set_cookie_params([
        'lifetime' => 0,                         // la cookie muere al cerrar el navegador
        'path'     => '/',
        'secure'   => es_https(),                // con https la cookie nunca viaja sin cifrar
        'httponly' => true,                      // JavaScript no puede leerla
        'samesite' => 'Lax',
    ]);
    session_start();

    $ahora = time();
    $inactividad = (int) cfg_valor('sesion_inactividad_min', 480) * 60;
    $maxima      = (int) cfg_valor('sesion_maxima_dias', 14) * 86400;
    $vencida = (isset($_SESSION['ultimo_acceso']) && $ahora - (int) $_SESSION['ultimo_acceso'] > $inactividad)
            || (isset($_SESSION['inicio']) && $ahora - (int) $_SESSION['inicio'] > $maxima);
    if ($vencida) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['inicio'] = $_SESSION['inicio'] ?? $ahora;
    $_SESSION['ultimo_acceso'] = $ahora;
}

// ── Cabeceras de seguridad (incluye la política de contenido, CSP) ──
function cabeceras_seguridad(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store');           // tras cerrar sesión, el botón "atrás" no muestra datos privados
    if (es_https() && cfg_valor('hsts', false)) {
        header('Strict-Transport-Security: max-age=15552000');
    }
    $modo = cfg_valor('csp', 'bloquear');        // 'bloquear' | 'avisar' (solo registra en la consola) | 'no'
    if ($modo !== 'no') {
        $csp = "default-src 'self'; "
             . "script-src 'self'; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
             . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
             . "img-src 'self' data: https:; "
             . "frame-src https://editor.p5js.org; "
             . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
        header(($modo === 'avisar' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy') . ': ' . $csp);
    }
}

// ── CSRF: impide que otra página haga acciones "a nombre" del usuario logueado ──
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_campo(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_meta(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_valido($enviado): bool {
    return is_string($enviado) && $enviado !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $enviado);
}
// Para las APIs (JSON): el token viaja en el encabezado X-CSRF-Token
function csrf_exigir_api(): void {
    if (!csrf_valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'codigo' => 'csrf', 'error' => 'La página venció. Recargala e intentá de nuevo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── Identificación del visitante (para los límites de uso) ──
function ip_cliente(): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
function ip_binaria(?string $ip = null): ?string {
    $b = @inet_pton($ip ?? ip_cliente());
    return $b === false ? null : $b;
}

// ── Registro de eventos (tabla auditoria) y límites de uso ──
function evento_registrar(string $accion, ?int $usuarioId = null, ?string $entidad = null, ?int $entidadId = null, ?string $detalle = null): void {
    try {
        $st = db()->prepare('INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip) VALUES (?, ?, ?, ?, ?, ?)');
        $st->bindValue(1, $usuarioId, $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(2, $accion);
        $st->bindValue(3, $entidad, $entidad === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue(4, $entidadId, $entidadId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(5, $detalle === null ? null : mb_substr($detalle, 0, 500), $detalle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ip = ip_binaria();
        $st->bindValue(6, $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $st->execute();
    } catch (PDOException $e) {
        error_log('BEDM auditoria: ' . $e->getMessage() . ' (¿falta la migración 002?)');
    }
}

// ¿Cuántas veces ocurrió $accion en los últimos $minutos? (por usuario si se indica; si no, por IP)
function eventos_recientes(string $accion, int $minutos, ?int $usuarioId = null): int {
    try {
        if ($usuarioId !== null) {
            $st = db()->prepare('SELECT COUNT(*) FROM auditoria WHERE accion = ? AND usuario_id = ? AND creado_en > (NOW() - INTERVAL ? MINUTE)');
            $st->bindValue(1, $accion);
            $st->bindValue(2, $usuarioId, PDO::PARAM_INT);
            $st->bindValue(3, $minutos, PDO::PARAM_INT);
            $st->execute();
        } else {
            $st = db()->prepare('SELECT COUNT(*) FROM auditoria WHERE accion = ? AND ip = ? AND creado_en > (NOW() - INTERVAL ? MINUTE)');
            $st->bindValue(1, $accion);
            $ip = ip_binaria();
            $st->bindValue(2, $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $st->bindValue(3, $minutos, PDO::PARAM_INT);
            $st->execute();
        }
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        error_log('BEDM auditoria: ' . $e->getMessage());
        return 0;
    }
}

// ── Límite de intentos de inicio de sesión (tabla intentos_login) ──
const LOGIN_VENTANA_MIN = 15;

function login_demasiados_intentos(string $email): bool {
    $ip = ip_binaria();
    $base = 'SELECT COUNT(*) FROM intentos_login WHERE exito = 0 AND creado_en > (NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE)';
    try {
        $q = function (string $extra, array $params) use ($base): int {
            $st = db()->prepare($base . $extra);
            foreach ($params as $i => $p) $st->bindValue($i + 1, $p, $p === null ? PDO::PARAM_NULL : (is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR));
            $st->execute();
            return (int) $st->fetchColumn();
        };
        return $q(' AND ip = ? AND email = ?', [$ip, $email]) >= 5      // mismo visitante probando la misma cuenta
            || $q(' AND ip = ?', [$ip]) >= 30                              // un visitante probando muchas cuentas
            || $q(' AND email = ?', [$email]) >= 15;                       // muchas direcciones contra la misma cuenta
    } catch (PDOException $e) {
        error_log('BEDM intentos_login: ' . $e->getMessage());
        return false;
    }
}

function login_registrar(string $email, bool $exito): void {
    try {
        $ip = ip_binaria();
        $st = db()->prepare('INSERT INTO intentos_login (ip, email, exito) VALUES (?, ?, ?)');
        $st->bindValue(1, $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $st->bindValue(2, $email);
        $st->bindValue(3, $exito ? 1 : 0, PDO::PARAM_INT);
        $st->execute();
        if ($exito) {   // un ingreso correcto borra los fallos previos de ese visitante con esa cuenta
            $d = db()->prepare('DELETE FROM intentos_login WHERE exito = 0 AND email = ? AND ip = ?');
            $d->bindValue(1, $email);
            $d->bindValue(2, $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $d->execute();
        }
    } catch (PDOException $e) {
        error_log('BEDM intentos_login: ' . $e->getMessage());
    }
}

// Limpieza de registros viejos (se ejecuta de vez en cuando, no en cada visita)
function mantenimiento_ocasional(): void {
    if (random_int(1, 50) !== 1) return;
    try {
        db()->exec("DELETE FROM intentos_login WHERE creado_en < (NOW() - INTERVAL 2 DAY)");
        db()->exec("DELETE FROM auditoria WHERE accion IN ('previsualizar','registro','reenvio_verificacion','olvide_password','cambio_nickname') AND creado_en < (NOW() - INTERVAL 30 DAY)");
        db()->exec("DELETE FROM tokens_usuario WHERE expira_en < (NOW() - INTERVAL 7 DAY)");
    } catch (PDOException $e) {
        error_log('BEDM mantenimiento: ' . $e->getMessage());
    }
}

// ── Tokens de un solo uso (verificar email / recuperar contraseña) ──
// En la base solo se guarda el hash: si alguien viera la tabla no podría usar los enlaces.
function token_crear(int $usuarioId, string $tipo, int $minutos): string {
    $pdo = db();
    $pdo->prepare('UPDATE tokens_usuario SET usado_en = NOW() WHERE usuario_id = ? AND tipo = ? AND usado_en IS NULL')->execute([$usuarioId, $tipo]);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO tokens_usuario (usuario_id, tipo, token_hash, expira_en) VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)')
        ->execute([$usuarioId, $tipo, hash('sha256', $token), $minutos]);
    return $token;
}

function token_forma_valida(string $token): bool {
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

// Mira si el token sirve, sin gastarlo. Devuelve el id del usuario o null.
function token_consultar(string $token, string $tipo): ?int {
    if (!token_forma_valida($token)) return null;
    $st = db()->prepare('SELECT usuario_id FROM tokens_usuario WHERE token_hash = ? AND tipo = ? AND usado_en IS NULL AND expira_en > NOW()');
    $st->execute([hash('sha256', $token), $tipo]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
}

// Gasta el token (una sola vez, aunque lleguen dos pedidos al mismo tiempo). Devuelve el id del usuario o null.
function token_consumir(string $token, string $tipo): ?int {
    if (!token_forma_valida($token)) return null;
    $hash = hash('sha256', $token);
    $st = db()->prepare('UPDATE tokens_usuario SET usado_en = NOW() WHERE token_hash = ? AND tipo = ? AND usado_en IS NULL AND expira_en > NOW()');
    $st->execute([$hash, $tipo]);
    if ($st->rowCount() !== 1) return null;
    $q = db()->prepare('SELECT usuario_id FROM tokens_usuario WHERE token_hash = ?');
    $q->execute([$hash]);
    return (int) $q->fetchColumn();
}

// ── Contraseñas y nicknames ──
function validar_password(string $pass, string $email = '', string $nickname = ''): ?string {
    if (strlen($pass) < 8)  return 'La contraseña debe tener al menos 8 caracteres.';
    if (strlen($pass) > 72) return 'La contraseña puede tener hasta 72 caracteres.';
    $minus = mb_strtolower($pass);
    if (($email !== '' && $minus === mb_strtolower($email)) || ($nickname !== '' && $minus === mb_strtolower($nickname))) {
        return 'La contraseña no puede ser igual a tu correo o a tu nickname.';
    }
    if (preg_match('/^(.)\1+$/u', $pass)) return 'Elegí una contraseña menos obvia (no repitas el mismo carácter).';
    return null;
}

// ¿Está libre este nickname? (ni en uso, ni reservado por alguien que lo usó antes)
function nickname_disponible(string $nick, ?int $paraUsuarioId = null): bool {
    $st = db()->prepare('SELECT id FROM usuarios WHERE nickname = ?');
    $st->execute([$nick]);
    $u = $st->fetchColumn();
    if ($u !== false && (int) $u !== $paraUsuarioId) return false;
    $h = db()->prepare('SELECT usuario_id FROM nickname_historial WHERE nickname = ?');
    $h->execute([$nick]);
    $dueno = $h->fetchColumn();
    return $dueno === false || ((int) $dueno === $paraUsuarioId && $paraUsuarioId !== null);
}
