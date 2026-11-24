<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . APP_BASE_URL . $path, true, 303);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function valid_csrf_token($submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function current_auth_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function auth_user_has_role(string ...$roles): bool
{
    $user = current_auth_user();
    return $user !== null && in_array((string) ($user['role'] ?? ''), $roles, true);
}

function require_roles(string ...$roles): void
{
    if (auth_user_has_role(...$roles)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="sq"><head><meta charset="utf-8"><title>Qasje e ndaluar</title>'
        . '<link rel="stylesheet" href="' . h(APP_BASE_URL) . '/assets/css/tokens.css">'
        . '<link rel="stylesheet" href="' . h(APP_BASE_URL) . '/assets/css/components.css"></head>'
        . '<body class="forbidden-page"><main class="forbidden-box"><h1>Qasje e ndaluar</h1>'
        . '<p>Nuk keni leje për të hyrë në këtë modul.</p>'
        . '<a class="button button-primary" href="' . h(APP_BASE_URL) . '/index.php">Kthehu te paneli</a></main></body></html>';
    exit;
}

function password_policy_error(string $password): ?string
{
    if (mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72) {
        return 'Fjalëkalimi duhet të ketë të paktën 12 karaktere dhe jo më shumë se 72 bajte.';
    }
    $classes = 0;
    $classes += preg_match('/[a-z]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[0-9]/', $password) === 1 ? 1 : 0;
    $classes += preg_match('/[^a-zA-Z0-9]/', $password) === 1 ? 1 : 0;
    if ($classes < 3) {
        return 'Përdorni të paktën 3 lloje karakteresh: shkronja të vogla/të mëdha, numra ose simbole.';
    }
    return null;
}

function auth_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function login_rate_limit_buckets(string $identity, string $ip): array
{
    $buckets = [];
    $normalizedIdentity = strtolower(trim($identity));
    if ($normalizedIdentity !== '') {
        $buckets[] = ['type' => 'identity', 'hash' => hash('sha256', $normalizedIdentity)];
    }
    if ($ip !== '') {
        $buckets[] = ['type' => 'ip', 'hash' => hash('sha256', $ip)];
    }
    return $buckets;
}

function login_is_rate_limited(PDO $pdo, string $identity, string $ip): bool
{
    $statement = $pdo->prepare(
        'SELECT 1 FROM login_rate_limits
         WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash
           AND locked_until > CURRENT_TIMESTAMP LIMIT 1'
    );
    foreach (login_rate_limit_buckets($identity, $ip) as $bucket) {
        $statement->execute(['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']]);
        if ($statement->fetchColumn()) {
            return true;
        }
    }
    return false;
}

function record_failed_login(PDO $pdo, string $identity, string $ip): void
{
    $buckets = login_rate_limit_buckets($identity, $ip);
    if (!$buckets) {
        return;
    }
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO login_rate_limits (bucket_type, bucket_hash, failed_attempts, locked_until)
             VALUES (:bucket_type, :bucket_hash, 0, NULL)'
        );
        $select = $pdo->prepare(
            'SELECT failed_attempts, locked_until, (locked_until > CURRENT_TIMESTAMP) AS is_locked
             FROM login_rate_limits
             WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash FOR UPDATE'
        );
        $update = $pdo->prepare(
            'UPDATE login_rate_limits SET failed_attempts = :failed_attempts,
             locked_until = IF(:is_locked = 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE), NULL),
             updated_at = CURRENT_TIMESTAMP
             WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash'
        );
        foreach ($buckets as $bucket) {
            $params = ['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']];
            $insert->execute($params);
            $select->execute($params);
            $current = $select->fetch();
            if (!$current) {
                continue;
            }
            if ((int) $current['is_locked'] === 1) {
                continue;
            }
            $attempts = $current['locked_until'] !== null ? 1 : ((int) $current['failed_attempts'] + 1);
            $attemptLimit = $bucket['type'] === 'ip' ? 20 : 5;
            $update->execute($params + ['failed_attempts' => $attempts, 'is_locked' => $attempts >= $attemptLimit ? 1 : 0]);
        }
        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function clear_login_rate_limits(PDO $pdo, string $identity, string $ip): void
{
    $statement = $pdo->prepare(
        'DELETE FROM login_rate_limits WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash'
    );
    foreach (login_rate_limit_buckets($identity, $ip) as $bucket) {
        $statement->execute(['bucket_type' => $bucket['type'], 'bucket_hash' => $bucket['hash']]);
    }
}

