<?php
// student/profile.php - Student Profile
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'My Profile';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$sStmt = $pdo->prepare("SELECT s.*, c.name as class_name, sec.name as section_name, y.name as academic_year_name, u.user_id, u.status, u.last_login
                        FROM students s
                        JOIN classes c ON s.class_id = c.id
                        JOIN sections sec ON s.section_id = sec.id
                        JOIN academic_years y ON s.academic_year_id = y.id
                        JOIN users u ON s.user_id = u.id
                        WHERE s.user_id = ?");
$sStmt->execute([$currentUserId]);
$student = $sStmt->fetch();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $address = trim($_POST['address'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;

        $photoPath = $student['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = handle_profile_upload($_FILES['photo']);
            if ($uploaded) {
                $photoPath = $uploaded;
                $_SESSION['user']['photo'] = $photoPath;
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE students SET address = ?, dob = ?, photo = ? WHERE user_id = ?");
            $stmt->execute([$address, $dob, $photoPath, $currentUserId]);
            log_audit('STUDENT_PROFILE_UPDATED', "Student updated permitted profile details.", $currentUserId);
            flash_message('success', 'Profile updated successfully.');
            header('Location: ' . BASE_URL . '/student/profile.php');
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
    <h1 class="page-title"><i class="fas fa-user-circle me-2 text-primary"></i> Student Profile</h1>
    <p class="page-subtitle">Inspect your academic enrollment record and edit personal address details.</p>
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
      <?php if (!empty($student['photo'])): ?>
        <img src="<?= BASE_URL ?>/<?= e($student['photo']) ?>" alt="Avatar" class="rounded-circle shadow mx-auto mb-3" style="width: 100px; height: 100px; object-fit: cover;">
      <?php else: ?>
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow fs-2 fw-bold" style="width: 100px; height: 100px;">
          <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
        </div>
      <?php endif; ?>

      <h4 class="fw-bold text-dark mb-1"><?= e($student['full_name']) ?></h4>
      <div class="badge bg-info text-dark text-uppercase px-3 py-1 mb-3">Student</div>

      <ul class="list-group list-group-flush text-start small">
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Student ID</span>
          <strong><code><?= e($student['student_id']) ?></code></strong>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Class & Section</span>
          <strong><?= e($student['class_name']) ?> - Sec <?= e($student['section_name']) ?></strong>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Roll Number</span>
          <span class="fw-bold text-primary">#<?= $student['roll_no'] ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Admission No</span>
          <span><?= e($student['admission_no']) ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">Academic Year</span>
          <span><?= e($student['academic_year_name']) ?></span>
        </li>
      </ul>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-id-card me-2 text-primary"></i> Personal Details</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="alert alert-light border small mb-4">
            <i class="fas fa-info-circle text-primary me-1"></i> As per institutional rules, students cannot alter their Student ID, Roll Number, Class, or Section. For academic changes, consult your Class Teacher or Principal.
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Full Name (Registered)</label>
              <input type="text" class="form-control bg-light" value="<?= e($student['full_name']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Gender</label>
              <input type="text" class="form-control bg-light text-capitalize" value="<?= e($student['gender']) ?>" readonly>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= e($student['dob'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Update Profile Photo</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Residential Address</label>
            <textarea name="address" class="form-control" rows="3"><?= e($student['address']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary px-4 fw-semibold">
            <i class="fas fa-save me-1"></i> Save Changes
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
