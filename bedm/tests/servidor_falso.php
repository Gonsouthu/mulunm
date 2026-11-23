<?php
// Servidor falso para las pruebas: simula lo que puede devolver una nube (php -S ... tests/servidor_falso.php)
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fx = __DIR__ . '/fixtures/';
function pdf(string $archivo): void { header('Content-Type: application/pdf'); header('Content-Length: ' . filesize($archivo)); readfile($archivo); }
switch ($ruta) {
    case '/ok.pdf':       pdf($fx . 'tres_paginas.pdf'); break;
    case '/treinta.pdf':  pdf($fx . 'treinta_paginas.pdf'); break;
    case '/clave.pdf':    pdf($fx . 'con_clave.pdf'); break;
    case '/falso.pdf':    pdf($fx . 'falso.pdf'); break;
    case '/roto.pdf':     pdf($fx . 'roto.pdf'); break;
    case '/web':          header('Content-Type: text/html; charset=utf-8'); readfile($fx . 'pagina_web.html'); break;
    case '/redir':        header('Location: /ok.pdf', true, 302); break;
    case '/redir-relativa': header('Location: ok.pdf', true, 302); break;
    case '/redir-loop':   header('Location: /redir-loop', true, 302); break;
    case '/nada':         http_response_code(404); echo 'no'; break;
    case '/prohibido':    http_response_code(403); echo 'no'; break;
    case '/cuota':        http_response_code(429); echo 'no'; break;
    case '/caido':        http_response_code(503); echo 'no'; break;
    case '/grande':       // con Content-Length declarado
        header('Content-Type: application/pdf'); header('Content-Length: ' . (6 * 1048576));
        echo "%PDF-1.4\n"; for ($i = 0; $i < 6; $i++) { echo str_repeat('A', 1048576 - ($i === 0 ? 9 : 0)); } break;
    case '/grande-chunked': // sin Content-Length (el tamaño se descubre sobre la marcha)
        header('Content-Type: application/pdf');
        echo "%PDF-1.4\n"; for ($i = 0; $i < 6; $i++) { echo str_repeat('A', 1048576); flush(); } break;
    case '/lento':        sleep(5); pdf($fx . 'tres_paginas.pdf'); break;
    default:              http_response_code(404); echo 'ruta desconocida';
}
