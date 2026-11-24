<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin');

$pageTitle = 'Katalogu i shërbimeve';
$activePage = 'services';
$services = [];
$errors = [];
$values = ['emri' => '', 'kategoria' => 'Konsultë', 'cmimi' => '30.00', 'kohezgjatja_min' => '30'];
$flash = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesioni i formularit skadoi. Provoni përsëri.';
    } else {
        $action = trim((string) ($_POST['veprimi'] ?? 'shto'));
        if ($action === 'toggle') {
            $sid = filter_input(INPUT_POST, 'id_sherbimi', FILTER_VALIDATE_INT);
            if ($sid && $sid > 0) {
                $stmt = $pdo->prepare('UPDATE sherbimet_klinike SET aktiv = IF(aktiv = 1, 0, 1) WHERE id_sherbimi = :id');
                $stmt->execute(['id' => $sid]);
                set_flash('success', 'Statusi i shërbimit u përditësua.');
                redirect_to('/faturimi/sherbimet.php');
            }
        } else {
            $values['emri'] = trim((string) ($_POST['emri'] ?? ''));
            $values['kategoria'] = trim((string) ($_POST['kategoria'] ?? 'Konsultë'));
            $priceRaw = str_replace(',', '.', trim((string) ($_POST['cmimi'] ?? '0')));
            $values['cmimi'] = is_numeric($priceRaw) ? number_format((float) $priceRaw, 2, '.', '') : '0.00';
            $dur = filter_var((string) ($_POST['kohezgjatja_min'] ?? '30'), FILTER_VALIDATE_INT);
            $values['kohezgjatja_min'] = ($dur !== false && $dur >= 5 && $dur <= 300) ? (string) $dur : '30';

            if ($values['emri'] === '' || mb_strlen($values['emri']) > 150) {
                $errors[] = 'Emri i shërbimit është i detyrueshëm (deri në 150 karaktere).';
            }
            if ($values['kategoria'] === '' || mb_strlen($values['kategoria']) > 80) {
                $errors[] = 'Kategoria është e detyrueshme (deri në 80 karaktere).';
            }
            if (!is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 50000) {
                $errors[] = 'Çmimi duhet të jetë një vlerë e vlefshme pozitive.';
            }

            if (!$errors) {
                try {
                    $ins = $pdo->prepare(
                        'INSERT INTO sherbimet_klinike (emri, kategoria, cmimi, kohezgjatja_min, aktiv)
                         VALUES (:emri, :kategoria, :cmimi, :dur, 1)'
                    );
                    $ins->execute([
                        'emri' => $values['emri'],
                        'kategoria' => $values['kategoria'],
                        'cmimi' => (float) $values['cmimi'],
                        'dur' => (int) $values['kohezgjatja_min'],
                    ]);
                    set_flash('success', 'Shërbimi "' . $values['emri'] . '" u shtua në katalog.');
                    redirect_to('/faturimi/sherbimet.php');
                } catch (Throwable $exception) {
                    error_log('Shtimi i shërbimit dështoi: ' . $exception->getMessage());
                    $errors[] = 'Shërbimi nuk u shtua për shkak të një gabimi.';
                }
            }
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $services = $pdo->query('SELECT * FROM sherbimet_klinike ORDER BY aktiv DESC, kategoria ASC, emri ASC')->fetchAll();
    } catch (Throwable $exception) {
        error_log('Leximi i katalogut të shërbimeve dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i katalogut të shërbimeve.';
        $pdo = null;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
    <div>
        <p class="eyebrow">KATALOGU KLINIK</p>
        <h1>Shërbimet &amp; Çmimorja</h1>
        <p>Menaxhoni tarifat standarde të klinikës të cilat përdoren automatikisht gjatë faturimit.</p>
    </div>
    <div class="page-head-actions">
        <a class="button button-secondary" href="<?= h(APP_BASE_URL) ?>/faturimi/lista.php">Kthehu te faturat</a>
    </div>
</section>

<?php if ($flash): ?><div class="notice notice-success" role="status"><?= h($flash['message']) ?></div><?php endif; ?>

<?php if (!$pdo instanceof PDO): ?>
    <?php include __DIR__ . '/../includes/database_notice.php'; ?>
<?php else: ?>
    <section class="content-card">
        <div class="card-heading"><div><p class="eyebrow">SHTIM I RI</p><h2>Shto shërbim në katalog</h2></div></div>
        <?php if ($errors): ?>
            <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form class="form-grid" action="<?= h(APP_BASE_URL) ?>/faturimi/sherbimet.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="veprimi" value="shto">
            <div class="form-field">
                <label for="emri">Emri i shërbimit *</label>
                <input id="emri" name="emri" type="text" required maxlength="150" value="<?= h($values['emri']) ?>" placeholder="P.sh. Ekografi abdominale">
            </div>
            <div class="form-field">
                <label for="kategoria">Kategoria *</label>
                <input id="kategoria" name="kategoria" type="text" required maxlength="80" value="<?= h($values['kategoria']) ?>">
            </div>
            <div class="form-field">
                <label for="cmimi">Çmimi (€) *</label>
                <input id="cmimi" name="cmimi" type="number" step="0.01" min="0" required value="<?= h($values['cmimi']) ?>">
            </div>
            <div class="form-field">
                <label for="kohezgjatja_min">Kohëzgjatja (min)</label>
                <input id="kohezgjatja_min" name="kohezgjatja_min" type="number" min="5" max="300" value="<?= h($values['kohezgjatja_min']) ?>">
            </div>
            <div class="form-actions form-field-full">
                <button class="button button-primary" type="submit">Shto në katalog</button>
            </div>
        </form>
    </section>

    <section class="content-card mt-section">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Shërbimi</th><th>Kategoria</th><th>Kohëzgjatja</th><th>Çmimi</th><th>Statusi</th><th class="actions-heading">Veprimet</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $srv): ?>
                        <tr>
                            <td><strong><?= h($srv['emri']) ?></strong></td>
                            <td><?= h($srv['kategoria']) ?></td>
                            <td class="tabular-nums"><?= h($srv['kohezgjatja_min']) ?> min</td>
                            <td class="tabular-nums"><strong><?= h(format_money($srv['cmimi'])) ?></strong></td>
                            <td><span class="status-pill <?= (int) $srv['aktiv'] === 1 ? 'status-perfunduar' : 'status-anuluar' ?>"><?= (int) $srv['aktiv'] === 1 ? 'Aktiv' : 'Joaktiv' ?></span></td>
                            <td class="actions-cell">
                                <form class="inline-form" action="<?= h(APP_BASE_URL) ?>/faturimi/sherbimet.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="veprimi" value="toggle">
                                    <input type="hidden" name="id_sherbimi" value="<?= h($srv['id_sherbimi']) ?>">
                                    <button class="link-button" type="submit"><?= (int) $srv['aktiv'] === 1 ? 'Çaktivizo' : 'Aktivizo' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$services): ?><tr><td class="empty-state" colspan="6">Katalogu është bosh.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
