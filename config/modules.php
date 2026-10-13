<?php
// =============================================================================
// config/modules.php — Activation et configuration des modules
// enabled: true/false — active/désactive le module
// consent: true/false — demande l'accord utilisateur avant exécution
// roles:   rôles autorisés (admin, user, partner, demo)
// =============================================================================

return [

  'terminal' => [
    'enabled'     => true,
    'consent'     => true,    // demande consentement avant chaque exécution
    'roles'       => ['admin'],
    'label'       => 'Terminal',
    'description' => 'Exécution de commandes système (PowerShell, CMD)',
    'icon'        => '⌨',
    'whitelist'   => ['powershell', 'cmd', 'info'],  // commandes autorisées
    'timeout'     => 30,      // timeout en secondes
  ],

  'sso' => [
    'enabled'     => false,
    'consent'     => false,
    'roles'       => ['admin', 'user', 'partner'],
    'label'       => 'SSO',
    'description' => 'Authentification unique (SAML/OIDC)',
    'icon'        => '🔐',
    'provider'    => 'oidc',  // 'saml' ou 'oidc'
    'issuer'      => '',
    'client_id'   => '',
    'client_secret'=> '',
    'redirect_uri' => '',
  ],

  'billing' => [
    'enabled'     => false,
    'consent'     => false,
    'roles'       => ['admin'],
    'label'       => 'Facturation',
    'description' => 'Gestion abonnements et paiements (Stripe)',
    'icon'        => '💳',
    'stripe_key'  => '',
    'stripe_secret'=> '',
  ],

  // ── Template pour ajouter un module ──────────────────────────────────────
  // 'mon_module' => [
  //   'enabled'     => false,
  //   'consent'     => true,
  //   'roles'       => ['admin'],
  //   'label'       => 'Mon Module',
  //   'description' => 'Description du module',
  //   'icon'        => '🔧',
  // ],

];