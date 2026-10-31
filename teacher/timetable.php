<?php
// teacher/timetable.php - Teacher's Weekly Teaching Schedule & Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'My Teaching Timetable';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$tStmt = $pdo->prepare("SELECT id, teacher_id, full_name FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacher = $tStmt->fetch();
$teacherId = (int)($teacher['id'] ?? 0);

$viewMode = $_GET['view'] ?? 'my'; // 'my' or 'class'

// Fetch teacher's assigned classes
$assignStmt = $pdo->prepare("SELECT DISTINCT ta.class_id, ta.section_id, c.name as class_name, sec.name as section_name
                             FROM teacher_assignments ta
                             JOIN classes c ON ta.class_id = c.id
                             JOIN sections sec ON ta.section_id = sec.id
                             WHERE ta.teacher_id = ? AND ta.academic_year_id = ?
                             ORDER BY c.numeric_level ASC, sec.name ASC");
$assignStmt->execute([$teacherId, $yearId]);
$teacherClasses = $assignStmt->fetchAll();

// If no specific assignments found, allow selecting from existing classes
if (empty($teacherClasses)) {
    $teacherClasses = $pdo->query("SELECT c.id as class_id, sec.id as section_id, c.name as class_name, sec.name as section_name
                                   FROM classes c
                                   JOIN sections sec ON sec.class_id = c.id
                                   ORDER BY c.numeric_level ASC, sec.name ASC LIMIT 15")->fetchAll();
}

$allSubjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? ($teacherClasses[0]['class_id'] ?? 1));
$selectedSectionId = (int)($_GET['section_id'] ?? ($teacherClasses[0]['section_id'] ?? 1));

// Handle POST actions: Save or Delete slot
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $slotId = (int)($_POST['slot_id'] ?? 0);
            if ($slotId > 0) {
                try {
                    $delStmt = $pdo->prepare("DELETE FROM timetable WHERE id = ? AND teacher_id = ?");
                    $delStmt->execute([$slotId, $teacherId]);
                    if ($delStmt->rowCount() > 0) {
                        log_audit('TEACHER_TIMETABLE_DELETED', "Teacher {$teacher['full_name']} removed timetable slot #{$slotId}.");
                        flash_message('success', 'Timetable slot removed successfully.');
                    } else {
                        flash_message('danger', 'You can only remove your own allocated slots.');
                    }
                } catch (Exception $e) {
                    flash_message('danger', 'Failed to remove slot: ' . $e->getMessage());
                }
            }
        } else {
            // Save / Update Slot
            $targetClassId = (int)($_POST['class_id'] ?? $selectedClassId);
            $targetSectionId = (int)($_POST['section_id'] ?? $selectedSectionId);
            $day = $_POST['day_of_week'] ?? 'Monday';
            $period = (int)($_POST['period_number'] ?? 1);
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $startTime = $_POST['start_time'] ?? '09:00';
            $endTime = $_POST['end_time'] ?? '09:45';
            $room = trim($_POST['room_no'] ?? '');

            if ($targetClassId > 0 && $targetSectionId > 0 && $subjectId > 0) {
                // Check if this slot is already occupied by another teacher
                $checkSlot = $pdo->prepare("SELECT tt.*, t.full_name as teacher_name, s.name as subject_name
                                             FROM timetable tt 
                                             JOIN teachers t ON tt.teacher_id = t.id 
                                             JOIN subjects s ON tt.subject_id = s.id
                                             WHERE tt.academic_year_id = ? AND tt.class_id = ? AND tt.section_id = ? AND tt.day_of_week = ? AND tt.period_number = ?");
                $checkSlot->execute([$yearId, $targetClassId, $targetSectionId, $day, $period]);
                $existing = $checkSlot->fetch();

                if ($existing && (int)$existing['teacher_id'] !== $teacherId) {
                    flash_message('danger', "Slot collision: {$day} Period {$period} is already assigned to " . e($existing['teacher_name']) . " (" . e($existing['subject_name']) . "). Please coordinate with the Principal or choose an open period.");
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO timetable (academic_year_id, class_id, section_id, subject_id, teacher_id, day_of_week, period_number, start_time, end_time, room_no)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                               ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_id = VALUES(teacher_id), start_time = VALUES(start_time), end_time = VALUES(end_time), room_no = VALUES(room_no)");
                        $stmt->execute([$yearId, $targetClassId, $targetSectionId, $subjectId, $teacherId, $day, $period, $startTime, $endTime, $room]);
                        log_audit('TEACHER_TIMETABLE_SAVED', "Teacher {$teacher['full_name']} updated timetable slot: Class {$targetClassId}-Sec {$targetSectionId} ({$day} Period {$period}).");
                        flash_message('success', "Timetable slot ({$day} Period {$period}) saved successfully.");
                    } catch (Exception $e) {
                        flash_message('danger', "Failed to save slot: " . $e->getMessage());
                    }
                }
            } else {
                flash_message('danger', 'Please provide a valid class and subject.');
            }
        }
    }

    if ($viewMode === 'class') {
        header("Location: " . BASE_URL . "/teacher/timetable.php?view=class&class_id={$selectedClassId}&section_id={$selectedSectionId}");
    } else {
        header("Location: " . BASE_URL . "/teacher/timetable.php?view=my");
    }
    exit;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Period time defaults
