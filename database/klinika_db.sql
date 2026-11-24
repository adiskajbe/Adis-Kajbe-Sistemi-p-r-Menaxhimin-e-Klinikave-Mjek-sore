CREATE DATABASE IF NOT EXISTS klinika_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE klinika_db;

CREATE TABLE IF NOT EXISTS pacienti (
  id_pacienti INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emri VARCHAR(100) NOT NULL,
  mbiemri VARCHAR(100) NOT NULL,
  datelindja DATE NULL,
  gjinia ENUM('F', 'M', 'Tjeter') NULL,
  nr_personal VARCHAR(30) NULL,
  grupi_gjakut VARCHAR(10) NULL,
  alergjite TEXT NULL,
  telefoni VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  adresa VARCHAR(255) NULL,
  kontakti_emergjent VARCHAR(120) NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pacienti),
  KEY idx_pacienti_emri (emri),
  KEY idx_pacienti_mbiemri (mbiemri),
  KEY idx_pacienti_nr_personal (nr_personal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mjeku (
  id_mjeku INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emri VARCHAR(100) NOT NULL,
  mbiemri VARCHAR(100) NOT NULL,
  specializimi VARCHAR(150) NOT NULL,
  telefoni VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  nr_dhome VARCHAR(30) NULL,
  orari_punes VARCHAR(120) NULL,
  tarifa_bazike DECIMAL(10,2) NOT NULL DEFAULT 30.00,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mjeku),
  KEY idx_mjeku_emri (emri, mbiemri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS termini (
  id_termini INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pacienti INT UNSIGNED NOT NULL,
  id_mjeku INT UNSIGNED NOT NULL,
  data_ora DATETIME NOT NULL,
  kohezgjatja_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  lloji_vizites VARCHAR(100) NOT NULL DEFAULT 'Kontrollë e përgjithshme',
  arsyeja VARCHAR(255) NULL,
  statusi ENUM('planifikuar', 'konfirmuar', 'ne_pritje', 'perfunduar', 'anuluar') NOT NULL DEFAULT 'planifikuar',
  shenime TEXT NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_termini),
  KEY idx_termini_pacienti (id_pacienti),
  KEY idx_termini_mjeku_data (id_mjeku, data_ora),
  KEY idx_termini_statusi (statusi),
  CONSTRAINT fk_termini_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_termini_mjeku FOREIGN KEY (id_mjeku) REFERENCES mjeku (id_mjeku) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perdorues (
  id_perdorues INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  id_mjeku INT UNSIGNED NULL,
  id_pacienti INT UNSIGNED NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_perdorues),
  UNIQUE KEY uq_perdorues_username (username),
  UNIQUE KEY uq_perdorues_email (email),
  UNIQUE KEY uq_perdorues_mjeku (id_mjeku),
  UNIQUE KEY uq_perdorues_pacienti (id_pacienti),
  CONSTRAINT fk_perdorues_mjeku FOREIGN KEY (id_mjeku) REFERENCES mjeku (id_mjeku) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_perdorues_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kartela_mjekesore (
  id_kartela INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pacienti INT UNSIGNED NOT NULL,
  id_mjeku INT UNSIGNED NULL,
  id_termini INT UNSIGNED NULL,
  data_vizites DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ankesat TEXT NULL,
  diagnoza VARCHAR(255) NOT NULL,
  kodi_icd VARCHAR(30) NULL,
  presioni_gjakut VARCHAR(30) NULL,
  pulsi SMALLINT UNSIGNED NULL,
  temperatura DECIMAL(4,1) NULL,
  pesha_kg DECIMAL(5,1) NULL,
  terapia_receta TEXT NULL,
  rekomandime TEXT NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_kartela),
  KEY idx_kartela_pacienti_data (id_pacienti, data_vizites),
  KEY idx_kartela_mjeku (id_mjeku),
  KEY idx_kartela_termini (id_termini),
  CONSTRAINT fk_kartela_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_kartela_mjeku FOREIGN KEY (id_mjeku) REFERENCES mjeku (id_mjeku) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_kartela_termini FOREIGN KEY (id_termini) REFERENCES termini (id_termini) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fatura (
  id_fatura INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nr_fatures VARCHAR(40) NOT NULL,
  id_pacienti INT UNSIGNED NOT NULL,
  id_termini INT UNSIGNED NULL,
  id_perdorues INT UNSIGNED NULL,
  data_fatures DATE NOT NULL,
  shuma_totale DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shuma_paguar DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  statusi ENUM('papaguar', 'pjeserisht', 'paguar', 'anuluar') NOT NULL DEFAULT 'papaguar',
  metoda_pageses ENUM('cash', 'karte', 'transferte', 'sigurim') NULL,
  pershkrimi TEXT NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_fatura),
  UNIQUE KEY uq_fatura_nr (nr_fatures),
  KEY idx_fatura_pacienti (id_pacienti),
  KEY idx_fatura_data_statusi (data_fatures, statusi),
  CONSTRAINT fk_fatura_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_fatura_termini FOREIGN KEY (id_termini) REFERENCES termini (id_termini) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_fatura_perdorues FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_login_logs_user FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE SET NULL
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
  CONSTRAINT fk_password_reset_user FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS njoftime (
  id_njoftimi BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_perdorues INT UNSIGNED NOT NULL,
  titulli VARCHAR(150) NOT NULL,
  mesazhi VARCHAR(255) NOT NULL,
  tipi ENUM('termin', 'fature', 'kartele', 'sistem') NOT NULL DEFAULT 'sistem',
  lexuar TINYINT(1) NOT NULL DEFAULT 0,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_njoftimi),
  KEY idx_njoftime_user_lexuar (id_perdorues, lexuar, krijuar_me),
  CONSTRAINT fk_njoftime_perdorues FOREIGN KEY (id_perdorues) REFERENCES perdorues (id_perdorues) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
