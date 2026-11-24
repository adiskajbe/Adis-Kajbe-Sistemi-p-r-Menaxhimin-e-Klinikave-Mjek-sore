INSERT INTO pacienti (emri, mbiemri, datelindja, gjinia, nr_personal, grupi_gjakut, alergjite, telefoni, email, adresa, kontakti_emergjent)
SELECT 'Arben', 'Testues', '1988-04-14', 'M', '1122334455', 'A+', 'Penicilinë', '044111222', 'arben.testues@example.com', 'Rr. Dëshmorët e Kombit 12, Prishtinë', 'Lira Testues · 044111223'
WHERE NOT EXISTS (SELECT 1 FROM pacienti WHERE email = 'arben.testues@example.com');

INSERT INTO pacienti (emri, mbiemri, datelindja, gjinia, nr_personal, grupi_gjakut, alergjite, telefoni, email, adresa, kontakti_emergjent)
SELECT 'Elena', 'Krasniqi', '1994-09-22', 'F', '2233445566', 'O+', 'Asnjë e njohur', '049333444', 'elena.krasniqi@example.com', 'Lagjja Ulpiana D-4, Prishtinë', 'Besa Krasniqi · 049333445'
WHERE NOT EXISTS (SELECT 1 FROM pacienti WHERE email = 'elena.krasniqi@example.com');

INSERT INTO mjeku (emri, mbiemri, specializimi, telefoni, email, nr_dhome, orari_punes, tarifa_bazike)
SELECT 'Mira', 'Demonstrim', 'Mjekësi familjare', '044555666', 'mira.demonstrim@example.com', 'Kabineti 102', 'Hën–Pre · 08:00 - 16:00', 25.00
WHERE NOT EXISTS (SELECT 1 FROM mjeku WHERE email = 'mira.demonstrim@example.com');

INSERT INTO mjeku (emri, mbiemri, specializimi, telefoni, email, nr_dhome, orari_punes, tarifa_bazike)
SELECT 'Driton', 'Gashi', 'Kardiologji', '044777888', 'driton.gashi@example.com', 'Kabineti 204', 'Hën–Sht · 09:00 - 17:00', 45.00
WHERE NOT EXISTS (SELECT 1 FROM mjeku WHERE email = 'driton.gashi@example.com');

INSERT INTO sherbimet_klinike (emri, kategoria, cmimi, kohezgjatja_min, aktiv)
SELECT 'Konsultë e mjekësisë familjare', 'Konsultë', 25.00, 30, 1
WHERE NOT EXISTS (SELECT 1 FROM sherbimet_klinike WHERE emri = 'Konsultë e mjekësisë familjare');

INSERT INTO sherbimet_klinike (emri, kategoria, cmimi, kohezgjatja_min, aktiv)
SELECT 'Konsultë kardiologjike + EKG', 'Kardiologji', 45.00, 45, 1
WHERE NOT EXISTS (SELECT 1 FROM sherbimet_klinike WHERE emri = 'Konsultë kardiologjike + EKG');

INSERT INTO sherbimet_klinike (emri, kategoria, cmimi, kohezgjatja_min, aktiv)
SELECT 'Analizë bazike laboratorike', 'Laborator', 20.00, 15, 1
WHERE NOT EXISTS (SELECT 1 FROM sherbimet_klinike WHERE emri = 'Analizë bazike laboratorike');

INSERT INTO sherbimet_klinike (emri, kategoria, cmimi, kohezgjatja_min, aktiv)
SELECT 'Eko abdominale', 'Diagnostikë', 35.00, 30, 1
WHERE NOT EXISTS (SELECT 1 FROM sherbimet_klinike WHERE emri = 'Eko abdominale');

SET @demo_patient_1 = (SELECT id_pacienti FROM pacienti WHERE email = 'arben.testues@example.com' ORDER BY id_pacienti DESC LIMIT 1);
SET @demo_patient_2 = (SELECT id_pacienti FROM pacienti WHERE email = 'elena.krasniqi@example.com' ORDER BY id_pacienti DESC LIMIT 1);
SET @demo_doctor_1 = (SELECT id_mjeku FROM mjeku WHERE email = 'mira.demonstrim@example.com' ORDER BY id_mjeku DESC LIMIT 1);
SET @demo_doctor_2 = (SELECT id_mjeku FROM mjeku WHERE email = 'driton.gashi@example.com' ORDER BY id_mjeku DESC LIMIT 1);

