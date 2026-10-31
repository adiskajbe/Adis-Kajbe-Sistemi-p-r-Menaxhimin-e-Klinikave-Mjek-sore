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
    redirect_to('/terminet/lista.php');
}
if (!$pdo instanceof PDO) {
    set_flash('error', 'Databaza nuk është e disponueshme.');
    redirect_to('/terminet/lista.php');
}
try {
    $statement = $pdo->prepare('DELETE FROM termini WHERE id_termini = :id');
    $statement->execute(['id' => $id]);
    $deleted = $statement->rowCount() > 0;
    set_flash($deleted ? 'success' : 'error', $deleted ? 'Termini u fshi përgjithmonë.' : 'Termini nuk u gjet.');
} catch (Throwable $exception) {
    error_log('Fshirja e terminit dështoi: ' . $exception->getMessage());
    set_flash('error', 'Termini nuk u fshi. Provoni përsëri.');
}
redirect_to('/terminet/lista.php');
