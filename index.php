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

$pageTitle = 'Analitika & Paneli';
$activePage = 'home';
$flash = take_flash();
$counts = [
    'pacienti' => 0,
    'mjeku' => 0,
    'termini_sot' => 0,
    'termini_ardhshem' => 0,
    'te_ardhura_muaj' => 0.0,
    'fatura_papaguara' => 0,
    'kartela_muaj' => 0,
];
$todayAppointments = [];
$unpaidInvoices = [];
$statusCounts = [
    'planifikuar' => 0,
    'konfirmuar' => 0,
    'ne_pritje' => 0,
    'perfunduar' => 0,
    'anuluar' => 0,
];
$weekdayLabels = [1 => 'Hën', 2 => 'Mar', 3 => 'Mër', 4 => 'Enj', 5 => 'Pre', 6 => 'Sht', 7 => 'Die'];
$today = new DateTimeImmutable('today');
$weekDays = [];

for ($offset = 0; $offset < 7; $offset++) {
    $date = $today->modify('+' . $offset . ' days');
    $weekDays[] = [
        'date' => $date->format('Y-m-d'),
        'label' => $weekdayLabels[(int) $date->format('N')],
        'number' => $date->format('d.m'),
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
             WHERE statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje') AND data_ora >= NOW()"
        )->fetchColumn();

        $counts['te_ardhura_muaj'] = (float) $pdo->query(
            "SELECT COALESCE(SUM(shuma_paguar), 0) FROM fatura
             WHERE statusi <> 'anuluar'
               AND YEAR(data_fatures) = YEAR(CURDATE())
               AND MONTH(data_fatures) = MONTH(CURDATE())"
        )->fetchColumn();

        $counts['fatura_papaguara'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM fatura WHERE statusi IN ('papaguar', 'pjeserisht')"
        )->fetchColumn();

        $counts['kartela_muaj'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM kartela_mjekesore
             WHERE YEAR(data_vizites) = YEAR(CURDATE())
               AND MONTH(data_vizites) = MONTH(CURDATE())"
        )->fetchColumn();

        $todayQuery = $pdo->query(
            'SELECT t.id_termini, t.id_pacienti, t.data_ora, t.statusi, t.lloji_vizites,
                    p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.specializimi
             FROM termini t
             INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.data_ora >= CURDATE() AND t.data_ora < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             ORDER BY t.data_ora ASC LIMIT 10'
        );
        $todayAppointments = $todayQuery->fetchAll();

        $weekQuery = $pdo->query(
            "SELECT DATE(data_ora) AS dita, COUNT(*) AS numri
             FROM termini
             WHERE statusi <> 'anuluar'
               AND data_ora >= CURDATE()
               AND data_ora < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(data_ora)"
        );
        $weekCounts = $weekQuery->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($weekDays as &$day) {
            $day['count'] = (int) ($weekCounts[$day['date']] ?? 0);
        }
        unset($day);

        $statusQuery = $pdo->query('SELECT statusi, COUNT(*) FROM termini GROUP BY statusi');
        foreach ($statusQuery->fetchAll(PDO::FETCH_KEY_PAIR) as $st => $cnt) {
            if (isset($statusCounts[$st])) {
                $statusCounts[$st] = (int) $cnt;
            }
        }

        $unpaidInvoices = $pdo->query(
            "SELECT f.id_fatura, f.nr_fatures, f.data_fatures, f.shuma_totale, f.shuma_paguar, f.statusi,
                    p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri
             FROM fatura f
             INNER JOIN pacienti p ON p.id_pacienti = f.id_pacienti
             WHERE f.statusi IN ('papaguar', 'pjeserisht')
             ORDER BY f.data_fatures ASC, f.id_fatura DESC LIMIT 5"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Leximi i panelit të klinikës dështoi: ' . $exception->getMessage());
        $databaseError = 'Skema nuk duket e plotë. Importoni database/klinika_db.sql ose ekzekutoni migrimin 005.';
        $pdo = null;
    }
}

$weekChartJson = json_encode(array_map(static fn ($d) => [
    'label' => $d['label'],
    'sublabel' => $d['number'],
    'value' => $d['count'],
    'today' => $d['is_today'],
], $weekDays), JSON_UNESCAPED_UNICODE);

$statusChartJson = json_encode([
    ['label' => 'Planifikuar', 'value' => $statusCounts['planifikuar'], 'tone' => 'teal'],
    ['label' => 'Konfirmuar', 'value' => $statusCounts['konfirmuar'], 'tone' => 'sky'],
    ['label' => 'Në pritje', 'value' => $statusCounts['ne_pritje'], 'tone' => 'amber'],
    ['label' => 'Përfunduar', 'value' => $statusCounts['perfunduar'], 'tone' => 'emerald'],
    ['label' => 'Anuluar', 'value' => $statusCounts['anuluar'], 'tone' => 'rose'],
], JSON_UNESCAPED_UNICODE);