$periodTimes = [
    1 => ['09:00', '09:45'],
    2 => ['09:50', '10:35'],
    3 => ['10:50', '11:35'],
    4 => ['11:40', '12:25'],
    5 => ['01:15', '02:00'],
    6 => ['02:05', '02:50']
];

$schedule = [];

if ($viewMode === 'class') {
    $slotsStmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name, sub.name as subject_name, sub.code as subject_code, t.full_name as teacher_name
                                FROM timetable tt
                                JOIN classes c ON tt.class_id = c.id
                                JOIN sections sec ON tt.section_id = sec.id
                                JOIN subjects sub ON tt.subject_id = sub.id
                                JOIN teachers t ON tt.teacher_id = t.id
                                WHERE tt.academic_year_id = ? AND tt.class_id = ? AND tt.section_id = ?
                                ORDER BY tt.period_number ASC");
    $slotsStmt->execute([$yearId, $selectedClassId, $selectedSectionId]);
} else {
    $slotsStmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name, sub.name as subject_name, sub.code as subject_code, t.full_name as teacher_name
                                FROM timetable tt
                                JOIN classes c ON tt.class_id = c.id
                                JOIN sections sec ON tt.section_id = sec.id
                                JOIN subjects sub ON tt.subject_id = sub.id
                                JOIN teachers t ON tt.teacher_id = t.id
                                WHERE tt.teacher_id = ? AND tt.academic_year_id = ?
                                ORDER BY tt.period_number ASC");
    $slotsStmt->execute([$teacherId, $yearId]);
}

