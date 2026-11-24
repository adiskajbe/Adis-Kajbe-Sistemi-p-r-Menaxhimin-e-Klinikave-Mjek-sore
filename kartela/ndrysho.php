<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isDoctor = auth_user_has_role('mjek');
$authDoctorId = (int) (current_auth_user()['doctor_id'] ?? 0);
$errors = [];
$record = null;
$doctors = [];

if (!$id || $id < 1) {
    http_response_code(400);
    $errors[] = 'ID-ja e kartelës nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare(
            'SELECT k.*, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri
             FROM kartela_mjekesore k INNER JOIN pacienti p ON p.id_pacienti = k.id_pacienti
             WHERE k.id_kartela = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();
        if (!$record) {
            http_response_code(404);
            $errors[] = 'Kartela nuk u gjet.';
        } elseif ($isDoctor && (int) $record['id_mjeku'] !== $authDoctorId) {
            http_response_code(403);
            $errors[] = 'Mund të ndryshoni vetëm kartelat e vizitave tuaja.';
            $record = null;
        } else {
            $record['data_vizites'] = appointment_datetime_for_input($record['data_vizites']);
            if (!$isDoctor) {
                $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri, specializimi FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
            }
        }
    } catch (Throwable $exception) {
        error_log('Leximi i kartelës për ndryshim dështoi: ' . $exception->getMessage());
        $errors[] = 'Nuk u arrit leximi i kartelës.';
    }
}

$values = $record ?: [
    'id_pacienti' => 0, 'id_mjeku' => 0, 'id_termini' => null, 'data_vizites' => '',
    'ankesat' => '', 'diagnoza' => '', 'kodi_icd' => '', 'presioni_gjakut' => '',
    'pulsi' => '', 'temperatura' => '', 'pesha_kg' => '', 'terapia_receta' => '', 'rekomandime' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma nuk është e vlefshme.';
    }
    $input = $_POST;
    $input['id_pacienti'] = $record['id_pacienti'];
    $input['id_termini'] = $record['id_termini'];
    if ($isDoctor) {
        $input['id_mjeku'] = $authDoctorId;
    }
    [$values, $fieldErrors] = valid_ehr_fields($input);
    $errors = array_merge($errors, $fieldErrors);
    if (!$errors && $pdo instanceof PDO) {
        try {
            $upd = $pdo->prepare(
                'UPDATE kartela_mjekesore SET id_mjeku = :did, data_vizites = :vdate, ankesat = :ankesat,
                 diagnoza = :diagnoza, kodi_icd = :icd, presioni_gjakut = :bp, pulsi = :pulse,
                 temperatura = :temp, pesha_kg = :weight, terapia_receta = :rx, rekomandime = :rec
                 WHERE id_kartela = :id'
            );
            $upd->execute([
                'did' => $values['id_mjeku'] > 0 ? $values['id_mjeku'] : null,
                'vdate' => appointment_datetime_for_database($values['data_vizites']),
                'ankesat' => $values['ankesat'] !== '' ? $values['ankesat'] : null,
                'diagnoza' => $values['diagnoza'],
                'icd' => $values['kodi_icd'] !== '' ? $values['kodi_icd'] : null,
                'bp' => $values['presioni_gjakut'] !== '' ? $values['presioni_gjakut'] : null,
                'pulse' => $values['pulsi'] !== '' ? (int) $values['pulsi'] : null,
                'temp' => $values['temperatura'] !== '' ? (float) $values['temperatura'] : null,
                'weight' => $values['pesha_kg'] !== '' ? (float) $values['pesha_kg'] : null,
                'rx' => $values['terapia_receta'] !== '' ? $values['terapia_receta'] : null,
                'rec' => $values['rekomandime'] !== '' ? $values['rekomandime'] : null,
                'id' => $id,
            ]);
            set_flash('success', 'Kartela mjekësore u përditësua.');
            redirect_to('/kartela/shiko.php?id=' . (int) $id);
        } catch (Throwable $exception) {
            error_log('Përditësimi i kartelës dështoi: ' . $exception->getMessage());
            $errors[] = 'Ndryshimet nuk u ruajtën.';
        }
    }
}

