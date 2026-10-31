<?php
// teacher/my_students.php - Student Management within Assigned Classes
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'My Students';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

// Fetch assigned classes/sections
$assignStmt = $pdo->prepare("SELECT DISTINCT ta.class_id, ta.section_id, c.name as class_name, sec.name as section_name
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             WHERE ta.teacher_id = ?");
$assignStmt->execute([$teacherId]);
$assignedPairs = $assignStmt->fetchAll();

if (empty($assignedPairs)) {
    flash_message('warning', 'You have no assigned classes to manage students.');
}

// Handle Remove Student POST (strictly authorized)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid session token.');
    } else {
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($studentId > 0) {
            // Check that the student belongs to one of this teacher's assigned classes
            $checkStmt = $pdo->prepare("SELECT s.*, u.id as user_row_id 
                                        FROM students s
                                        JOIN users u ON s.user_id = u.id
                                        JOIN teacher_assignments ta ON (s.class_id = ta.class_id AND s.section_id = ta.section_id)
                                        WHERE s.id = ? AND ta.teacher_id = ?");
            $checkStmt->execute([$studentId, $teacherId]);
            $student = $checkStmt->fetch();

            if ($student) {
                $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delStmt->execute([$student['user_row_id']]);
                log_audit('STUDENT_REMOVED_BY_TEACHER', "Teacher removed student {$student['full_name']} ({$student['student_id']}).", $currentUserId);
                flash_message('success', "Student {$student['full_name']} removed successfully.");
            } else {
                flash_message('danger', 'Unauthorized: You do not have permission to remove students outside your assigned classes.');
            }
        }
    }
    header('Location: ' . BASE_URL . '/teacher/my_students.php');
    exit;
}

$selectedPair = $_GET['class_sec'] ?? '';
$classFilter = 0;
$sectionFilter = 0;

if (!empty($selectedPair) && str_contains($selectedPair, ':')) {
    [$classFilter, $sectionFilter] = array_map('intval', explode(':', $selectedPair));
} else {
    $classFilter = (int)($_GET['class_id'] ?? 0);
    $sectionFilter = (int)($_GET['section_id'] ?? 0);
}
$search = trim($_GET['search'] ?? '');

$students = [];
if (!empty($assignedPairs)) {
    $csConditions = [];
    $params = [];
    foreach ($assignedPairs as $p) {
        $csConditions[] = "(s.class_id = ? AND s.section_id = ?)";
        $params[] = (int)$p['class_id'];
        $params[] = (int)$p['section_id'];
    }
    $whereCS = "(" . implode(' OR ', $csConditions) . ")";

    $extraWhere = [];
    if ($classFilter > 0) {
        $extraWhere[] = "s.class_id = ?";
        $params[] = $classFilter;
    }
    if ($sectionFilter > 0) {
        $extraWhere[] = "s.section_id = ?";
        $params[] = $sectionFilter;
    }
    if (!empty($search)) {
        $extraWhere[] = "(s.full_name LIKE ? OR s.student_id LIKE ? OR s.admission_no LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $extraClause = !empty($extraWhere) ? " AND " . implode(' AND ', $extraWhere) : "";

    $sql = "SELECT s.*, c.name as class_name, sec.name as section_name, u.status as account_status
            FROM students s
            JOIN classes c ON s.class_id = c.id
            JOIN sections sec ON s.section_id = sec.id
            JOIN users u ON s.user_id = u.id
            WHERE {$whereCS} {$extraClause}
            ORDER BY c.numeric_level, sec.name, s.roll_no ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-user-graduate me-2 text-primary"></i> Student Management</h1>
    <p class="page-subtitle">Roster of students enrolled within your designated classes and sections.</p>
  </div>
  <a href="<?= BASE_URL ?>/teacher/student_add.php" class="btn btn-primary btn-sm">
    <i class="fas fa-user-plus me-1"></i> Add New Student
  </a>
</div>

<!-- Filters -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Assigned Class / Section</label>
        <select name="class_sec" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0:0">All My Assigned Classes</option>
          <?php foreach ($assignedPairs as $p): ?>
            <?php $pVal = $p['class_id'] . ':' . $p['section_id']; ?>
            <option value="<?= $pVal ?>" <?= ($classFilter === (int)$p['class_id'] && $sectionFilter === (int)$p['section_id']) ? 'selected' : '' ?>>
              <?= e($p['class_name']) ?> - Section <?= e($p['section_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Search Student</label>
        <div class="input-group input-group-sm">
          <input type="text" name="search" class="form-control" placeholder="Search by name, ID, roll no..." value="<?= e($search) ?>">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </div>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/teacher/my_students.php" class="btn btn-light btn-sm w-100 border text-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Students Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Enrolled Students</h5>
    <span class="badge bg-primary-subtle text-primary"><?= count($students) ?> Students</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Roll #</th>
            <th>Student Name</th>
            <th>Student ID</th>
            <th>Class & Section</th>
            <th>Gender</th>
            <th>Admission No</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $stu): ?>
              <tr>
                <td><strong>#<?= $stu['roll_no'] ?></strong></td>
                <td>
                  <strong><?= e($stu['full_name']) ?></strong>
                </td>
                <td><code><?= e($stu['student_id']) ?></code></td>
                <td>
                  <span class="badge bg-light text-dark border">
                    <?= e($stu['class_name']) ?> - Sec <?= e($stu['section_name']) ?>
                  </span>
                </td>
                <td><small class="text-secondary text-capitalize"><?= e($stu['gender']) ?></small></td>
                <td><small class="text-muted"><?= e($stu['admission_no']) ?></small></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="<?= BASE_URL ?>/teacher/student_edit.php?id=<?= $stu['id'] ?>" class="btn btn-outline-secondary" title="Edit Student">
                      <i class="fas fa-edit"></i>
                    </a>
                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove student <?= e($stu['full_name']) ?> from class?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="student_id" value="<?= $stu['id'] ?>">
                      <button type="submit" class="btn btn-outline-danger" title="Remove Student">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No students found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
