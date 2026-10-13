<?php
// =============================================================================
// demo.php — Point d'entrée « demo(trial) »
// Ouvre une session de démonstration éphémère (rôle demo), sans mot de passe,
// puis renvoie vers le chat. L'agent y est VISIBLE mais SIMULÉ, et le journal
// d'audit est visible (données d'exemple). Le rôle demo est reconnu par
// check.php via le cookie HMAC « vox » (même format que vox_make_token).
// Prérequis : config/users.php contient l'entrée 'demo(trial)' => ['role'=>'demo'].
// =============================================================================
require_once __DIR__ . '/config/secrets.php';

$DEMO_USER = 'demo(trial)';   // doit exister dans config/users.php (role=demo)
$DEMO_TTL  = 30 * 60;         // durée de l'essai démo en secondes (modifiable)

$exp   = time() + $DEMO_TTL;
$sig   = hash_hmac('sha256', $DEMO_USER . '|' . $exp, HMAC_SECRET);
$token = base64_encode($DEMO_USER . '|' . $exp . '|' . $sig);

setcookie('vox', $token, [
    'expires'  => $exp,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'fr';
header('Location: /app/' . $lang . '/chat/');
exit;
