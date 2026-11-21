-- Lidh një llogari pacienti me profilin përkatës, pa ndryshuar të dhënat ekzistuese.
-- Ekzekutojeni pasi të jenë ekzekutuar migrimet 002 dhe 003.

SET @patient_id_type = (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacienti' AND COLUMN_NAME = 'id_pacienti'
  LIMIT 1
);

SET @has_patient_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND COLUMN_NAME = 'id_pacienti'
);
SET @add_patient_column_sql = IF(@has_patient_id = 0,
  CONCAT('ALTER TABLE perdorues ADD COLUMN id_pacienti ', @patient_id_type, ' NULL AFTER id_mjeku'),
  'SELECT 1'
);
PREPARE add_patient_column_statement FROM @add_patient_column_sql;
EXECUTE add_patient_column_statement;
DEALLOCATE PREPARE add_patient_column_statement;

SET @has_patient_unique = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND INDEX_NAME = 'uq_perdorues_pacienti'
);
SET @add_patient_unique_sql = IF(@has_patient_unique = 0,
  'ALTER TABLE perdorues ADD UNIQUE KEY uq_perdorues_pacienti (id_pacienti)',
  'SELECT 1'
);
PREPARE add_patient_unique_statement FROM @add_patient_unique_sql;
EXECUTE add_patient_unique_statement;
DEALLOCATE PREPARE add_patient_unique_statement;

SET @has_patient_fk = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'perdorues' AND CONSTRAINT_NAME = 'fk_perdorues_pacienti'
);
SET @add_patient_fk_sql = IF(@has_patient_fk = 0,
  'ALTER TABLE perdorues ADD CONSTRAINT fk_perdorues_pacienti FOREIGN KEY (id_pacienti) REFERENCES pacienti (id_pacienti) ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE add_patient_fk_statement FROM @add_patient_fk_sql;
EXECUTE add_patient_fk_statement;
DEALLOCATE PREPARE add_patient_fk_statement;
