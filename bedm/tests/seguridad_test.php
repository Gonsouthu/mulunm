<?php
// Pruebas de las funciones de seguridad que no necesitan base de datos. Uso:  php tests/seguridad_test.php
// (Las pruebas de punta a punta contra el sitio completo se hacen con un navegador y no están en este archivo.)
$cfg = [];
require __DIR__ . '/../includes/seguridad.php';

$ok = 0; $mal = 0;
function chequear(string $n, bool $c): void { global $ok, $mal; if ($c) { $ok++; echo "  [ok]   $n\n"; } else { $mal++; echo "  [FALLA] $n\n"; } }

echo "\n== Contraseñas\n";
chequear('muy corta → error', validar_password('abc123') !== null);
chequear('8 caracteres → válida', validar_password('Abcd1234') === null);
chequear('72 caracteres → válida', validar_password(str_repeat('a1', 36)) === null);
chequear('73 caracteres → rechazada', validar_password(str_repeat('a', 73)) !== null);
chequear('un solo carácter repetido → rechazada', validar_password('zzzzzzzz') !== null);
chequear('igual al correo → rechazada', validar_password('alumno@gmail.com', 'alumno@gmail.com') !== null);
chequear('igual al nickname (sin importar mayúsculas) → rechazada', validar_password('MiNickname', '', 'minickname') !== null);

echo "\n== Forma de los tokens\n";
chequear('64 hexadecimales → forma válida', token_forma_valida(str_repeat('ab12', 16)));
foreach (['', 'abc', str_repeat('g', 64), str_repeat('A', 64), str_repeat('a', 63), str_repeat('a', 65), "../" . str_repeat('a', 61), str_repeat('a', 63) . "\n"] as $t) {
    chequear('forma inválida rechazada: ' . json_encode(substr($t, 0, 14)), !token_forma_valida($t));
}

echo "\n== CSRF\n";
$_SESSION = [];
$t = csrf_token();
chequear('el token tiene 64 caracteres hexadecimales', (bool) preg_match('/^[a-f0-9]{64}$/', $t));
chequear('pedirlo dos veces devuelve el mismo token', csrf_token() === $t);
chequear('el token correcto es válido', csrf_valido($t));
foreach ([null, '', 'x', str_repeat('0', 64), $t . 'a', substr($t, 1), [$t], 123] as $malo) chequear('token inválido rechazado: ' . json_encode($malo === $t ? 'x' : (is_array($malo) ? 'array' : $malo)), !csrf_valido($malo));
$_SESSION = [];
chequear('sin token en la sesión, nada es válido', !csrf_valido($t) && !csrf_valido(''));
chequear('el campo oculto escapa HTML', str_contains(csrf_campo(), 'name="csrf"') && !str_contains(csrf_campo(), '<script'));

echo "\n== HTTPS y dirección\n";
$_SERVER = [];                                   chequear('sin datos: no es https', !es_https());
$_SERVER = ['HTTPS' => 'on'];                    chequear('HTTPS=on → https', es_https());
$_SERVER = ['HTTPS' => 'off'];                   chequear('HTTPS=off → no es https', !es_https());
$_SERVER = ['SERVER_PORT' => '443'];             chequear('puerto 443 → https', es_https());
$_SERVER = ['HTTP_X_FORWARDED_PROTO' => 'https']; chequear('encabezado de proxy IGNORADO por defecto (se podría falsificar)', !es_https());
$cfg = ['confiar_proxy' => true];                chequear('con confiar_proxy activado, se respeta', es_https());
$cfg = [];
$_SERVER = ['REMOTE_ADDR' => '203.0.113.7'];     chequear('IP del visitante válida', ip_cliente() === '203.0.113.7');
$_SERVER = ['REMOTE_ADDR' => 'basura'];          chequear('IP inválida → 0.0.0.0', ip_cliente() === '0.0.0.0');
$_SERVER = ['REMOTE_ADDR' => '2001:db8::1'];     chequear('IPv6 → 16 bytes binarios', strlen((string) ip_binaria()) === 16);

echo "\n== Configuración\n";
$cfg = ['a' => 5, 'b' => '', 'c' => false];
chequear('valor presente', cfg_valor('a', 1) === 5);
chequear('valor vacío → usa el predeterminado', cfg_valor('b', 'x') === 'x');
chequear('valor false se respeta', cfg_valor('c', true) === false);
chequear('clave inexistente → predeterminado', cfg_valor('zzz', 'p') === 'p');

echo "\n════════ Resultado: $ok correctas, $mal con falla ════════\n";
exit($mal ? 1 : 0);
