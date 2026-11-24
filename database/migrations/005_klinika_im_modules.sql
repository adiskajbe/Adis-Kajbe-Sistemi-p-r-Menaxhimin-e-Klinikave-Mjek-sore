-- Migrimi 005: Shton fushat e zgjeruara për pacientët, mjekët, terminet
-- dhe krijon modulet e reja: kartela_mjekesore (EHR), sherbimet_klinike, fatura, fatura_artikuj dhe njoftime.
-- I sigurt për t'u ekzekutuar mbi databazë ekzistuese pa humbur të dhëna.

SET @patient_id_type = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'id_pacienti' LIMIT 1
);
SET @doctor_id_type = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mjeku' AND COLUMN_NAME = 'id_mjeku' LIMIT 1
);
SET @appt_id_type = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'termini' AND COLUMN_NAME = 'id_termini' LIMIT 1
);
SET @user_id_type = (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND COLUMN_NAME = 'id_perdorues' LIMIT 1
);

-- Zgjerimi i tabelës pacienti
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'datelindja');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN datelindja DATE NULL AFTER mbiemri', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'gjinia');
SET @sql = IF(@col_exists = 0, "ALTER TABLE pacienti ADD COLUMN gjinia ENUM('F', 'M', 'Tjeter') NULL AFTER datelindja", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'nr_personal');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN nr_personal VARCHAR(30) NULL AFTER gjinia', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'grupi_gjakut');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN grupi_gjakut VARCHAR(10) NULL AFTER nr_personal', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'alergjite');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN alergjite TEXT NULL AFTER grupi_gjakut', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'adresa');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN adresa VARCHAR(255) NULL AFTER email', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'kontakti_emergjent');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE pacienti ADD COLUMN kontakti_emergjent VARCHAR(120) NULL AFTER adresa', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Zgjerimi i tabelës mjeku
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mjeku' AND COLUMN_NAME = 'nr_dhome');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE mjeku ADD COLUMN nr_dhome VARCHAR(30) NULL AFTER email', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mjeku' AND COLUMN_NAME = 'orari_punes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE mjeku ADD COLUMN orari_punes VARCHAR(120) NULL AFTER nr_dhome', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mjeku' AND COLUMN_NAME = 'tarifa_bazike');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE mjeku ADD COLUMN tarifa_bazike DECIMAL(10,2) NOT NULL DEFAULT 30.00 AFTER orari_punes', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Zgjerimi i tabelës termini
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'termini' AND COLUMN_NAME = 'kohezgjatja_min');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE termini ADD COLUMN kohezgjatja_min SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER data_ora', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'termini' AND COLUMN_NAME = 'lloji_vizites');
SET @sql = IF(@col_exists = 0, "ALTER TABLE termini ADD COLUMN lloji_vizites VARCHAR(100) NOT NULL DEFAULT 'Kontrollë e përgjithshme' AFTER kohezgjatja_min", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'termini' AND COLUMN_NAME = 'arsyeja');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE termini ADD COLUMN arsyeja VARCHAR(255) NULL AFTER lloji_vizites', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE termini MODIFY COLUMN statusi ENUM('planifikuar', 'konfirmuar', 'ne_pritje', 'perfunduar', 'anuluar') NOT NULL DEFAULT 'planifikuar';

-- Tabela e shërbimeve klinike
CREATE TABLE IF NOT EXISTS sherbimet_klinike (
  id_sherbimi INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emri VARCHAR(150) NOT NULL,
  kategoria VARCHAR(80) NOT NULL DEFAULT 'Konsultë',
  cmimi DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  kohezgjatja_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  aktiv TINYINT(1) NOT NULL DEFAULT 1,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_sherbimi),
  KEY idx_sherbimet_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela e kartelave mjekësore (EHR)
