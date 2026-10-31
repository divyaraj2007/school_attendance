<?php
// teacher/correction_request.php - Submit & Track Attendance Correction Requests
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'Attendance Correction Requests';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

// Pre-fill parameters if arriving from attendance history
$preType = $_GET['attendance_type'] ?? 'daily';
$preAttendanceId = (int)($_GET['attendance_id'] ?? 0);
$preStudentCode = trim($_GET['student_id'] ?? '');
$preOriginalStatus = trim($_GET['original_status'] ?? 'absent');

$errors = [];

// Handle New Request POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid session token.';
    } else {
        $attType = in_array($_POST['attendance_type'] ?? '', ['daily', 'subject']) ? $_POST['attendance_type'] : 'daily';
        $attId = (int)($_POST['attendance_id'] ?? 0);
        $studentCode = trim($_POST['student_code'] ?? '');
        $origStatus = in_array($_POST['original_status'] ?? '', ['present', 'absent', 'late', 'half_day']) ? $_POST['original_status'] : 'absent';
        $reqStatus = in_array($_POST['requested_status'] ?? '', ['present', 'absent', 'late', 'half_day']) ? $_POST['requested_status'] : 'present';
        $reason = trim($_POST['reason'] ?? '');

        if ($attId <= 0) $errors[] = 'Valid attendance record ID is required.';
        if (empty($reason)) $errors[] = 'Detailed justification/reason is required.';

        // Look up student by student_id
        $sStmt = $pdo->prepare("SELECT id, full_name FROM students WHERE student_id = ?");
        $sStmt->execute([$studentCode]);
        $student = $sStmt->fetch();
        if (!$student) {
            $errors[] = "Student with ID '{$studentCode}' not found.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO correction_requests (attendance_type, attendance_id, student_id, requested_by, requested_status, original_status, reason, status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$attType, $attId, $student['id'], $currentUserId, $reqStatus, $origStatus, $reason]);
                log_audit('CORRECTION_REQUESTED', "Teacher submitted attendance correction request for {$student['full_name']} (ID: {$attId}).", $currentUserId);
                flash_message('success', "Correction request for {$student['full_name']} submitted to Principal for review.");
                header('Location: ' . BASE_URL . '/teacher/correction_request.php');
                exit;
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all requests submitted by this teacher
$myRequestsStmt = $pdo->prepare("SELECT cr.*, s.student_id, s.full_name as student_name, c.name as class_name, sec.name as section_name, reviewer.user_id as reviewer_code
                                 FROM correction_requests cr
                                 JOIN students s ON cr.student_id = s.id
                                 JOIN classes c ON s.class_id = c.id
                                 JOIN sections sec ON s.section_id = sec.id
                                 LEFT JOIN users reviewer ON cr.reviewed_by = reviewer.id
                                 WHERE cr.requested_by = ?
                                 ORDER BY cr.id DESC");
$myRequestsStmt->execute([$currentUserId]);
$myRequests = $myRequestsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-spell-check me-2 text-primary"></i> Attendance Correction Requests</h1>
    <p class="page-subtitle">Submit formal modification requests for past attendance records with supporting reasons.</p>
  </div>
  <a href="<?= BASE_URL ?>/teacher/attendance_history.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-history me-1"></i> Browse Attendance History
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

<div class="row g-4">
  <!-- Submission Form -->
  <div class="col-lg-5">
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-file-signature me-2 text-primary"></i> New Correction Request</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Attendance Type</label>
              <select name="attendance_type" class="form-select">
                <option value="daily" <?= ($preType === 'daily') ? 'selected' : '' ?>>Daily Attendance</option>
                <option value="subject" <?= ($preType === 'subject') ? 'selected' : '' ?>>Subject Attendance</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Record ID <span class="text-danger">*</span></label>
              <input type="number" name="attendance_id" class="form-control" value="<?= $preAttendanceId > 0 ? $preAttendanceId : '' ?>" placeholder="e.g. 14" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Student ID <span class="text-danger">*</span></label>
            <input type="text" name="student_code" class="form-control" value="<?= e($preStudentCode) ?>" placeholder="e.g. STU2026001" required>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Original Status</label>
              <select name="original_status" class="form-select">
                <option value="absent" <?= ($preOriginalStatus === 'absent') ? 'selected' : '' ?>>Absent</option>
                <option value="late" <?= ($preOriginalStatus === 'late') ? 'selected' : '' ?>>Late</option>
                <option value="half_day" <?= ($preOriginalStatus === 'half_day') ? 'selected' : '' ?>>Half Day</option>
                <option value="present" <?= ($preOriginalStatus === 'present') ? 'selected' : '' ?>>Present</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Requested Status <span class="text-danger">*</span></label>
              <select name="requested_status" class="form-select">
                <option value="present">Present</option>
                <option value="late">Late</option>
                <option value="half_day">Half Day</option>
                <option value="absent">Absent</option>
              </select>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Reason for Modification <span class="text-danger">*</span></label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Provide clear explanation (e.g. Medical excuse letter submitted, student was attending debate competition...)" required></textarea>
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="fas fa-paper-plane me-1"></i> Send Request to Principal
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Status of Submitted Requests -->
  <div class="col-lg-7">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title">My Correction Requests History</h5>
        <span class="badge bg-secondary-subtle text-secondary"><?= count($myRequests) ?> Requests</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>Status Change</th>
                <th>Reason</th>
                <th>Review Result</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($myRequests)): ?>
                <?php foreach ($myRequests as $mr): ?>
                  <tr>
                    <td><strong>#<?= $mr['id'] ?></strong></td>
                    <td>
                      <strong><?= e($mr['student_name']) ?></strong>
                      <small class="text-muted d-block"><?= e($mr['student_id']) ?></small>
                    </td>
                    <td>
                      <small><?= ucfirst($mr['original_status']) ?> &rarr; <span class="fw-bold text-success"><?= ucfirst($mr['requested_status']) ?></span></small>
                    </td>
                    <td>
                      <small class="text-muted d-inline-block text-truncate" style="max-width: 160px;" title="<?= e($mr['reason']) ?>">
                        <?= e($mr['reason']) ?>
                      </small>
                    </td>
                    <td>
                      <?= status_badge($mr['status']) ?>
                      <?php if (!empty($mr['review_remarks'])): ?>
                        <small class="text-muted d-block mt-1"><em>"<?= e($mr['review_remarks']) ?>"</em></small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">You have not submitted any correction requests yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
