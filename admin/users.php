<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_roles('admin');
$pageTitle = 'Llogaritë dhe rolet';
$activePage = 'admin';
$flash = take_flash();
$formError = '';
$roles = ['admin' => 'Admin', 'mjek' => 'Mjek', 'recepsionist' => 'Recepsionist', 'pacient' => 'Pacient'];
$doctors = [];
$patients = [];
$accounts = [];

if ($pdo instanceof PDO) {
    try {
        $doctors = $pdo->query('SELECT id_mjeku, emri, mbiemri, specializimi FROM mjeku ORDER BY emri, mbiemri')->fetchAll();
        $patients = $pdo->query('SELECT id_pacienti, emri, mbiemri FROM pacienti ORDER BY emri, mbiemri')->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e mjekëve për llogaritë nuk u lexua: ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = 'Kërkesa nuk u verifikua. Rifreskojeni faqen dhe provoni përsëri.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $username = strtolower(trim((string) ($_POST['username'] ?? '')));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $role = (string) ($_POST['role'] ?? '');
                $temporaryPassword = (string) ($_POST['temporary_password'] ?? '');
                $doctorIdRaw = trim((string) ($_POST['doctor_id'] ?? ''));
                $patientIdRaw = trim((string) ($_POST['patient_id'] ?? ''));
                if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/', $username)) {
                    $formError = 'Emri i përdoruesit duhet të ketë 3–50 karaktere të vlefshme.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    $formError = 'Shkruani një email të vlefshëm.';
                } elseif (!isset($roles[$role])) {
                    $formError = 'Zgjidhni një rol të vlefshëm.';
                } elseif (($policyError = password_policy_error($temporaryPassword)) !== null) {
                    $formError = 'Fjalëkalimi fillestar: ' . $policyError;
                } else {
                    $collision = $pdo->prepare(
                        'SELECT COUNT(*) FROM perdorues
                         WHERE username IN (:username_a, :email_a) OR email IN (:username_b, :email_b)'
                    );
                    $collision->execute([
                        'username_a' => $username, 'email_a' => $email,
                        'username_b' => $username, 'email_b' => $email,
                    ]);
                    if ((int) $collision->fetchColumn() > 0) {
                        $formError = 'Ky emër përdoruesi ose email ekziston.';
                    } else {
                        $linkedDoctorId = null;
                        $linkedPatientId = null;
                        if ($role === 'mjek') {
                            $doctorId = filter_var($doctorIdRaw, FILTER_VALIDATE_INT);
                            if ($doctorId === false || $doctorId < 1) {
                                $formError = 'Lidhni llogarinë e mjekut me një mjek ekzistues.';
                            } else {
                                $doctorCheck = $pdo->prepare(
                                    'SELECT m.id_mjeku FROM mjeku m
                                     LEFT JOIN perdorues u ON u.id_mjeku = m.id_mjeku
                                     WHERE m.id_mjeku = :doctor_id AND u.id_perdorues IS NULL'
                                );
                                $doctorCheck->execute(['doctor_id' => $doctorId]);
                                $linkedDoctorId = $doctorCheck->fetchColumn();
                                if ($linkedDoctorId === false) {
                                    $formError = 'Mjeku nuk ekziston ose është lidhur me një llogari tjetër.';
                                }
                            }
                        }
                        if ($role === 'pacient' && $formError === '') {
                            $patientId = filter_var($patientIdRaw, FILTER_VALIDATE_INT);
                            if ($patientId === false || $patientId < 1) {
                                $formError = 'Lidhni llogarinë e pacientit me një profil ekzistues.';
                            } else {
                                $patientCheck = $pdo->prepare(
                                    'SELECT p.id_pacienti FROM pacienti p
                                     LEFT JOIN perdorues u ON u.id_pacienti = p.id_pacienti
                                     WHERE p.id_pacienti = :patient_id AND u.id_perdorues IS NULL'
                                );
                                $patientCheck->execute(['patient_id' => $patientId]);
                                $linkedPatientId = $patientCheck->fetchColumn();
                                if ($linkedPatientId === false) {
                                    $formError = 'Pacienti nuk ekziston ose është lidhur me një llogari tjetër.';
                                }
                            }
                        }
                        if ($formError === '') {
                            $insert = $pdo->prepare(
                                'INSERT INTO perdorues
                                 (username, email, password_hash, role, is_active, must_change_password, id_mjeku, id_pacienti)
                                 VALUES (:username, :email, :password_hash, :role, 1, 1, :doctor_id, :patient_id)'
                            );
                            $insert->execute([
                                'username' => $username,
                                'email' => $email,
                                'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                                'role' => $role,
                                'doctor_id' => $linkedDoctorId === null ? null : (int) $linkedDoctorId,
                                'patient_id' => $linkedPatientId === null ? null : (int) $linkedPatientId,
                            ]);
                            set_flash('success', 'Llogaria u krijua. Përdoruesi duhet ta ndryshojë fjalëkalimin në kyçjen e parë.');
                            redirect_to('/admin/users.php');
                        }
                    }
                }
            } elseif ($action === 'status') {
                $userId = filter_var((string) ($_POST['user_id'] ?? ''), FILTER_VALIDATE_INT);
                $newStatus = filter_var((string) ($_POST['is_active'] ?? ''), FILTER_VALIDATE_INT);
                if ($userId === false || $userId < 1 || !in_array($newStatus, [0, 1], true)) {
                    $formError = 'Përditësimi i statusit nuk është i vlefshëm.';
                } elseif ((int) $userId === (int) current_auth_user()['id'] && $newStatus === 0) {
                    $formError = 'Nuk mund ta çaktivizoni llogarinë me të cilën jeni kyçur.';
                } else {
                    $pdo->beginTransaction();
                    $activeAdminRows = $pdo->query(
                        "SELECT id_perdorues FROM perdorues WHERE role = 'admin' AND is_active = 1 ORDER BY id_perdorues FOR UPDATE"
                    )->fetchAll();
                    $target = $pdo->prepare('SELECT role, is_active FROM perdorues WHERE id_perdorues = :id');
                    $target->execute(['id' => $userId]);
                    $targetUser = $target->fetch();
                    if (!$targetUser) {
                        $formError = 'Llogaria nuk u gjet.';
                    } elseif ($targetUser['role'] === 'admin' && (int) $targetUser['is_active'] === 1 && $newStatus === 0) {
                        if (count($activeAdminRows) <= 1) {
                            $formError = 'Duhet të mbetet të paktën një administrator aktiv.';
                        }
                    }
                    if ($formError === '') {
                        $update = $pdo->prepare('UPDATE perdorues SET is_active = :is_active WHERE id_perdorues = :id');
                        $update->execute(['is_active' => $newStatus, 'id' => $userId]);
                        $pdo->commit();
                        set_flash('success', $newStatus ? 'Llogaria u aktivizua.' : 'Llogaria u pezullua.');
                        redirect_to('/admin/users.php');
                    }
                    $pdo->rollBack();
                }
            } elseif ($action === 'role') {
                $userId = filter_var((string) ($_POST['user_id'] ?? ''), FILTER_VALIDATE_INT);
                $role = (string) ($_POST['role'] ?? '');
                $doctorIdRaw = trim((string) ($_POST['doctor_id'] ?? ''));
                $patientIdRaw = trim((string) ($_POST['patient_id'] ?? ''));
                if ($userId === false || $userId < 1 || !isset($roles[$role])) {
                    $formError = 'Roli i zgjedhur nuk është i vlefshëm.';
                } elseif ((int) $userId === (int) current_auth_user()['id'] && $role !== 'admin') {
                    $formError = 'Nuk mund ta hiqni rolin Admin nga llogaria juaj aktive.';
                } else {
                    $pdo->beginTransaction();
                    $activeAdminRows = $pdo->query(
                        "SELECT id_perdorues FROM perdorues WHERE role = 'admin' AND is_active = 1 ORDER BY id_perdorues FOR UPDATE"
                    )->fetchAll();
                    $target = $pdo->prepare('SELECT role, is_active FROM perdorues WHERE id_perdorues = :id');
                    $target->execute(['id' => $userId]);
                    $targetUser = $target->fetch();
                    if (!$targetUser) {
                        $formError = 'Llogaria nuk u gjet.';
                    } elseif ($targetUser['role'] === 'admin' && $targetUser['role'] !== $role && (int) $targetUser['is_active'] === 1) {
                        if (count($activeAdminRows) <= 1) {
                            $formError = 'Duhet të mbetet të paktën një administrator aktiv.';
                        }
                    }
                    $linkedDoctorId = null;
                    $linkedPatientId = null;
                    if ($formError === '' && $role === 'mjek') {
                        $doctorId = filter_var($doctorIdRaw, FILTER_VALIDATE_INT);
                        if ($doctorId === false || $doctorId < 1) {
                            $formError = 'Zgjidhni një mjek ekzistues për rolin Mjek.';
                        } else {
                            $doctorCheck = $pdo->prepare(
                                'SELECT id_perdorues FROM perdorues WHERE id_mjeku = :doctor_id AND id_perdorues <> :user_id LIMIT 1'
                            );
                            $doctorCheck->execute(['doctor_id' => $doctorId, 'user_id' => $userId]);
                            if ($doctorCheck->fetchColumn()) {
                                $formError = 'Mjeku është lidhur me një llogari tjetër.';
                            } else {
                                $linkedDoctorId = $doctorId;
                            }
                        }
                    }
                    if ($formError === '' && $role === 'pacient') {
                        $patientId = filter_var($patientIdRaw, FILTER_VALIDATE_INT);
                        if ($patientId === false || $patientId < 1) {
                            $formError = 'Zgjidhni një profil pacienti për rolin Pacient.';
                        } else {
                            $patientCheck = $pdo->prepare(
                                'SELECT p.id_pacienti FROM pacienti p
                                 LEFT JOIN perdorues u ON u.id_pacienti = p.id_pacienti AND u.id_perdorues <> :user_id
                                 WHERE p.id_pacienti = :patient_id AND u.id_perdorues IS NULL'
                            );
                            $patientCheck->execute(['user_id' => $userId, 'patient_id' => $patientId]);
                            $availablePatient = $patientCheck->fetchColumn();
                            if ($availablePatient === false) {
                                $formError = 'Pacienti nuk ekziston ose është lidhur me një llogari tjetër.';
                            } else {
                                $linkedPatientId = $patientId;
                            }
                        }
                    }
                    if ($formError === '') {
                        $update = $pdo->prepare('UPDATE perdorues SET role = :role, id_mjeku = :doctor_id, id_pacienti = :patient_id WHERE id_perdorues = :id');
                        $update->execute(['role' => $role, 'doctor_id' => $linkedDoctorId, 'patient_id' => $linkedPatientId, 'id' => $userId]);
                        $pdo->commit();
                        set_flash('success', 'Roli i llogarisë u përditësua.');
                        redirect_to('/admin/users.php');
                    }
                    $pdo->rollBack();
                }
            } else {
                $formError = 'Veprimi nuk njihet.';
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Menaxhimi i llogarive dështoi: ' . $exception->getMessage());
            $formError = 'Ndryshimi nuk u ruajt. Kontrolloni të dhënat dhe provoni përsëri.';
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $accounts = $pdo->query(
            'SELECT u.id_perdorues, u.username, u.email, u.role, u.is_active, u.must_change_password,
                    u.id_mjeku AS doctor_id, m.emri AS doctor_name, m.mbiemri AS doctor_surname,
                    u.id_pacienti AS patient_id, p.emri AS patient_name, p.mbiemri AS patient_surname
             FROM perdorues u LEFT JOIN mjeku m ON m.id_mjeku = u.id_mjeku
             LEFT JOIN pacienti p ON p.id_pacienti = u.id_pacienti
             ORDER BY u.id_perdorues'
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Lista e llogarive nuk u lexua: ' . $exception->getMessage());
    }
}
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">ADMINISTRIMI</p><h1>Llogaritë dhe rolet</h1><p>Krijoni përdorues, caktoni rolet dhe pezulloni qasjen pa fshirë llogaritë.</p></div></section>
<?php if ($flash): ?><div class="notice notice-success" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($formError !== ''): ?><div class="notice notice-warning" role="alert"><?= h($formError) ?></div><?php endif; ?>
<section class="content-card form-card">
    <h2>Krijo llogari</h2>
    <p class="muted">Jepni fjalëkalim fillestar përdoruesit përmes një kanali të sigurt; ai do të detyrohet ta ndryshojë në kyçjen e parë.</p>
    <form class="form-grid admin-role-form" action="<?= h(APP_BASE_URL) ?>/admin/users.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="create">
        <div class="form-field"><label for="username">Emri i përdoruesit</label><input id="username" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,49}"></div>
        <div class="form-field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="190"></div>
        <div class="form-field"><label for="role">Roli</label><select id="role" name="role" required><option value="recepsionist">Recepsionist</option><option value="mjek">Mjek</option><option value="pacient">Pacient</option><option value="admin">Admin</option></select></div>
        <div class="form-field role-link-field" data-role-link="mjek"><label for="doctor_id">Profili i mjekut</label><select id="doctor_id" name="doctor_id"><option value="">Zgjidhni mjekun</option><?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id_mjeku']) ?>"><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' · ' . $doctor['specializimi']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field role-link-field" data-role-link="pacient"><label for="patient_id">Profili i pacientit</label><select id="patient_id" name="patient_id"><option value="">Zgjidhni pacientin</option><?php foreach ($patients as $patient): ?><option value="<?= h($patient['id_pacienti']) ?>"><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field form-field-wide"><label for="temporary_password">Fjalëkalimi fillestar</label><input id="temporary_password" name="temporary_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><small class="auth-field-hint">12+ karaktere; të paktën 3 lloje karakteresh.</small></div>
        <div class="form-actions form-field-wide"><button class="button button-primary" type="submit">Krijo llogarinë</button></div>
    </form>
