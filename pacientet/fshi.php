<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Kjo kërkesë nuk mbështetet.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1 || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Kërkesa për fshirje nuk është e vlefshme.');
    redirect_to('/pacientet/lista.php');
}

if (!$pdo instanceof PDO) {
    set_flash('error', 'Databaza nuk është e disponueshme.');
    redirect_to('/pacientet/lista.php');
}

try {
    $check = $pdo->prepare('SELECT COUNT(*) FROM termini WHERE id_pacienti = :id');
    $check->execute(['id' => $id]);

    if ((int) $check->fetchColumn() > 0) {
        set_flash('error', 'Pacienti nuk mund të fshihet, sepse ka termine të lidhura.');
    } else {
        $delete = $pdo->prepare('DELETE FROM pacienti WHERE id_pacienti = :id');
        $delete->execute(['id' => $id]);
        set_flash($delete->rowCount() > 0 ? 'success' : 'error', $delete->rowCount() > 0 ? 'Pacienti u fshi.' : 'Pacienti nuk u gjet.');
    }
} catch (Throwable $exception) {
    error_log('Fshirja e pacientit dështoi: ' . $exception->getMessage());
    set_flash('error', 'Pacienti nuk mund të fshihet. Kontrolloni nëse ka të dhëna të lidhura.');
}

redirect_to('/pacientet/lista.php');