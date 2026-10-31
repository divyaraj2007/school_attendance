<?php
// teacher/profile.php - Teacher Profile Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'My Profile';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT t.*, u.user_id, u.status, u.last_login 
                        FROM teachers t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.user_id = ?");
$tStmt->execute([$currentUserId]);
$profile = $tStmt->fetch();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $qualification = trim($_POST['qualification'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $gender = trim($_POST['gender'] ?? 'female');
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;

            if (empty($fullName)) $errors[] = 'Full Name is required.';
            if (empty($email)) $errors[] = 'Email Address is required.';

            $photoPath = $profile['photo'] ?? null;
            if (!empty($_FILES['photo']['name'])) {
                $uploaded = handle_profile_upload($_FILES['photo']);
                if ($uploaded) {
                    $photoPath = $uploaded;
                    $_SESSION['user']['photo'] = $photoPath;
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE teachers SET full_name = ?, email = ?, mobile = ?, qualification = ?, address = ?, gender = ?, dob = ?, photo = ? WHERE user_id = ?");
                $stmt->execute([$fullName, $email, $mobile, $qualification, $address, $gender, $dob, $photoPath, $currentUserId]);
                $_SESSION['user']['full_name'] = $fullName;
                log_audit('TEACHER_PROFILE_UPDATED', "Teacher updated profile.", $currentUserId);
                flash_message('success', 'Profile updated successfully.');
                header('Location: ' . BASE_URL . '/teacher/profile.php');
                exit;
            }
        } elseif ($action === 'change_password') {
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
                $errors[] = 'Password must be at least 4 characters long.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $currentUserId]);
                log_audit('TEACHER_PASSWORD_CHANGED', "Teacher changed password.", $currentUserId);
                flash_message('success', 'Password successfully changed.');
                header('Location: ' . BASE_URL . '/teacher/profile.php');
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
    <h1 class="page-title"><i class="fas fa-user-circle me-2 text-primary"></i> Faculty Profile</h1>
    <p class="page-subtitle">View teaching credentials, edit personal details, and change security password.</p>
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
  <div class="col-lg-4">
    <div class="card card-custom text-center p-4">
      <?php if (!empty($profile['photo'])): ?>
        <img src="<?= BASE_URL ?>/<?= e($profile['photo']) ?>" alt="Avatar" class="rounded-circle shadow mx-auto mb-3" style="width: 100px; height: 100px; object-fit: cover;">
      <?php else: ?>
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow fs-2 fw-bold" style="width: 100px; height: 100px;">
          <?= strtoupper(substr($profile['full_name'] ?? 'T', 0, 1)) ?>
        </div>
      <?php endif; ?>

      <h4 class="fw-bold text-dark mb-1"><?= e($profile['full_name']) ?></h4>
      <div class="badge bg-primary text-uppercase px-3 py-1 mb-3">Teacher</div>

      <ul class="list-group list-group-flush text-start small">
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Teacher ID</span>
          <strong><code><?= e($profile['teacher_id'] ?? $profile['user_id']) ?></code></strong>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Qualification</span>
          <span><?= e($profile['qualification'] ?? '-') ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Joining Date</span>
          <span><?= format_date($profile['joining_date']) ?></span>
        </li>
      </ul>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card card-custom mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-id-card me-2 text-primary"></i> Edit Faculty Information</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Teacher ID (Immutable)</label>
              <input type="text" class="form-control bg-light" value="<?= e($profile['teacher_id'] ?? $profile['user_id']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Mobile Phone</label>
              <input type="tel" name="mobile" class="form-control" value="<?= e($profile['mobile']) ?>">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Qualification</label>
              <input type="text" name="qualification" class="form-control" value="<?= e($profile['qualification'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Profile Photo</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Gender</label>
              <select name="gender" class="form-select">
                <option value="male" <?= ($profile['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= ($profile['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= ($profile['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= e($profile['dob'] ?? '') ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Address</label>
            <textarea name="address" class="form-control" rows="2"><?= e($profile['address']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary px-4 fw-semibold">
            <i class="fas fa-save me-1"></i> Update Faculty Details
          </button>
        </form>
      </div>
    </div>

    <!-- Password Change -->
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-key me-2 text-primary"></i> Change Account Password</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">New Password</label>
              <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Confirm New</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
          </div>

          <div class="mt-3 text-end">
            <button type="submit" class="btn btn-outline-primary fw-semibold">
              <i class="fas fa-lock me-1"></i> Update Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
