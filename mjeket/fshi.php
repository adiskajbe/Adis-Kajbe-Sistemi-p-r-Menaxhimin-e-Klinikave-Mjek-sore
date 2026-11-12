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
    redirect_to('/mjeket/lista.php');
}
if (!$pdo instanceof PDO) {
    set_flash('error', 'Databaza nuk është e disponueshme.');
    redirect_to('/mjeket/lista.php');
}

try {
    $check = $pdo->prepare('SELECT COUNT(*) FROM termini WHERE id_mjeku = :id');
    $check->execute(['id' => $id]);
    if ((int) $check->fetchColumn() > 0) {
        set_flash('error', 'Mjeku nuk mund të fshihet, sepse ka termine të lidhura.');
    } else {
        $delete = $pdo->prepare('DELETE FROM mjeku WHERE id_mjeku = :id');
        $delete->execute(['id' => $id]);
        $deleted = $delete->rowCount() > 0;
        set_flash($deleted ? 'success' : 'error', $deleted ? 'Mjeku u fshi.' : 'Mjeku nuk u gjet.');
    }
} catch (Throwable $exception) {
    error_log('Fshirja e mjekut dështoi: ' . $exception->getMessage());
    set_flash('error', 'Mjeku nuk mund të fshihet. Kontrolloni nëse ka termine të lidhura.');
}

redirect_to('/mjeket/lista.php');
