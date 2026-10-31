CREATE DATABASE IF NOT EXISTS klinika_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE klinika_db;

CREATE TABLE IF NOT EXISTS pacienti (
  id_pacienti INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emri VARCHAR(100) NOT NULL,
  mbiemri VARCHAR(100) NOT NULL,
  telefoni VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pacienti),
  KEY idx_pacienti_emri (emri),
  KEY idx_pacienti_mbiemri (mbiemri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mjeku (
  id_mjeku INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emri VARCHAR(100) NOT NULL,
  mbiemri VARCHAR(100) NOT NULL,
  specializimi VARCHAR(150) NOT NULL,
  telefoni VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mjeku),
  KEY idx_mjeku_emri (emri, mbiemri)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS termini (
  id_termini INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pacienti INT UNSIGNED NOT NULL,
  id_mjeku INT UNSIGNED NOT NULL,
  data_ora DATETIME NOT NULL,
  statusi ENUM('planifikuar', 'perfunduar', 'anuluar') NOT NULL DEFAULT 'planifikuar',
  shenime TEXT NULL,
  krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perditesuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_termini),
  KEY idx_termini_pacienti (id_pacienti),
  KEY idx_termini_mjeku_data (id_mjeku, data_ora),
  CONSTRAINT fk_termini_pacienti
    FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_termini_mjeku
    FOREIGN KEY (id_mjeku) REFERENCES mjeku (id_mjeku)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;