function log_login_attempt(PDO $pdo, ?int $userId, string $identity, string $ip, string $status): void
{
    $identifier = mb_substr(trim($identity), 0, 190, 'UTF-8');
    $statement = $pdo->prepare(
        'INSERT INTO login_logs (id_perdorues, email_or_username, ip_address, status)
         VALUES (:user_id, :identifier, :ip_address, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'identifier' => $identifier,
        'ip_address' => $ip,
        'status' => $status,
    ]);
}

function absolute_app_url(string $path): string
{
    $configuredUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($configuredUrl !== '') {
        $parts = parse_url($configuredUrl);
        if (is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass'])
            && empty($parts['query']) && empty($parts['fragment'])) {
            return rtrim($configuredUrl, '/') . '/' . ltrim($path, '/');
        }
    }
    return 'http://localhost' . APP_BASE_URL . '/' . ltrim($path, '/');
}

function format_money($amount): string
{
    return number_format((float) $amount, 2, '.', ' ') . ' €';
}

function format_date_sq(?string $value, string $format = 'd.m.Y'): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable $exception) {
        return (string) $value;
    }
}

function calculate_age(?string $birthDate): ?int
{
    if ($birthDate === null || trim($birthDate) === '') {
        return null;
    }
    try {
        $dob = new DateTimeImmutable($birthDate);
        $now = new DateTimeImmutable('today');
        if ($dob > $now) {
            return null;
        }
        return $dob->diff($now)->y;
    } catch (Throwable $exception) {
        return null;
    }
}

function appointment_status_label(string $status): string
{
    $labels = [
        'planifikuar' => 'Planifikuar',
        'konfirmuar' => 'Konfirmuar',
        'ne_pritje' => 'Në pritje',
        'perfunduar' => 'Përfunduar',
        'anuluar' => 'Anuluar',
    ];
    return $labels[$status] ?? $status;
}

function invoice_status_label(string $status): string
{
    $labels = [
        'papaguar' => 'E papaguar',
        'pjeserisht' => 'Pjesërisht',
        'paguar' => 'E paguar',
        'anuluar' => 'Anuluar',
    ];
    return $labels[$status] ?? $status;
}

function payment_method_label(?string $method): string
{
    $labels = [
        'cash' => 'Para në dorë (Cash)',
        'karte' => 'Kartë bankare',
        'transferte' => 'Transfertë bankare',
        'sigurim' => 'Sigurim shëndetësor',
    ];
    return $method !== null && isset($labels[$method]) ? $labels[$method] : '—';
}

