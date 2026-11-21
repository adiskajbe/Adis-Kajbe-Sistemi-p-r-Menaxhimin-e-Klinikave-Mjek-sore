<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('pacient');
$pageTitle = 'Paneli im';
$activePage = 'home';
$user = current_auth_user();
$patientId = (int) ($user['patient_id'] ?? 0);
$appointments = [];
$upcomingCount = 0;

if ($pdo instanceof PDO && $patientId > 0) {
    try {
        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE id_pacienti = :patient_id AND data_ora >= NOW() AND statusi = 'planifikuar'");
        $countQuery->execute(['patient_id' => $patientId]);
        $upcomingCount = (int) $countQuery->fetchColumn();
        $listQuery = $pdo->prepare(
            "SELECT t.data_ora, t.statusi, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
             FROM termini t INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.id_pacienti = :patient_id AND t.data_ora >= NOW() AND t.statusi = 'planifikuar'
             ORDER BY t.data_ora ASC LIMIT 5"
        );
        $listQuery->execute(['patient_id' => $patientId]);
        $appointments = $listQuery->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i pacientit nuk u lexua: ' . $exception->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-welcome patient-welcome">
    <div><p class="eyebrow">PANELI I PACIENTIT</p><h1>Përshëndetje, <?= h($user['username'] ?? 'Pacient') ?></h1><p>Shikoni terminet tuaja dhe zgjidhni mjekun për vizitën e ardhshme.</p></div>
    <?php if ($patientId > 0): ?><a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Rezervo termin</a><?php endif; ?>
</section>

<?php if ($patientId < 1): ?>
    <div class="notice notice-warning" role="status"><strong>Llogaria juaj nuk është lidhur ende me një profil pacienti.</strong><p>Kërkoni nga administratori i klinikës ta lidhë llogarinë me profilin tuaj, pastaj do të mund të shihni dhe rezervoni terminet.</p></div>
<?php elseif (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="stat-grid patient-stats" aria-label="Përmbledhja ime">
        <article class="stat-card"><span class="stat-icon icon-appointment" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg></span><div><p>Termine të ardhshme</p><strong><?= h($upcomingCount) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-doctor" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4m8 0h4M12 14v2a5 5 0 0 0 5 5h2"/><circle cx="20" cy="21" r="1"/></svg></span><div><p>Zgjedhja e mjekut</p><strong>Ti vendos</strong></div></article>
    </section>
    <section class="section-heading"><div><p class="eyebrow">VIZITAT E PLANIFIKUARA</p><h2>Terminet e mia</h2></div><a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Shiko të gjitha <span aria-hidden="true">→</span></a></section>
    <section class="content-card">
        <div class="table-wrap"><table>
            <thead><tr><th>Data dhe ora</th><th>Mjeku</th><th>Specializimi</th><th>Statusi</th></tr></thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr><td><strong><?= h((new DateTimeImmutable($appointment['data_ora']))->format('d.m.Y H:i')) ?></strong></td><td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?></td><td><?= h($appointment['specializimi']) ?></td><td><span class="status-pill status-planifikuar">Planifikuar</span></td></tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td class="empty-state" colspan="4">Nuk keni ende termine të ardhshme. Mund të zgjidhni mjekun dhe të kërkoni termin e parë.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <section class="patient-shortcuts" aria-label="Shërbime të shpejta">
        <a class="patient-shortcut" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php"><span class="stat-icon icon-doctor" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2M12 12v6m-3-3h6"/></svg></span><span><strong>Gjej mjek</strong><small>Shiko ekipin dhe specializimet</small></span><span aria-hidden="true">→</span></a>
        <a class="patient-shortcut" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span class="stat-icon icon-appointment" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span><strong>Rezervo vizitë</strong><small>Zgjidh datën, orën dhe mjekun</small></span><span aria-hidden="true">→</span></a>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
