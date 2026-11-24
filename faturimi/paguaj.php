<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin', 'recepsionist');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/faturimi/lista.php');
}
if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('warning', 'Sesioni i formularit skadoi. Provoni përsëri.');
    redirect_to('/faturimi/lista.php');
}
if (!$pdo instanceof PDO) {
    set_flash('warning', 'Databaza nuk është e lidhur.');
    redirect_to('/faturimi/lista.php');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = trim((string) ($_POST['veprimi'] ?? 'paguaj'));
if (!$id || $id < 1) {
    set_flash('warning', 'Fatura nuk është e vlefshme.');
    redirect_to('/faturimi/lista.php');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM fatura WHERE id_fatura = :id LIMIT 1 FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        $pdo->rollBack();
        set_flash('warning', 'Fatura nuk u gjet.');
        redirect_to('/faturimi/lista.php');
    }

    if ($action === 'anulo') {
        if ((float) $invoice['shuma_paguar'] > 0.0) {
            $pdo->rollBack();
            set_flash('warning', 'Fatura me pagesa të regjistruara nuk mund të anulohet.');
            redirect_to('/faturimi/shiko.php?id=' . $id);
        }
        $upd = $pdo->prepare("UPDATE fatura SET statusi = 'anuluar' WHERE id_fatura = :id");
        $upd->execute(['id' => $id]);
        $pdo->commit();
        set_flash('success', 'Fatura ' . $invoice['nr_fatures'] . ' u anulua.');
        redirect_to('/faturimi/shiko.php?id=' . $id);
    }

    if (!in_array($invoice['statusi'], ['papaguar', 'pjeserisht'], true)) {
        $pdo->rollBack();
        set_flash('warning', 'Kjo faturë nuk pranon pagesa të reja.');
        redirect_to('/faturimi/shiko.php?id=' . $id);
    }

    $amountRaw = str_replace(',', '.', trim((string) ($_POST['shuma'] ?? '0')));
    $method = trim((string) ($_POST['metoda_pageses'] ?? 'cash'));
    if (!in_array($method, ['cash', 'karte', 'transferte', 'sigurim'], true)) {
        $method = 'cash';
    }

    if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        $pdo->rollBack();
        set_flash('warning', 'Shuma e pagesës duhet të jetë më e madhe se zero.');
        redirect_to('/faturimi/shiko.php?id=' . $id);
    }

    $payment = round((float) $amountRaw, 2);
    $total = round((float) $invoice['shuma_totale'], 2);
    $currentPaid = round((float) $invoice['shuma_paguar'], 2);
    $remaining = round($total - $currentPaid, 2);

    if ($payment > $remaining + 0.001) {
        $pdo->rollBack();
        set_flash('warning', 'Shuma e vendosur e kalon mbetjen aktuale prej ' . format_money($remaining) . '.');
        redirect_to('/faturimi/shiko.php?id=' . $id);
    }

    $newPaid = min($total, round($currentPaid + $payment, 2));
    $newStatus = $newPaid >= $total ? 'paguar' : 'pjeserisht';

    $upd = $pdo->prepare(
        'UPDATE fatura SET shuma_paguar = :paid, statusi = :status, metoda_pageses = :method WHERE id_fatura = :id'
    );
    $upd->execute([
        'paid' => $newPaid,
        'status' => $newStatus,
        'method' => $method,
        'id' => $id,
    ]);

    $uStmt = $pdo->prepare('SELECT id_perdorues FROM perdorues WHERE id_pacienti = :pid LIMIT 1');
    $uStmt->execute(['pid' => $invoice['id_pacienti']]);
    $pUserId = $uStmt->fetchColumn();
    if ($pUserId) {
        create_notification(
            $pdo,
            (int) $pUserId,
            'Pagesë e regjistruar: ' . $invoice['nr_fatures'],
            'U regjistrua pagesa prej ' . format_money($payment) . ' për faturën ' . $invoice['nr_fatures'] . '.',
            'fature'
        );
    }

    $pdo->commit();
    set_flash('success', 'Pagesa prej ' . format_money($payment) . ' u regjistrua me sukses.');
    redirect_to('/faturimi/shiko.php?id=' . $id);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Regjistrimi i pagesës dështoi: ' . $exception->getMessage());
    set_flash('warning', 'Pagesa nuk u regjistrua për shkak të një gabimi.');
    redirect_to('/faturimi/shiko.php?id=' . $id);
}
