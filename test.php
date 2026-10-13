<?php


require_once __DIR__ . '/config/secrets.php';

echo "secrets.php chargé correctement";



ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<h3>Test PHP / secrets.php</h3>";

$file = __DIR__ . '/config/secrets.php';

echo "1 - PHP OK<br>";
echo "2 - SAPI : " . htmlspecialchars(php_sapi_name()) . "<br>";
echo "3 - PHP : " . htmlspecialchars(PHP_VERSION) . "<br>";
echo "4 - Chemin : " . htmlspecialchars($file) . "<br>";

echo "5 - Existe : " . (file_exists($file) ? 'OUI' : 'NON') . "<br>";
echo "6 - Lisible : " . (is_readable($file) ? 'OUI' : 'NON') . "<br>";

echo "7 - Taille : ";
if (file_exists($file)) {
    echo filesize($file) . " octets<br>";
} else {
    echo "N/A<br>";
}

echo "8 - fopen : ";

$handle = @fopen($file, 'rb');

if ($handle === false) {
    echo "ECHEC<br>";

    $error = error_get_last();

    if ($error) {
        echo "<strong>Erreur :</strong> "
           . htmlspecialchars($error['message'])
           . "<br>";
    }
} else {
    echo "OK<br>";
    fclose($handle);
}

echo "9 - require_once : ";

try {
    require_once $file;
    echo "OK<br>";
} catch (Throwable $e) {
    echo "ECHEC<br>";
    echo "<strong>Erreur :</strong> "
       . htmlspecialchars($e->getMessage())
       . "<br>";
}

echo "<hr>";
echo "10 - Fin du test";
