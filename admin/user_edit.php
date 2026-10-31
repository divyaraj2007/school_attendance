<?php
// admin/user_edit.php - Edit User Account
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Edit User Account';
$pdo = get_db_connection();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    flash_message('danger', 'Invalid user ID.');
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    flash_message('danger', 'User account not found.');
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Load role-specific profile
$profile = [];
if ($user['role'] === 'student') {
    $pStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $profile = $pStmt->fetch() ?: [];
} elseif ($user['role'] === 'teacher') {
    $pStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $profile = $pStmt->fetch() ?: [];
} elseif ($user['role'] === 'principal') {
    $pStmt = $pdo->prepare("SELECT * FROM principals WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $profile = $pStmt->fetch() ?: [];
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$sections = [];
if (!empty($profile['class_id'])) {
    $secStmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY name ASC");
    $secStmt->execute([$profile['class_id']]);
    $sections = $secStmt->fetchAll();
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : $user['status'];
        $newPassword = (string)($_POST['new_password'] ?? '');
        $gender = trim($_POST['gender'] ?? 'male');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');

        if (empty($fullName)) {
            $errors[] = 'Full Name is required.';
        }

        $photoPath = $profile['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = handle_profile_upload($_FILES['photo']);
            if ($uploaded) {
                $photoPath = $uploaded;
            }
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                // Update password if provided
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 4) {
                        throw new Exception('New password must be at least 4 characters.');
                    }
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $pwdStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $pwdStmt->execute([$hash, $userId]);
                }

                // Update status
                $statusStmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $statusStmt->execute([$status, $userId]);

                if ($user['role'] === 'student') {
                    $rollNo = (int)($_POST['roll_no'] ?? $profile['roll_no']);
                    $admissionNo = trim($_POST['admission_no'] ?? $profile['admission_no']);
                    $classId = (int)($_POST['class_id'] ?? $profile['class_id']);
                    $sectionId = (int)($_POST['section_id'] ?? $profile['section_id']);

                    $sUpdate = $pdo->prepare("UPDATE students SET full_name = ?, roll_no = ?, admission_no = ?, class_id = ?, section_id = ?, gender = ?, dob = ?, address = ?, photo = ? WHERE user_id = ?");
                    $sUpdate->execute([$fullName, $rollNo, $admissionNo, $classId, $sectionId, $gender, $dob, $address, $photoPath, $userId]);

                } elseif ($user['role'] === 'teacher') {
                    $email = trim($_POST['email'] ?? $profile['email']);
                    $mobile = trim($_POST['mobile'] ?? $profile['mobile']);
                    $qualification = trim($_POST['qualification'] ?? $profile['qualification']);

                    $tUpdate = $pdo->prepare("UPDATE teachers SET full_name = ?, email = ?, mobile = ?, qualification = ?, gender = ?, dob = ?, address = ?, photo = ? WHERE user_id = ?");
                    $tUpdate->execute([$fullName, $email, $mobile, $qualification, $gender, $dob, $address, $photoPath, $userId]);

                } elseif ($user['role'] === 'principal') {
                    $email = trim($_POST['email'] ?? $profile['email']);
                    $mobile = trim($_POST['mobile'] ?? $profile['mobile']);

                    $pUpdate = $pdo->prepare("UPDATE principals SET full_name = ?, email = ?, mobile = ?, gender = ?, dob = ?, address = ?, photo = ? WHERE user_id = ?");
                    $pUpdate->execute([$fullName, $email, $mobile, $gender, $dob, $address, $photoPath, $userId]);
                }

                $pdo->commit();
                log_audit('USER_UPDATED', "User {$user['user_id']} ({$user['role']}) profile updated.");
                flash_message('success', "Account for {$fullName} ({$user['user_id']}) updated successfully.");
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
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
    <h1 class="page-title"><i class="fas fa-user-edit me-2 text-primary"></i> Edit User Account</h1>
    <p class="page-subtitle">Modifying profile and security access for <code><?= e($user['user_id']) ?></code> (<?= ucfirst($user['role']) ?>)</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back to Users
    </a>
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

<div class="card card-custom">
  <div class="card-body p-4">
    <form method="POST" action="" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">User ID</label>
          <input type="text" class="form-control bg-light" value="<?= e($user['user_id']) ?>" readonly>
          <div class="form-text small">Permanent institutional identifier.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Account Role</label>
          <input type="text" class="form-control bg-light text-capitalize" value="<?= e($user['role']) ?>" readonly>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Account Status</label>
          <select name="status" class="form-select">
            <option value="active" <?= ($user['status'] === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($user['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" required value="<?= e($profile['full_name'] ?? 'Admin') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small fw-bold">Reset Password</label>
          <input type="password" name="new_password" class="form-control" placeholder="Leave empty to keep current password">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Gender</label>
          <select name="gender" class="form-select">
            <option value="male" <?= (($profile['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= (($profile['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= (($profile['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Date of Birth</label>
          <input type="date" name="dob" class="form-control" value="<?= e($profile['dob'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Profile Picture</label>
          <input type="file" name="photo" class="form-control" accept="image/*">
        </div>
      </div>

      <!-- STUDENT DETAILS -->
      <?php if ($user['role'] === 'student'): ?>
        <hr class="my-4">
        <h5 class="fw-bold text-primary mb-3">Student Academic Placement</h5>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Admission Number</label>
            <input type="text" name="admission_no" class="form-control" value="<?= e($profile['admission_no'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Roll Number</label>
            <input type="number" name="roll_no" class="form-control" value="<?= (int)($profile['roll_no'] ?? 1) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Class</label>
            <select name="class_id" id="classSelect" class="form-select">
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($profile['class_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Section</label>
            <select name="section_id" id="sectionSelect" class="form-select">
              <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (($profile['section_id'] ?? 0) == $s['id']) ? 'selected' : '' ?>>
                  Section <?= e($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php elseif (in_array($user['role'], ['teacher', 'principal'])): ?>
        <hr class="my-4">
        <h5 class="fw-bold text-primary mb-3">Contact & Institutional Information</h5>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= e($profile['email'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Mobile Phone</label>
            <input type="tel" name="mobile" class="form-control" value="<?= e($profile['mobile'] ?? '') ?>">
          </div>
          <?php if ($user['role'] === 'teacher'): ?>
            <div class="col-md-12">
              <label class="form-label small fw-bold">Qualification</label>
              <input type="text" name="qualification" class="form-control" value="<?= e($profile['qualification'] ?? '') ?>">
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label small fw-bold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= e($profile['address'] ?? '') ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">
          <i class="fas fa-save me-1"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
