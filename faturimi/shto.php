<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'recepsionist');

$pageTitle = 'Krijo faturë';
$activePage = 'billing';

$prePatientId = filter_input(INPUT_GET, 'id_pacienti', FILTER_VALIDATE_INT) ?: 0;
$preAppointmentId = filter_input(INPUT_GET, 'id_termini', FILTER_VALIDATE_INT) ?: 0;

$values = [
    'id_pacienti' => $prePatientId > 0 ? $prePatientId : 0,
    'id_termini' => $preAppointmentId > 0 ? $preAppointmentId : 0,
    'data_fatures' => (new DateTimeImmutable('today'))->format('Y-m-d'),
    'shuma_paguar' => '0.00',
    'metoda_pageses' => 'cash',
    'pershkrimi' => '',
];
$rows = [
    ['id_sherbimi' => 0, 'pershkrimi' => 'Kontrollë e përgjithshme mjekësore', 'sasia' => '1', 'cmimi_njesi' => '25.00'],
];

$patients = [];
$services = [];
$appointments = [];
$errors = [];
$authUser = current_auth_user();

if ($pdo instanceof PDO) {
    try {
        $patients = $pdo->query('SELECT id_pacienti, emri, mbiemri, telefoni FROM pacienti ORDER BY emri, mbiemri')->fetchAll();
        $services = $pdo->query('SELECT id_sherbimi, emri, kategoria, cmimi FROM sherbimet_klinike WHERE aktiv = 1 ORDER BY kategoria, emri')->fetchAll();
        $appointments = $pdo->query(
            'SELECT t.id_termini, t.id_pacienti, t.data_ora, p.emri AS pacient_emri, p.mbiemri AS pacient_mbiemri,
                    m.emri AS mjek_emri, m.mbiemri AS mjek_mbiemri, m.tarifa_bazike
             FROM termini t
             INNER JOIN pacienti p ON p.id_pacienti = t.id_pacienti
             INNER JOIN mjeku m ON m.id_mjeku = t.id_mjeku
             ORDER BY t.data_ora DESC LIMIT 40'
        )->fetchAll();

        if ($preAppointmentId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            foreach ($appointments as $appt) {
                if ((int) $appt['id_termini'] === $preAppointmentId) {
                    $values['id_pacienti'] = (int) $appt['id_pacienti'];
                    $rows[0] = [
                        'id_sherbimi' => 0,
                        'pershkrimi' => 'Vizitë te Dr. ' . $appt['mjek_emri'] . ' ' . $appt['mjek_mbiemri'],
                        'sasia' => '1',
                        'cmimi_njesi' => number_format((float) $appt['tarifa_bazike'], 2, '.', ''),
                    ];
                    break;
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Leximi i të dhënave për faturim dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i pacientëve ose katalogut të shërbimeve.';
        $pdo = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesioni i formularit skadoi. Ringarkoni faqen dhe provoni përsëri.';
    }

    $values['id_pacienti'] = (int) (filter_var((string) ($_POST['id_pacienti'] ?? 0), FILTER_VALIDATE_INT) ?: 0);
    $values['id_termini'] = (int) (filter_var((string) ($_POST['id_termini'] ?? 0), FILTER_VALIDATE_INT) ?: 0);
    $values['data_fatures'] = trim((string) ($_POST['data_fatures'] ?? date('Y-m-d')));
    $paidRaw = str_replace(',', '.', trim((string) ($_POST['shuma_paguar'] ?? '0')));
    $values['shuma_paguar'] = is_numeric($paidRaw) ? number_format((float) $paidRaw, 2, '.', '') : '0.00';
    $values['metoda_pageses'] = trim((string) ($_POST['metoda_pageses'] ?? 'cash'));
    $values['pershkrimi'] = trim((string) ($_POST['pershkrimi'] ?? ''));

    if ($values['id_pacienti'] < 1 || !patient_exists($pdo, $values['id_pacienti'])) {
        $errors[] = 'Zgjidhni një pacient të vlefshëm.';
    }
    $invDate = DateTimeImmutable::createFromFormat('!Y-m-d', $values['data_fatures']);
    if (!$invDate || $invDate->format('Y-m-d') !== $values['data_fatures']) {
        $errors[] = 'Data e faturës nuk është e vlefshme.';
    }
    if (!is_numeric($paidRaw) || (float) $paidRaw < 0) {
        $errors[] = 'Shuma e paguar nuk mund të jetë negative.';
    }
    if (!in_array($values['metoda_pageses'], ['cash', 'karte', 'transferte', 'sigurim'], true)) {
        $values['metoda_pageses'] = 'cash';
    }

    $itemServices = $_POST['artikuj_sherbimi'] ?? [];
    $itemDescs = $_POST['artikuj_pershkrimi'] ?? [];
    $itemQtys = $_POST['artikuj_sasia'] ?? [];
    $itemPrices = $_POST['artikuj_cmimi'] ?? [];

    $parsedItems = [];
    $rows = [];
    $grandTotal = 0.0;

    if (is_array($itemDescs)) {
        $count = count($itemDescs);
        for ($i = 0; $i < $count; $i++) {
            $sid = isset($itemServices[$i]) ? (int) (filter_var((string) $itemServices[$i], FILTER_VALIDATE_INT) ?: 0) : 0;
            $desc = trim((string) ($itemDescs[$i] ?? ''));
            $qtyRaw = trim((string) ($itemQtys[$i] ?? '1'));
            $priceRaw = str_replace(',', '.', trim((string) ($itemPrices[$i] ?? '0')));

            if ($desc === '' && (!is_numeric($priceRaw) || (float) $priceRaw == 0.0)) {
                continue;
            }
            $rows[] = [
                'id_sherbimi' => $sid,
                'pershkrimi' => $desc,
                'sasia' => $qtyRaw,
                'cmimi_njesi' => $priceRaw,
            ];
            if ($desc === '' || mb_strlen($desc) > 190) {
                $errors[] = 'Çdo artikull duhet të ketë përshkrim (deri në 190 karaktere).';
                continue;
            }
            $qty = filter_var($qtyRaw, FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 1 || $qty > 500) {
                $errors[] = 'Sasia e artikullit duhet të jetë ndërmjet 1 dhe 500.';
                continue;
            }
            if (!is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 100000) {
                $errors[] = 'Çmimi për njësi duhet të jetë vlerë pozitive.';
                continue;
            }
            $unitPrice = round((float) $priceRaw, 2);
            $subtotal = round($qty * $unitPrice, 2);
            $grandTotal += $subtotal;
            $parsedItems[] = [
                'id_sherbimi' => $sid > 0 ? $sid : null,
                'pershkrimi' => $desc,
                'sasia' => (int) $qty,
                'cmimi_njesi' => $unitPrice,
                'nentotali' => $subtotal,
            ];
        }
    }

    if (!$parsedItems) {
        $errors[] = 'Shtoni të paktën një shërbim ose artikull në faturë.';
        $rows = [['id_sherbimi' => 0, 'pershkrimi' => '', 'sasia' => '1', 'cmimi_njesi' => '0.00']];
    }

    $paidAmount = round((float) $values['shuma_paguar'], 2);
    if ($paidAmount > $grandTotal + 0.001) {
        $errors[] = 'Shuma e paguar nuk mund ta kalojë totalin e faturës.';
    }

    if ($values['id_termini'] > 0) {
        $tCheck = $pdo->prepare('SELECT id_pacienti FROM termini WHERE id_termini = :tid LIMIT 1');
        $tCheck->execute(['tid' => $values['id_termini']]);
        $tPid = $tCheck->fetchColumn();
        if (!$tPid || (int) $tPid !== $values['id_pacienti']) {
            $errors[] = 'Termini i zgjedhur nuk i takon këtij pacienti.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $invoiceNumber = next_invoice_number($pdo);
            $status = 'papaguar';
            $paymentMethod = null;
            if ($paidAmount >= $grandTotal && $grandTotal > 0) {
                $status = 'paguar';
                $paymentMethod = $values['metoda_pageses'];
            } elseif ($paidAmount > 0) {
                $status = 'pjeserisht';
                $paymentMethod = $values['metoda_pageses'];
            }

            $ins = $pdo->prepare(
                'INSERT INTO fatura (nr_fatures, id_pacienti, id_termini, id_perdorues, data_fatures, shuma_totale, shuma_paguar, statusi, metoda_pageses, pershkrimi)
                 VALUES (:nr, :pid, :tid, :uid, :dt, :total, :paid, :status, :method, :notes)'
            );
            $ins->execute([
                'nr' => $invoiceNumber,
                'pid' => $values['id_pacienti'],
                'tid' => $values['id_termini'] > 0 ? $values['id_termini'] : null,
                'uid' => (int) ($authUser['id'] ?? 0) ?: null,
                'dt' => $values['data_fatures'],
                'total' => $grandTotal,
                'paid' => $paidAmount,
                'status' => $status,
                'method' => $paymentMethod,
                'notes' => $values['pershkrimi'] !== '' ? $values['pershkrimi'] : null,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $itemIns = $pdo->prepare(
                'INSERT INTO fatura_artikuj (id_fatura, id_sherbimi, pershkrimi, sasia, cmimi_njesi, nentotali)
                 VALUES (:fid, :sid, :desc, :qty, :price, :sub)'
            );
            foreach ($parsedItems as $item) {
                $itemIns->execute([
                    'fid' => $invoiceId,
                    'sid' => $item['id_sherbimi'],
                    'desc' => $item['pershkrimi'],
                    'qty' => $item['sasia'],
                    'price' => $item['cmimi_njesi'],
                    'sub' => $item['nentotali'],
                ]);
            }

            $uStmt = $pdo->prepare('SELECT id_perdorues FROM perdorues WHERE id_pacienti = :pid LIMIT 1');
            $uStmt->execute(['pid' => $values['id_pacienti']]);
            $pUserId = $uStmt->fetchColumn();
            if ($pUserId) {
                create_notification(
                    $pdo,
                    (int) $pUserId,
                    'Faturë e re: ' . $invoiceNumber,
                    'U lëshua fatura ' . $invoiceNumber . ' me vlerë ' . format_money($grandTotal) . '.',
                    'fature'
                );
            }

            $pdo->commit();
            set_flash('success', 'Fatura ' . $invoiceNumber . ' u krijua me sukses.');
            redirect_to('/faturimi/shiko.php?id=' . $invoiceId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Krijimi i faturës dështoi: ' . $exception->getMessage());
            $errors[] = 'Ruajtja e faturës dështoi.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">FATURIM I RI</p>
        <h1>Krijo faturë për pacientin</h1>
        <p>Përzgjidhni pacientin, shërbimet mjekësore dhe regjistroni pagesën fillestare nëse është kryer.</p>
    </div>
</section>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="content-card">
        <?php if ($errors): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form class="form-grid" action="<?= h(APP_BASE_URL) ?>/faturimi/shto.php" method="post" data-billing-form>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-field">
                <label for="id_pacienti">Pacienti *</label>
                <select id="id_pacienti" name="id_pacienti" required>
                    <option value="">Zgjidhni pacientin...</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= h($p['id_pacienti']) ?>" <?= (int) $values['id_pacienti'] === (int) $p['id_pacienti'] ? 'selected' : '' ?>>
                            <?= h($p['emri'] . ' ' . $p['mbiemri'] . ' (' . $p['telefoni'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="id_termini">Termini i lidhur (opsional)</label>
                <select id="id_termini" name="id_termini">
                    <option value="">Pa lidhje me termin</option>
                    <?php foreach ($appointments as $appt): ?>
                        <option value="<?= h($appt['id_termini']) ?>" <?= (int) $values['id_termini'] === (int) $appt['id_termini'] ? 'selected' : '' ?>>
                            <?= h(format_date_sq($appt['data_ora'], 'd.m.Y H:i') . ' · ' . $appt['pacient_emri'] . ' ' . $appt['pacient_mbiemri'] . ' (' . $appt['mjek_emri'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="data_fatures">Data e faturës *</label>
                <input id="data_fatures" name="data_fatures" type="date" required value="<?= h($values['data_fatures']) ?>">
            </div>

            <div class="form-field">
                <label for="shuma_paguar">Pagesa fillestare (€)</label>
                <input id="shuma_paguar" name="shuma_paguar" type="number" step="0.01" min="0" value="<?= h($values['shuma_paguar']) ?>" data-paid-input>
            </div>

            <div class="form-field">
                <label for="metoda_pageses">Metoda e pagesës</label>
                <select id="metoda_pageses" name="metoda_pageses">
                    <option value="cash" <?= $values['metoda_pageses'] === 'cash' ? 'selected' : '' ?>>Para në dorë (Cash)</option>
                    <option value="karte" <?= $values['metoda_pageses'] === 'karte' ? 'selected' : '' ?>>Kartë bankare</option>
                    <option value="transferte" <?= $values['metoda_pageses'] === 'transferte' ? 'selected' : '' ?>>Transfertë bankare</option>
                    <option value="sigurim" <?= $values['metoda_pageses'] === 'sigurim' ? 'selected' : '' ?>>Sigurim shëndetësor</option>
                </select>
            </div>

            <div class="form-field">
                <label for="pershkrimi">Shënime shtesë</label>
                <input id="pershkrimi" name="pershkrimi" type="text" maxlength="500" value="<?= h($values['pershkrimi']) ?>" placeholder="P.sh. Kontrollë kardiologjike dhe EKG">
            </div>

            <div class="form-field form-field-full">
                <div class="card-heading">
                    <div><p class="eyebrow">ARTIKUJT &amp; SHËRBIMET</p><h2>Zërat e faturës</h2></div>
                    <button class="button button-secondary button-sm" type="button" data-billing-add>+ Shto shërbim</button>
                </div>

                <div class="table-wrap">
                    <table class="invoice-items-table">
                        <thead>
                            <tr>
                                <th>Katalogu (opsional)</th>
                                <th>Përshkrimi i shërbimit *</th>
                                <th>Sasia</th>
                                <th>Çmimi (€)</th>
                                <th>Nëntotali</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-billing-rows>
                            <?php foreach ($rows as $row): ?>
                                <tr data-billing-row>
                                    <td>
                                        <select name="artikuj_sherbimi[]" data-service-select>
                                            <option value="0" data-price="">Shërbim i lirë</option>
                                            <?php foreach ($services as $srv): ?>
                                                <option value="<?= h($srv['id_sherbimi']) ?>" data-price="<?= h(number_format((float) $srv['cmimi'], 2, '.', '')) ?>" data-name="<?= h($srv['emri']) ?>" <?= (int) $row['id_sherbimi'] === (int) $srv['id_sherbimi'] ? 'selected' : '' ?>>
                                                    <?= h($srv['emri'] . ' (' . format_money($srv['cmimi']) . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input name="artikuj_pershkrimi[]" type="text" required maxlength="190" value="<?= h($row['pershkrimi']) ?>" data-desc-input></td>
                                    <td><input name="artikuj_sasia[]" type="number" min="1" max="500" required value="<?= h($row['sasia']) ?>" data-qty-input></td>
                                    <td><input name="artikuj_cmimi[]" type="number" step="0.01" min="0" required value="<?= h($row['cmimi_njesi']) ?>" data-price-input></td>
                                    <td class="tabular-nums"><strong data-row-total>0.00 €</strong></td>
                                    <td><button class="link-button danger-link" type="button" data-billing-remove>Hiq</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="summary-label">Totali i faturës:</td>
                                <td class="tabular-nums"><strong data-billing-grand-total>0.00 €</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <template id="billing-row-template">
                    <tr data-billing-row>
                        <td>
                            <select name="artikuj_sherbimi[]" data-service-select>
                                <option value="0" data-price="">Shërbim i lirë</option>
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?= h($srv['id_sherbimi']) ?>" data-price="<?= h(number_format((float) $srv['cmimi'], 2, '.', '')) ?>" data-name="<?= h($srv['emri']) ?>">
                                        <?= h($srv['emri'] . ' (' . format_money($srv['cmimi']) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input name="artikuj_pershkrimi[]" type="text" required maxlength="190" value="" placeholder="Emërtimi i shërbimit" data-desc-input></td>
                        <td><input name="artikuj_sasia[]" type="number" min="1" max="500" required value="1" data-qty-input></td>
                        <td><input name="artikuj_cmimi[]" type="number" step="0.01" min="0" required value="0.00" data-price-input></td>
                        <td class="tabular-nums"><strong data-row-total>0.00 €</strong></td>
                        <td><button class="link-button danger-link" type="button" data-billing-remove>Hiq</button></td>
                    </tr>
                </template>
            </div>

            <div class="form-actions form-field-full">
                <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Anulo</a>
                <button class="button button-primary" type="submit">Ruaj dhe lësho faturën</button>
            </div>
        </form>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
