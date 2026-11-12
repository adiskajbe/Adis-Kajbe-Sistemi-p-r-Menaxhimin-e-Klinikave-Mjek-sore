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
<<<<<<< HEAD
<section class="hero hero-photo">
    <div class="hero-copy">
        <p class="eyebrow">SISTEMI PËR MENAXHIMIN E KLINIKAVE MJEKËSORE</p>
        <h1>Pacientët, mjekët dhe terminet në një vend të vetëm.</h1>
        <p>Aplikacion web me HTML, CSS, JavaScript, PHP dhe MySQL për regjistrimin, kërkimin dhe caktimin e termineve pa konflikte.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="<?= h(APP_BASE_URL) ?>/terminet/shto.php">Cakto termin <span aria-hidden="true">→</span></a>
            <a class="button button-ghost" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">Lista e pacientëve</a>
        </div>
    </div>
</section>

<section class="module-grid" aria-label="Modulet">
    <a class="module-card" href="<?= h(APP_BASE_URL) ?>/pacientet/lista.php">
        <img src="<?= h(APP_BASE_URL) ?>/assets/img/patients.jpg" alt="Mjeku duke folur me pacientin" loading="lazy">
        <div><p class="eyebrow">F-01</p><h3>Pacientët</h3><p>Regjistrim, kërkim, ndryshim dhe fshirje.</p></div>
    </a>
    <a class="module-card" href="<?= h(APP_BASE_URL) ?>/mjeket/lista.php">
        <img src="<?= h(APP_BASE_URL) ?>/assets/img/doctors.jpg" alt="Ekipi i mjekëve" loading="lazy">
        <div><p class="eyebrow">F-02</p><h3>Mjekët</h3><p>Emri, specializimi dhe kontakti i mjekut.</p></div>
    </a>
    <a class="module-card" href="<?= h(APP_BASE_URL) ?>/terminet/lista.php">
        <img src="<?= h(APP_BASE_URL) ?>/assets/img/appointments.jpg" alt="Dhoma e vizitave në klinikë" loading="lazy">
        <div><p class="eyebrow">F-03 · F-04</p><h3>Terminet</h3><p>Caktim, filtrim sipas datës ose mjekut.</p></div>
    </a>
=======
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
>>>>>>> 8ea089d10acf3afd02a4571590b990271931a742
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
