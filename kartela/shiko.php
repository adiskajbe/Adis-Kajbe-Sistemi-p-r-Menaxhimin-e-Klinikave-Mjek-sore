<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek', 'pacient');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isDoctor = auth_user_has_role('mjek');
$isPatient = auth_user_has_role('pacient');
$authUser = current_auth_user();
$record = null;
$error = '';

if (!$id || $id < 1) {
    http_response_code(400);
    $error = 'ID-ja e kartelës nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare(
            'SELECT k.*, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri, p.datelindja, p.grupi_gjakut, p.alergjite, p.nr_personal,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi, m.nr_dhome
             FROM kartela_mjekesore k
             INNER JOIN pacienti p ON p.id_pacienti = k.id_pacienti
             LEFT JOIN mjeku m ON m.id_mjeku = k.id_mjeku
             WHERE k.id_kartela = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();
        if (!$record) {
            http_response_code(404);
            $error = 'Kartela mjekësore nuk u gjet.';
        } elseif ($isPatient && (int) $record['id_pacienti'] !== (int) ($authUser['patient_id'] ?? 0)) {
            http_response_code(403);
            $error = 'Nuk keni leje për të parë këtë kartelë.';
            $record = null;
        } elseif ($isDoctor && (int) $record['id_mjeku'] !== (int) ($authUser['doctor_id'] ?? 0)) {
            $checkOwn = $pdo->prepare('SELECT 1 FROM termini WHERE id_pacienti = :pid AND id_mjeku = :did LIMIT 1');
            $checkOwn->execute(['pid' => $record['id_pacienti'], 'did' => (int) ($authUser['doctor_id'] ?? 0)]);
            if (!$checkOwn->fetchColumn()) {
                http_response_code(403);
                $error = 'Nuk keni leje për të parë këtë kartelë.';
                $record = null;
            }
        }
    } catch (Throwable $exception) {
        error_log('Leximi i kartelës dështoi: ' . $exception->getMessage());
        $error = 'Nuk u arrit leximi i kartelës.';
    }
}

$pageTitle = $record ? ('Raporti EHR #' . $record['id_kartela']) : 'Kartela mjekësore';
$activePage = 'ehr';
$flash = take_flash();
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head no-print">
    <div><p class="eyebrow">DOKUMENTIMI KLINIK (EHR)</p><h1>Raporti i vizitës mjekësore</h1><p>Detajet e ekzaminimit, shenjat vitale dhe terapia e përshkruar.</p></div>
    <?php if ($record): ?>
        <div class="page-head-actions">
            <?php if (!$isPatient): ?>
                <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/kartela/ndrysho.php?id=<?= h($record['id_kartela']) ?>">Ndrysho</a>
            <?php endif; ?>
            <button class="button button-primary" type="button" data-print-trigger>Printo raportin</button>
        </div>
    <?php endif; ?>
</section>

<?php if ($flash): ?><div class="notice notice-success no-print" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="notice notice-warning" role="alert"><?= h($error) ?></div>
<?php elseif ($record): ?>
    <article class="content-card printable-doc">
        <div class="doc-header">
            <div>
                <p class="eyebrow">KLINIKA MJEKËSORE · RAPORT KLINIK</p>
                <h2><?= h($record['diagnoza']) ?></h2>
                <p class="muted"><?= $record['kodi_icd'] ? ('Kodi ICD-10: ' . h($record['kodi_icd']) . ' · ') : '' ?>Data e vizitës: <span class="tabular-nums"><?= h(format_date_sq($record['data_vizites'], 'd.m.Y H:i')) ?></span></p>
            </div>
            <div class="doc-id tabular-nums">EHR #<?= h($record['id_kartela']) ?></div>
        </div>

        <div class="doc-meta-grid">
            <div>
                <p class="eyebrow">PACIENTI</p>
                <strong><?= h($record['pacient_emri'] . ' ' . $record['pacient_mbiemri']) ?></strong>
                <small class="muted">Grupi i gjakut: <?= h($record['grupi_gjakut'] ?: '—') ?> · Nr. Personal: <?= h($record['nr_personal'] ?: '—') ?></small>
                <?php if (!empty($record['alergjite'])): ?><small class="muted text-alert">Alergjitë: <?= h($record['alergjite']) ?></small><?php endif; ?>
            </div>
            <div>
                <p class="eyebrow">MJEKU EKZAMINUES</p>
                <strong><?= h($record['mjek_emri'] ? ('Dr. ' . $record['mjek_emri'] . ' ' . $record['mjek_mbiemri']) : '—') ?></strong>
                <small class="muted"><?= h($record['specializimi'] ?: '') ?><?= $record['nr_dhome'] ? (' · ' . h($record['nr_dhome'])) : '' ?></small>
            </div>
        </div>

        <div class="vitals-strip tabular-nums">
            <div class="vital-box"><span>Presioni i gjakut</span><strong><?= h($record['presioni_gjakut'] ?: '—') ?></strong></div>
            <div class="vital-box"><span>Pulsi</span><strong><?= $record['pulsi'] ? h($record['pulsi'] . ' bpm') : '—' ?></strong></div>
            <div class="vital-box"><span>Temperatura</span><strong><?= $record['temperatura'] ? h($record['temperatura'] . ' °C') : '—' ?></strong></div>
            <div class="vital-box"><span>Pesha</span><strong><?= $record['pesha_kg'] ? h($record['pesha_kg'] . ' kg') : '—' ?></strong></div>
        </div>

        <div class="doc-sections">
            <section><h3>Ankesat dhe anamneza</h3><p><?= nl2br(h($record['ankesat'] ?: 'Nuk ka shënime shtesë.')) ?></p></section>
            <section><h3>Terapia dhe receta</h3><p><?= nl2br(h($record['terapia_receta'] ?: 'Nuk është përshkruar terapi.')) ?></p></section>
            <section><h3>Rekomandimet</h3><p><?= nl2br(h($record['rekomandime'] ?: 'Nuk ka rekomandime shtesë.')) ?></p></section>
        </div>
    </article>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
