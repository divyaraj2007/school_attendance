<?php
// index.php - Modern Login Page
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect_by_role();
}

$error = '';
$schoolName = get_setting('school_name', 'St. Xavier International Academy & College');
$schoolTagline = get_setting('school_tagline', 'Smart Digital Attendance Portal');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $userId = trim($_POST['user_id'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $remember = !empty($_POST['remember']);

        if (empty($userId) || empty($password)) {
            $error = 'Please enter both your User ID and Password.';
        } else {
            $pdo = get_db_connection();
            $sql = "SELECT * FROM users WHERE user_id = ? AND status = 'active' LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid User ID or Password. Please try again.';
                log_audit('LOGIN_FAILED', "Failed login attempt for user ID '{$userId}'.");
            } else {
                // If role was selected in the form and doesn't match
                if (!empty($role) && $user['role'] !== $role) {
                    $error = "Selected role does not match this user account.";
                } else {
                    login_user($user, $remember);
                    flash_message('success', 'Welcome back, ' . e($user['user_id']) . '!');
                    redirect_by_role($user['role']);
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
  <title>Login | <?= e($schoolName) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo.svg">
  
  <!-- Mobile & PWA App Capabilities -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#1e40af">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Smart Attendance">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logo.svg">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
  <div class="login-header">
    <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="School Emblem">
    <h2><?= e($schoolName) ?></h2>
    <p><?= e($schoolTagline) ?></p>
  </div>

  <div class="login-body-content">
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 small" role="alert">
        <i class="fas fa-exclamation-circle me-2 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <?= render_flash_messages() ?>

    <form method="POST" action="<?= BASE_URL ?>/index.php" id="loginForm" autocomplete="off">
      <?= csrf_field() ?>

      <!-- Role Selector Tabs -->
      <div class="role-pill-selector">
        <input type="radio" name="role" id="role_admin" value="admin" checked>
        <label for="role_admin"><i class="fas fa-shield-alt me-1"></i> Admin</label>

        <input type="radio" name="role" id="role_principal" value="principal">
        <label for="role_principal"><i class="fas fa-user-tie me-1"></i> Principal</label>

        <input type="radio" name="role" id="role_teacher" value="teacher">
        <label for="role_teacher"><i class="fas fa-chalkboard-teacher me-1"></i> Teacher</label>

        <input type="radio" name="role" id="role_student" value="student">
        <label for="role_student"><i class="fas fa-user-graduate me-1"></i> Student</label>
      </div>

      <!-- User ID -->
      <div class="mb-3">
        <label for="userIdInput" class="form-label small fw-semibold text-secondary">
          <i class="fas fa-id-card me-1 text-primary"></i> <span id="userIdLabel">Admin ID / User ID</span>
        </label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-user"></i></span>
          <input type="text" class="form-control border-start-0" id="userIdInput" name="user_id" placeholder="e.g. admin or ADM001" required autofocus value="<?= e($_POST['user_id'] ?? '') ?>">
        </div>
      </div>

      <!-- Password with Show/Hide -->
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <label for="passwordInput" class="form-label small fw-semibold text-secondary mb-1">
            <i class="fas fa-lock me-1 text-primary"></i> Password
          </label>
          <a href="<?= BASE_URL ?>/forgot_password.php" class="small text-decoration-none text-primary">Forgot Password?</a>
        </div>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-key"></i></span>
          <input type="password" class="form-control border-start-0 border-end-0" id="passwordInput" name="password" placeholder="Enter password" required>
          <button class="btn btn-outline-secondary border-start-0 toggle-password-btn" type="button" data-target="passwordInput" aria-label="Toggle password visibility">
            <i class="fas fa-eye text-muted"></i>
          </button>
        </div>
      </div>

      <!-- Remember Me -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember" value="1">
        <label class="form-check-label small text-secondary" for="rememberMe">Remember me on this browser</label>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
        <i class="fas fa-sign-in-alt me-2"></i> Sign In to Portal
      </button>
    </form>

    <!-- Quick Credentials Helper for Testing -->
    <div class="quick-login-box">
      <div class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
        <span><i class="fas fa-key text-warning me-1"></i> Demo Credentials (Click to prefill):</span>
      </div>
      <div class="d-flex flex-wrap gap-1 mt-2">
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 text-start small fill-cred-btn" data-role="admin" data-id="admin" data-pass="password123">
          <strong>Admin:</strong> admin
        </button>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 text-start small fill-cred-btn" data-role="principal" data-id="PRN101" data-pass="password123">
          <strong>Principal:</strong> PRN101
        </button>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 text-start small fill-cred-btn" data-role="teacher" data-id="TCH1001" data-pass="password123">
          <strong>Teacher:</strong> TCH1001
        </button>
        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 text-start small fill-cred-btn" data-role="student" data-id="STU2026001" data-pass="password123">
          <strong>Student:</strong> STU2026001
        </button>
      </div>
      <div class="text-muted mt-2" style="font-size: 0.72rem;">Default password for all demo accounts: <code>password123</code></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
  // Dynamic label updating based on chosen role
  const roleRadios = document.querySelectorAll('input[name="role"]');
  const userIdLabel = document.getElementById('userIdLabel');
  const userIdInput = document.getElementById('userIdInput');

  const labelMap = {
    'admin': { label: 'Admin ID / User ID', placeholder: 'e.g. admin or ADM001' },
    'principal': { label: 'Principal ID', placeholder: 'e.g. PRN101' },
    'teacher': { label: 'Teacher ID', placeholder: 'e.g. TCH1001' },
    'student': { label: 'Student ID', placeholder: 'e.g. STU2026001' }
  };

  roleRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const info = labelMap[this.value] || labelMap['admin'];
      userIdLabel.textContent = info.label;
      userIdInput.placeholder = info.placeholder;
    });
  });

  // Prefill credentials on click
  document.querySelectorAll('.fill-cred-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const role = this.getAttribute('data-role');
      const id = this.getAttribute('data-id');
      const pass = this.getAttribute('data-pass');
      
      const roleRadio = document.getElementById('role_' + role);
      if (roleRadio) {
        roleRadio.checked = true;
        roleRadio.dispatchEvent(new Event('change'));
      }
      document.getElementById('userIdInput').value = id;
      document.getElementById('passwordInput').value = pass;
    });
  });
</script>
</body>
</html>
