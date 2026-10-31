<?php
// teacher/student_add.php - Add Student within Assigned Class
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Add New Student';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch assigned classes/sections for this teacher
$assignStmt = $pdo->prepare("SELECT DISTINCT ta.class_id, ta.section_id, c.name as class_name, sec.name as section_name
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             WHERE ta.teacher_id = ?");
$assignStmt->execute([$teacherId]);
$assignedPairs = $assignStmt->fetchAll();

if (empty($assignedPairs)) {
    flash_message('danger', 'You do not have any assigned classes to add students to.');
    header('Location: ' . BASE_URL . '/teacher/index.php');
    exit;
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $studentId = trim($_POST['student_id'] ?? generate_student_id());
        $admissionNo = trim($_POST['admission_no'] ?? '');
        $rollNo = (int)($_POST['roll_no'] ?? 0);
        $pairKey = $_POST['class_section'] ?? ''; // e.g. "10_1"
        $password = (string)($_POST['password'] ?? 'password123');
        $gender = trim($_POST['gender'] ?? 'male');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');

        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($admissionNo)) $errors[] = 'Admission number is required.';
        if ($rollNo <= 0) $errors[] = 'Valid roll number is required.';

        // Parse class and section
        $parts = explode('_', $pairKey);
        $classId = (int)($parts[0] ?? 0);
        $sectionId = (int)($parts[1] ?? 0);

        // Security check: Verify teacher is authorized for this pair
        $isAuthorized = false;
        foreach ($assignedPairs as $p) {
            if ($p['class_id'] == $classId && $p['section_id'] == $sectionId) {
                $isAuthorized = true;
                break;
            }
        }
        if (!$isAuthorized) {
            $errors[] = 'Unauthorized: You can only enroll students in your own assigned class/section.';
        }

        // Check duplicate Student ID
        $chk1 = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $chk1->execute([$studentId]);
        if ($chk1->fetch()) {
            $errors[] = "Student ID '{$studentId}' is already registered.";
        }

        // Check duplicate Roll in same class & section
        $chk2 = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND section_id = ? AND roll_no = ?");
        $chk2->execute([$classId, $sectionId, $rollNo]);
        if ($chk2->fetch()) {
            $errors[] = "Roll number #{$rollNo} is already used in this class and section.";
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (user_id, password_hash, role, status) VALUES (?, ?, 'student', 'active')");
                $stmt->execute([$studentId, $hash]);
                $userRowId = (int)$pdo->lastInsertId();

                $stmt2 = $pdo->prepare("INSERT INTO students (user_id, student_id, admission_no, roll_no, full_name, dob, gender, address, class_id, section_id, academic_year_id, admission_date)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->execute([$userRowId, $studentId, $admissionNo, $rollNo, $fullName, $dob, $gender, $address, $classId, $sectionId, $yearId, date('Y-m-d')]);

                $pdo->commit();
                log_audit('STUDENT_ADDED_BY_TEACHER', "Teacher enrolled student {$fullName} ({$studentId}) in Class {$classId}, Sec {$sectionId}.", $currentUserId);
                flash_message('success', "Student {$fullName} ({$studentId}) successfully enrolled.");
                header('Location: ' . BASE_URL . '/teacher/my_students.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to add student: " . $e->getMessage();
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
    <h1 class="page-title"><i class="fas fa-user-plus me-2 text-primary"></i> Enroll Student in Assigned Class</h1>
    <p class="page-subtitle">Add a student directly into your authorized class cohort.</p>
  </div>
  <a href="<?= BASE_URL ?>/teacher/my_students.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i> Back to Student List
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
        <div class="col-md-6">
          <label class="form-label small fw-bold">Student Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Liam Johnson" required value="<?= e($_POST['full_name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small fw-bold">Student ID (Auto-Generated)</label>
          <input type="text" name="student_id" class="form-control bg-light" value="<?= e($_POST['student_id'] ?? generate_student_id()) ?>">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Select Assigned Class & Section <span class="text-danger">*</span></label>
          <select name="class_section" class="form-select" required>
            <?php foreach ($assignedPairs as $p): ?>
              <option value="<?= $p['class_id'] ?>_<?= $p['section_id'] ?>">
                <?= e($p['class_name']) ?> - Section <?= e($p['section_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text small">Restricted strictly to your assigned classes.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Roll Number <span class="text-danger">*</span></label>
          <input type="number" name="roll_no" class="form-control" min="1" placeholder="e.g. 18" required value="<?= e($_POST['roll_no'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Admission Number <span class="text-danger">*</span></label>
          <input type="text" name="admission_no" class="form-control" placeholder="e.g. ADM-2026-105" required value="<?= e($_POST['admission_no'] ?? '') ?>">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Gender</label>
          <select name="gender" class="form-select">
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Date of Birth</label>
          <input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Portal Password</label>
          <input type="password" name="password" class="form-control" value="password123" required>
          <div class="form-text small">Default: <code>password123</code></div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-bold">Residential Address</label>
        <textarea name="address" class="form-control" rows="2"><?= e($_POST['address'] ?? '') ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= BASE_URL ?>/teacher/my_students.php" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">
          <i class="fas fa-check-circle me-1"></i> Enroll Student
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
