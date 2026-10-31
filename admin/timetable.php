<?php
// admin/timetable.php - Timetable Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Class Timetable Management';
$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
$teachers = $pdo->query("SELECT id, teacher_id, full_name FROM teachers ORDER BY full_name ASC")->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 1));
$secStmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY name ASC");
$secStmt->execute([$selectedClassId]);
$sections = $secStmt->fetchAll();
$selectedSectionId = (int)($_GET['section_id'] ?? ($sections[0]['id'] ?? 1));

// Handle POST actions: Save or Delete
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'delete') {
            $slotId = (int)($_POST['slot_id'] ?? 0);
            if ($slotId > 0) {
                try {
                    $delStmt = $pdo->prepare("DELETE FROM timetable WHERE id = ?");
                    $delStmt->execute([$slotId]);
                    log_audit('TIMETABLE_DELETED', "Admin removed timetable slot #{$slotId} from Class {$selectedClassId}-Sec {$selectedSectionId}.");
                    flash_message('success', 'Timetable slot removed successfully.');
                } catch (Exception $e) {
                    flash_message('danger', 'Failed to remove slot: ' . $e->getMessage());
                }
            }
        } else {
            // Save / Update Slot
            $day = $_POST['day_of_week'] ?? 'Monday';
            $period = (int)($_POST['period_number'] ?? 1);
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $startTime = $_POST['start_time'] ?? '09:00';
            $endTime = $_POST['end_time'] ?? '09:45';
            $room = trim($_POST['room_no'] ?? '');

            if ($selectedClassId > 0 && $selectedSectionId > 0 && $subjectId > 0 && $teacherId > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO timetable (academic_year_id, class_id, section_id, subject_id, teacher_id, day_of_week, period_number, start_time, end_time, room_no)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                           ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_id = VALUES(teacher_id), start_time = VALUES(start_time), end_time = VALUES(end_time), room_no = VALUES(room_no)");
                    $stmt->execute([$yearId, $selectedClassId, $selectedSectionId, $subjectId, $teacherId, $day, $period, $startTime, $endTime, $room]);
                    log_audit('TIMETABLE_UPDATED', "Admin saved timetable slot: Class {$selectedClassId}-Sec {$selectedSectionId} ({$day} Period {$period}).");
                    flash_message('success', "Timetable slot ({$day} Period {$period}) saved successfully.");
                } catch (Exception $e) {
                    flash_message('danger', "Failed to save slot: " . $e->getMessage());
                }
            } else {
                flash_message('danger', 'Please select a valid subject and teacher.');
            }
        }
    }
    header("Location: " . BASE_URL . "/admin/timetable.php?class_id={$selectedClassId}&section_id={$selectedSectionId}");
    exit;
}

