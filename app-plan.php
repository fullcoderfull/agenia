<?php
// =============================================================================
// app-plan.php — Offre de l'utilisateur pour le front Next.js (lecture seule)
//   - invité / démo        → « Créer un compte » (pas de paiement sans compte)
//   - compte non PRO       → « Passer à PRO » (y compris les admins)
//   - compte PRO / super-admin → aucun bouton
// La règle PRO est dans lib/plan.php (offres PRO réglables dans settings.json).
// Ne modifie rien. Aucune donnée de paiement ne transite ici.
// =============================================================================
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/lib/plan.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isGuest = !empty($GLOBALS['isGuest']);
$isDemo  = !empty($GLOBALS['isDemo']);
$user    = (string)($GLOBALS['authUser'] ?? '');

$paid = agenia_is_pro();
$plan = agenia_user_plan()['plan'];

$upgrade = null;
if (!$paid) {
    $upgrade = ($isGuest || $isDemo || $user === '')
        ? ['kind' => 'register', 'href' => '/register.php']
        : ['kind' => 'pro',      'href' => '/checkout.php?plan=pro_monthly'];
}

echo json_encode([
    'plan'    => $plan !== '' ? $plan : ($paid ? 'internal' : 'free'),
    'paid'    => $paid,
    'upgrade' => $upgrade,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
