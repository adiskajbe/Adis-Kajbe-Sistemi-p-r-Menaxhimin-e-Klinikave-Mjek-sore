SET @has_role = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND COLUMN_NAME = 'role'
);
SET @role_sql = IF(@has_role = 0,
  'ALTER TABLE perdorues ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT ''admin'' AFTER password_hash',
  'SELECT 1'
);
PREPARE role_statement FROM @role_sql;
EXECUTE role_statement;
DEALLOCATE PREPARE role_statement;

SET @has_active = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND COLUMN_NAME = 'is_active'
);
SET @active_sql = IF(@has_active = 0,
  'ALTER TABLE perdorues ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role',
  'SELECT 1'
);
PREPARE active_statement FROM @active_sql;
EXECUTE active_statement;
DEALLOCATE PREPARE active_statement;

SET @has_must_change = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND COLUMN_NAME = 'must_change_password'
);
SET @must_change_sql = IF(@has_must_change = 0,
  'ALTER TABLE perdorues ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'SELECT 1'
);
PREPARE must_change_statement FROM @must_change_sql;
EXECUTE must_change_statement;
DEALLOCATE PREPARE must_change_statement;

UPDATE perdorues SET role = 'admin' WHERE role IS NULL OR role = '';
UPDATE perdorues SET is_active = 1 WHERE is_active IS NULL;

CREATE TABLE IF NOT EXISTS login_logs (
  id_login_log BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_perdorues INT UNSIGNED NULL,
  email_or_username VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  status ENUM('success', 'failure') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_login_log),
  KEY idx_login_logs_identifier_created (email_or_username, created_at),
  KEY idx_login_logs_ip_created (ip_address, created_at),
  KEY idx_login_logs_user_created (id_perdorues, created_at),
  CONSTRAINT fk_login_logs_user FOREIGN KEY (id_perdorues)
    REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id_reset_token BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_perdorues INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_reset_token),
  UNIQUE KEY uq_password_reset_hash (token_hash),
  KEY idx_password_reset_user_expiry (id_perdorues, expires_at),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (id_perdorues)
    REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_rate_limits (
  bucket_type ENUM('identity', 'ip') NOT NULL,
  bucket_hash CHAR(64) NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bucket_type, bucket_hash),
  KEY idx_login_rate_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
