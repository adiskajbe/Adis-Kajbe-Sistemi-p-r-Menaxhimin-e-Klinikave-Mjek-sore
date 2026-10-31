-- Zgjidhni databazën klinika_db në phpMyAdmin para ekzekutimit.
-- Ky migrim mund të ekzekutohet përsëri pa dështuar nëse kolona ekziston.
SET @has_doctor_email = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mjeku'
    AND COLUMN_NAME = 'email'
);
SET @doctor_email_sql = IF(
  @has_doctor_email = 0,
  'ALTER TABLE mjeku ADD COLUMN email VARCHAR(190) NULL AFTER telefoni',
  'SELECT 1'
);
PREPARE doctor_email_statement FROM @doctor_email_sql;
EXECUTE doctor_email_statement;
DEALLOCATE PREPARE doctor_email_statement;
