<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('mjek');
$pageTitle = 'Paneli i mjekut';
$activePage = 'home';
$user = current_auth_user();
$doctorId = (int) ($user['doctor_id'] ?? 0);
$appointments = [];
$recentRecords = [];
$patientCount = 0;
$upcomingCount = 0;
$todayCount = 0;
$ehrMonthCount = 0;

if ($pdo instanceof PDO && $doctorId > 0) {
    try {
        $q1 = $pdo->prepare('SELECT COUNT(DISTINCT id_pacienti) FROM termini WHERE id_mjeku = :doctor_id');
        $q1->execute(['doctor_id' => $doctorId]);
        $patientCount = (int) $q1->fetchColumn();

        $q2 = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE id_mjeku = :doctor_id AND data_ora >= NOW() AND statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje')");
        $q2->execute(['doctor_id' => $doctorId]);
        $upcomingCount = (int) $q2->fetchColumn();

        $q3 = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE id_mjeku = :doctor_id AND DATE(data_ora) = CURDATE() AND statusi <> 'anuluar'");
        $q3->execute(['doctor_id' => $doctorId]);
        $todayCount = (int) $q3->fetchColumn();

        $q4 = $pdo->prepare("SELECT COUNT(*) FROM kartela_mjekesore WHERE id_mjeku = :doctor_id AND YEAR(data_vizites) = YEAR(CURDATE()) AND MONTH(data_vizites) = MONTH(CURDATE())");
        $q4->execute(['doctor_id' => $doctorId]);
        $ehrMonthCount = (int) $q4->fetchColumn();

        $list = $pdo->prepare(
            "SELECT t.id_termini, t.id_pacienti, t.data_ora, t.statusi, t.lloji_vizites, t.arsyeja,
                    p.emri, p.mbiemri, p.alergjite
             FROM termini t
             INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             WHERE t.id_mjeku = :doctor_id AND t.data_ora >= CURDATE() AND t.statusi <> 'anuluar'
             ORDER BY t.data_ora ASC LIMIT 10"
        );
        $list->execute(['doctor_id' => $doctorId]);
        $appointments = $list->fetchAll();

        $recStmt = $pdo->prepare(
            'SELECT k.id_kartela, k.id_pacienti, k.data_vizites, k.diagnoza, k.kodi_icd, p.emri, p.mbiemri
             FROM kartela_mjekesore k
             INNER JOIN pacienti p ON p.id_pacienti = k.id_pacienti
             WHERE k.id_mjeku = :doctor_id
             ORDER BY k.data_vizites DESC LIMIT 5'
        );
        $recStmt->execute(['doctor_id' => $doctorId]);
        $recentRecords = $recStmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i mjekut nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div><p class="eyebrow">PANELI I MJEKUT</p><h1>Përshëndetje, Dr. <?= h($user['username']) ?></h1><p>Menaxhoni vizitat e sotme, hapni kartelat EHR dhe dokumentoni diagnozat.</p></div>
    <div class="page-head-actions">
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Orari i plotë</a>
        <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php"><span aria-hidden="true">+</span> Shkruaj vizitë EHR</a>
    </div>
</section>

<?php if ($doctorId < 1): ?>
    <div class="notice notice-warning" role="status">Llogaria juaj nuk është lidhur ende me një profil mjeku. Kontaktoni administratorin.</div>
<?php else: ?>
    <section class="kpi-grid">
        <article class="kpi-card"><span class="kpi-label">Termine sot</span><strong class="kpi-value tabular-nums"><?= h($todayCount) ?></strong><span class="kpi-meta">Vizita në agjendën e ditës</span></article>
        <article class="kpi-card"><span class="kpi-label">Termine të ardhshme</span><strong class="kpi-value tabular-nums"><?= h($upcomingCount) ?></strong><span class="kpi-meta">Të planifikuara dhe në pritje</span></article>
        <article class="kpi-card"><span class="kpi-label">Pacientët e mi</span><strong class="kpi-value tabular-nums"><?= h($patientCount) ?></strong><span class="kpi-meta">Të lidhur me vizitat tuaja</span></article>
        <article class="kpi-card"><span class="kpi-label">Kartela EHR këtë muaj</span><strong class="kpi-value tabular-nums"><?= h($ehrMonthCount) ?></strong><span class="kpi-meta">Dokumentime klinike</span></article>
    </section>

    <section class="dashboard-split">
        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">RADHA E VIZITAVE</p><h2>Terminet e ardhshme</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Të gjitha →</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Data dhe ora</th><th>Pacienti</th><th>Lloji &amp; Arsyeja</th><th>Statusi</th><th class="actions-heading">Veprime</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td class="tabular-nums"><strong><?= h(format_date_sq($appointment['data_ora'], 'd.m.Y H:i')) ?></strong></td>
                        <td>
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($appointment['id_pacienti']) ?>">
                                <?= h($appointment['emri'] . ' ' . $appointment['mbiemri']) ?>
                            </a>
                            <?php if (!empty($appointment['alergjite']) && mb_strtolower($appointment['alergjite']) !== 'asnjë e njohur'): ?>
                                <small class="muted text-alert">Alergji: <?= h($appointment['alergjite']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h($appointment['lloji_vizites']) ?></strong><?php if (!empty($appointment['arsyeja'])): ?><small class="muted"><?= h($appointment['arsyeja']) ?></small><?php endif; ?></td>
                        <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span></td>
                        <td class="actions-cell">
                            <a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">Shkruaj EHR</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$appointments): ?><tr><td class="empty-state" colspan="5">Nuk ka termine të ardhshme.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>

        <article class="content-card">
            <div class="card-heading"><div><p class="eyebrow">DOKUMENTIMI</p><h2>Kartelat e fundit</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/lista.php">Shiko të gjitha →</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Data</th><th>Pacienti</th><th>Diagnoza</th></tr></thead>
                <tbody>
                <?php foreach ($recentRecords as $rec): ?>
                    <tr>
                        <td class="tabular-nums"><?= h(format_date_sq($rec['data_vizites'], 'd.m.Y')) ?></td>
                        <td><?= h($rec['emri'] . ' ' . $rec['mbiemri']) ?></td>
                        <td><a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shiko.php?id=<?= h($rec['id_kartela']) ?>"><?= h($rec['diagnoza']) ?></a><?= $rec['kodi_icd'] ? '<small class="muted">ICD: ' . h($rec['kodi_icd']) . '</small>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentRecords): ?><tr><td class="empty-state" colspan="3">Ende nuk keni regjistruar kartela mjekësore.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </article>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
