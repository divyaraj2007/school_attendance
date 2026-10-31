<?php
// admin/academics.php - Academic Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Academic Management';
$pdo = get_db_connection();

// Process POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/admin/academics.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Add Academic Year
    if ($action === 'add_academic_year') {
        $name = trim($_POST['year_name'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $isCurrent = !empty($_POST['is_current']) ? 1 : 0;

        if (!empty($name) && !empty($startDate) && !empty($endDate)) {
            try {
                if ($isCurrent) {
                    $pdo->query("UPDATE academic_years SET is_current = 0");
                }
                $stmt = $pdo->prepare("INSERT INTO academic_years (name, start_date, end_date, is_current) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $startDate, $endDate, $isCurrent]);
                log_audit('ACADEMIC_YEAR_ADDED', "Added academic year: {$name}.");
                flash_message('success', "Academic Year '{$name}' created successfully.");
            } catch (Exception $e) {
                flash_message('danger', "Error: " . $e->getMessage());
            }
        }
    }

    // 2. Set Current Academic Year
    elseif ($action === 'set_current_year') {
        $yearId = (int)($_POST['year_id'] ?? 0);
        if ($yearId > 0) {
            $pdo->query("UPDATE academic_years SET is_current = 0");
            $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?");
            $stmt->execute([$yearId]);
            log_audit('CURRENT_YEAR_UPDATED', "Academic Year ID {$yearId} set as current active.");
            flash_message('success', "Current academic year updated.");
        }
    }

    // 3. Add Class
    elseif ($action === 'add_class') {
        $className = trim($_POST['class_name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['school', 'college']) ? $_POST['type'] : 'school';
        $level = (int)($_POST['numeric_level'] ?? 1);

        if (!empty($className)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (name, type, numeric_level) VALUES (?, ?, ?)");
                $stmt->execute([$className, $type, $level]);
                $classId = (int)$pdo->lastInsertId();

                // Automatically add default Section A
                $pdo->prepare("INSERT INTO sections (class_id, name) VALUES (?, 'A')")->execute([$classId]);

                log_audit('CLASS_ADDED', "Added class: {$className} ({$type}).");
                flash_message('success', "Class '{$className}' added with Section A.");
            } catch (Exception $e) {
                flash_message('danger', "Class already exists or error occurred: " . $e->getMessage());
            }
        }
    }

    // 4. Add Section
    elseif ($action === 'add_section') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $sectionName = strtoupper(trim($_POST['section_name'] ?? ''));

        if ($classId > 0 && !empty($sectionName)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sections (class_id, name) VALUES (?, ?)");
                $stmt->execute([$classId, $sectionName]);
                flash_message('success', "Section {$sectionName} added to class.");
            } catch (Exception $e) {
                flash_message('danger', "Section already exists in this class.");
            }
        }
    }

    // 5. Add Subject
    elseif ($action === 'add_subject') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['core', 'elective', 'practical']) ? $_POST['type'] : 'core';

        if (!empty($code) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (code, name, type) VALUES (?, ?, ?)");
                $stmt->execute([$code, $name, $type]);
                log_audit('SUBJECT_ADDED', "Added subject: {$name} ({$code}).");
                flash_message('success', "Subject '{$name}' created.");
            } catch (Exception $e) {
                flash_message('danger', "Subject code already exists.");
            }
        }
    }

    // 6. Assign Subject to Class
    elseif ($action === 'assign_class_subject') {
        $classId = (int)($_POST['class_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        if ($classId > 0 && $subjectId > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
                $stmt->execute([$classId, $subjectId]);
                flash_message('success', "Subject linked to class.");
            } catch (Exception $e) {
                flash_message('warning', "Subject already linked to this class.");
            }
        }
    }

    // 7. Assign Teacher to Class/Subject
    elseif ($action === 'assign_teacher') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $isClassTeacher = !empty($_POST['is_class_teacher']) ? 1 : 0;
        $activeYear = get_active_academic_year();
        $yearId = $activeYear['id'] ?? 1;

        if ($teacherId > 0 && $classId > 0 && $sectionId > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO teacher_assignments (teacher_id, class_id, section_id, subject_id, academic_year_id, is_class_teacher) 
                                       VALUES (?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE is_class_teacher = ?");
                $stmt->execute([$teacherId, $classId, $sectionId, ($subjectId > 0 ? $subjectId : null), $yearId, $isClassTeacher, $isClassTeacher]);
                log_audit('TEACHER_ASSIGNED', "Teacher ID {$teacherId} assigned to Class {$classId}, Section {$sectionId}.");
                flash_message('success', "Teacher assignment saved.");
            } catch (Exception $e) {
                flash_message('danger', "Error assigning teacher: " . $e->getMessage());
            }
        }
    }

    header('Location: ' . BASE_URL . '/admin/academics.php');
    exit;
}

