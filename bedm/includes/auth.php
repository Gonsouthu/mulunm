<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/seguridad.php';

iniciar_sesion_segura();     // cookie segura, vencimiento por inactividad
cabeceras_seguridad();       // CSP y demás cabeceras
mantenimiento_ocasional();

function usuario_logueado(): bool {
    return !empty($_SESSION['usuario_id']);
}

// Devuelve el usuario de la sesión leyéndolo de la base (así el nombre y el nickname siempre están al día)
function usuario_actual(): ?array {
    if (!usuario_logueado()) return null;
    $st = db()->prepare(
        'SELECT u.id, u.nombre, u.apellido, u.nickname, u.email, u.email_verificado, u.sesion_version,
                u.legajo, u.avatar_url, u.banner_url, u.bio, c.nombre AS carrera
           FROM usuarios u JOIN carreras c ON c.id = u.carrera_id
          WHERE u.id = ? AND u.activo = 1'
    );
    $st->execute([$_SESSION['usuario_id']]);
    $u = $st->fetch();
    // Cuenta dada de baja, o la contraseña cambió después de abrir esta sesión → la sesión ya no vale
    if (!$u || (int) ($_SESSION['sesion_version'] ?? 1) !== (int) $u['sesion_version']) {
        cerrar_sesion();
        return null;
    }
    return $u;
}

// Para páginas protegidas: sin sesión → login.php
function requerir_login(): array {
    $u = usuario_actual();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

// Para login/register: si ya hay sesión, directo al index
function redirigir_si_logueado(): void {
    if (usuario_logueado()) { header('Location: index.php'); exit; }
}

function iniciar_sesion(int $usuarioId, int $version = 1): void {
    session_regenerate_id(true);              // un identificador nuevo al entrar (evita la "fijación de sesión")
    $_SESSION = [
        'usuario_id'     => $usuarioId,
        'sesion_version' => $version,
        'inicio'         => time(),
        'ultimo_acceso'  => time(),
    ];
}

function cerrar_sesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies') && session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

// Aviso para quien todavía no confirmó su correo
function banner_verificacion(array $yo): string {
    if (!empty($yo['email_verificado'])) return '';
    return '<div class="aviso-verificacion" id="avisoVerificacion">'
         . '<span id="avisoVerificacionTexto">Confirmá tu correo para poder publicar trabajos. Te enviamos un email a <b>'
         . htmlspecialchars($yo['email'], ENT_QUOTES, 'UTF-8') . '</b>.</span> '
         . '<button type="button" id="reenviarVerificacion" class="aviso-btn">Reenviar email</button></div>';
}