INSERT INTO termini (id_pacienti, id_mjeku, data_ora, kohezgjatja_min, lloji_vizites, arsyeja, statusi, shenime)
SELECT @demo_patient_1, @demo_doctor_1, CONCAT(CURDATE(), ' 10:00:00'), 30, 'Kontrollë e përgjithshme', 'Kontrollë rutinë vjetore', 'konfirmuar', 'Vizitë demonstruese'
WHERE NOT EXISTS (SELECT 1 FROM termini WHERE id_pacienti = @demo_patient_1 AND id_mjeku = @demo_doctor_1 AND lloji_vizites = 'Kontrollë e përgjithshme');

INSERT INTO termini (id_pacienti, id_mjeku, data_ora, kohezgjatja_min, lloji_vizites, arsyeja, statusi, shenime)
SELECT @demo_patient_2, @demo_doctor_2, CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 11:30:00'), 45, 'Konsultë kardiologjike + EKG', 'Dhimbje e lehtë në kraharor', 'planifikuar', 'Të sillet analiza e mëparshme'
WHERE NOT EXISTS (SELECT 1 FROM termini WHERE id_pacienti = @demo_patient_2 AND id_mjeku = @demo_doctor_2 AND lloji_vizites = 'Konsultë kardiologjike + EKG');

INSERT INTO kartela_mjekesore (id_pacienti, id_mjeku, data_vizites, ankesat, diagnoza, kodi_icd, presioni_gjakut, pulsi, temperatura, pesha_kg, terapia_receta, rekomandime)
SELECT @demo_patient_1, @demo_doctor_1, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Lodhje sezonale dhe dhimbje e lehtë koke', 'Hipertension arterial i lehtë (kontrollë)', 'I10', '135/85 mmHg', 76, 36.6, 78.5, 'Monitorim i tensionit 2 herë në ditë; Vitamina D3 1000 IU', 'Ulje e kripës dhe ecje 30 min në ditë; rikontrollë pas 1 muaji.'
WHERE NOT EXISTS (SELECT 1 FROM kartela_mjekesore WHERE id_pacienti = @demo_patient_1 AND diagnoza = 'Hipertension arterial i lehtë (kontrollë)');

INSERT INTO fatura (nr_fatures, id_pacienti, data_fatures, shuma_totale, shuma_paguar, statusi, metoda_pageses, pershkrimi)
SELECT 'FAT-2026-0001', @demo_patient_1, CURDATE(), 45.00, 45.00, 'paguar', 'karte', 'Konsultë mjekësore dhe analiza laboratorike'
WHERE NOT EXISTS (SELECT 1 FROM fatura WHERE nr_fatures = 'FAT-2026-0001');

SET @demo_inv_1 = (SELECT id_fatura FROM fatura WHERE nr_fatures = 'FAT-2026-0001' LIMIT 1);
INSERT INTO fatura_artikuj (id_fatura, pershkrimi, sasia, cmimi_njesi, nentotali)
SELECT @demo_inv_1, 'Konsultë e mjekësisë familjare', 1, 25.00, 25.00
WHERE @demo_inv_1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM fatura_artikuj WHERE id_fatura = @demo_inv_1 AND pershkrimi = 'Konsultë e mjekësisë familjare');

INSERT INTO fatura_artikuj (id_fatura, pershkrimi, sasia, cmimi_njesi, nentotali)
SELECT @demo_inv_1, 'Analizë bazike laboratorike', 1, 20.00, 20.00
WHERE @demo_inv_1 IS NOT NULL AND NOT EXISTS (SELECT 1 FROM fatura_artikuj WHERE id_fatura = @demo_inv_1 AND pershkrimi = 'Analizë bazike laboratorike');
