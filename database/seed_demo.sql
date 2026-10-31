-- Vetëm të dhëna sintetike për demonstrim. Zgjidhni klinika_db para ekzekutimit.
INSERT INTO pacienti (emri, mbiemri, telefoni, email)
SELECT 'Arben', 'Testues', '000000001', 'arben.testues@example.com'
WHERE NOT EXISTS (SELECT 1 FROM pacienti WHERE email = 'arben.testues@example.com');
SET @demo_patient = (
  SELECT id_pacienti FROM pacienti
  WHERE email = 'arben.testues@example.com'
  ORDER BY id_pacienti DESC LIMIT 1
);

INSERT INTO mjeku (emri, mbiemri, specializimi, telefoni, email)
SELECT 'Mira', 'Demonstrim', 'Mjekesi familjare', '000000002', 'mira.demonstrim@example.com'
WHERE NOT EXISTS (SELECT 1 FROM mjeku WHERE email = 'mira.demonstrim@example.com');
SET @demo_doctor = (
  SELECT id_mjeku FROM mjeku
  WHERE email = 'mira.demonstrim@example.com'
  ORDER BY id_mjeku DESC LIMIT 1
);

INSERT INTO termini (id_pacienti, id_mjeku, data_ora, statusi, shenime)
SELECT @demo_patient, @demo_doctor, '2030-02-10 10:00:00', 'planifikuar', 'Synthetic demo appointment'
WHERE NOT EXISTS (
  SELECT 1 FROM termini
  WHERE id_pacienti = @demo_patient
    AND id_mjeku = @demo_doctor
    AND data_ora = '2030-02-10 10:00:00'
);
