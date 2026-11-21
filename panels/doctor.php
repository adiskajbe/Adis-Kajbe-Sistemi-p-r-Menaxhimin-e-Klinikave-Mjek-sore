<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('mjek');
$pageTitle = 'Paneli i mjekut';
$activePage = 'home';
$user = current_auth_user();
$appointments = [];
$patientCount = 0;
$upcomingCount = 0;
if ($pdo instanceof PDO && !empty($user['doctor_id'])) {
    try {
        $patientCountQuery = $pdo->prepare('SELECT COUNT(DISTINCT id_pacienti) FROM termini WHERE id_mjeku = :doctor_id');
        $patientCountQuery->execute(['doctor_id' => (int) $user['doctor_id']]);
        $patientCount = (int) $patientCountQuery->fetchColumn();
        $upcomingQuery = $pdo->prepare("SELECT COUNT(*) FROM termini WHERE id_mjeku = :doctor_id AND data_ora >= NOW() AND statusi = 'planifikuar'");
        $upcomingQuery->execute(['doctor_id' => (int) $user['doctor_id']]);
        $upcomingCount = (int) $upcomingQuery->fetchColumn();
        $list = $pdo->prepare(
            'SELECT t.data_ora, t.statusi, p.emri, p.mbiemri
             FROM termini t INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             WHERE t.id_mjeku = :doctor_id AND t.data_ora >= NOW()
             ORDER BY t.data_ora ASC LIMIT 6'
        );
        $list->execute(['doctor_id' => (int) $user['doctor_id']]);
        $appointments = $list->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i mjekut nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">PANELI I MJEKUT</p><h1>Përshëndetje, <?= h($user['username']) ?></h1><p>Shikoni pacientët dhe terminet e lidhura me ju.</p></div></section>
<section class="stat-grid"><article class="stat-card"><span class="stat-icon icon-patient" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg></span><div><p>Pacientë të lidhur me ju</p><strong><?= h($patientCount) ?></strong></div></article><article class="stat-card"><span class="stat-icon icon-appointment" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg></span><div><p>Termine të ardhshme</p><strong><?= h($upcomingCount) ?></strong></div></article></section>
<section class="section-heading"><div><p class="eyebrow">ORARI</p><h2>Terminet e ardhshme</h2></div><a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Shiko të gjitha</a></section>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Data dhe ora</th><th>Pacienti</th><th>Statusi</th></tr></thead><tbody>
<?php foreach ($appointments as $appointment): ?><tr><td><?= h((new DateTimeImmutable($appointment['data_ora']))->format('d.m.Y H:i')) ?></td><td><?= h($appointment['emri'] . ' ' . $appointment['mbiemri']) ?></td><td><?= h($appointment['statusi']) ?></td></tr><?php endforeach; ?>
<?php if (!$appointments): ?><tr><td class="empty-state" colspan="3">Nuk ka termine të ardhshme.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
