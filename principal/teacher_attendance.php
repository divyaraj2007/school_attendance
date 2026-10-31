<?php
// principal/teacher_attendance.php - Teacher Attendance Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Teacher Attendance';
$pdo = get_db_connection();
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');

// Handle Save Attendance POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Session token invalid.');
    } else {
        $teacherStatuses = $_POST['status'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $date;
        $currentUserId = (int)$_SESSION['user']['id'];

        if (!empty($teacherStatuses)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks, marked_by)
                                       VALUES (?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)");

                foreach ($teacherStatuses as $teacherId => $status) {
                    $validStatus = in_array($status, ['present', 'absent', 'late', 'half_day', 'on_leave'], true) ? $status : 'present';
                    $remark = trim($remarks[$teacherId] ?? '');
                    $stmt->execute([(int)$teacherId, $attDate, $validStatus, $remark, $currentUserId]);
                }

                $pdo->commit();
                log_audit('TEACHER_ATTENDANCE_SAVED', "Principal marked teacher attendance for {$attDate}.");
                flash_message('success', "Teacher attendance for " . count($teacherStatuses) . " faculty members recorded.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_message('danger', "Failed to save teacher attendance: " . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "/principal/teacher_attendance.php?date={$date}");
    exit;
}

// Fetch Teachers & Their Attendance for the selected date
$query = "SELECT t.*, ta.status as recorded_status, ta.remarks as recorded_remarks
          FROM teachers t
          LEFT JOIN teacher_attendance ta ON t.id = ta.teacher_id AND ta.attendance_date = ?
          ORDER BY t.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([$date]);
$teachers = $stmt->fetchAll();

// Monthly summary stats for teachers
$summaryStmt = $pdo->prepare("SELECT 
                                 COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                                 COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                                 COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                                 COUNT(CASE WHEN status = 'on_leave' THEN 1 END) as leave_count,
                                 COUNT(*) as total_records
                              FROM teacher_attendance 
                              WHERE attendance_date LIKE ?");
$summaryStmt->execute([$month . '%']);
$monthlyStats = $summaryStmt->fetch();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i> Faculty Attendance Management</h1>
    <p class="page-subtitle">Track, mark, and monitor daily teacher attendance, leaves, and monthly statistics.</p>
  </div>
</div>

<!-- Date / Month Filter -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-3 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Mark Attendance for Date</label>
        <div class="input-group">
          <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
          <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-day me-1"></i> Load Date</button>
        </div>
      </div>

      <div class="col-md-7">
        <div class="d-flex justify-content-end align-items-center gap-3 pt-md-3">
          <div class="small text-muted">Monthly Summary (<?= date('F Y', strtotime($month . '-01')) ?>):</div>
          <span class="badge bg-success-subtle text-success border border-success px-2 py-1"><?= (int)($monthlyStats['present_count'] ?? 0) ?> Present</span>
          <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><?= (int)($monthlyStats['absent_count'] ?? 0) ?> Absent</span>
          <span class="badge bg-info-subtle text-info-emphasis border border-info px-2 py-1"><?= (int)($monthlyStats['leave_count'] ?? 0) ?> On Leave</span>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Teacher Attendance Form -->
<form method="POST" action="">
  <?= csrf_field() ?>
  <input type="hidden" name="attendance_date" value="<?= e($date) ?>">

  <div class="card card-custom">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title">Faculty Roster: <?= format_date($date) ?></h5>
      <span class="badge bg-primary-subtle text-primary"><?= count($teachers) ?> Faculty Members</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Teacher ID</th>
              <th>Faculty Name</th>
              <th>Qualification</th>
              <th>Contact</th>
              <th class="text-center" style="width: 360px;">Attendance Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($teachers)): ?>
              <?php foreach ($teachers as $t): ?>
                <?php $currentStatus = $t['recorded_status'] ?? 'present'; ?>
                <tr>
                  <td><code><?= e($t['teacher_id']) ?></code></td>
                  <td>
                    <strong><?= e($t['full_name']) ?></strong>
                  </td>
                  <td><small class="text-muted"><?= e($t['qualification'] ?? '-') ?></small></td>
                  <td>
                    <small class="text-secondary d-block"><i class="far fa-envelope me-1"></i><?= e($t['email']) ?></small>
                    <small class="text-secondary"><i class="fas fa-phone-alt me-1"></i><?= e($t['mobile']) ?></small>
                  </td>
                  <td class="text-center">
                    <div class="attendance-toggle-group">
                      <input type="radio" name="status[<?= $t['id'] ?>]" id="t_pres_<?= $t['id'] ?>" value="present" <?= ($currentStatus === 'present') ? 'checked' : '' ?>>
                      <label for="t_pres_<?= $t['id'] ?>">Present</label>

                      <input type="radio" name="status[<?= $t['id'] ?>]" id="t_abs_<?= $t['id'] ?>" value="absent" <?= ($currentStatus === 'absent') ? 'checked' : '' ?>>
                      <label for="t_abs_<?= $t['id'] ?>">Absent</label>

                      <input type="radio" name="status[<?= $t['id'] ?>]" id="t_late_<?= $t['id'] ?>" value="late" <?= ($currentStatus === 'late') ? 'checked' : '' ?>>
                      <label for="t_late_<?= $t['id'] ?>">Late</label>

                      <input type="radio" name="status[<?= $t['id'] ?>]" id="t_half_<?= $t['id'] ?>" value="half_day" <?= ($currentStatus === 'half_day') ? 'checked' : '' ?>>
                      <label for="t_half_<?= $t['id'] ?>">Half Day</label>

                      <input type="radio" name="status[<?= $t['id'] ?>]" id="t_leave_<?= $t['id'] ?>" value="on_leave" <?= ($currentStatus === 'on_leave') ? 'checked' : '' ?>>
                      <label for="t_leave_<?= $t['id'] ?>">Leave</label>
                    </div>
                  </td>
                  <td>
                    <input type="text" name="remarks[<?= $t['id'] ?>]" class="form-control form-control-sm" placeholder="Optional notes..." value="<?= e($t['recorded_remarks'] ?? '') ?>">
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No teachers registered in the database.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer bg-white p-3 text-end">
      <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Save Teacher Attendance
      </button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
