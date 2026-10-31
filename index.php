<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Ballina';
$activePage = 'home';
$counts = ['pacienti' => 0, 'mjeku' => 0, 'termini' => 0];

if ($pdo instanceof PDO) {
    try {
        foreach (array_keys($counts) as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
    } catch (Throwable $exception) {
        error_log('Leximi i përmbledhjes dështoi: ' . $exception->getMessage());
        $databaseError = 'Skema nuk duket e plotë. Importoni database/klinika_db.sql.';
        $pdo = null;
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">FAZA FILLESTARE</p>
        <h1>Organizoni të dhënat e klinikës në një vend.</h1>
        <p>Ky version vendos bazën e aplikacionit dhe përfshin regjistrimin, kërkimin dhe mirëmbajtjen e të dhënave të pacientëve.</p>
        <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Hap listën e pacientëve <span aria-hidden="true">→</span></a>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="medical-card"><span class="medical-cross">+</span><span class="medical-line"></span><span class="medical-line short"></span><span class="medical-dot"></span></div>
    </div>
</section>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/includes/database_notice.php'; ?>
<?php else: ?>
    <section class="section-heading">
        <div><p class="eyebrow">PËRMBLEDHJE</p><h2>Gjendja e databazës</h2></div>
        <span class="status-pill"><span class="status-dot"></span> Lidhja aktive</span>
    </section>
    <section class="stat-grid" aria-label="Numri i të dhënave">
        <article class="stat-card"><span class="stat-icon icon-patient">P</span><div><p>Pacientë</p><strong><?= h($counts['pacienti']) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-doctor">M</span><div><p>Mjekë</p><strong><?= h($counts['mjeku']) ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon icon-appointment">T</span><div><p>Termine</p><strong><?= h($counts['termini']) ?></strong></div></article>
    </section>
<?php endif; ?>

<section class="notice notice-info">
    <strong>Për përdorim mësimor</strong>
    <p>Ky prototip nuk është sistem klinik i certifikuar dhe nuk duhet të përdoret për të dhëna reale mjekësore.</p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
