<section class="notice notice-warning" role="status">
    <strong>Databaza nuk është e disponueshme.</strong>
    <p><?= h($databaseError ?? 'Kontrolloni konfigurimin e databazës.') ?></p>
    <p>Importoni <code>database/klinika_db.sql</code> në MySQL dhe kontrolloni parametrat në <code>config/database.php</code>.</p>
</section>