<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin');
$pageTitle = 'Regjistri i kyçjeve';
$activePage = 'admin';
$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['kerko'] ?? ''));
if (!in_array($status, ['', 'success', 'failure'], true)) {
    $status = '';
}
$logs = [];
if ($pdo instanceof PDO) {
    try {
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'l.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(l.email_or_username LIKE :identifier OR l.ip_address LIKE :ip_address)';
            $params['identifier'] = '%' . $search . '%';
            $params['ip_address'] = '%' . $search . '%';
        }
        $sql = 'SELECT l.id_login_log, l.email_or_username, l.ip_address, l.status, l.created_at,
                       l.id_perdorues, u.username
                FROM login_logs l LEFT JOIN perdorues u ON u.id_perdorues = l.id_perdorues';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY l.id_login_log DESC LIMIT 250';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $logs = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Regjistri i kyçjeve nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">SIGURIA</p><h1>Regjistri i kyçjeve</h1><p>Tentativat e suksesshme dhe të dështuara, me kohën dhe adresën IP.</p></div></section>
<section class="content-card">
    <form class="search-form" action="<?= h(APP_BASE_URL) ?>/admin/login_logs.php" method="get">
        <label class="sr-only" for="kerko">Kërko email, përdorues ose IP</label>
        <input id="kerko" name="kerko" type="search" value="<?= h($search) ?>" placeholder="Email, emër përdoruesi ose IP">
        <select name="status" aria-label="Filtro sipas statusit"><option value="">Të gjitha</option><option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Sukses</option><option value="failure" <?= $status === 'failure' ? 'selected' : '' ?>>Dështim</option></select>
        <button class="button button-secondary" type="submit">Filtro</button>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Data dhe ora</th><th>Llogaria e kërkuar</th><th>ID përdoruesi</th><th>IP</th><th>Statusi</th></tr></thead><tbody>
    <?php foreach ($logs as $log): ?><tr><td><?= h((new DateTimeImmutable($log['created_at']))->format('d.m.Y H:i:s')) ?></td><td><?= h($log['email_or_username']) ?></td><td><?= h($log['id_perdorues'] ?? '—') ?><?= $log['username'] ? '<small class="muted">' . h($log['username']) . '</small>' : '' ?></td><td><?= h($log['ip_address']) ?></td><td><span class="status-pill <?= $log['status'] === 'success' ? 'status-planifikuar' : 'status-anuluar' ?>"><?= $log['status'] === 'success' ? 'Sukses' : 'Dështim' ?></span></td></tr><?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5" class="empty-state">Nuk u gjetën tentativa kyçjeje.</td></tr><?php endif; ?>
    </tbody></table></div>
    <p class="table-caption">Shfaqen maksimumi 250 hyrjet më të fundit. Regjistri përmban identifikues hyrjeje dhe adresa IP; mbajeni me qasje vetëm për administratorët.</p>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
