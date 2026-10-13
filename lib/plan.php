<?php
// =============================================================================
// lib/plan.php — Règle unique « est-ce un compte PRO ? »
// Utilisée par app-plan.php (bouton « Passer à PRO ») et superadmin-journal.php
// (le journal d'audit est la fonctionnalité PRO).
//
// Source : waitlist_subscribers.plan (+ plan_expires_at).
// Offres PRO : par NOM d'abonnement (les prix changent, les noms restent).
// Réglable dans config/settings.json : { "plans": { "pro": [...] } } (facultatif)
// Prérequis : check.php déjà inclus (db_row, globals d'authentification).
// =============================================================================

function agenia_pro_plans(): array
{
    static $plans = null;
    if ($plans !== null) { return $plans; }
    // Plans payants déjà reconnus par l'ancien code (bouton PRO d'index.php) + fulltop.
    $plans = ['pro', 'enterprise', 'white_label', 'fulltop'];
    $f = __DIR__ . '/../config/settings.json';
    if (is_file($f)) {
        $s = json_decode((string)file_get_contents($f), true);
        $list = $s['plans']['pro'] ?? null;
        if (is_array($list) && $list) {
            $plans = array_values(array_map(fn($v) => strtolower(trim((string)$v)), $list));
        }
    }
    return $plans;
}

// Plan courant de l'utilisateur connecté : ['plan' => 'free', 'expires' => null|'Y-m-d H:i:s'].
function agenia_user_plan(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = ['plan' => '', 'expires' => null];
    $user = (string)($GLOBALS['authUser'] ?? '');
    if ($user === '' || !empty($GLOBALS['isGuest']) || !empty($GLOBALS['isDemo'])) { return $cache; }
    if (!function_exists('db_row')) { return $cache; }
    try {
        $row = db_row('SELECT plan, plan_expires_at FROM waitlist_subscribers WHERE id = ? OR email = ? LIMIT 1', [$user, $user]);
        $cache['plan']    = strtolower(trim((string)($row['plan'] ?? '')));
        $cache['expires'] = $row['plan_expires_at'] ?? null;
    } catch (Throwable $e) {
        error_log('plan.php: ' . $e->getMessage());
    }
    return $cache;
}

// PRO = super-admin, ou offre PRO non expirée. Démo et invité ne sont jamais PRO.
function agenia_is_pro(): bool
{
    if (!empty($GLOBALS['isSuperAdmin'])) { return true; }
    $p = agenia_user_plan();
    if ($p['plan'] === '' || !in_array($p['plan'], agenia_pro_plans(), true)) { return false; }
    if (!empty($p['expires']) && strtotime((string)$p['expires']) !== false && strtotime((string)$p['expires']) < time()) {
        return false;
    }
    return true;
}