$pageTitle = 'Ndrysho kartelën EHR';
$activePage = 'ehr';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head"><div><p class="eyebrow">DOKUMENTIMI KLINIK (EHR)</p><h1>Ndrysho kartelën</h1><p><?= $record ? ('Pacienti: ' . h($record['pacient_emri'] . ' ' . $record['pacient_mbiemri'])) : 'Përditësoni të dhënat e vizitës.' ?></p></div></section>
<?php if ($errors): ?><div class="notice notice-warning" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($record): ?>
<section class="content-card form-card">
    <form method="post" action="<?= h(APP_BASE_URL) ?>/kartela/ndrysho.php?id=<?= h($id) ?>" class="patient-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="form-grid">
            <div class="form-field"><label for="data_vizites">Data dhe ora e vizitës <span>*</span></label><input id="data_vizites" name="data_vizites" type="datetime-local" value="<?= h($values['data_vizites']) ?>" required></div>
            <?php if (!$isDoctor): ?>
            <div class="form-field"><label for="id_mjeku">Mjeku</label><select id="id_mjeku" name="id_mjeku"><option value="">Pa zgjedhur</option><?php foreach ($doctors as $d): ?><option value="<?= h($d['id_mjeku']) ?>" <?= (int) $values['id_mjeku'] === (int) $d['id_mjeku'] ? 'selected' : '' ?>><?= h($d['emri'] . ' ' . $d['mbiemri']) ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="form-field"><label for="diagnoza">Diagnoza <span>*</span></label><input id="diagnoza" name="diagnoza" maxlength="255" value="<?= h($values['diagnoza']) ?>" required></div>
            <div class="form-field"><label for="kodi_icd">Kodi ICD-10</label><input id="kodi_icd" name="kodi_icd" maxlength="30" value="<?= h($values['kodi_icd'] ?? '') ?>"></div>
            <div class="form-field"><label for="presioni_gjakut">Presioni i gjakut</label><input id="presioni_gjakut" name="presioni_gjakut" maxlength="30" value="<?= h($values['presioni_gjakut'] ?? '') ?>"></div>
            <div class="form-field"><label for="pulsi">Pulsi (bpm)</label><input id="pulsi" name="pulsi" type="number" min="25" max="250" value="<?= h($values['pulsi'] ?? '') ?>"></div>
            <div class="form-field"><label for="temperatura">Temperatura (°C)</label><input id="temperatura" name="temperatura" type="number" step="0.1" min="32" max="43" value="<?= h($values['temperatura'] ?? '') ?>"></div>
            <div class="form-field"><label for="pesha_kg">Pesha (kg)</label><input id="pesha_kg" name="pesha_kg" type="number" step="0.1" min="1" max="350" value="<?= h($values['pesha_kg'] ?? '') ?>"></div>
            <div class="form-field form-field-wide"><label for="ankesat">Ankesat</label><textarea id="ankesat" name="ankesat" rows="3"><?= h($values['ankesat'] ?? '') ?></textarea></div>
            <div class="form-field form-field-wide"><label for="terapia_receta">Terapia / Receta</label><textarea id="terapia_receta" name="terapia_receta" rows="3"><?= h($values['terapia_receta'] ?? '') ?></textarea></div>
            <div class="form-field form-field-wide"><label for="rekomandime">Rekomandime</label><textarea id="rekomandime" name="rekomandime" rows="2"><?= h($values['rekomandime'] ?? '') ?></textarea></div>
        </div>
        <div class="form-actions"><a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/kartela/shiko.php?id=<?= h($id) ?>">Anulo</a><button class="button button-primary" type="submit">Ruaj ndryshimet</button></div>
    </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
