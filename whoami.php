<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<pre>';

echo "=== PHP / IIS / FastCGI ===" . PHP_EOL;

echo "SAPI        : " . php_sapi_name() . PHP_EOL;
echo "PHP version : " . PHP_VERSION . PHP_EOL;
echo "PHP binary  : " . PHP_BINARY . PHP_EOL;
echo "php.ini     : " . (php_ini_loaded_file() ?: '(aucun)') . PHP_EOL;
echo "PID         : " . getmypid() . PHP_EOL;

echo PHP_EOL . "=== Utilisateur Windows ===" . PHP_EOL;

$output = [];
$returnCode = 0;

exec('C:\\Windows\\System32\\whoami.exe 2>&1', $output, $returnCode);

echo "Code retour : " . $returnCode . PHP_EOL;
echo implode(PHP_EOL, $output) . PHP_EOL;

echo PHP_EOL . "=== Test environnement ===" . PHP_EOL;

echo "Utilisateur PHP (get_current_user) : "
   . get_current_user() . PHP_EOL;

echo "Utilisateur ini                    : "
   . (ini_get('user') ?: '(non défini)') . PHP_EOL;

echo '</pre>';
