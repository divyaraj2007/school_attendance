<?php
// admin/profile.php - Admin Profile Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'My Profile';
$pdo = get_db_connection();
$currentUser = current_user();

$userId = (int)$currentUser['id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$userData = $user->fetch();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (!empty($newPass)) {
            if (empty($currentPass) || !password_verify($currentPass, $userData['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif ($newPass !== $confirmPass) {
                $errors[] = 'New password and confirmation do not match.';
            } elseif (strlen($newPass) < 4) {
                $errors[] = 'Password must be at least 4 characters long.';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                log_audit('PASSWORD_CHANGED', "Administrator changed their account password.", $userId);
                flash_message('success', 'Your password has been changed successfully.');
                header('Location: ' . BASE_URL . '/admin/profile.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-user-circle me-2 text-primary"></i> Administrator Profile</h1>
    <p class="page-subtitle">Manage administrative credentials and security preferences.</p>
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

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-custom text-center p-4">
      <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow" style="width: 80px; height: 80px; font-size: 2rem;">
        <i class="fas fa-user-shield"></i>
      </div>
      <h4 class="fw-bold text-dark mb-1">System Administrator</h4>
      <div class="badge bg-danger text-uppercase px-3 py-1 mb-3">Super Admin</div>

      <ul class="list-group list-group-flush text-start small">
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">User ID</span>
          <strong><code><?= e($userData['user_id']) ?></code></strong>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Access Level</span>
          <span class="text-success fw-bold">Full Root Privileges</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Account Created</span>
          <span><?= format_date($userData['created_at']) ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Last Logged In</span>
          <span><?= format_date($userData['last_login'], 'M d, Y h:i A') ?></span>
        </li>
      </ul>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-key me-2 text-primary"></i> Change Administrator Password</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label small fw-bold">Current Password <span class="text-danger">*</span></label>
            <input type="password" name="current_password" class="form-control" required placeholder="Enter existing password">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">New Password <span class="text-danger">*</span></label>
            <input type="password" name="new_password" class="form-control" required placeholder="Minimum 4 characters">
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Confirm New Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="Re-type new password">
          </div>

          <button type="submit" class="btn btn-primary px-4 fw-semibold">
            <i class="fas fa-lock me-1"></i> Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
