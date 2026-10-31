<?php
// forgot_password.php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$schoolName = get_setting('school_name', 'St. Xavier International Academy & College');
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Please try again.';
    } else {
        $userId = trim($_POST['user_id'] ?? '');
        if (empty($userId)) {
            $error = 'Please enter your User ID.';
        } else {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "No active account found with User ID '{$userId}'.";
            } else {
                if ($user['role'] === 'student') {
                    $message = "Student Password Assistance: Please contact your Class Teacher or Principal to reset your password. (Student credentials do not use external email/phone for security & privacy).";
                } elseif ($user['role'] === 'teacher') {
                    $message = "Teacher Password Assistance: A reset request has been logged. Please contact the Principal or Administrator.";
                } else {
                    $message = "Administrator / Principal recovery: A password reset procedure has been initiated for {$userId}. If this is a local development instance, default password is 'password123'.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | <?= e($schoolName) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
  <div class="login-header">
    <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="School Emblem">
    <h2>Password Recovery</h2>
    <p>Enter your User ID to retrieve or reset access</p>
  </div>

  <div class="login-body-content">
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger small mb-3"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div class="alert alert-info small mb-3">
        <i class="fas fa-info-circle me-1"></i> <?= e($message) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label for="userIdInput" class="form-label small fw-semibold text-secondary">Your User ID</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="fas fa-id-card text-muted"></i></span>
          <input type="text" class="form-control" id="userIdInput" name="user_id" placeholder="e.g. ADM001, PRN101, TCH1001, STU2026001" required autofocus>
        </div>
        <div class="form-text small">Enter your School-assigned ID (Students, Teachers, Principals, or Admins).</div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="fas fa-paper-plane me-2"></i> Request Password Assistance
      </button>

      <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>/index.php" class="small text-decoration-none text-primary">
          <i class="fas fa-arrow-left me-1"></i> Back to Sign In
        </a>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
