<?php
  // =============================================================================
  // lib/db.php — Connexion PDO MySQL (singleton)
  // =============================================================================
  require_once __DIR__ . '/../config/secrets.php';

  function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) { return $pdo; }

    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
      throw new RuntimeException('DB credentials manquants dans config/secrets.php');
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    return $pdo;
  }