<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'recepsionist', 'pacient');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isPatient = auth_user_has_role('pacient');
$canManage = auth_user_has_role('admin', 'recepsionist');
$authUser = current_auth_user();
$invoice = null;
$invoiceItems = [];
$error = '';

if (!$id || $id < 1) {
    http_response_code(400);
    $error = 'ID-ja e faturës nuk është e vlefshme.';
} elseif ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare(
            'SELECT f.*, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri, p.telefoni, p.email, p.adresa, p.nr_personal,
                    t.data_ora AS termin_data, m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri
             FROM fatura f
             INNER JOIN pacienti p ON p.id_pacienti = f.id_pacienti
             LEFT JOIN termini t ON t.id_termini = f.id_termini
             LEFT JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             WHERE f.id_fatura = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            http_response_code(404);
            $error = 'Fatura nuk u gjet.';
        } elseif ($isPatient && (int) $invoice['id_pacienti'] !== (int) ($authUser['patient_id'] ?? 0)) {
            http_response_code(403);
            $error = 'Nuk keni leje për të parë këtë faturë.';
            $invoice = null;
        } else {
            $itemStmt = $pdo->prepare('SELECT * FROM fatura_artikuj WHERE id_fatura = :fid ORDER BY id_artikulli ASC');
            $itemStmt->execute(['fid' => $id]);
            $invoiceItems = $itemStmt->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log('Leximi i faturës dështoi: ' . $exception->getMessage());
        $error = 'Nuk u arrit leximi i faturës.';
    }
}

$pageTitle = $invoice ? ('Fatura ' . $invoice['nr_fatures']) : 'Detajet e faturës';
$activePage = 'billing';
$flash = take_flash();
include __DIR__ . '/../includes/header.php';
?>
<section class="page-head no-print">
    <div>
        <p class="eyebrow">DOKUMENT FINANCIAR</p>
        <h1><?= h($invoice ? $invoice['nr_fatures'] : 'Fatura') ?></h1>
        <p>Pamje e detajuar e shërbimeve të faturuara dhe gjendjes së pagesës.</p>
    </div>
    <?php if ($invoice): ?>
        <div class="page-head-actions">
            <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Të gjitha faturat</a>
            <button class="button button-primary" type="button" data-print-trigger>Printo faturën</button>
        </div>
    <?php endif; ?>
</section>

<?php if ($flash): ?>
    <div class="notice <?= $flash['type'] === 'success' ? 'notice-success' : 'notice-warning' ?> no-print" role="status"><?= h($flash['message']) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="notice notice-warning" role="alert"><?= h($error) ?></div>
<?php elseif ($invoice): ?>
    <?php $remaining = max(0.0, (float) $invoice['shuma_totale'] - (float) $invoice['shuma_paguar']); ?>
    <article class="content-card printable-doc">
        <div class="doc-header">
            <div>
                <p class="eyebrow">KLINIKA MJEKËSORE · FATURË SHËRBIMI</p>
                <h2><?= h($invoice['nr_fatures']) ?></h2>
                <p class="muted">Data e lëshimit: <span class="tabular-nums"><?= h(format_date_sq($invoice['data_fatures'])) ?></span></p>
            </div>
            <div>
                <span class="status-pill status-<?= h($invoice['statusi']) ?>"><?= h(invoice_status_label($invoice['statusi'])) ?></span>
            </div>
        </div>

        <div class="doc-meta-grid">
            <div>
                <p class="eyebrow">FATURUAR PËR</p>
                <strong><?= h($invoice['pacient_emri'] . ' ' . $invoice['pacient_mbiemri']) ?></strong>
                <small class="muted">Tel: <?= h($invoice['telefoni']) ?><?= $invoice['nr_personal'] ? ' · Nr. Personal: ' . h($invoice['nr_personal']) : '' ?></small>
                <?php if (!empty($invoice['adresa'])): ?><small class="muted"><?= h($invoice['adresa']) ?></small><?php endif; ?>
            </div>
            <div>
                <p class="eyebrow">DETAJET E PAGESËS</p>
                <strong>Metoda: <?= h(payment_method_label($invoice['metoda_pageses'] ?? null)) ?></strong>
                <?php if (!empty($invoice['termin_data'])): ?>
                    <small class="muted">Termini: <?= h(format_date_sq($invoice['termin_data'], 'd.m.Y H:i')) ?> · Dr. <?= h($invoice['mjek_emri'] . ' ' . $invoice['mjek_mbiemri']) ?></small>
                <?php endif; ?>
                <?php if (!empty($invoice['pershkrimi'])): ?>
                    <small class="muted">Shënim: <?= h($invoice['pershkrimi']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap mt-section">
            <table>
                <thead>
                    <tr>
                        <th>Përshkrimi i shërbimit</th>
                        <th>Sasia</th>
                        <th>Çmimi njësi</th>
                        <th>Nëntotali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceItems as $invoiceItem): ?>
                        <tr>
                            <td><?= h($invoiceItem['pershkrimi']) ?></td>
                            <td class="tabular-nums"><?= h($invoiceItem['sasia']) ?></td>
                            <td class="tabular-nums"><?= h(format_money($invoiceItem['cmimi_njesi'])) ?></td>
                            <td class="tabular-nums"><strong><?= h(format_money($invoiceItem['nentotali'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="summary-label">Totali i faturës</td>
                        <td class="tabular-nums"><strong><?= h(format_money($invoice['shuma_totale'])) ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="summary-label">Shuma e paguar</td>
                        <td class="tabular-nums"><?= h(format_money($invoice['shuma_paguar'])) ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="summary-label">Mbetja për pagesë</td>
                        <td class="tabular-nums"><strong><?= h(format_money($remaining)) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </article>

    <?php if ($canManage && in_array($invoice['statusi'], ['papaguar', 'pjeserisht'], true)): ?>
        <section class="content-card mt-section no-print">
            <div class="card-heading">
                <div><p class="eyebrow">ARKËTIMI</p><h2>Regjistro pagesë</h2></div>
            </div>
            <form class="form-grid" action="<?= h(APP_BASE_URL) ?>/faturimi/paguaj.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= h($invoice['id_fatura']) ?>">
                <input type="hidden" name="veprimi" value="paguaj">

                <div class="form-field">
                    <label for="shuma">Shuma e arkëtuar (€) *</label>
                    <input id="shuma" name="shuma" type="number" step="0.01" min="0.01" max="<?= h(number_format($remaining, 2, '.', '')) ?>" required value="<?= h(number_format($remaining, 2, '.', '')) ?>">
                </div>

                <div class="form-field">
                    <label for="metoda_pageses">Metoda e pagesës *</label>
                    <select id="metoda_pageses" name="metoda_pageses" required>
                        <option value="cash">Para në dorë (Cash)</option>
                        <option value="karte">Kartë bankare</option>
                        <option value="transferte">Transfertë bankare</option>
                        <option value="sigurim">Sigurim shëndetësor</option>
                    </select>
                </div>

                <div class="form-actions form-field-full">
                    <button class="button button-primary" type="submit">Konfirmo pagesën</button>
                </div>
            </form>

            <?php if ((float) $invoice['shuma_paguar'] == 0.0): ?>
                <form class="inline-form mt-section" action="<?= h(APP_BASE_URL) ?>/faturimi/paguaj.php" method="post" data-confirm="A jeni të sigurt që dëshironi ta anuloni këtë faturë?">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= h($invoice['id_fatura']) ?>">
                    <input type="hidden" name="veprimi" value="anulo">
                    <button class="button button-secondary button-sm" type="submit">Anulo faturën</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