include __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">PANELI KRYESOR · <?= h($today->format('d.m.Y')) ?></p>
        <h1>Mirë se erdhe, <?= h($currentUser['username'] ?? 'Administrator') ?></h1>
        <p>Pamje e unifikuar e operacioneve klinike, termineve, kartelave EHR dhe performancës financiare.</p>
    </div>
    <div class="page-head-actions">
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php">Regjistro pacient</a>
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php">Krijo faturë</a>
        <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php"><span aria-hidden="true">+</span> Cakto termin</a>
    </div>
</section>

<?php if ($flash): ?>
    <div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status"><?= h($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/includes/database_notice.php'; ?>
<?php else: ?>
    <section class="kpi-grid" aria-label="Treguesit kryesorë të klinikës">
        <article class="kpi-card">
            <span class="kpi-label">Termine sot</span>
            <strong class="kpi-value tabular-nums"><?= h($counts['termini_sot']) ?></strong>
            <span class="kpi-meta"><?= h($counts['termini_ardhshem']) ?> aktive në vijim</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Pacientë të regjistruar</span>
            <strong class="kpi-value tabular-nums"><?= h($counts['pacienti']) ?></strong>
            <span class="kpi-meta"><?= h($counts['mjeku']) ?> mjekë aktivë</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Vizita EHR këtë muaj</span>
            <strong class="kpi-value tabular-nums"><?= h($counts['kartela_muaj']) ?></strong>
            <span class="kpi-meta">Kartela mjekësore të dokumentuara</span>
        </article>
        <article class="kpi-card">
            <span class="kpi-label">Arkëtimet e muajit</span>
            <strong class="kpi-value tabular-nums"><?= h(format_money($counts['te_ardhura_muaj'])) ?></strong>
            <span class="kpi-meta"><?= h($counts['fatura_papaguara']) ?> fatura në pritje pagese</span>
        </article>
    </section>

    <section class="analytics-grid" aria-label="Analitikat vizuale">
        <article class="content-card">
            <div class="card-heading">
                <div><p class="eyebrow">7 DITËT E ARDHSHME</p><h2>Ngarkesa ditore e termineve</h2></div>
                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php?pamja=java">Kalendari javor →</a>
            </div>
            <div class="chart-host" data-bar-chart="<?= h($weekChartJson) ?>"></div>
        </article>

        <article class="content-card">
            <div class="card-heading">
                <div><p class="eyebrow">SHPËRNDARJA</p><h2>Statusi i termineve</h2></div>
                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Lista e plotë →</a>
            </div>
            <div class="chart-host" data-status-chart="<?= h($statusChartJson) ?>"></div>
        </article>
    </section>

    <section class="dashboard-split">
        <article class="content-card">
            <div class="card-heading">
                <div><p class="eyebrow">AGJENDA E SOTME</p><h2>Terminet e ditës</h2></div>
                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Menaxho →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Ora</th><th>Pacienti</th><th>Mjeku &amp; Lloji</th><th>Statusi</th><th class="actions-heading">Veprime</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayAppointments as $appointment): ?>
                        <tr>
                            <td class="tabular-nums"><strong><?= h(format_date_sq($appointment['data_ora'], 'H:i')) ?></strong></td>
                            <td>
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/pacientet/profili.php?id=<?= h($appointment['id_pacienti']) ?>">
                                    <?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?></strong>
                                <small class="muted"><?= h($appointment['lloji_vizites'] ?: $appointment['specializimi']) ?></small>
                            </td>
                            <td><span class="status-pill status-<?= h($appointment['statusi']) ?>"><?= h(appointment_status_label($appointment['statusi'])) ?></span></td>
                            <td class="actions-cell">
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/kartela/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">EHR</a>
                                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shto.php?id_pacienti=<?= h($appointment['id_pacienti']) ?>&id_termini=<?= h($appointment['id_termini']) ?>">Faturë</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$todayAppointments): ?>
                        <tr><td class="empty-state" colspan="5">Nuk ka termine të regjistruara për ditën e sotme.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="content-card">
            <div class="card-heading">
                <div><p class="eyebrow">FINANCAT</p><h2>Fatura në pritje</h2></div>
                <a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Të gjitha →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fatura</th><th>Pacienti</th><th>Mbetja</th><th>Statusi</th></tr></thead>
                    <tbody>
                    <?php foreach ($unpaidInvoices as $inv): ?>
                        <?php $remaining = max(0, (float) $inv['shuma_totale'] - (float) $inv['shuma_paguar']); ?>
                        <tr>
                            <td class="tabular-nums"><a class="text-link" href="<?= h(APP_BASE_URL) ?>/faturimi/shiko.php?id=<?= h($inv['id_fatura']) ?>"><?= h($inv['nr_fatures']) ?></a><small class="muted"><?= h(format_date_sq($inv['data_fatures'])) ?></small></td>
                            <td><?= h($inv['pacient_emri'] . ' ' . $inv['pacient_mbiemri']) ?></td>
                            <td class="tabular-nums"><strong><?= h(format_money($remaining)) ?></strong></td>
                            <td><span class="status-pill status-<?= h($inv['statusi']) ?>"><?= h(invoice_status_label($inv['statusi'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$unpaidInvoices): ?>
                        <tr><td class="empty-state" colspan="4">Nuk ka fatura të papaguara.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
