-- Shton llogaritë e hyrjes pa ndryshuar tabelat ekzistuese të klinikës.
-- Ekzekutojeni pasi të jetë importuar klinika_db.sql.
-- Mjekët ekzistues mund të kenë id INT të shenjëzuar ose të pashenjëzuar.
-- Përdor llojin real të kolonës që lidhja e huaj të përputhet në të dy skemat.
SET @doctor_id_type = (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mjeku' AND COLUMN_NAME = 'id_mjeku'
  LIMIT 1
);

SET @create_users_sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS perdorues (',
  'id_perdorues INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  'username VARCHAR(50) NOT NULL,',
  'email VARCHAR(190) NOT NULL,',
  'password_hash VARCHAR(255) NOT NULL,',
  'id_mjeku ', @doctor_id_type, ' NULL,',
  'krijuar_me TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,',
  'PRIMARY KEY (id_perdorues),',
  'UNIQUE KEY uq_perdorues_username (username),',
  'UNIQUE KEY uq_perdorues_email (email),',
  'UNIQUE KEY uq_perdorues_mjeku (id_mjeku),',
  'CONSTRAINT fk_perdorues_mjeku FOREIGN KEY (id_mjeku) ',
    'REFERENCES mjeku (id_mjeku) ON UPDATE CASCADE ON DELETE SET NULL',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

PREPARE create_users_statement FROM @create_users_sql;
EXECUTE create_users_statement;
DEALLOCATE PREPARE create_users_statement;