function valid_patient_fields(array $input): array
{
    $values = [
        'emri' => trim((string) ($input['emri'] ?? '')),
        'mbiemri' => trim((string) ($input['mbiemri'] ?? '')),
        'datelindja' => trim((string) ($input['datelindja'] ?? '')),
        'gjinia' => trim((string) ($input['gjinia'] ?? '')),
        'nr_personal' => trim((string) ($input['nr_personal'] ?? '')),
        'grupi_gjakut' => trim((string) ($input['grupi_gjakut'] ?? '')),
        'alergjite' => trim((string) ($input['alergjite'] ?? '')),
        'telefoni' => trim((string) ($input['telefoni'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'adresa' => trim((string) ($input['adresa'] ?? '')),
        'kontakti_emergjent' => trim((string) ($input['kontakti_emergjent'] ?? '')),
    ];
    $errors = [];

    if ($values['emri'] === '' || mb_strlen($values['emri']) > 100) {
        $errors[] = 'Emri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['mbiemri'] === '' || mb_strlen($values['mbiemri']) > 100) {
        $errors[] = 'Mbiemri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['datelindja'] !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $values['datelindja']);
        if (!$d || $d->format('Y-m-d') !== $values['datelindja'] || $d > new DateTimeImmutable('today')) {
            $errors[] = 'Datëlindja nuk është e vlefshme.';
        }
    }
    if ($values['gjinia'] !== '' && !in_array($values['gjinia'], ['F', 'M', 'Tjeter'], true)) {
        $errors[] = 'Zgjidhni një gjini të vlefshme.';
    }
    if (mb_strlen($values['nr_personal']) > 30) {
        $errors[] = 'Numri personal nuk duhet të kalojë 30 karaktere.';
    }
    if ($values['grupi_gjakut'] !== '' && !in_array($values['grupi_gjakut'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
        $errors[] = 'Grupi i gjakut nuk është i vlefshëm.';
    }
    if (mb_strlen($values['alergjite']) > 1000) {
        $errors[] = 'Fusha e alergjive duhet të ketë deri në 1000 karaktere.';
    }
    if ($values['telefoni'] === '' || mb_strlen($values['telefoni']) > 30) {
        $errors[] = 'Numri i telefonit është i detyrueshëm dhe duhet të ketë deri në 30 karaktere.';
    }
    if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190)) {
        $errors[] = 'Shkruani një adresë email të vlefshme, deri në 190 karaktere.';
    }
    if (mb_strlen($values['adresa']) > 255) {
        $errors[] = 'Adresa duhet të ketë deri në 255 karaktere.';
    }
    if (mb_strlen($values['kontakti_emergjent']) > 120) {
        $errors[] = 'Kontakti emergjent duhet të ketë deri në 120 karaktere.';
    }
    return [$values, $errors];
}

function valid_doctor_fields(array $input): array
{
    $tarifaRaw = str_replace(',', '.', trim((string) ($input['tarifa_bazike'] ?? '30.00')));
    $values = [
        'emri' => trim((string) ($input['emri'] ?? '')),
        'mbiemri' => trim((string) ($input['mbiemri'] ?? '')),
        'specializimi' => trim((string) ($input['specializimi'] ?? '')),
        'telefoni' => trim((string) ($input['telefoni'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'nr_dhome' => trim((string) ($input['nr_dhome'] ?? '')),
        'orari_punes' => trim((string) ($input['orari_punes'] ?? '')),
        'tarifa_bazike' => is_numeric($tarifaRaw) ? number_format((float) $tarifaRaw, 2, '.', '') : '30.00',
    ];
    $errors = [];

    if ($values['emri'] === '' || mb_strlen($values['emri']) > 100) {
        $errors[] = 'Emri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['mbiemri'] === '' || mb_strlen($values['mbiemri']) > 100) {
        $errors[] = 'Mbiemri është i detyrueshëm dhe duhet të ketë deri në 100 karaktere.';
    }
    if ($values['specializimi'] === '' || mb_strlen($values['specializimi']) > 150) {
        $errors[] = 'Specializimi është i detyrueshëm dhe duhet të ketë deri në 150 karaktere.';
    }
    if ($values['telefoni'] === '' || mb_strlen($values['telefoni']) > 30) {
        $errors[] = 'Numri i telefonit është i detyrueshëm dhe duhet të ketë deri në 30 karaktere.';
    }
    if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190)) {
        $errors[] = 'Shkruani një adresë email të vlefshme, deri në 190 karaktere.';
    }
    if (mb_strlen($values['nr_dhome']) > 30) {
        $errors[] = 'Numri i kabinetit duhet të ketë deri në 30 karaktere.';
    }
    if (mb_strlen($values['orari_punes']) > 120) {
        $errors[] = 'Orari i punës duhet të ketë deri në 120 karaktere.';
    }
    if (!is_numeric($tarifaRaw) || (float) $tarifaRaw < 0 || (float) $tarifaRaw > 10000) {
        $errors[] = 'Tarifa bazike duhet të jetë një vlerë e vlefshme pozitive.';
    }
    return [$values, $errors];
}

function valid_appointment_fields(array $input): array
{
    $patientId = filter_var((string) ($input['id_pacienti'] ?? ''), FILTER_VALIDATE_INT);
    $doctorId = filter_var((string) ($input['id_mjeku'] ?? ''), FILTER_VALIDATE_INT);
    $duration = filter_var((string) ($input['kohezgjatja_min'] ?? '30'), FILTER_VALIDATE_INT);
    $values = [
        'id_pacienti' => $patientId !== false && $patientId > 0 ? (int) $patientId : 0,
        'id_mjeku' => $doctorId !== false && $doctorId > 0 ? (int) $doctorId : 0,
        'data_ora' => trim((string) ($input['data_ora'] ?? '')),
        'kohezgjatja_min' => ($duration !== false && $duration >= 10 && $duration <= 240) ? (int) $duration : 30,
        'lloji_vizites' => trim((string) ($input['lloji_vizites'] ?? 'Kontrollë e përgjithshme')),
        'arsyeja' => trim((string) ($input['arsyeja'] ?? '')),
        'statusi' => trim((string) ($input['statusi'] ?? 'planifikuar')),
        'shenime' => trim((string) ($input['shenime'] ?? '')),
    ];
    if ($values['lloji_vizites'] === '') {
        $values['lloji_vizites'] = 'Kontrollë e përgjithshme';
    }
    $errors = [];

    if ($values['id_pacienti'] < 1) {
        $errors[] = 'Zgjidhni një pacient të vlefshëm.';
    }
    if ($values['id_mjeku'] < 1) {
        $errors[] = 'Zgjidhni një mjek të vlefshëm.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $values['data_ora']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $dateIsValid = $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d\\TH:i') === $values['data_ora'];
    if (!$dateIsValid) {
        $errors[] = 'Zgjidhni një datë dhe orë të vlefshme.';
    }

    $statuses = ['planifikuar', 'konfirmuar', 'ne_pritje', 'perfunduar', 'anuluar'];
    if (!in_array($values['statusi'], $statuses, true)) {
        $errors[] = 'Statusi i terminit nuk është i vlefshëm.';
    } elseif ($dateIsValid && in_array($values['statusi'], ['planifikuar', 'konfirmuar'], true) && $date <= new DateTimeImmutable('now')) {
        $errors[] = 'Termini i planifikuar ose i konfirmuar nuk mund të caktohet në të kaluarën.';
    }
    if (mb_strlen($values['lloji_vizites']) > 100) {
        $errors[] = 'Lloji i vizitës duhet të ketë deri në 100 karaktere.';
    }
    if (mb_strlen($values['arsyeja']) > 255) {
        $errors[] = 'Arsyeja e vizitës duhet të ketë deri në 255 karaktere.';
    }
    if (mb_strlen($values['shenime']) > 2000) {
        $errors[] = 'Shënimet duhet të kenë deri në 2000 karaktere.';
    }
    return [$values, $errors];
}

function valid_ehr_fields(array $input): array
{
    $patientId = filter_var((string) ($input['id_pacienti'] ?? ''), FILTER_VALIDATE_INT);
    $doctorId = filter_var((string) ($input['id_mjeku'] ?? ''), FILTER_VALIDATE_INT);
    $appointmentId = filter_var((string) ($input['id_termini'] ?? ''), FILTER_VALIDATE_INT);
    $pulsiRaw = trim((string) ($input['pulsi'] ?? ''));
    $tempRaw = str_replace(',', '.', trim((string) ($input['temperatura'] ?? '')));
    $weightRaw = str_replace(',', '.', trim((string) ($input['pesha_kg'] ?? '')));

    $values = [
        'id_pacienti' => $patientId !== false && $patientId > 0 ? (int) $patientId : 0,
        'id_mjeku' => $doctorId !== false && $doctorId > 0 ? (int) $doctorId : 0,
        'id_termini' => $appointmentId !== false && $appointmentId > 0 ? (int) $appointmentId : null,
        'data_vizites' => trim((string) ($input['data_vizites'] ?? (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'))),
        'ankesat' => trim((string) ($input['ankesat'] ?? '')),
        'diagnoza' => trim((string) ($input['diagnoza'] ?? '')),
        'kodi_icd' => strtoupper(trim((string) ($input['kodi_icd'] ?? ''))),
        'presioni_gjakut' => trim((string) ($input['presioni_gjakut'] ?? '')),
        'pulsi' => $pulsiRaw,
        'temperatura' => $tempRaw,
        'pesha_kg' => $weightRaw,
        'terapia_receta' => trim((string) ($input['terapia_receta'] ?? '')),
        'rekomandime' => trim((string) ($input['rekomandime'] ?? '')),
    ];
    $errors = [];

    if ($values['id_pacienti'] < 1) {
        $errors[] = 'Zgjidhni një pacient të vlefshëm.';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $values['data_vizites']);
    if (!$date || $date->format('Y-m-d\\TH:i') !== $values['data_vizites']) {
        $errors[] = 'Data dhe ora e vizitës nuk është e vlefshme.';
    }
    if ($values['diagnoza'] === '' || mb_strlen($values['diagnoza']) > 255) {
        $errors[] = 'Diagnoza është e detyrueshme (deri në 255 karaktere).';
    }
    if (mb_strlen($values['kodi_icd']) > 30) {
        $errors[] = 'Kodi ICD duhet të ketë deri në 30 karaktere.';
    }
    if (mb_strlen($values['presioni_gjakut']) > 30) {
        $errors[] = 'Presioni i gjakut duhet të ketë deri në 30 karaktere.';
    }
    if ($pulsiRaw !== '' && (filter_var($pulsiRaw, FILTER_VALIDATE_INT) === false || (int) $pulsiRaw < 25 || (int) $pulsiRaw > 250)) {
        $errors[] = 'Pulsi duhet të jetë ndërmjet 25 dhe 250 rrahje/min.';
    }
    if ($tempRaw !== '' && (!is_numeric($tempRaw) || (float) $tempRaw < 32.0 || (float) $tempRaw > 43.0)) {
        $errors[] = 'Temperatura duhet të jetë ndërmjet 32.0 dhe 43.0 °C.';
    }
    if ($weightRaw !== '' && (!is_numeric($weightRaw) || (float) $weightRaw < 1.0 || (float) $weightRaw > 350.0)) {
        $errors[] = 'Pesha duhet të jetë ndërmjet 1.0 dhe 350.0 kg.';
    }
    return [$values, $errors];
}

function patient_exists(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM pacienti WHERE id_pacienti = :id');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function doctor_exists(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM mjeku WHERE id_mjeku = :id');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function appointment_time_conflicts(PDO $pdo, int $doctorId, string $dateTime, ?int $exceptId = null): bool
{
    $sql = "SELECT COUNT(*) FROM termini
            WHERE id_mjeku = :doctor AND data_ora = :date_time AND statusi IN ('planifikuar', 'konfirmuar', 'ne_pritje')";
    $params = ['doctor' => $doctorId, 'date_time' => $dateTime];
    if ($exceptId !== null) {
        $sql .= ' AND id_termini <> :except_id';
        $params['except_id'] = $exceptId;
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn() > 0;
}

function appointment_datetime_for_database(string $value): string
{
    return str_replace('T', ' ', $value) . ':00';
}

function appointment_datetime_for_input(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\\TH:i');
    } catch (Throwable $exception) {
        return '';
    }
}

function create_notification(PDO $pdo, int $userId, string $title, string $message, string $type = 'sistem'): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO njoftime (id_perdorues, titulli, mesazhi, tipi) VALUES (:user_id, :title, :message, :type)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 150, 'UTF-8'),
            'message' => mb_substr($message, 0, 255, 'UTF-8'),
            'type' => in_array($type, ['termin', 'fature', 'kartele', 'sistem'], true) ? $type : 'sistem',
        ]);
    } catch (Throwable $exception) {
        error_log('Krijimi i njoftimit dështoi: ' . $exception->getMessage());
    }
}

function next_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM fatura WHERE nr_fatures LIKE :prefix');
    $stmt->execute(['prefix' => 'FAT-' . $year . '-%']);
    $next = (int) $stmt->fetchColumn() + 1;
    return sprintf('FAT-%s-%04d', $year, $next);
}