$slots = $slotsStmt->fetchAll();
foreach ($slots as $s) {
    $schedule[$s['day_of_week']][$s['period_number']] = $s;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div>
    <h1 class="page-title"><i class="fas fa-clock me-2 text-primary"></i> Faculty Timetable Management</h1>
    <p class="page-subtitle">View and update your allocated weekly lecture sessions, classroom assignments, and timings.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openAddSlotModal('', 1)">
    <i class="fas fa-plus me-1"></i> Add / Edit Timetable Slot
  </button>
</div>

<!-- View Switcher -->
<ul class="nav nav-pills mb-4">
  <li class="nav-item">
    <a class="nav-link <?= ($viewMode === 'my') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/timetable.php?view=my">
      <i class="fas fa-user-clock me-1"></i> My Personal Teaching Schedule
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= ($viewMode === 'class') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/timetable.php?view=class&class_id=<?= $selectedClassId ?>&section_id=<?= $selectedSectionId ?>">
      <i class="fas fa-school me-1"></i> View & Claim Class Timetable
    </a>
  </li>
</ul>

<?php if ($viewMode === 'class'): ?>
  <!-- Class Selector -->
  <div class="card card-custom mb-4">
    <div class="card-body">
      <form method="GET" action="" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="class">
        <div class="col-md-10">
          <label class="form-label small fw-bold mb-1">Select Assigned Class & Section</label>
          <select name="class_and_sec" class="form-select" onchange="const p = this.value.split(':'); window.location.href = '<?= BASE_URL ?>/teacher/timetable.php?view=class&class_id=' + p[0] + '&section_id=' + p[1];">
            <?php foreach ($teacherClasses as $tc): ?>
              <?php $selectedKey = ($selectedClassId === (int)$tc['class_id'] && $selectedSectionId === (int)$tc['section_id']) ? 'selected' : ''; ?>
              <option value="<?= $tc['class_id'] ?>:<?= $tc['section_id'] ?>" <?= $selectedKey ?>>
                <?= e($tc['class_name']) ?> - Section <?= e($tc['section_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary w-100">
            <i class="fas fa-filter me-1"></i> View Class
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Timetable Grid -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">
      <?php if ($viewMode === 'my'): ?>
        Weekly Lecture Schedule &bull; <?= e($teacher['full_name']) ?>
      <?php else: ?>
        Full Class Schedule &bull; Class <?= e($selectedClassId) ?> - Section <?= e($selectedSectionId) ?>
      <?php endif; ?>
    </h5>
    <span class="badge bg-primary-subtle text-primary">Academic Year: <?= e($activeYear['name'] ?? '2026-2027') ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-custom text-center align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 130px;">Day</th>
            <?php for ($p = 1; $p <= 6; $p++): ?>
              <th>
                Period <?= $p ?><br>
                <small class="text-muted"><?= $periodTimes[$p][0] ?> - <?= $periodTimes[$p][1] ?></small>
              </th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($days as $day): ?>
            <tr>
              <td class="fw-bold bg-light text-start ps-3"><?= $day ?></td>
              <?php for ($p = 1; $p <= 6; $p++): ?>
                <?php $slot = $schedule[$day][$p] ?? null; ?>
                <?php $isMySlot = $slot && ((int)$slot['teacher_id'] === $teacherId); ?>

                <td class="p-2" style="min-width: 140px; height: 95px; vertical-align: top;">
                  <?php if ($slot): ?>
                    <div class="p-2 rounded <?= $isMySlot ? 'bg-primary-subtle border border-primary-subtle' : 'bg-light border text-muted' ?> text-start small position-relative h-100 d-flex flex-column justify-content-between">
                      <div>
                        <div class="d-flex justify-content-between align-items-start gap-1">
                          <strong class="<?= $isMySlot ? 'text-primary' : 'text-dark' ?> text-truncate d-block" title="<?= e($slot['subject_name']) ?>">
                            <?= e($slot['subject_name']) ?>
                          </strong>

                          <?php if ($isMySlot): ?>
                            <div class="dropdown">
                              <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v fa-xs"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                <li>
                                  <a class="dropdown-item" href="javascript:void(0)" onclick='editSlot(<?= json_encode($slot) ?>)'>
                                    <i class="fas fa-pencil-alt text-primary me-2"></i> Edit Slot
                                  </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                  <form method="POST" action="" onsubmit="return confirm('Remove your slot for <?= $day ?>, Period <?= $p ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                      <i class="fas fa-trash-alt me-2"></i> Delete Slot
                                    </button>
                                  </form>
                                </li>
                              </ul>
                            </div>
                          <?php endif; ?>
                        </div>

                        <div class="text-secondary text-truncate mt-1" style="font-size: 0.75rem;">
                          <?php if ($viewMode === 'my'): ?>
                            <i class="fas fa-school me-1 text-primary"></i><?= e($slot['class_name']) ?> - Sec <?= e($slot['section_name']) ?>
                          <?php else: ?>
                            <i class="fas fa-user-tie me-1"></i><?= $isMySlot ? '<span class="badge bg-success-subtle text-success">You</span>' : e($slot['teacher_name']) ?>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($slot['room_no'])): ?>
                          <div class="text-muted text-truncate mt-1" style="font-size: 0.7rem;">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i><?= e($slot['room_no']) ?>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center text-muted" style="font-size: 0.68rem;">
                        <span><?= substr($slot['start_time'], 0, 5) ?> - <?= substr($slot['end_time'], 0, 5) ?></span>
                        <?php if ($isMySlot): ?>
                          <a href="javascript:void(0)" onclick='editSlot(<?= json_encode($slot) ?>)' class="text-decoration-none text-primary" title="Quick Edit">
                            <i class="fas fa-edit"></i>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center py-2">
                      <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 border-dashed" style="font-size: 0.75rem; border-style: dashed;" onclick="openAddSlotModal('<?= $day ?>', <?= $p ?>)">
                        <i class="fas fa-plus fa-xs me-1"></i> Add
                      </button>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Add / Edit Timetable Slot -->
<div class="modal fade" id="slotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="slotForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="slotModalTitle"><i class="fas fa-clock text-primary me-2"></i> Update Timetable Slot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Class & Section</label>
            <select name="class_and_sec" id="modalClassSec" class="form-select" onchange="syncClassSec(this.value)" required>
              <?php foreach ($teacherClasses as $tc): ?>
                <?php $val = $tc['class_id'] . ':' . $tc['section_id']; ?>
                <option value="<?= $val ?>"><?= e($tc['class_name']) ?> - Section <?= e($tc['section_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="class_id" id="modalClassId" value="<?= $teacherClasses[0]['class_id'] ?? 1 ?>">
            <input type="hidden" name="section_id" id="modalSectionId" value="<?= $teacherClasses[0]['section_id'] ?? 1 ?>">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Day of Week</label>
              <select name="day_of_week" id="modalDay" class="form-select" required>
                <?php foreach ($days as $d): ?>
                  <option value="<?= $d ?>"><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Period Number</label>
              <select name="period_number" id="modalPeriod" class="form-select" onchange="autoFillPeriodTimes(this.value)" required>
                <?php for ($i = 1; $i <= 6; $i++): ?>
                  <option value="<?= $i ?>">Period <?= $i ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Subject</label>
            <select name="subject_id" id="modalSubject" class="form-select" required>
              <option value="">-- Choose Subject --</option>
              <?php foreach ($allSubjects as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= e($sub['name']) ?> (<?= e($sub['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Start Time</label>
              <input type="time" name="start_time" id="modalStartTime" class="form-control" value="09:00" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">End Time</label>
              <input type="time" name="end_time" id="modalEndTime" class="form-control" value="09:45" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Classroom / Laboratory (Room No)</label>
            <input type="text" name="room_no" id="modalRoom" class="form-control" placeholder="e.g. Room 101, Lab 2, or Smart Hall">
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Timetable Slot</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const periodDefaults = <?= json_encode($periodTimes) ?>;

function autoFillPeriodTimes(period) {
  if (periodDefaults[period]) {
    document.getElementById('modalStartTime').value = periodDefaults[period][0];
    document.getElementById('modalEndTime').value = periodDefaults[period][1];
  }
}

function syncClassSec(val) {
  const parts = val.split(':');
  document.getElementById('modalClassId').value = parts[0];
  document.getElementById('modalSectionId').value = parts[1];
}

function openAddSlotModal(day, period) {
  document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-plus-circle text-primary me-2"></i> Add / Claim Timetable Slot';
  if (day) {
    document.getElementById('modalDay').value = day;
  }
  if (period) {
    document.getElementById('modalPeriod').value = period;
    autoFillPeriodTimes(period);
  }
  document.getElementById('modalSubject').value = '';
  document.getElementById('modalRoom').value = '';

  const modal = new bootstrap.Modal(document.getElementById('slotModal'));
  modal.show();
}

function editSlot(slot) {
  document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-edit text-primary me-2"></i> Edit Timetable Slot';
  const classSecVal = slot.class_id + ':' + slot.section_id;
  const selectElem = document.getElementById('modalClassSec');
  if (selectElem) {
    selectElem.value = classSecVal;
    syncClassSec(classSecVal);
  }
  document.getElementById('modalDay').value = slot.day_of_week;
  document.getElementById('modalPeriod').value = slot.period_number;
  document.getElementById('modalSubject').value = slot.subject_id;
  document.getElementById('modalStartTime').value = slot.start_time.substring(0, 5);
  document.getElementById('modalEndTime').value = slot.end_time.substring(0, 5);
  document.getElementById('modalRoom').value = slot.room_no || '';

  const modal = new bootstrap.Modal(document.getElementById('slotModal'));
  modal.show();
}

// Initial sync
syncClassSec(document.getElementById('modalClassSec').value);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
