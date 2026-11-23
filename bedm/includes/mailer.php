<?php
// ══════════════════════════════════════════════════════════════
// ENVÍO DE EMAILS (verificación de cuenta y recuperación de contraseña)
//
// Dos modos, según config/config.local.php:
//  · SIN 'smtp_host'  → modo prueba: el email NO se envía, se guarda como archivo de texto en
//                       storage/mails/ (diagnostico.php te muestra los últimos con sus enlaces).
//  · CON 'smtp_host'  → se envía de verdad por SMTP (usa PHPMailer, carpeta lib/phpmailer).
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/seguridad.php';

// Dirección base del sitio para armar los enlaces de los emails (https://tusitio.edu.ar/BEDM).
// NUNCA se arma con lo que manda el visitante (podrían falsificarlo para robar enlaces de recuperación):
// en un servidor real hay que configurar 'url_base'. Solo en localhost se deduce sola.
function url_base(): ?string {
    $conf = cfg_valor('url_base');
    if ($conf) return rtrim((string) $conf, '/');

    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) return null;

    $raiz = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $doc  = str_replace('\\', '/', (string) realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')));
    $rel  = ($doc !== '' && stripos($raiz, $doc) === 0) ? substr($raiz, strlen($doc)) : '';
    return (es_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($rel, '/');
}

function enviar_mail(string $para, string $asunto, string $texto): bool {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $asunto)) return false;
    $desde       = (string) cfg_valor('mail_desde', 'no-responder@localhost');
    $desdeNombre = (string) cfg_valor('mail_desde_nombre', 'BEDM · Repositorio UNM');

    // ── Modo prueba: guardar en un archivo ──
    if (!cfg_valor('smtp_host')) {
        $dir = rtrim((string) cfg_valor('storage_dir', __DIR__ . '/../storage'), '/\\') . DIRECTORY_SEPARATOR . 'mails';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ht = dirname($dir) . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n");
        $archivo = $dir . DIRECTORY_SEPARATOR . date('Ymd_His') . sprintf('_%06d', (int) (fmod(microtime(true), 1) * 1000000)) . '_' . bin2hex(random_bytes(2)) . '.txt';   // ordenable por fecha, con microsegundos
        $ok = @file_put_contents($archivo, "Para: $para\nDe: $desdeNombre <$desde>\nAsunto: $asunto\n\n$texto\n") !== false;
        if (!$ok) error_log('BEDM mail: no se pudo escribir en ' . $dir);
        return $ok;
    }

    // ── Envío real por SMTP ──
    require_once __DIR__ . '/../lib/phpmailer/Exception.php';
    require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/phpmailer/SMTP.php';
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = (string) cfg_valor('smtp_host');
        $m->Port       = (int) cfg_valor('smtp_puerto', 587);
        $m->Timeout    = 15;
        $m->CharSet    = 'UTF-8';
        $seg = strtolower((string) cfg_valor('smtp_seguridad', 'tls'));
        if ($seg === 'ssl')      $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($seg === 'tls')  $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }   // 'ninguna': sin cifrado (solo para pruebas)
        if (cfg_valor('smtp_usuario')) {
            $m->SMTPAuth = true;
            $m->Username = (string) cfg_valor('smtp_usuario');
            $m->Password = (string) cfg_valor('smtp_clave', '');
        }
        $m->setFrom($desde, $desdeNombre);
        $m->addAddress($para);
        $m->Subject = $asunto;
        $m->Body    = $texto;
        $m->isHTML(false);
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('BEDM mail: no se pudo enviar a ' . $para . ': ' . $e->getMessage());
        return false;
    }
}

// ── Los emails del sistema ──
function mail_verificacion(int $usuarioId, string $email, string $nombre): bool {
    $base = url_base();
    if ($base === null) { error_log("BEDM mail: configurá 'url_base' en config.local.php para poder enviar enlaces."); return false; }
    $token = token_crear($usuarioId, 'verificar_email', 60 * 48);
    return enviar_mail($email, 'Confirmá tu correo en BEDM',
        "Hola $nombre,\n\nPara confirmar tu correo y poder publicar tus trabajos en BEDM, entrá a este enlace (vale 48 horas):\n\n"
        . "$base/verificar.php?t=$token\n\nSi no creaste una cuenta en BEDM, ignorá este mensaje.\n");
}

function mail_recuperacion(int $usuarioId, string $email, string $nombre): bool {
    $base = url_base();
    if ($base === null) { error_log("BEDM mail: configurá 'url_base' en config.local.php para poder enviar enlaces."); return false; }
    $token = token_crear($usuarioId, 'recuperar_password', 60);
    return enviar_mail($email, 'Recuperá tu contraseña de BEDM',
        "Hola $nombre,\n\nRecibimos un pedido para cambiar tu contraseña. Entrá a este enlace (vale 1 hora y se puede usar una sola vez):\n\n"
        . "$base/restablecer.php?t=$token\n\nSi no fuiste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.\n");
}
