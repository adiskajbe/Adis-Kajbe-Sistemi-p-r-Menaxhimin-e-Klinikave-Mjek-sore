<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Ndrysho termin';
$activePage = 'appointments';
$heading = 'Ndrysho termin';
$submitLabel = 'Ruaj ndryshimet';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$action = APP_BASE_URL . '/terminet/ndrysho.php?id=' . (int) $id;
$errors = [];
$patients = [];
$doctors = [];
$appointment = null;
$showForm = true;

if (!$id || $id < 1) {
    http_response_code(400);
    $errors[] = 'ID-ja e terminit nuk është e vlefshme.';
    $showForm = false;
}
if ($pdo instanceof PDO) {
    try {
        $patients = $pdo->query('SELECT id_pacienti, emri, mbiemri FROM pacienti ORDER BY emri, mbiemri')->fetchAll();
        $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri, specializimi FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
        if ($id && $id > 0) {
            $statement = $pdo->prepare(
                'SELECT id_termini, id_pacienti, id_mjeku, data_ora, statusi, shenime FROM termini WHERE id_termini = :id'
            );
            $statement->execute(['id' => $id]);
            $appointment = $statement->fetch();
            if (!$appointment) {
                http_response_code(404);
                $errors[] = 'Termini nuk u gjet.';
                $showForm = false;
            } else {
                $appointment['id_pacienti'] = (int) $appointment['id_pacienti'];
                $appointment['id_mjeku'] = (int) $appointment['id_mjeku'];
                $appointment['data_ora'] = appointment_datetime_for_input($appointment['data_ora']);
            }
        }
    } catch (Throwable $exception) {
        error_log('Leximi i terminit dështoi: ' . $exception->getMessage());
        $databaseError = 'Nuk u arrit leximi i terminit.';
        $pdo = null;
        $showForm = false;
    }
}
$values = $appointment ?: ['id_pacienti' => 0, 'id_mjeku' => 0, 'data_ora' => '', 'statusi' => 'planifikuar', 'shenime' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) {
    $submittedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ((int) $submittedId !== (int) $id || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Forma nuk është e vlefshme. Rifreskojeni faqen dhe provoni përsëri.';
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
        if (!$errors && $values['statusi'] === 'planifikuar' && appointment_time_conflicts($pdo, $values['id_mjeku'], $databaseDate, (int) $id)) {
            $errors[] = 'Mjeku ka një termin tjetër të planifikuar në këtë datë dhe orë.';
        }
    }
    if ($pdo instanceof PDO && !$errors) {
        try {
            $statement = $pdo->prepare(
                'UPDATE termini SET id_pacienti = :patient, id_mjeku = :doctor, data_ora = :date_time,
                 statusi = :status, shenime = :notes WHERE id_termini = :id'
            );
            $statement->execute([
                'patient' => $values['id_pacienti'],
                'doctor' => $values['id_mjeku'],
                'date_time' => appointment_datetime_for_database($values['data_ora']),
                'status' => $values['statusi'],
                'notes' => $values['shenime'] !== '' ? $values['shenime'] : null,
                'id' => $id,
            ]);
            set_flash('success', 'Të dhënat e terminit u përditësuan.');
            redirect_to('/terminet/lista.php');
        } catch (Throwable $exception) {
            error_log('Përditësimi i terminit dështoi: ' . $exception->getMessage());
            $errors[] = 'Ndryshimet nuk u ruajtën. Kontrolloni datën, orën dhe lidhjet.';
        }
    }
}

include __DIR__ . '/_forma.php';
