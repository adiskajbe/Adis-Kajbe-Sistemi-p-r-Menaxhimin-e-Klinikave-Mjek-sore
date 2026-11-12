<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Cakto termin';
$activePage = 'appointments';
$heading = 'Cakto termin';
$submitLabel = 'Ruaj terminin';
$action = APP_BASE_URL . '/terminet/shto.php';
$id = null;
$errors = [];
$patients = [];
$doctors = [];
$showForm = true;
$values = ['id_pacienti' => 0, 'id_mjeku' => 0, 'data_ora' => '', 'statusi' => 'planifikuar', 'shenime' => ''];

if ($pdo instanceof PDO) {
    try {
        $patients = $pdo->query('SELECT id_pacienti, emri, mbiemri FROM pacienti ORDER BY emri, mbiemri')->fetchAll();
        $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri, specializimi FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
    } catch (Throwable $exception) {
        error_log('Leximi i zgjedhjeve për termin dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i pacientëve dhe mjekëve.';
        $pdo = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma ka skaduar. Rifreskojeni faqen dhe provoni përsëri.';
    }
    [$values, $fieldErrors] = valid_appointment_fields($_POST);
    $errors = array_merge($errors, $fieldErrors);
    if (!$pdo instanceof PDO) {
        $errors[] = 'Databaza nuk është e disponueshme.';
    }
    if ($pdo instanceof PDO && !$errors) {
        if (!patient_exists($pdo, $values['id_pacienti'])) {
            $errors[] = 'Pacienti i zgjedhur nuk ekziston.';
        }
        if (!doctor_exists($pdo, $values['id_mjeku'])) {
            $errors[] = 'Mjeku i zgjedhur nuk ekziston.';
        }
        $databaseDate = appointment_datetime_for_database($values['data_ora']);
        if (!$errors && $values['statusi'] === 'planifikuar' && appointment_time_conflicts($pdo, $values['id_mjeku'], $databaseDate)) {
            $errors[] = 'Mjeku ka një termin tjetër të planifikuar në këtë datë dhe orë.';
        }
    }
    if ($pdo instanceof PDO && !$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO termini (id_pacienti, id_mjeku, data_ora, statusi, shenime)
                 VALUES (:patient, :doctor, :date_time, :status, :notes)'
            );
            $statement->execute([
                'patient' => $values['id_pacienti'],
                'doctor' => $values['id_mjeku'],
                'date_time' => appointment_datetime_for_database($values['data_ora']),
                'status' => $values['statusi'],
                'notes' => $values['shenime'] !== '' ? $values['shenime'] : null,
            ]);
            set_flash('success', 'Termini u regjistrua me sukses.');
            redirect_to('/terminet/lista.php');
        } catch (Throwable $exception) {
            error_log('Regjistrimi i terminit dështoi: ' . $exception->getMessage());
            $errors[] = 'Termini nuk u regjistrua. Kontrolloni të dhënat dhe provoni përsëri.';
        }
    }
}

include __DIR__ . '/_forma.php';
