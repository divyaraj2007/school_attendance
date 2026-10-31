<?php
// student/change_password.php - Student Change Password
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'Change Password';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session token invalid.';
    } else {
        $currPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confPass = (string)($_POST['confirm_password'] ?? '');

        $uStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $uStmt->execute([$currentUserId]);
        $hash = $uStmt->fetchColumn();

        if (empty($currPass) || !password_verify($currPass, (string)$hash)) {
            $errors[] = 'Current password is not correct.';
        } elseif ($newPass !== $confPass) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($newPass) < 4) {
            $errors[] = 'New password must be at least 4 characters long.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $currentUserId]);
            log_audit('STUDENT_PASSWORD_CHANGED', "Student updated account password.", $currentUserId);
            flash_message('success', 'Your password has been successfully updated.');
            header('Location: ' . BASE_URL . '/student/change_password.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-key me-2 text-primary"></i> Change Portal Password</h1>
    <p class="page-subtitle">Update your personal login password for the student portal.</p>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger shadow-sm">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-md-8 col-lg-6">
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-lock me-2 text-primary"></i> Update Credentials</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label small fw-bold">Current Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="current_password" id="currPass" class="form-control" required placeholder="Enter your current password">
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="currPass">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="new_password" id="newPass" class="form-control" required placeholder="Minimum 4 characters">
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="newPass">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="confPass" class="form-control" required placeholder="Re-type new password">
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="confPass">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="fas fa-shield-alt me-1"></i> Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
