<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek');

$pageTitle = 'Shkruaj vizitë EHR';
$activePage = 'ehr';
$authUser = current_auth_user();
$isDoctor = auth_user_has_role('mjek');
$authDoctorId = (int) ($authUser['doctor_id'] ?? 0);

$prePatientId = filter_input(INPUT_GET, 'id_pacienti', FILTER_VALIDATE_INT) ?: 0;
$preAppointmentId = filter_input(INPUT_GET, 'id_termini', FILTER_VALIDATE_INT) ?: '';

$values = [
    'id_pacienti' => (int) $prePatientId,
    'id_mjeku' => $isDoctor ? $authDoctorId : 0,
    'id_termini' => $preAppointmentId,
    'data_vizites' => (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'),
    'ankesat' => '',
    'diagnoza' => '',
    'kodi_icd' => '',
    'presioni_gjakut' => '',
    'pulsi' => '',
    'temperatura' => '',
    'pesha_kg' => '',
    'terapia_receta' => '',
    'rekomandime' => '',
];
$errors = [];
$patients = [];
$doctors = [];
$appointments = [];

if ($pdo instanceof PDO) {
    try {
        if ($isDoctor) {
            $pStmt = $pdo->prepare(
                'SELECT DISTINCT p.id_pacienti, p.emri, p.mbiemri FROM pacienti p
                 INNER JOIN termini t ON t.id_pacienti = p.id_pacienti
                 WHERE t.id_mjeku = :did ORDER BY p.emri, p.mbiemri'
            );
            $pStmt->execute(['did' => $authDoctorId]);
            $patients = $pStmt->fetchAll();
            $aStmt = $pdo->prepare(
                "SELECT t.id_termini, t.id_pacienti, t.data_ora, p.emri, p.mbiemri
                 FROM termini t INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
                 WHERE t.id_mjeku = :did AND t.statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje')
                 ORDER BY t.data_ora DESC LIMIT 30"
            );
            $aStmt->execute(['did' => $authDoctorId]);
            $appointments = $aStmt->fetchAll();
        } else {
            $patients = $pdo->query('SELECT id_pacienti, emri, mbiemri FROM pacienti ORDER BY emri, mbiemri')->fetchAll();
            $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri, specializimi FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
            $appointments = $pdo->query(
                "SELECT t.id_termini, t.id_pacienti, t.data_ora, p.emri, p.mbiemri
                 FROM termini t INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
                 WHERE t.statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje')
                 ORDER BY t.data_ora DESC LIMIT 40"
            )->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log('Leximi i zgjedhjeve për kartelën dështoi: ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma ka skaduar. Rifreskojeni faqen dhe provoni përsëri.';
    }
    $ehrInput = $_POST;
    if ($isDoctor) {
        $ehrInput['id_mjeku'] = $authDoctorId;
    }
    [$values, $fieldErrors] = valid_ehr_fields($ehrInput);
    $errors = array_merge($errors, $fieldErrors);

    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    } elseif (!$errors) {
        if (!patient_exists($pdo, $values['id_pacienti'])) {
            $errors[] = 'Pacienti i zgjedhur nuk ekziston.';
        }
        if ($values['id_mjeku'] > 0 && !doctor_exists($pdo, $values['id_mjeku'])) {
            $errors[] = 'Mjeku i zgjedhur nuk ekziston.';
        }
        if ($isDoctor) {
            $ownCheck = $pdo->prepare('SELECT 1 FROM termini WHERE id_pacienti = :pid AND id_mjeku = :did LIMIT 1');
            $ownCheck->execute(['pid' => $values['id_pacienti'], 'did' => $authDoctorId]);
            if (!$ownCheck->fetchColumn()) {
                $errors[] = 'Mund të dokumentoni kartela vetëm për pacientët e termineve tuaja.';
            }
        }
    }

    if ($pdo instanceof PDO && !$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO kartela_mjekesore
                 (id_pacienti, id_mjeku, id_termini, data_vizites, ankesat, diagnoza, kodi_icd, presioni_gjakut, pulsi, temperatura, pesha_kg, terapia_receta, rekomandime)
                 VALUES (:pid, :did, :tid, :visit_date, :ankesat, :diagnoza, :icd, :bp, :pulse, :temp, :weight, :rx, :rec)'
            );
            $stmt->execute([
                'pid' => $values['id_pacienti'],
                'did' => $values['id_mjeku'] > 0 ? $values['id_mjeku'] : null,
                'tid' => $values['id_termini'],
                'visit_date' => appointment_datetime_for_database($values['data_vizites']),
                'ankesat' => $values['ankesat'] !== '' ? $values['ankesat'] : null,
                'diagnoza' => $values['diagnoza'],
                'icd' => $values['kodi_icd'] !== '' ? $values['kodi_icd'] : null,
                'bp' => $values['presioni_gjakut'] !== '' ? $values['presioni_gjakut'] : null,
                'pulse' => $values['pulsi'] !== '' ? (int) $values['pulsi'] : null,
                'temp' => $values['temperatura'] !== '' ? (float) $values['temperatura'] : null,
                'weight' => $values['pesha_kg'] !== '' ? (float) $values['pesha_kg'] : null,
                'rx' => $values['terapia_receta'] !== '' ? $values['terapia_receta'] : null,
                'rec' => $values['rekomandime'] !== '' ? $values['rekomandime'] : null,
            ]);
            $newRecordId = (int) $pdo->lastInsertId();

            if ($values['id_termini'] !== null && !empty($_POST['complete_appointment'])) {
                $completeStmt = $pdo->prepare("UPDATE termini SET statusi = 'perfunduar' WHERE id_termini = :tid AND statusi <> 'anuluar'");
                $completeStmt->execute(['tid' => $values['id_termini']]);
            }

            $uStmt = $pdo->prepare('SELECT id_perdorues FROM perdorues WHERE id_pacienti = :pid AND is_active = 1 LIMIT 1');
            $uStmt->execute(['pid' => $values['id_pacienti']]);
            $patientUserId = $uStmt->fetchColumn();
            if ($patientUserId) {
                create_notification($pdo, (int) $patientUserId, 'Kartelë e re mjekësore', 'U regjistrua raporti i vizitës: ' . $values['diagnoza'], 'kartele');
            }

            $pdo->commit();
            set_flash('success', 'Kartela mjekësore u ruajt me sukses.');
            redirect_to('/kartela/shiko.php?id=' . $newRecordId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Ruajtja e kartelës EHR dështoi: ' . $exception->getMessage());
            $errors[] = 'Kartela nuk u ruajt. Kontrolloni të dhënat dhe provoni përsëri.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head"><div><p class="eyebrow">DOKUMENTIMI KLINIK (EHR)</p><h1>Shkruaj vizitë në kartelë</h1><p>Regjistroni ankesat, shenjat vitale, diagnozën dhe terapinë e pacientit.</p></div></section>
<?php if (!$pdo instanceof PDO): ?><?php include __DIR__ . '/../includes/database_notice.php'; ?><?php endif; ?>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><strong>Kontrolloni të dhënat e kartelës.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="content-card form-card">
    <form method="post" action="<?= h(APP_BASE_URL) ?>/kartela/shto.php" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div class="form-field"><label for="id_pacienti">Pacienti <span>*</span></label>
                <select id="id_pacienti" name="id_pacienti" required><option value="">Zgjidhni pacientin</option>
                    <?php foreach ($patients as $p): ?><option value="<?= h($p['id_pacienti']) ?>" <?= (int) $values['id_pacienti'] === (int) $p['id_pacienti'] ? 'selected' : '' ?>><?= h($p['emri'] . ' ' . $p['mbiemri']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php if (!$isDoctor): ?>
            <div class="form-field"><label for="id_mjeku">Mjeku ekzaminues</label>
                <select id="id_mjeku" name="id_mjeku"><option value="">Pa zgjedhur</option>
                    <?php foreach ($doctors as $d): ?><option value="<?= h($d['id_mjeku']) ?>" <?= (int) $values['id_mjeku'] === (int) $d['id_mjeku'] ? 'selected' : '' ?>><?= h($d['emri'] . ' ' . $d['mbiemri'] . ' · ' . $d['specializimi']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-field"><label for="data_vizites">Data dhe ora e vizitës <span>*</span></label><input id="data_vizites" name="data_vizites" type="datetime-local" value="<?= h($values['data_vizites']) ?>" required></div>
            <div class="form-field"><label for="id_termini">Termini i lidhur <small>(opsionale)</small></label>
                <select id="id_termini" name="id_termini"><option value="">Asnjë termin i lidhur</option>
                    <?php foreach ($appointments as $a): ?><option value="<?= h($a['id_termini']) ?>" <?= (int) ($values['id_termini'] ?? 0) === (int) $a['id_termini'] ? 'selected' : '' ?>><?= h(format_date_sq($a['data_ora'], 'd.m.Y H:i') . ' · ' . $a['emri'] . ' ' . $a['mbiemri']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="form-field"><label for="diagnoza">Diagnoza kryesore <span>*</span></label><input id="diagnoza" name="diagnoza" maxlength="255" placeholder="p.sh. Tonsilit akut" value="<?= h($values['diagnoza']) ?>" required></div>
            <div class="form-field"><label for="kodi_icd">Kodi ICD-10 <small>(opsionale)</small></label><input id="kodi_icd" name="kodi_icd" maxlength="30" placeholder="p.sh. J03.9" value="<?= h($values['kodi_icd']) ?>"></div>

            <div class="form-field"><label for="presioni_gjakut">Presioni i gjakut (TA)</label><input id="presioni_gjakut" name="presioni_gjakut" maxlength="30" placeholder="p.sh. 120/80 mmHg" value="<?= h($values['presioni_gjakut']) ?>"></div>
            <div class="form-field"><label for="pulsi">Pulsi (rrahje/min)</label><input id="pulsi" name="pulsi" type="number" min="25" max="250" placeholder="p.sh. 72" value="<?= h($values['pulsi']) ?>"></div>
            <div class="form-field"><label for="temperatura">Temperatura (°C)</label><input id="temperatura" name="temperatura" type="number" step="0.1" min="32" max="43" placeholder="p.sh. 36.6" value="<?= h($values['temperatura']) ?>"></div>
            <div class="form-field"><label for="pesha_kg">Pesha (kg)</label><input id="pesha_kg" name="pesha_kg" type="number" step="0.1" min="1" max="350" placeholder="p.sh. 74.5" value="<?= h($values['pesha_kg']) ?>"></div>

            <div class="form-field form-field-wide"><label for="ankesat">Ankesat dhe anamneza</label><textarea id="ankesat" name="ankesat" rows="3" placeholder="Përshkrimi i simptomave të pacientit..."><?= h($values['ankesat']) ?></textarea></div>
            <div class="form-field form-field-wide"><label for="terapia_receta">Terapia / Receta mjekësore</label><textarea id="terapia_receta" name="terapia_receta" rows="3" placeholder="Barnat, doza dhe kohëzgjatja..."><?= h($values['terapia_receta']) ?></textarea></div>
            <div class="form-field form-field-wide"><label for="rekomandime">Rekomandime dhe kontrolle vijuese</label><textarea id="rekomandime" name="rekomandime" rows="2" placeholder="Analiza shtesë, regjim ushqimor ose rikontrollë..."><?= h($values['rekomandime']) ?></textarea></div>

            <div class="form-field form-field-wide">
                <label class="checkbox-label"><input type="checkbox" name="complete_appointment" value="1" checked> Shëno terminin e lidhur si “Përfunduar”</label>
            </div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/kartela/lista.php">Anulo</a><button class="button button-primary" type="submit">Ruaj kartelën</button></div>
    </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
