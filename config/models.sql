-- =============================================================================
-- agenia_tables.sql — Tables à créer dans waitlist_datacorners.com
-- Exécuter une seule fois via phpMyAdmin ou CLI
-- Base existante : waitlist_datacorners.com
-- Table existante : waitlist_subscribers (ne pas toucher)
-- =============================================================================

USE `waitlist_datacorners.com`;

-- ── 1. Sessions guest ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agenia_guest_sessions` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `token_hash`   CHAR(64)         NOT NULL,           -- SHA256 du token brut
  `ip_hash`      CHAR(64)         NOT NULL,           -- SHA256 de l'IP (RGPD)
  `messages_used` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `photos_used`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`   DATETIME         NOT NULL,
  `blocked_until` DATETIME        NULL DEFAULT NULL,  -- blocage IP post-expiration
  `subscriber_id` INT UNSIGNED    NULL DEFAULT NULL,  -- lié à waitlist_subscribers.id si invite
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`   (`token_hash`),
  KEY `idx_ip`            (`ip_hash`),
  KEY `idx_expires`       (`expires_at`),
  KEY `idx_subscriber`    (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Tokens d'invitation ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agenia_invite_tokens` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `subscriber_id` INT UNSIGNED  NOT NULL,             -- FK waitlist_subscribers.id
  `token_hash`    CHAR(64)      NOT NULL,             -- SHA256 du confirm_token
  `uses`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_uses`      TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `expires_at`    DATETIME      NOT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`      (`token_hash`),
  KEY `idx_subscriber`       (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Config prospects ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agenia_prospect_configs` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `subscriber_id` INT UNSIGNED  NOT NULL UNIQUE,      -- FK waitlist_subscribers.id
  `system_prompt` TEXT          NULL,                 -- prompt personnalisé
  `llm_config`    JSON          NULL,                 -- surcharge config LLM/TTS/STT
  `modules`       JSON          NULL,                 -- modules activés/désactivés
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriber` (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Modules ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agenia_modules` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`          VARCHAR(64)   NOT NULL UNIQUE,
  `label_fr`      VARCHAR(128)  NOT NULL,
  `label_en`      VARCHAR(128)  NOT NULL,
  `plan_min`      ENUM('guest','free','pro','enterprise') NOT NULL DEFAULT 'free',
  `guest_allowed` TINYINT(1)    NOT NULL DEFAULT 0,
  `guest_max_uses` TINYINT UNSIGNED NULL DEFAULT NULL, -- NULL = illimité dans session
  `active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug`  (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales modules
INSERT IGNORE INTO `agenia_modules`
  (`slug`, `label_fr`, `label_en`, `plan_min`, `guest_allowed`, `guest_max_uses`, `active`)
VALUES
  ('llm_cloud',     'LLM Cloud',        'Cloud LLM',        'guest',      1, NULL, 1),
  ('llm_local',     'LLM Local',        'Local LLM',        'guest',      1, NULL, 1),
  ('tts',           'Synthèse vocale',  'Text to Speech',   'guest',      1, NULL, 1),
  ('stt',           'Transcription',    'Speech to Text',   'guest',      1, NULL, 1),
  ('vision',        'Vision webcam',    'Webcam Vision',    'guest',      1,    2, 1),
  ('upload',        'Fichiers joints',  'File Upload',      'guest',      1, NULL, 1),
  ('history',       'Historique',       'History',          'free',       0, NULL, 1),
  ('custom_prompt', 'Prompt personnalisé','Custom Prompt',  'free',       0, NULL, 1);

-- ── 5. Compteur tokens ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agenia_token_usage` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`    VARCHAR(128)    NOT NULL,            -- token_hash ou user login
  `user_type`     ENUM('guest','user','admin','partner') NOT NULL DEFAULT 'guest',
  `provider`      VARCHAR(32)     NOT NULL,            -- ollama, openai, anthropic...
  `model`         VARCHAR(128)    NOT NULL,
  `tokens_in`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `tokens_out`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `credits_used`  DECIMAL(10,4)   NOT NULL DEFAULT 0.0000,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session`   (`session_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;