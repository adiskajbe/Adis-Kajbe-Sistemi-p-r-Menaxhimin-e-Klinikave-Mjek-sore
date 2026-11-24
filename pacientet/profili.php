<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'mjek', 'recepsionist');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isDoctor = auth_user_has_role('mjek');
$isReceptionist = auth_user_has_role('recepsionist');
$doctorId = (int) (current_auth_user()['doctor_id'] ?? 0);

$patient = null;
$appointments = [];
$ehrRecords = [];
$invoices = [];
$error = '';

if (!$id || $id < 1) {
    http_response_code(400);
    $error = 'ID-ja e pacientit nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        if ($isDoctor) {
            $accessStmt = $pdo->prepare('SELECT 1 FROM termini WHERE id_pacienti = :pid AND id_mjeku = :did LIMIT 1');
            $accessStmt->execute(['pid' => $id, 'did' => $doctorId]);
            if (!$accessStmt->fetchColumn()) {
                http_response_code(403);
                $error = 'Nuk keni qasje në profilin e këtij pacienti.';
            }
        }
        if ($error === '') {
            $stmt = $pdo->prepare('SELECT * FROM pacienti WHERE id_pacienti = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $patient = $stmt->fetch();
            if (!$patient) {
                http_response_code(404);
                $error = 'Pacienti nuk u gjet.';
            } else {
                $apptStmt = $pdo->prepare(
                    'SELECT t.id_termini, t.data_ora, t.statusi, t.lloji_vizites, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
                     FROM termini t INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
                     WHERE t.id_pacienti = :pid ORDER BY t.data_ora DESC LIMIT 12'
                );
                $apptStmt->execute(['pid' => $id]);
                $appointments = $apptStmt->fetchAll();

                if (!$isReceptionist) {
                    $ehrStmt = $pdo->prepare(
                        'SELECT k.*, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
                         FROM kartela_mjekesore k LEFT JOIN mjeku m ON m.id_mjeku = k.id_mjeku
                         WHERE k.id_pacienti = :pid ORDER BY k.data_vizites DESC'
                    );
                    $ehrStmt->execute(['pid' => $id]);
                    $ehrRecords = $ehrStmt->fetchAll();
                }

                if (!$isDoctor) {
                    $invStmt = $pdo->prepare(
                        'SELECT * FROM fatura WHERE id_pacienti = :pid ORDER BY data_fatures DESC, id_fatura DESC'
                    );
                    $invStmt->execute(['pid' => $id]);
                    $invoices = $invStmt->fetchAll();
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Leximi i profilit të pacientit dështoi: ' . $exception->getMessage());
        $error = 'Nuk u arrit leximi i të dhënave të pacientit.';
    }
}

$pageTitle = $patient ? ($patient['emri'] . ' ' . $patient['mbiemri']) : 'Profili i pacientit';
$activePage = 'patients';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">DOSJA E PACIENTIT</p>
        <h1><?= h($patient ? ($patient['emri'] . ' ' . $patient['mbiemri']) : 'Profili i pacientit') ?></h1>
        <p><?= $patient ? ('ID #' . h($patient['id_pacienti']) . ' · Tel: ' . h($patient['telefoni'])) : 'Pamja e plotë e të dhënave klinike dhe administrative.' ?></p>
    </div>
    <?php if ($patient): ?>
        <div class="page-head-actions">
            <?php if (!$isDoctor): ?>
                <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/pacientet/ndrysho.php?id=<?= h($patient['id_pacienti']) ?>">Ndrysho të dhënat</a>
                <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php?id_pacienti=<?= h($patient['id_pacienti']) ?>">Krijo faturë</a>
            <?php endif; ?>
            <?php if (!$isReceptionist): ?>
                <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php?id_pacienti=<?= h($patient['id_pacienti']) ?>"><span aria-hidden="true">+</span> Shkruaj vizitë EHR</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($error !== ''): ?>
    <div class="notice notice-warning" role="alert"><?= h($error) ?></div>
<?php elseif ($patient): ?>
    <?php $age = calculate_age($patient['datelindja'] ?? null); ?>
    <section class="profile-summary-grid">
        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">TË DHËNA DEMOGRAFIKE</p><h2>Identiteti &amp; Kontakti</h2></div></div>
            <dl class="detail-list">
                <div><dt>Datëlindja / Mosha</dt><dd class="tabular-nums"><?= h(format_date_sq($patient['datelindja'] ?? null)) ?><?= $age !== null ? ' (' . h($age) . ' vjeç)' : '' ?></dd></div>
                <div><dt>Gjinia</dt><dd><?= h($patient['gjinia'] ?: '—') ?></dd></div>
                <div><dt>Nr. Personal</dt><dd class="tabular-nums"><?= h($patient['nr_personal'] ?: '—') ?></dd></div>
                <div><dt>Email</dt><dd><?= h($patient['email'] ?: '—') ?></dd></div>
                <div><dt>Adresa</dt><dd><?= h($patient['adresa'] ?: '—') ?></dd></div>
                <div><dt>Kontakti emergjent</dt><dd><?= h($patient['kontakti_emergjent'] ?: '—') ?></dd></div>
            </dl>
        </article>

        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">PROFILI KLINIK</p><h2>Grupi i gjakut &amp; Alergjitë</h2></div></div>
            <dl class="detail-list">
                <div><dt>Grupi i gjakut</dt><dd><strong><?= h($patient['grupi_gjakut'] ?: 'I papërcaktuar') ?></strong></dd></div>
                <div><dt>Alergji të njohura</dt><dd class="<?= !empty($patient['alergjite']) && mb_strtolower($patient['alergjite']) !== 'asnjë e njohur' ? 'text-alert' : '' ?>"><?= h($patient['alergjite'] ?: 'Asnjë e regjistruar') ?></dd></div>
                <div><dt>Termine gjithsej</dt><dd class="tabular-nums"><?= h(count($appointments)) ?></dd></div>
                <?php if (!$isReceptionist): ?>
                    <div><dt>Vizita EHR</dt><dd class="tabular-nums"><?= h(count($ehrRecords)) ?></dd></div>
                <?php endif; ?>
            </dl>
        </article>
    </section>

    <?php if (!$isReceptionist): ?>
        <section class="content-card mt-section">
            <div class="card-heading">
                <div><p class="eyebrow">KARTELA MJEKËSORE (EHR)</p><h2>Historia e vizitave &amp; diagnozave</h2></div>
                <a class="button button-secondary button-sm" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php?id_pacienti=<?= h($patient['id_pacienti']) ?>">+ Vizitë e re</a>
            </div>
            <?php if ($ehrRecords): ?>
                <div class="ehr-timeline">
                    <?php foreach ($ehrRecords as $rec): ?>
                        <article class="ehr-entry">
                            <div class="ehr-entry-head">
                                <div>
                                    <span class="ehr-date tabular-nums"><?= h(format_date_sq($rec['data_vizites'], 'd.m.Y H:i')) ?></span>
                                    <h3><?= h($rec['diagnoza']) ?><?= $rec['kodi_icd'] ? ' · ICD: ' . h($rec['kodi_icd']) : '' ?></h3>
                                    <p class="muted">Mjeku: <?= h($rec['mjek_emri'] ? ($rec['mjek_emri'] . ' ' . $rec['mjek_mbiemri']) : '—') ?></p>
                                </div>
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shiko.php?id=<?= h($rec['id_kartela']) ?>">Hap raportin →</a>
                            </div>
                            <div class="ehr-vitals-row tabular-nums">
                                <?php if ($rec['presioni_gjakut']): ?><span>TA: <strong><?= h($rec['presioni_gjakut']) ?></strong></span><?php endif; ?>
                                <?php if ($rec['pulsi']): ?><span>Pulsi: <strong><?= h($rec['pulsi']) ?> bpm</strong></span><?php endif; ?>
                                <?php if ($rec['temperatura']): ?><span>Temp: <strong><?= h($rec['temperatura']) ?> °C</strong></span><?php endif; ?>
                                <?php if ($rec['pesha_kg']): ?><span>Pesha: <strong><?= h($rec['pesha_kg']) ?> kg</strong></span><?php endif; ?>
                            </div>
                            <?php if ($rec['terapia_receta']): ?>
                                <p class="ehr-rx"><strong>Terapia / Receta:</strong> <?= h($rec['terapia_receta']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Nuk ka ende shënime mjekësore në kartelën e këtij pacienti.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="dashboard-split mt-section">
        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">ORARI</p><h2>Terminet e pacientit</h2></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Data dhe ora</th><th>Mjeku</th><th>Lloji</th><th>Statusi</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appt): ?>
                    <tr>
                        <td class="tabular-nums"><?= h(format_date_sq($appt['data_ora'], 'd.m.Y H:i')) ?></td>
                        <td><?= h($appt['mjek_emri'] . ' ' . $appt['mjek_mbiemri']) ?></td>
                        <td><?= h($appt['lloji_vizites']) ?></td>
                        <td><span class="status-pill status-<?= h($appt['statusi']) ?>"><?= h(appointment_status_label($appt['statusi'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$appointments): ?><tr><td class="empty-state" colspan="4">Nuk ka termine të regjistruara.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>

        <?php if (!$isDoctor): ?>
        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">FINANCAT</p><h2>Faturat e pacientit</h2></div></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Nr.</th><th>Data</th><th>Totali</th><th>Statusi</th><th class="actions-heading">Veprim</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td class="tabular-nums"><strong><?= h($inv['nr_fatures']) ?></strong></td>
                        <td class="tabular-nums"><?= h(format_date_sq($inv['data_fatures'])) ?></td>
                        <td class="tabular-nums"><?= h(format_money($inv['shuma_totale'])) ?></td>
                        <td><span class="status-pill status-<?= h($inv['statusi']) ?>"><?= h(invoice_status_label($inv['statusi'])) ?></span></td>
                        <td class="actions-cell"><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shiko.php?id=<?= h($inv['id_fatura']) ?>">Shiko</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$invoices): ?><tr><td class="empty-state" colspan="5">Nuk ka fatura të lëshuara.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
