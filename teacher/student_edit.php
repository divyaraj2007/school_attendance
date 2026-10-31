<?php
// teacher/student_edit.php - Edit Student Profile
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Edit Student Details';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

$studentId = (int)($_GET['id'] ?? 0);

// Authorize student belongs to teacher's assigned classes
$stmt = $pdo->prepare("SELECT s.*, c.name as class_name, sec.name as section_name
                       FROM students s
                       JOIN classes c ON s.class_id = c.id
                       JOIN sections sec ON s.section_id = sec.id
                       JOIN teacher_assignments ta ON (s.class_id = ta.class_id AND s.section_id = ta.section_id)
                       WHERE s.id = ? AND ta.teacher_id = ?");
$stmt->execute([$studentId, $teacherId]);
$student = $stmt->fetch();

if (!$student) {
    flash_message('danger', 'Unauthorized or student record not found.');
    header('Location: ' . BASE_URL . '/teacher/my_students.php');
    exit;
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security session.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $rollNo = (int)($_POST['roll_no'] ?? 0);
        $admissionNo = trim($_POST['admission_no'] ?? '');
        $gender = trim($_POST['gender'] ?? 'male');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');

        if (empty($fullName)) $errors[] = 'Full name is required.';
        if ($rollNo <= 0) $errors[] = 'Valid roll number is required.';

        if (empty($errors)) {
            $uStmt = $pdo->prepare("UPDATE students SET full_name = ?, roll_no = ?, admission_no = ?, gender = ?, dob = ?, address = ? WHERE id = ?");
            $uStmt->execute([$fullName, $rollNo, $admissionNo, $gender, $dob, $address, $studentId]);
            log_audit('STUDENT_UPDATED_BY_TEACHER', "Teacher updated student {$fullName} ({$student['student_id']}).", $currentUserId);
            flash_message('success', "Student profile for {$fullName} updated successfully.");
            header('Location: ' . BASE_URL . '/teacher/my_students.php');
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
    <h1 class="page-title"><i class="fas fa-user-edit me-2 text-primary"></i> Edit Student Profile</h1>
    <p class="page-subtitle">Modifying enrollment records for <code><?= e($student['student_id']) ?></code></p>
  </div>
  <a href="<?= BASE_URL ?>/teacher/my_students.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i> Back to Roster
  </a>
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
    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Student ID (Read-only)</label>
          <input type="text" class="form-control bg-light" value="<?= e($student['student_id']) ?>" readonly>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Class & Section</label>
          <input type="text" class="form-control bg-light" value="<?= e($student['class_name']) ?> - Section <?= e($student['section_name']) ?>" readonly>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Admission Date</label>
          <input type="text" class="form-control bg-light" value="<?= format_date($student['admission_date']) ?>" readonly>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($student['full_name']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Roll Number <span class="text-danger">*</span></label>
          <input type="number" name="roll_no" class="form-control" value="<?= (int)$student['roll_no'] ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Admission Number</label>
          <input type="text" name="admission_no" class="form-control" value="<?= e($student['admission_no']) ?>">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold">Gender</label>
          <select name="gender" class="form-select">
            <option value="male" <?= ($student['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= ($student['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= ($student['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-bold">Date of Birth</label>
          <input type="date" name="dob" class="form-control" value="<?= e($student['dob'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-bold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= e($student['address']) ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= BASE_URL ?>/teacher/my_students.php" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">
          <i class="fas fa-save me-1"></i> Save Student Details
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