// Fetch Timetable Slots for Class & Section
$tStmt = $pdo->prepare("SELECT tt.*, s.name as subject_name, s.code as subject_code, t.full_name as teacher_name
                        FROM timetable tt
                        JOIN subjects s ON tt.subject_id = s.id
                        JOIN teachers t ON tt.teacher_id = t.id
                        WHERE tt.academic_year_id = ? AND tt.class_id = ? AND tt.section_id = ?
                        ORDER BY tt.period_number ASC");
$tStmt->execute([$yearId, $selectedClassId, $selectedSectionId]);
$slots = $tStmt->fetchAll();

// Map by [Day][Period]
$schedule = [];
foreach ($slots as $s) {
    $schedule[$s['day_of_week']][$s['period_number']] = $s;
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div>
    <h1 class="page-title"><i class="fas fa-calendar-alt me-2 text-primary"></i> Academic Timetable Management</h1>
    <p class="page-subtitle">Schedule, modify, and manage weekly class periods, subjects, teachers, and classrooms.</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="openAddSlotModal('', 1)">
    <i class="fas fa-plus me-1"></i> Add / Edit Period Slot
  </button>
</div>

<!-- Class / Section Selector -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Select Class</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($selectedClassId === (int)$c['id']) ? 'selected' : '' ?>>
              <?= e($c['name']) ?> (<?= ucfirst($c['type']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold mb-1">Select Section</label>
        <select name="section_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($selectedSectionId === (int)$s['id']) ? 'selected' : '' ?>>
              Section <?= e($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary w-100">
          <i class="fas fa-filter me-1"></i> View Schedule
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Timetable Grid -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Weekly Schedule Grid</h5>
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
                <td class="p-2" style="min-width: 140px; height: 95px; vertical-align: top;">
                  <?php if ($slot): ?>
                    <div class="p-2 rounded bg-primary-subtle border border-primary-subtle text-start small position-relative h-100 d-flex flex-column justify-content-between">
                      <div>
                        <div class="d-flex justify-content-between align-items-start gap-1">
                          <strong class="text-primary text-truncate d-block" title="<?= e($slot['subject_name']) ?>">
                            <?= e($slot['subject_name']) ?>
                          </strong>
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
                                <form method="POST" action="" onsubmit="return confirm('Delete this slot for <?= $day ?>, Period <?= $p ?>?');">
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
                        </div>

                        <div class="text-secondary text-truncate mt-1" style="font-size: 0.75rem;">
                          <i class="fas fa-user-tie me-1"></i><?= e($slot['teacher_name']) ?>
                        </div>

                        <?php if (!empty($slot['room_no'])): ?>
                          <div class="text-muted text-truncate mt-1" style="font-size: 0.7rem;">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i><?= e($slot['room_no']) ?>
                          </div>
                        <?php endif; ?>
                      </div>

                      <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center text-muted" style="font-size: 0.68rem;">
                        <span><?= substr($slot['start_time'], 0, 5) ?> - <?= substr($slot['end_time'], 0, 5) ?></span>
                        <a href="javascript:void(0)" onclick='editSlot(<?= json_encode($slot) ?>)' class="text-decoration-none text-primary" title="Quick Edit">
                          <i class="fas fa-edit"></i>
                        </a>
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
          <h5 class="modal-title fw-bold" id="slotModalTitle"><i class="fas fa-clock text-primary me-2"></i> Set Timetable Slot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
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
              <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= e($sub['name']) ?> (<?= e($sub['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Assigned Teacher</label>
            <select name="teacher_id" id="modalTeacher" class="form-select" required>
              <option value="">-- Choose Teacher --</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?> (<?= e($t['teacher_id']) ?>)</option>
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
            <label class="form-label small fw-bold">Classroom / Lab (Room No)</label>
            <input type="text" name="room_no" id="modalRoom" class="form-control" placeholder="e.g. Room 204 or Physics Lab">
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

function openAddSlotModal(day, period) {
  document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-plus-circle text-primary me-2"></i> Add Timetable Slot';
  if (day) {
    document.getElementById('modalDay').value = day;
  }
  if (period) {
    document.getElementById('modalPeriod').value = period;
    autoFillPeriodTimes(period);
  }
  document.getElementById('modalSubject').value = '';
  document.getElementById('modalTeacher').value = '';
  document.getElementById('modalRoom').value = '';

  const modal = new bootstrap.Modal(document.getElementById('slotModal'));
  modal.show();
}

function editSlot(slot) {
  document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-edit text-primary me-2"></i> Edit Timetable Slot';
  document.getElementById('modalDay').value = slot.day_of_week;
  document.getElementById('modalPeriod').value = slot.period_number;
  document.getElementById('modalSubject').value = slot.subject_id;
  document.getElementById('modalTeacher').value = slot.teacher_id;
  document.getElementById('modalStartTime').value = slot.start_time.substring(0, 5);
  document.getElementById('modalEndTime').value = slot.end_time.substring(0, 5);
  document.getElementById('modalRoom').value = slot.room_no || '';

  const modal = new bootstrap.Modal(document.getElementById('slotModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
