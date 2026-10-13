<?php
// index.php — point d'entrée : renvoie vers la nouvelle interface (Next.js, dossier app\).
// check.php garde le contrôle d'accès : sans session, il renvoie vers la connexion.
// Ancienne interface conservée dans index.php.ancien-<date>.
require_once __DIR__ . '/check.php';
$lang = (defined('UI_LANG') && UI_LANG === 'en') ? 'en' : 'fr';
header('Location: /app/' . $lang . '/chat/', true, 302);
exit;