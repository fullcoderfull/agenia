<?php
// =============================================================================
// config/users.php — Vue unifiée (comptes figés + waitlist_subscribers)
// Rétro-compatible : structure identique à l'historique (role string, api_token).
// =============================================================================

// --- 1) Comptes figés — source de vérité prioritaire, inchangés ---
$manual = [
  'demo(trial)' => ['password' => '', 'role' => 'demo', 'email' => 'admin@example.com', 'api_token' => null], /* AGENIA-DEMO-USER */
  'mike' => [
    'password'  => password_hash('CHANGEME', PASSWORD_BCRYPT),
    'role'      => 'admin',
    'email'     => 'admin@example.com',
    'api_token' => null,  // FIXE — à changer
  ],
  'demo' => [
    'password'  => password_hash('CHANGEME', PASSWORD_BCRYPT),
    'role'      => 'demo',
    'email'     => 'admin@example.com',
    'api_token' => null,
  ],
];

// --- 2) Mapping user_type_id (int en base) → role (string, comme les figés) ---
// VÉRIFIE ces ids face à ta table user_type :
$ROLE_BY_TYPE = [
  1 => 'guest',     // Utilisateur invité
  2 => 'admin',     // Administrateur
  3 => 'standard',  // Utilisateur standard
  4 => 'free',      // Utilisateur gratuit
  5 => 'pro',       // Utilisateur Pro
  6 => 'superadmin' // super administrateur
];

// --- 3) Comptes en base (créés via register.php), indexés par email ---
// --- Chargement base, blindé ---
$db_users = [];
$dbLib = __DIR__ . '/../lib/db.php';   // depuis config/ on remonte d'un cran
if (is_file($dbLib)) {
  require_once $dbLib;
}
if (function_exists('db')) {        // ← garde-fou : n'appelle que si définie
  try {
    $sql = 'SELECT email, password, user_type_id FROM waitlist_subscribers
            WHERE email IS NOT NULL AND email <> ""';
    foreach ( db()->query($sql) as $r) {
      $db_users[$r['email']] = [
        'password'     => $r['password'],
        'role'         => 'admin',           // ou ton mapping user_type_id
        'email'        => $r['email'],
        'user_type_id' => (int) $r['user_type_id'],
        'api_token'    => null,
      ];
    }
  } catch (Throwable $e) {
    error_log('users.php DB load: ' . $e->getMessage());
  }
} else {
  error_log('users.php: db() introuvable — lib/db.php non chargé ou chemin faux');
}

return $manual + $db_users;