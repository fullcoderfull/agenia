-- =============================================================================
-- VoxMind — Schéma MySQL
-- À importer une fois : mysql -u root -p < schema.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS voxmind
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE voxmind;

-- ── Utilisateurs ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(64)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('admin','user','demo','partner') DEFAULT 'user',
  active       TINYINT(1)   DEFAULT 1,
  fail_count   INT          DEFAULT 0,
  locked_until DATETIME     DEFAULT NULL,
  last_fail    DATETIME     DEFAULT NULL,
  last_login   DATETIME     DEFAULT NULL,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  api_token    VARCHAR(255) DEFAULT NULL,
  INDEX idx_username (username),
  INDEX idx_active (active)
) ENGINE=InnoDB;

-- ── Quotas par utilisateur ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quotas (
  user_id      INT NOT NULL,
  date         DATE NOT NULL,
  llm_tokens   INT DEFAULT 0,
  stt_seconds  INT DEFAULT 0,
  tts_chars    INT DEFAULT 0,
  PRIMARY KEY (user_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Rate limiting global ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
  ip_hash      VARCHAR(64) NOT NULL,
  endpoint     VARCHAR(32) NOT NULL,
  ts           BIGINT      NOT NULL,
  PRIMARY KEY (ip_hash, endpoint, ts),
  INDEX idx_ts (ts)
) ENGINE=InnoDB;

-- ── Logs sécurité (audit) ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS security_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ts           DATETIME    DEFAULT CURRENT_TIMESTAMP,
  event        VARCHAR(64) NOT NULL,
  ip_pseudo    VARCHAR(64) NOT NULL,
  username     VARCHAR(64) DEFAULT NULL,
  ctx          JSON        DEFAULT NULL,
  INDEX idx_ts (ts),
  INDEX idx_event (event)
) ENGINE=InnoDB;

-- ── Compte admin initial (mot de passe : à changer) ─────────────────────────
INSERT IGNORE INTO users (username, password, role)
VALUES ('admin', '$2y$12$placeholder.changeme.via.script', 'admin');

-- Pour générer un vrai hash :
-- php -r "echo password_hash('mon_mot_de_passe', PASSWORD_BCRYPT, ['cost'=>12]);"