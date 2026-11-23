<?php
// Anonimiza las cuentas dadas de baja hace más de 30 días. Pensado para una tarea programada (cron).
// Uso:  php tools/purgar_bajas.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo se puede ejecutar desde la línea de comandos.'); }
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/cuentas.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
echo 'Cuentas anonimizadas: ', cuenta_purgar_vencidas(), "\n";
