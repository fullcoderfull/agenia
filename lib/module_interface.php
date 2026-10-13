<?php
// =============================================================================
// lib/module_interface.php — Interface commune pour tous les modules VoxMind
// =============================================================================

interface VoxModule {

  /**
   * Initialiser le module
   * @return bool true si prêt, false sinon
   */
  public function init(): bool;

  /**
   * Exécuter une action
   * @param string $action  nom de l'action
   * @param array  $params  paramètres de l'action
   * @return array ['output' => string, 'error' => string|null, 'status' => int]
   */
  public function execute(string $action, array $params): array;

  /**
   * Nettoyer les ressources après exécution
   */
  public function cleanup(): void;

  /**
   * Ce module nécessite-t-il le consentement utilisateur ?
   */
  public function requires_consent(): bool;

  /**
   * Informations du module
   * @return array ['name', 'version', 'description']
   */
  public function info(): array;
}