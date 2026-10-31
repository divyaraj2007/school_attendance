<?php
// mobile/index.php - Dedicated Touch-First Mobile Application for Smart Attendance
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// If not logged in, show mobile login screen or redirect
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$user = current_user();
$role = current_role();
$pdo = get_db_connection();
$schoolName = get_setting('school_name', 'Smart School Attendance');
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// Role specific data
$studentData = null;
$studentStats = null;
$teacherData = null;
$teacherAssignments = [];
$todayTimetable = [];
$todayName = date('l');

if ($role === 'student') {
    $sStmt = $pdo->prepare("SELECT s.*, c.name as class_name, sec.name as section_name
                            FROM students s
                            JOIN classes c ON s.class_id = c.id
                            JOIN sections sec ON s.section_id = sec.id
                            WHERE s.user_id = ?");
    $sStmt->execute([(int)$user['id']]);
    $studentData = $sStmt->fetch();
    if ($studentData) {
        $studentStats = calculate_student_stats((int)$studentData['id'], $yearId);
        
        $ttStmt = $pdo->prepare("SELECT tt.*, s.name as subject_name, s.code as subject_code, t.full_name as teacher_name
                                 FROM timetable tt
                                 JOIN subjects s ON tt.subject_id = s.id
                                 JOIN teachers t ON tt.teacher_id = t.id
                                 WHERE tt.class_id = ? AND tt.section_id = ? AND tt.day_of_week = ? AND tt.academic_year_id = ?
                                 ORDER BY tt.period_number ASC");
        $ttStmt->execute([(int)$studentData['class_id'], (int)$studentData['section_id'], $todayName, $yearId]);
        $todayTimetable = $ttStmt->fetchAll();
    }
} elseif ($role === 'teacher') {
    $tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
    $tStmt->execute([(int)$user['id']]);
    $teacherData = $tStmt->fetch();
    if ($teacherData) {
        $assignStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sec.name as section_name, sub.name as subject_name
                                     FROM teacher_assignments ta
                                     JOIN classes c ON ta.class_id = c.id
                                     JOIN sections sec ON ta.section_id = sec.id
                                     LEFT JOIN subjects sub ON ta.subject_id = sub.id
                                     WHERE ta.teacher_id = ? AND ta.academic_year_id = ?");
        $assignStmt->execute([(int)$teacherData['id'], $yearId]);
        $teacherAssignments = $assignStmt->fetchAll();

        $ttStmt = $pdo->prepare("SELECT tt.*, c.name as class_name, sec.name as section_name, sub.name as subject_name
                                 FROM timetable tt
                                 JOIN classes c ON tt.class_id = c.id
                                 JOIN sections sec ON tt.section_id = sec.id
                                 JOIN subjects sub ON tt.subject_id = sub.id
                                 WHERE tt.teacher_id = ? AND tt.day_of_week = ? AND tt.academic_year_id = ?
                                 ORDER BY tt.period_number ASC");
        $ttStmt->execute([(int)$teacherData['id'], $todayName, $yearId]);
        $todayTimetable = $ttStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>Smart Attendance App</title>
  
  <!-- PWA Manifest & App Metas -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#1e40af">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/logo.svg">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logo.svg">
  
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    :root {
      --mobile-primary: #1e40af;
      --mobile-primary-dark: #1e3a8a;
      --mobile-bg: #f8fafc;
      --mobile-card: #ffffff;
      --mobile-text: #0f172a;
      --mobile-muted: #64748b;
    }
    * {
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--mobile-bg);
      color: var(--mobile-text);
      margin: 0;
      padding: 0;
      padding-bottom: 75px;
      user-select: none;
    }
    /* Mobile App Header */
    .mobile-header {
      background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
      color: #ffffff;
      padding: 16px 20px 24px;
      border-bottom-left-radius: 24px;
      border-bottom-right-radius: 24px;
      box-shadow: 0 4px 20px rgba(37, 99, 235, 0.25);
    }
    .user-avatar-circle {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      border: 2px solid #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      font-weight: 700;
    }
    /* App Cards */
    .mobile-card {
      background: var(--mobile-card);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 14px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }
    /* Attendance Progress Ring / Stat */
    .stat-badge-box {
      background: #f1f5f9;
      border-radius: 12px;
      padding: 12px;
      text-align: center;
    }
    /* Mobile Schedule Timeline */
    .timeline-item {
      display: flex;
      gap: 14px;
      margin-bottom: 14px;
      position: relative;
    }
    .timeline-time {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--mobile-primary);
      width: 55px;
      flex-shrink: 0;
      text-align: right;
    }
    .timeline-content {
      background: #ffffff;
      border-left: 3px solid var(--mobile-primary);
      border-radius: 8px;
      padding: 10px 14px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.05);
      flex-grow: 1;
    }
    /* Mobile Bottom Tab Navigation */
    .mobile-bottom-tabs {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 64px;
      background: #ffffff;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 1050;
      box-shadow: 0 -4px 16px rgba(0,0,0,0.08);
      padding-bottom: env(safe-area-inset-bottom);
    }
    .tab-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #64748b;
      background: none;
      border: none;
      outline: none;
      font-size: 0.72rem;
      font-weight: 600;
      flex: 1;
      height: 100%;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tab-btn i {
      font-size: 1.15rem;
      margin-bottom: 2px;
    }
    .tab-btn.active {
      color: var(--mobile-primary);
    }
    .tab-btn.active i {
      transform: translateY(-2px);
    }
    .view-section {
      display: none;
      animation: fadeIn 0.25s ease-in-out;
    }
    .view-section.active {
      display: block;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<!-- Mobile App Header -->
<header class="mobile-header">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo" style="width: 32px; height: 32px; filter: brightness(0) invert(1);">
      <span class="fw-bold fs-6">Smart Attendance</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="<?= BASE_URL ?>/<?= $role ?>/index.php" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1" style="font-size: 0.72rem;">
        <i class="fas fa-desktop me-1"></i> Web Portal
      </a>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-danger rounded-circle p-1" style="width: 28px; height: 28px;" title="Logout">
        <i class="fas fa-power-off fa-xs"></i>
      </a>
    </div>
  </div>

  <div class="d-flex align-items-center gap-3 pt-2">
    <div class="user-avatar-circle">
      <?= strtoupper(substr($user['user_id'], 0, 1)) ?>
    </div>
    <div>
      <div class="fs-5 fw-bold mb-0 text-truncate" style="max-width: 240px;">
        <?= e($studentData['full_name'] ?? $teacherData['full_name'] ?? $user['user_id']) ?>
      </div>
      <div class="small opacity-75">
        <span class="badge bg-light text-primary text-uppercase me-1"><?= e($role) ?></span>
        <?php if ($role === 'student' && $studentData): ?>
          <span><?= e($studentData['class_name']) ?> - Sec <?= e($studentData['section_name']) ?> &bull; Roll #<?= $studentData['roll_no'] ?></span>
        <?php elseif ($role === 'teacher' && $teacherData): ?>
          <span>Faculty Member &bull; ID: <?= e($teacherData['teacher_id']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="container py-3">

  <!-- ==================== TAB 1: DASHBOARD ==================== -->
  <section id="tabDashboard" class="view-section active">
    <?php if ($role === 'student' && $studentStats): ?>
      <!-- Overall Percentage Card -->
      <div class="mobile-card text-center">
        <span class="text-muted small fw-semibold text-uppercase">Overall Attendance</span>
        <div class="my-2">
          <span class="display-5 fw-extrabold text-<?= $studentStats['percentage'] >= 75 ? 'primary' : 'danger' ?>">
            <?= $studentStats['percentage'] ?>%
          </span>
        </div>
        <div class="progress mb-3" style="height: 8px; border-radius: 4px;">
          <div class="progress-bar <?= $studentStats['percentage'] >= 75 ? 'bg-success' : 'bg-danger' ?>" 
               role="progressbar" style="width: <?= min(100, $studentStats['percentage']) ?>%"></div>
        </div>
        <div class="row g-2">
          <div class="col-4">
            <div class="stat-badge-box">
              <div class="fw-bold text-success fs-5"><?= $studentStats['present_days'] ?></div>
              <small class="text-muted">Present</small>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-badge-box">
              <div class="fw-bold text-danger fs-5"><?= $studentStats['absent_days'] ?></div>
              <small class="text-muted">Absent</small>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-badge-box">
              <div class="fw-bold text-warning fs-5"><?= $studentStats['late_days'] ?></div>
              <small class="text-muted">Late</small>
            </div>
          </div>
        </div>
      </div>
    <?php elseif ($role === 'teacher'): ?>
      <!-- Teacher Quick Stats -->
      <div class="mobile-card">
        <h6 class="fw-bold text-primary mb-2"><i class="fas fa-chalkboard me-1"></i> Today's Teaching Scope</h6>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted small">Assigned Classes</span>
          <strong><?= count($teacherAssignments) ?> Batches</strong>
        </div>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted small">Today's Lectures</span>
          <strong><?= count($todayTimetable) ?> Periods</strong>
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2">
          <span class="text-muted small">Day of Week</span>
          <span class="badge bg-primary"><?= date('l, M d') ?></span>
        </div>
      </div>
    <?php endif; ?>

    <!-- Today's Timetable Feed -->
    <div class="mobile-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="far fa-clock me-1 text-primary"></i> Today's Schedule (<?= $todayName ?>)</h6>
        <span class="badge bg-primary-subtle text-primary"><?= count($todayTimetable) ?> Periods</span>
      </div>

      <?php if (!empty($todayTimetable)): ?>
        <div class="timetable-timeline">
          <?php foreach ($todayTimetable as $slot): ?>
            <div class="timeline-item">
              <div class="timeline-time">
                Period <?= $slot['period_number'] ?><br>
                <small class="text-muted"><?= substr($slot['start_time'], 0, 5) ?></small>
              </div>
              <div class="timeline-content">
                <div class="fw-bold text-dark"><?= e($slot['subject_name']) ?></div>
                <div class="small text-muted">
                  <?php if ($role === 'student'): ?>
                    <i class="fas fa-user-tie me-1"></i> <?= e($slot['teacher_name']) ?>
                  <?php else: ?>
                    <i class="fas fa-school me-1"></i> <?= e($slot['class_name']) ?> - Sec <?= e($slot['section_name']) ?>
                  <?php endif; ?>
                  <?php if (!empty($slot['room_no'])): ?>
                    &bull; <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= e($slot['room_no']) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-3 text-muted">
          <i class="fas fa-coffee fa-2x mb-2 text-secondary"></i>
          <p class="small mb-0">No classes scheduled for today.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ==================== TAB 2: SCHEDULE / TIMETABLE ==================== -->
  <section id="tabSchedule" class="view-section">
    <div class="mobile-card">
      <h6 class="fw-bold mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i> Weekly Schedule</h6>
      <p class="text-muted small">Switch between days of the week to inspect your full allocated periods.</p>
      <div class="btn-group w-100 mb-3" role="group">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
          <button type="button" class="btn btn-outline-primary btn-sm <?= ($d === substr($todayName, 0, 3)) ? 'active' : '' ?>" onclick="filterDaySchedule('<?= $d ?>', this)">
            <?= $d ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div id="weeklyScheduleContent" class="py-2">
        <div class="text-center text-muted py-3">
          <span class="spinner-border spinner-border-sm me-1"></span> Loading timetable...
        </div>
      </div>
    </div>
  </section>

  <!-- ==================== TAB 3: ATTENDANCE ACTION / LOGS ==================== -->
  <section id="tabAttendance" class="view-section">
    <?php if ($role === 'teacher'): ?>
      <div class="mobile-card">
        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-edit me-1"></i> Mobile Attendance Punch</h6>
        <div class="mb-3">
          <label class="form-label small fw-bold">Select Class & Section</label>
          <select id="mobileClassSelect" class="form-select form-select-sm" onchange="loadClassRosterForMobile(this.value)">
            <option value="">-- Choose Assigned Class --</option>
            <?php foreach ($teacherAssignments as $ta): ?>
              <option value="<?= $ta['class_id'] ?>:<?= $ta['section_id'] ?>">
                <?= e($ta['class_name']) ?> - Section <?= e($ta['section_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="mobileRosterContainer" class="d-none">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-bold">Student Roster</span>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-success py-0" onclick="mobileMarkAll('present')">All Present</button>
              <button class="btn btn-outline-danger py-0" onclick="mobileMarkAll('absent')">All Absent</button>
            </div>
          </div>
          <div id="rosterList" class="mb-3"></div>
          <button class="btn btn-primary w-100 btn-sm py-2 fw-bold" onclick="submitMobileAttendance()">
            <i class="fas fa-check-circle me-1"></i> Submit Attendance Now
          </button>
        </div>
      </div>
    <?php else: ?>
      <div class="mobile-card">
        <h6 class="fw-bold mb-3"><i class="fas fa-history text-primary me-2"></i> Attendance Record History</h6>
        <div id="mobileHistoryList">
          <div class="text-center text-muted py-3">
            <span class="spinner-border spinner-border-sm me-1"></span> Loading recent records...
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- ==================== TAB 4: PROFILE ==================== -->
  <section id="tabProfile" class="view-section">
    <div class="mobile-card text-center">
      <div class="user-avatar-circle mx-auto mb-2" style="width: 64px; height: 64px; font-size: 1.8rem; background: var(--mobile-primary); color: #fff;">
        <?= strtoupper(substr($user['user_id'], 0, 1)) ?>
      </div>
      <h5 class="fw-bold mb-1"><?= e($studentData['full_name'] ?? $teacherData['full_name'] ?? $user['user_id']) ?></h5>
      <span class="badge bg-primary text-uppercase"><?= e($role) ?></span>

      <div class="list-group list-group-flush text-start mt-3 small">
        <div class="list-group-item d-flex justify-content-between px-0">
          <span class="text-muted">Account User ID</span>
          <strong><?= e($user['user_id']) ?></strong>
        </div>
        <div class="list-group-item d-flex justify-content-between px-0">
          <span class="text-muted">Account Status</span>
          <span class="badge bg-success-subtle text-success">Active</span>
        </div>
        <div class="list-group-item d-flex justify-content-between px-0">
          <span class="text-muted">Academic Year</span>
          <strong><?= e($activeYear['name'] ?? '2026-2027') ?></strong>
        </div>
      </div>

      <div class="mt-3 pt-3 border-top">
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger w-100 btn-sm">
          <i class="fas fa-sign-out-alt me-1"></i> Log Out of Mobile App
        </a>
      </div>
    </div>
  </section>

</main>

<!-- Bottom Tabs -->
<nav class="mobile-bottom-tabs">
  <button class="tab-btn active" onclick="switchMobileTab('tabDashboard', this)">
    <i class="fas fa-home"></i>
    <span>Home</span>
  </button>
  <button class="tab-btn" onclick="switchMobileTab('tabSchedule', this)">
    <i class="fas fa-calendar-alt"></i>
    <span>Schedule</span>
  </button>
  <button class="tab-btn" onclick="switchMobileTab('tabAttendance', this)">
    <i class="fas fa-check-circle"></i>
    <span>Attendance</span>
  </button>
  <button class="tab-btn" onclick="switchMobileTab('tabProfile', this)">
    <i class="fas fa-user"></i>
    <span>Profile</span>
  </button>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.BASE_URL = '<?= BASE_URL ?>';
  const userRole = '<?= $role ?>';

  function switchMobileTab(tabId, btn) {
    document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');
    btn.classList.add('active');

    if (tabId === 'tabSchedule') {
      loadWeeklySchedule('<?= substr($todayName, 0, 3) ?>');
    } else if (tabId === 'tabAttendance' && userRole === 'student') {
      loadStudentHistory();
    }
  }

  function filterDaySchedule(dayPrefix, btn) {
    btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadWeeklySchedule(dayPrefix);
  }

  const dayMap = {
    'Mon': 'Monday',
    'Tue': 'Tuesday',
    'Wed': 'Wednesday',
    'Thu': 'Thursday',
    'Fri': 'Friday',
    'Sat': 'Saturday'
  };

  function loadWeeklySchedule(dayPrefix) {
    const fullDay = dayMap[dayPrefix] || 'Monday';
    const container = document.getElementById('weeklyScheduleContent');
    container.innerHTML = '<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span> Fetching ' + fullDay + '...</div>';

    fetch(window.BASE_URL + '/api/timetable.php?day=' + fullDay)
      .then(r => r.json())
      .then(res => {
        if (!res.success || !res.slots || res.slots.length === 0) {
          container.innerHTML = '<div class="text-center py-3 text-muted"><p class="small mb-0">No classes scheduled for ' + fullDay + '.</p></div>';
          return;
        }
        let html = '';
        res.slots.forEach(slot => {
          html += `
            <div class="timeline-item">
              <div class="timeline-time">
                Period ${slot.period_number}<br>
                <small class="text-muted">${slot.start_time.substring(0, 5)}</small>
              </div>
              <div class="timeline-content">
                <div class="fw-bold text-dark">${slot.subject_name}</div>
                <div class="small text-muted">
                  ${slot.class_name} - Sec ${slot.section_name} &bull; ${slot.teacher_name}
                  ${slot.room_no ? '&bull; <span class="text-danger">' + slot.room_no + '</span>' : ''}
                </div>
              </div>
            </div>`;
        });
        container.innerHTML = html;
      })
      .catch(err => {
        container.innerHTML = '<div class="text-danger small py-2">Failed to load schedule.</div>';
      });
  }

  function loadStudentHistory() {
    const container = document.getElementById('mobileHistoryList');
    fetch(window.BASE_URL + '/api/student.php')
      .then(r => r.json())
      .then(res => {
        if (!res.success || !res.recent_attendance || res.recent_attendance.length === 0) {
          container.innerHTML = '<div class="text-center text-muted small py-3">No records found.</div>';
          return;
        }
        let html = '<div class="list-group list-group-flush">';
        res.recent_attendance.forEach(rec => {
          const badgeClass = rec.status === 'present' ? 'bg-success' : (rec.status === 'absent' ? 'bg-danger' : 'bg-warning text-dark');
          html += `
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
              <div>
                <strong class="d-block small text-dark">${rec.attendance_date}</strong>
                <small class="text-muted">${rec.remarks || 'Regular daily check'}</small>
              </div>
              <span class="badge ${badgeClass} text-capitalize">${rec.status}</span>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
      })
      .catch(() => {
        container.innerHTML = '<div class="text-danger small py-2">Failed to load attendance logs.</div>';
      });
  }

  let currentRoster = [];
  function loadClassRosterForMobile(pair) {
    if (!pair) {
      document.getElementById('mobileRosterContainer').classList.add('d-none');
      return;
    }
    const [classId, sectionId] = pair.split(':');
    const rosterList = document.getElementById('rosterList');
    rosterList.innerHTML = '<div class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-1"></span> Fetching students...</div>';
    document.getElementById('mobileRosterContainer').classList.remove('d-none');

    fetch(`${window.BASE_URL}/api/teacher.php?action=students&class_id=${classId}&section_id=${sectionId}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success || !res.students || res.students.length === 0) {
          rosterList.innerHTML = '<div class="text-muted small py-2">No students enrolled in this section.</div>';
          return;
        }
        currentRoster = res.students;
        renderRosterList();
      })
      .catch(() => {
        rosterList.innerHTML = '<div class="text-danger small py-2">Failed to load students.</div>';
      });
  }

  function renderRosterList() {
    const rosterList = document.getElementById('rosterList');
    let html = '';
    currentRoster.forEach(stu => {
      const currentVal = stu.today_status || 'present';
      html += `
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div>
            <strong class="d-block small">#${stu.roll_no} ${stu.full_name}</strong>
            <small class="text-muted">${stu.student_id}</small>
          </div>
          <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="att_${stu.id}" id="p_${stu.id}" value="present" ${currentVal === 'present' ? 'checked' : ''}>
            <label class="btn btn-outline-success py-1 px-2" for="p_${stu.id}">P</label>

            <input type="radio" class="btn-check" name="att_${stu.id}" id="a_${stu.id}" value="absent" ${currentVal === 'absent' ? 'checked' : ''}>
            <label class="btn btn-outline-danger py-1 px-2" for="a_${stu.id}">A</label>

            <input type="radio" class="btn-check" name="att_${stu.id}" id="l_${stu.id}" value="late" ${currentVal === 'late' ? 'checked' : ''}>
            <label class="btn btn-outline-warning py-1 px-2" for="l_${stu.id}">L</label>
          </div>
        </div>`;
    });
    rosterList.innerHTML = html;
  }

  function mobileMarkAll(status) {
    currentRoster.forEach(stu => {
      const el = document.getElementById((status === 'present' ? 'p_' : 'a_') + stu.id);
      if (el) el.checked = true;
    });
  }

  function submitMobileAttendance() {
    const pair = document.getElementById('mobileClassSelect').value;
    if (!pair) return;
    const [classId, sectionId] = pair.split(':');
    
    const attendance = {};
    currentRoster.forEach(stu => {
      const selected = document.querySelector(`input[name="att_${stu.id}"]:checked`);
      attendance[stu.id] = selected ? selected.value : 'present';
    });

    fetch(window.BASE_URL + '/api/teacher.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        class_id: classId,
        section_id: sectionId,
        attendance: attendance,
        type: 'daily'
      })
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          alert('Attendance successfully recorded for ' + res.count + ' students!');
        } else {
          alert('Error: ' + res.message);
        }
      })
      .catch(err => alert('Failed to submit attendance.'));
  }
</script>
</body>
</html>
