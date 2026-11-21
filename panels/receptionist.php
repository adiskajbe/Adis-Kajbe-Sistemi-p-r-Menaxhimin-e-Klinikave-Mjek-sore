<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('recepsionist');
$pageTitle = 'Paneli i recepsionit';
$activePage = 'home';
$counts = ['pacienti' => 0, 'mjeku' => 0, 'termini' => 0];
$upcoming = [];
if ($pdo instanceof PDO) {
    try {
        foreach (array_keys($counts) as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $upcoming = $pdo->query(
            "SELECT t.data_ora, t.statusi, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
             FROM termini t INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE t.data_ora >= NOW() AND t.statusi = 'planifikuar'
             ORDER BY t.data_ora ASC LIMIT 8"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Paneli i recepsionit nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">PANELI I RECEPSIONIT</p><h1>Organizimi i klinikës</h1><p>Menaxhoni regjistrimin e pacientëve dhe orarin e termineve.</p></div></section>
<section class="stat-grid" aria-label="Përmbledhje"><article class="stat-card"><span class="stat-icon icon-patient" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6m-3-3h6"/></svg></span><div><p>Pacientë</p><strong><?= h($counts['pacienti']) ?></strong></div></article><article class="stat-card"><span class="stat-icon icon-doctor" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4m8 0h4M12 14v2a5 5 0 0 0 5 5h2"/><circle cx="20" cy="21" r="1"/></svg></span><div><p>Mjekë</p><strong><?= h($counts['mjeku']) ?></strong></div></article><article class="stat-card"><span class="stat-icon icon-appointment" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3"/></svg></span><div><p>Termine</p><strong><?= h($counts['termini']) ?></strong></div></article></section>
<section class="hero-actions panel-shortcuts"><a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/pacientet/shto.php">Regjistro pacient <span aria-hidden="true">→</span></a><a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php">Cakto termin <span aria-hidden="true">→</span></a></section>
<section class="section-heading"><div><p class="eyebrow">ORARI</p><h2>Terminet e ardhshme</h2></div><a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">Shiko të gjitha</a></section>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Data dhe ora</th><th>Pacienti</th><th>Mjeku</th><th>Statusi</th></tr></thead><tbody>
<?php foreach ($upcoming as $appointment): ?><tr><td><?= h((new DateTimeImmutable($appointment['data_ora']))->format('d.m.Y H:i')) ?></td><td><?= h($appointment['pacient_emri'] . ' ' . $appointment['pacient_mbiemri']) ?></td><td><?= h($appointment['mjek_emri'] . ' ' . $appointment['mjek_mbiemri']) ?></td><td><?= h($appointment['statusi']) ?></td></tr><?php endforeach; ?>
<?php if (!$upcoming): ?><tr><td class="empty-state" colspan="4">Nuk ka termine të ardhshme.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