SET @sql_ehr = CONCAT(
  'CREATE TABLE IF NOT EXISTS kartela_mjekesore (',
  'id_kartela INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  'id_pacienti ', @patient_id_type, ' NOT NULL,',
  'id_mjeku ', @doctor_id_type, ' NULL,',
  'id_termini ', @appt_id_type, ' NULL,',
  'data_vizites DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'ankesat TEXT NULL,',
  'diagnoza VARCHAR(255) NOT NULL,',
  'kodi_icd VARCHAR(30) NULL,',
  'presioni_gjakut VARCHAR(30) NULL,',
  'pulsi SMALLINT UNSIGNED NULL,',
  'temperatura DECIMAL(4,1) NULL,',
  'pesha_kg DECIMAL(5,1) NULL,',
  'terapia_receta TEXT NULL,',
  'rekomandime TEXT NULL,',
  'krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,',
  'PRIMARY KEY (id_kartela),',
  'KEY idx_kartela_pacienti_data (id_pacienti, data_vizites),',
  'KEY idx_kartela_mjeku (id_mjeku),',
  'KEY idx_kartela_termini (id_termini),',
  'CONSTRAINT fk_kartela_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE CASCADE,',
  'CONSTRAINT fk_kartela_mjeku FOREIGN KEY (id_mjeku) REFERENCES mjeku (id_mjeku) ON UPDATE CASCADE ON DELETE SET NULL,',
  'CONSTRAINT fk_kartela_termini FOREIGN KEY (id_termini) REFERENCES termini (id_termini) ON UPDATE CASCADE ON DELETE SET NULL',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt_ehr FROM @sql_ehr; EXECUTE stmt_ehr; DEALLOCATE PREPARE stmt_ehr;

-- Tabela e faturave
SET @sql_inv = CONCAT(
  'CREATE TABLE IF NOT EXISTS fatura (',
  'id_fatura INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  'nr_fatures VARCHAR(40) NOT NULL,',
  'id_pacienti ', @patient_id_type, ' NOT NULL,',
  'id_termini ', @appt_id_type, ' NULL,',
  'id_perdorues ', @user_id_type, ' NULL,',
  'data_fatures DATE NOT NULL,',
  'shuma_totale DECIMAL(10,2) NOT NULL DEFAULT 0.00,',
  'shuma_paguar DECIMAL(10,2) NOT NULL DEFAULT 0.00,',
  "statusi ENUM('papaguar', 'pjeserisht', 'paguar', 'anuluar') NOT NULL DEFAULT 'papaguar',",
  "metoda_pageses ENUM('cash', 'karte', 'transferte', 'sigurim') NULL,",
  'pershkrimi TEXT NULL,',
  'krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,',
  'PRIMARY KEY (id_fatura),',
  'UNIQUE KEY uq_fatura_nr (nr_fatures),',
  'KEY idx_fatura_pacienti (id_pacienti),',
  'KEY idx_fatura_data_statusi (data_fatures, statusi),',
  'CONSTRAINT fk_fatura_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE RESTRICT,',
  'CONSTRAINT fk_fatura_termini FOREIGN KEY (id_termini) REFERENCES termini (id_termini) ON UPDATE CASCADE ON DELETE SET NULL,',
  'CONSTRAINT fk_fatura_perdorues FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE SET NULL',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt_inv FROM @sql_inv; EXECUTE stmt_inv; DEALLOCATE PREPARE stmt_inv;

CREATE TABLE IF NOT EXISTS fatura_artikuj (
  id_artikulli INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_fatura INT UNSIGNED NOT NULL,
  id_sherbimi INT UNSIGNED NULL,
  pershkrimi VARCHAR(190) NOT NULL,
  sasia SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  cmimi_njesi DECIMAL(10,2) NOT NULL,
  nentotali DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id_artikulli),
  KEY idx_fatura_artikuj_fatura (id_fatura),
  CONSTRAINT fk_artikuj_fatura FOREIGN KEY (id_fatura) REFERENCES fatura (id_fatura) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_artikuj_sherbimi FOREIGN KEY (id_sherbimi) REFERENCES sherbimet_klinike (id_sherbimi) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql_notif = CONCAT(
  'CREATE TABLE IF NOT EXISTS njoftime (',
  'id_njoftimi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  'id_perdorues ', @user_id_type, ' NOT NULL,',
  'titulli VARCHAR(150) NOT NULL,',
  'mesazhi VARCHAR(255) NOT NULL,',
  "tipi ENUM('termin', 'fature', 'kartele', 'sistem') NOT NULL DEFAULT 'sistem',",
  'lexuar TINYINT(1) NOT NULL DEFAULT 0,',
  'krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'PRIMARY KEY (id_njoftimi),',
  'KEY idx_njoftime_user_lexuar (id_perdorues, lexuar, krijuar_me),',
  'CONSTRAINT fk_njoftime_perdorues FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE CASCADE',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE stmt_notif FROM @sql_notif; EXECUTE stmt_notif; DEALLOCATE PREPARE stmt_notif;