</section>
<section class="content-card admin-user-list">
    <h2>Llogaritë ekzistuese</h2>
    <div class="table-wrap"><table><thead><tr><th>Përdoruesi</th><th>Roli / profili i lidhur</th><th>Statusi</th><th>Fjalëkalimi</th><th>Veprimet</th></tr></thead><tbody>
    <?php foreach ($accounts as $account): ?>
        <tr>
            <td><strong><?= h($account['username']) ?></strong><small class="muted"><?= h($account['email']) ?></small></td>
            <td>
                <form class="admin-role-form" action="<?= h(APP_BASE_URL) ?>/admin/users.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= h($account['id_perdorues']) ?>">
                    <select name="role" aria-label="Roli"><?php foreach ($roles as $key => $label): ?><option value="<?= h($key) ?>" <?= $account['role'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <select class="role-link-field" data-role-link="mjek" name="doctor_id" aria-label="Profili i mjekut"><option value="">Zgjidhni mjekun</option><?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id_mjeku']) ?>" <?= (string) $doctor['id_mjeku'] === (string) ($account['doctor_id'] ?? '') ? 'selected' : '' ?>><?= h($doctor['emri'] . ' ' . $doctor['mbiemri'] . ' · ' . $doctor['specializimi']) ?></option><?php endforeach; ?></select>
                    <select class="role-link-field" data-role-link="pacient" name="patient_id" aria-label="Profili i pacientit"><option value="">Zgjidhni pacientin</option><?php foreach ($patients as $patient): ?><option value="<?= h($patient['id_pacienti']) ?>" <?= (string) $patient['id_pacienti'] === (string) ($account['patient_id'] ?? '') ? 'selected' : '' ?>><?= h($patient['emri'] . ' ' . $patient['mbiemri']) ?></option><?php endforeach; ?></select>
                    <button class="link-button" type="submit">Ruaj rolin</button>
                </form>
                <?php if ($account['doctor_name']): ?><small class="muted">Mjeku: <?= h($account['doctor_name'] . ' ' . $account['doctor_surname']) ?></small><?php elseif ($account['patient_name']): ?><small class="muted">Pacienti: <?= h($account['patient_name'] . ' ' . $account['patient_surname']) ?></small><?php endif; ?>
            </td>
            <td><span class="status-pill <?= (int) $account['is_active'] === 1 ? 'status-planifikuar' : 'status-anuluar' ?>"><?= (int) $account['is_active'] === 1 ? 'Aktive' : 'Pezulluar' ?></span></td>
            <td><?= (int) $account['must_change_password'] === 1 ? 'Ndryshim i detyrueshëm' : 'Në rregull' ?></td>
            <td><form action="<?= h(APP_BASE_URL) ?>/admin/users.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="user_id" value="<?= h($account['id_perdorues']) ?>"><input type="hidden" name="is_active" value="<?= (int) $account['is_active'] === 1 ? '0' : '1' ?>"><button class="link-button <?= (int) $account['is_active'] === 1 ? 'danger-link' : '' ?>" type="submit"><?= (int) $account['is_active'] === 1 ? 'Pezullo' : 'Aktivizo' ?></button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><tr><td colspan="5" class="empty-state">Nuk u gjet llogari.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
