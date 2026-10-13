<?php
// =============================================================================
// lib/modules.php — Registre et chargeur de modules VoxMind
// =============================================================================
require_once __DIR__ . '/module_interface.php';
require_once __DIR__ . '/security.php';

/**
 * Charger la config des modules
 */
function modules_config(): array {
  static $cfg = null;
  if ($cfg === null) { $cfg = require __DIR__ . '/../config/modules.php'; }
  return $cfg;
}

/**
 * Lister les modules activés pour un rôle donné
 */
function modules_list(string $role): array {
  $cfg    = modules_config();
  $result = [];
  foreach ($cfg as $name => $mod) {
    if (!$mod['enabled']) { continue; }
    if (!in_array($role, $mod['roles'])) { continue; }
    $result[$name] = [
      'label'       => $mod['label'],
      'description' => $mod['description'],
      'icon'        => $mod['icon'],
      'consent'     => $mod['consent'],
    ];
  }
  return $result;
}

/**
 * Vérifier qu'un module est disponible pour un rôle
 */
function module_allowed(string $name, string $role): bool {
  $cfg = modules_config();
  if (!isset($cfg[$name])) { return false; }
  $mod = $cfg[$name];
  return $mod['enabled'] && in_array($role, $mod['roles']);
}

/**
 * Charger un module — retourne une instance VoxModule
 */
function module_load(string $name): ?VoxModule {
  $cfg = modules_config();
  if (!isset($cfg[$name]) || !$cfg[$name]['enabled']) { return null; }

  $modulePath = __DIR__ . '/../modules/' . $name . '/module.php';
  if (!file_exists($modulePath)) {
    security_log('module_not_found', ['module' => $name]);
    return null;
  }

  require_once $modulePath;

  // Convention : classe = Mod_{nom}
  $className = 'Mod_' . $name;
  if (!class_exists($className)) { return null; }

  $instance = new $className();
  if (!($instance instanceof VoxModule)) { return null; }

  // Passer la config du module à l'instance
  if (method_exists($instance, 'setConfig')) {
    $instance->setConfig($cfg[$name]);
  }

  return $instance;
}

/**
 * Exécuter un module — point d'entrée principal
 * Vérifie le rôle, le consentement et le rate limiting
 */
function module_exec(string $name, string $action, array $params, string $role, bool $consent = false): array {
  // Vérification rôle
  if (!module_allowed($name, $role)) {
    security_log('module_denied', ['module' => $name, 'role' => $role]);
    return ['output' => '', 'error' => 'Module non autorisé', 'status' => 403];
  }

  // Vérification consentement
  $cfg = modules_config();
  if ($cfg[$name]['consent'] && !$consent) {
    return ['output' => '', 'error' => 'Consentement requis', 'status' => 428, 'needs_consent' => true];
  }

  // Rate limiting par module
  $ip = get_client_ip();
  if (!rl_check('module_' . $name . '_' . $ip, 10, 60)) {
    return ['output' => '', 'error' => 'Trop de requêtes .', 'status' => 429];
  }

  // Charger et exécuter
  $mod = module_load($name);
  if (!$mod) {
    return ['output' => '', 'error' => 'Module introuvable', 'status' => 404];
  }

  try {
    if (!$mod->init()) {
      return ['output' => '', 'error' => 'Initialisation échouée', 'status' => 500];
    }

    security_log('module_exec', ['module' => $name, 'action' => $action]);

    // Timeout
    $timeout = $cfg[$name]['timeout'] ?? 30;
    set_time_limit($timeout);

    $result = $mod->execute($action, $params);
    $mod->cleanup();

    return $result;

  } catch (Throwable $e) {
    security_log('module_error', ['module' => $name, 'error' => $e->getMessage()]);
    return ['output' => '', 'error' => 'Erreur interne', 'status' => 500];
  }
}