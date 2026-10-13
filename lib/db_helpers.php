<?php
// =============================================================================
// lib/db_helpers.php — Helpers PDO (séparé de db.php)
// Require : lib/db.php doit être chargé avant
// Usage   : db_query(), db_row(), db_rows()
// =============================================================================
require_once __DIR__ . '/db.php';
/**
 * Exécuter une requête préparée
 */
function db_query(string $sql, array $params = []): PDOStatement {
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return $stmt;
}

/**
 * Récupérer une seule ligne — retourne null si aucun résultat
 */
function db_row(string $sql, array $params = []): ?array {
  $row = db_query($sql, $params)->fetch();
  return $row ?: null;
}

/**
 * Récupérer toutes les lignes
 */
function db_rows(string $sql, array $params = []): array {
  return db_query($sql, $params)->fetchAll();
}