// Fetch Records for Display
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC")->fetchAll();
$classes = $pdo->query("SELECT c.*, COUNT(DISTINCT s.id) as section_count, COUNT(DISTINCT stu.id) as student_count 
                        FROM classes c 
                        LEFT JOIN sections s ON c.id = s.class_id 
                        LEFT JOIN students stu ON c.id = stu.class_id
                        GROUP BY c.id 
                        ORDER BY c.numeric_level ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY code ASC")->fetchAll();
$teachers = $pdo->query("SELECT id, teacher_id, full_name FROM teachers ORDER BY full_name ASC")->fetchAll();

$assignments = $pdo->query("SELECT ta.*, t.full_name as teacher_name, t.teacher_id, c.name as class_name, sec.name as section_name, sub.name as subject_name
                            FROM teacher_assignments ta
                            JOIN teachers t ON ta.teacher_id = t.id
                            JOIN classes c ON ta.class_id = c.id
                            JOIN sections sec ON ta.section_id = sec.id
                            LEFT JOIN subjects sub ON ta.subject_id = sub.id
                            ORDER BY c.numeric_level, sec.name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-graduation-cap me-2 text-primary"></i> Academic Management</h1>
    <p class="page-subtitle">Configure Academic Years, Classes, Sections, Subjects, and Faculty Assignments.</p>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- 1. Academic Years Management -->
  <div class="col-lg-5">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-calendar-alt me-2 text-primary"></i> Academic Years</h5>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addYearModal">
          <i class="fas fa-plus me-1"></i> Add Year
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom mb-0">
            <thead>
              <tr>
                <th>Year</th>
                <th>Dates</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($academicYears as $y): ?>
                <tr>
                  <td>
                    <strong><?= e($y['name']) ?></strong>
                    <?php if ($y['is_current'] == 1): ?>
                      <span class="badge bg-success ms-1">Active</span>
                    <?php endif; ?>
                  </td>
                  <td><small class="text-muted"><?= format_date($y['start_date'], 'M Y') ?> - <?= format_date($y['end_date'], 'M Y') ?></small></td>
                  <td><?= status_badge($y['status']) ?></td>
                  <td>
                    <?php if ($y['is_current'] != 1): ?>
                      <form method="POST" action="" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_current_year">
                        <input type="hidden" name="year_id" value="<?= $y['id'] ?>">
                        <button type="submit" class="btn btn-xs btn-outline-primary py-1 px-2 small">Make Active</button>
                      </form>
                    <?php else: ?>
                      <span class="text-success small fw-semibold"><i class="fas fa-check"></i> Current</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Classes & Sections -->
  <div class="col-lg-7">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-school me-2 text-primary"></i> Classes & Sections</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="fas fa-layer-group me-1"></i> Add Section
          </button>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="fas fa-plus me-1"></i> Add Class
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom mb-0">
            <thead>
              <tr>
                <th>Class / Standard</th>
                <th>Type</th>
                <th>Sections</th>
                <th>Enrolled</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classes as $c): ?>
                <tr>
                  <td><strong class="text-dark"><?= e($c['name']) ?></strong></td>
                  <td><span class="badge bg-light text-secondary border text-uppercase"><?= e($c['type']) ?></span></td>
                  <td>
                    <span class="badge bg-primary-subtle text-primary"><?= $c['section_count'] ?> Sections</span>
                  </td>
                  <td>
                    <span class="badge bg-secondary-subtle text-secondary"><?= $c['student_count'] ?> Students</span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3. Subjects & Faculty Assignments -->
<div class="row g-4">
  <!-- Subjects List -->
  <div class="col-lg-5">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-book me-2 text-primary"></i> Subjects Master</h5>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
          <i class="fas fa-plus me-1"></i> Add Subject
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom mb-0">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subjects as $sub): ?>
                <tr>
                  <td><code><?= e($sub['code']) ?></code></td>
                  <td><strong><?= e($sub['name']) ?></strong></td>
                  <td><span class="badge bg-light text-dark border text-uppercase"><?= e($sub['type']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Faculty Class Assignments -->
  <div class="col-lg-7">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-user-tag me-2 text-primary"></i> Teacher Class Assignments</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignTeacherModal">
          <i class="fas fa-plus me-1"></i> New Assignment
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom mb-0">
            <thead>
              <tr>
                <th>Teacher</th>
                <th>Class & Section</th>
                <th>Subject</th>
                <th>Role</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($assignments)): ?>
                <?php foreach ($assignments as $a): ?>
                  <tr>
                    <td>
                      <strong><?= e($a['teacher_name']) ?></strong>
                      <small class="text-muted d-block"><?= e($a['teacher_id']) ?></small>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($a['class_name']) ?> - Sec <?= e($a['section_name']) ?></span>
                    </td>
                    <td><?= e($a['subject_name'] ?? 'Class In-charge') ?></td>
                    <td>
                      <?php if ($a['is_class_teacher'] == 1): ?>
                        <span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-star me-1"></i>Class Teacher</span>
                      <?php else: ?>
                        <span class="badge bg-light text-secondary border">Subject Teacher</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center py-3 text-muted">No assignments yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODALS -->

<!-- Modal: Add Academic Year -->
<div class="modal fade" id="addYearModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_academic_year">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Academic Year</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Year Name</label>
            <input type="text" name="year_name" class="form-control" placeholder="e.g. 2027-2028" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_current" id="isCurrentYear" value="1">
            <label class="form-check-label small" for="isCurrentYear">Set as Current Active Academic Year</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Year</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add Class -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_class">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Class / Standard / Semester</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Class Name</label>
            <input type="text" name="class_name" class="form-control" placeholder="e.g. Standard 11 (Commerce) or Semester 5" required>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-bold">Category</label>
              <select name="type" class="form-select">
                <option value="school">School (Standard 1-12)</option>
                <option value="college">College (Semester 1-8)</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Numeric Level</label>
              <input type="number" name="numeric_level" class="form-control" min="1" max="12" value="1" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Class</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add Section -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_section">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Section to Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Select Class</label>
            <select name="class_id" class="form-select" required>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Section Letter</label>
            <input type="text" name="section_name" class="form-control" placeholder="e.g. B, C, or D" maxlength="10" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add Subject -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_subject">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Code</label>
            <input type="text" name="code" class="form-control" placeholder="e.g. PHY-101" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Physics & Mechanics" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Type</label>
            <select name="type" class="form-select">
              <option value="core">Core</option>
              <option value="elective">Elective</option>
              <option value="practical">Practical / Lab</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Subject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Assign Teacher -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign_teacher">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Assign Teacher to Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Select Teacher</label>
            <select name="teacher_id" class="form-select" required>
              <option value="">-- Choose Teacher --</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?> (<?= e($t['teacher_id']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Class</label>
              <select name="class_id" id="classSelectModal" class="form-select" required>
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Section</label>
              <select name="section_id" id="sectionSelectModal" class="form-select" required>
                <option value="">-- Choose Class First --</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject (Optional)</label>
            <select name="subject_id" class="form-select">
              <option value="0">-- None / General Class Teacher --</option>
              <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_class_teacher" id="assignClassTeacher" value="1">
            <label class="form-check-label small" for="assignClassTeacher">Appoint as Primary Class Teacher</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const cSelect = document.getElementById('classSelectModal');
  const sSelect = document.getElementById('sectionSelectModal');
  if (cSelect && sSelect) {
    cSelect.addEventListener('change', function() {
      const cid = this.value;
      if (!cid) return;
      fetch(window.BASE_URL + '/api/get_sections.php?class_id=' + cid)
        .then(r => r.json())
        .then(data => {
          sSelect.innerHTML = '<option value="">-- Select Section --</option>';
          data.forEach(sec => {
            sSelect.innerHTML += '<option value="' + sec.id + '">Section ' + sec.name + '</option>';
          });
        });
    });
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
