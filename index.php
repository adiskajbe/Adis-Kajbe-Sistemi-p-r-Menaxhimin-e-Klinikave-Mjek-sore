<?php
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = current_auth_user();
if ($currentUser && $currentUser['role'] === 'mjek') {
    redirect_to('/panels/doctor.php');
}
if ($currentUser && $currentUser['role'] === 'recepsionist') {
    redirect_to('/panels/receptionist.php');
}
if ($currentUser && $currentUser['role'] === 'pacient') {
    redirect_to('/panels/patient.php');
}
$pageTitle = 'Paneli i klinikës';
$activePage = 'home';
$counts = ['pacienti' => 0, 'mjeku' => 0, 'termini_sot' => 0, 'termini_ardhshem' => 0];
$todayAppointments = [];
$weekdayLabels = [1 => 'Hën', 2 => 'Mar', 3 => 'Mër', 4 => 'Enj', 5 => 'Pre', 6 => 'Sht', 7 => 'Die'];
$today = new DateTimeImmutable('today');
$weekDays = [];

for ($offset = 0; $offset < 7; $offset++) {
    $date = $today->modify('+' . $offset . ' days');
    $weekDays[] = [
        'date' => $date->format('Y-m-d'),
        'label' => $weekdayLabels[(int) $date->format('N')],
        'number' => $date->format('d'),
        'count' => 0,
        'is_today' => $offset === 0,
    ];
}

if ($pdo instanceof PDO) {
    try {
        $counts['pacienti'] = (int) $pdo->query('SELECT COUNT(*) FROM pacienti')->fetchColumn();
        $counts['mjeku'] = (int) $pdo->query('SELECT COUNT(*) FROM mjeku')->fetchColumn();
        $counts['termini_sot'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM termini
             WHERE statusi <> 'anuluar'
               AND data_ora >= CURDATE()
               AND data_ora < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        )->fetchColumn();
        $counts['termini_ardhshem'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM termini
             WHERE statusi = 'planifikuar' AND data_ora >= NOW()"
        )->fetchColumn();

        $todayQuery = $pdo->query(
            'SELECT t.data_ora, t.statusi,
                    p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
             FROM termini t
             INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.data_ora >= CURDATE() AND t.data_ora < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             ORDER BY t.data_ora ASC LIMIT 8'
        );
        $todayAppointments = $todayQuery->fetchAll();

        $weekQuery = $pdo->query(
            "SELECT DATE(data_ora) AS dita, COUNT(*) AS numri
             FROM termini
             WHERE statusi = 'planifikuar'
               AND data_ora >= CURDATE()
               AND data_ora < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(data_ora)"
        );
        $weekCounts = $weekQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($weekDays as &$day) {
            $day['count'] = (int) ($weekCounts[$day['date']] ?? 0);
        }
        unset($day);
    } catch (Throwable $exception) {
        error_log('Leximi i panelit të klinikës dështoi: ' . $exception->getMessage());
        $databaseError = 'Skema nuk duket e plotë. Importoni database/klinika_db.sql.';
        $pdo = null;
    }
}

$maxWeekCount = max(1, ...array_column($weekDays, 'count'));
$statusLabels = ['planifikuar' => 'Planifikuar', 'perfunduar' => 'Përfunduar', 'anuluar' => 'Anuluar'];
include __DIR__ . '/includes/header.php';
?>
<section class="dashboard-welcome">
    <div>
        <p class="eyebrow">PANELI I KLINIKËS</p>
        <h1>Mirë se erdhe, <?= h($currentUser['username'] ?? 'Administrator') ?></h1>
        <p>Përmbledhja e sotme dhe orari i ardhshëm i klinikës.</p>
    </div>
    <div class="dashboard-actions">
        <a class="button button-quiet" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php">Regjistro pacient</a>
        <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> Cakto termin</a>
    </div>
</section>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/includes/database_notice.php'; ?>
<?php else: ?>
    <section class="stat-grid dashboard-stats" aria-label="Përmbledhje e klinikës">
        <article class="stat-card"><span class="stat-icon icon-appointment" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg></span><div><p>Termine sot</p><strong><?= h($counts['termini_sot']) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-doctor" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2M12 12v5m-2.5-2.5h5"/></svg></span><div><p>Termine të ardhshme</p><strong><?= h($counts['termini_ardhshem']) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-patient" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg></span><div><p>Pacientë gjithsej</p><strong><?= h($counts['pacienti']) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-doctor" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4m8 0h4M12 14v2a5 5 0 0 0 5 5h2"/><circle cx="20" cy="21" r="1"/></svg></span><div><p>Mjekë gjithsej</p><strong><?= h($counts['mjeku']) ?></strong></div></article>
    </section>

    <section class="dashboard-grid" aria-label="Orari dhe përmbledhja javore">
        <div class="content-card dashboard-agenda">
            <div class="card-heading">
                <div><p class="eyebrow">SOT · <?= h($today->format('d.m.Y')) ?></p><h2>Terminet e sotme</h2></div>
                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Shiko orarin <span aria-hidden="true">→</span></a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ora</th><th>Pacienti</th><th>Mjeku</th><th>Statusi</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayAppointments as $appointment): ?>
                        <tr>
                            <td><strong><?= h((new DateTimeImmutable($appointment['data_ora']))->format('H:i')) ?></strong></td>
                            <td><?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></td>
                            <td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?><small class="muted"><?= h($appointment['specializimi']) ?></small></td>
                            <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h($statusLabels[$appointment['statusi']] ?? $appointment['statusi']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$todayAppointments): ?>
                        <tr><td class="empty-state" colspan="4">Nuk ka termine të regjistruara për sot.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="content-card dashboard-week">
            <div class="card-heading">
                <div><p class="eyebrow">7 DITËT E ARDHSHME</p><h2>Ngarkesa javore</h2></div>
            </div>
            <div class="week-chart" role="list" aria-label="Terminet e planifikuara për shtatë ditët e ardhshme">
                <?php foreach ($weekDays as $day): ?>
                    <?php $barHeight = $day['count'] > 0 ? max(14, (int) round(($day['count'] / $maxWeekCount) * 100)) : 8; ?>
                    <div class="week-day<?= $day['is_today'] ? ' is-today' : '' ?>" role="listitem" aria-label="<?= h($day['label'] . ' ' . $day['number'] . ': ' . $day['count'] . ' termine') ?>">
                        <span class="week-day-label"><?= h($day['label']) ?></span>
                        <strong class="week-day-date"><?= h($day['number']) ?></strong>
                        <span class="week-bar" aria-hidden="true"><span style="height: <?= h($barHeight) ?>%"></span></span>
                        <span class="week-day-count"><?= h($day['count']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="week-caption">Numri i termineve të planifikuara për secilën ditë.</p>
            <a class="button button-secondary week-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Hap listën e termineve</a>
        </aside>
    </section>
<?php endif; ?>

<section class="notice notice-info dashboard-note">
    <strong>Për përdorim mësimor</strong>
    <p>Ky prototip nuk është sistem klinik i certifikuar dhe nuk duhet të përdoret për të dhëna reale mjekësore.</p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
