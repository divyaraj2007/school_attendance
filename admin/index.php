<?php
// admin/index.php - Administrator Dashboard
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Administrator Dashboard';
$pdo = get_db_connection();
$today = date('Y-m-d');
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// Fetch Dashboard Statistics
$stats = [
    'total_students' => (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'total_teachers' => (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
    'total_principals' => (int)$pdo->query("SELECT COUNT(*) FROM principals")->fetchColumn(),
    'total_classes' => (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'total_sections' => (int)$pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn(),
];

// Today's Student Attendance
$stmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_marked,
                          COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                          COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
                          COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day
                       FROM daily_attendance 
                       WHERE attendance_date = ?");
$stmt->execute([$today]);
$todayStudentAtt = $stmt->fetch();
$stats['today_student_marked'] = (int)($todayStudentAtt['total_marked'] ?? 0);
$stats['today_student_present'] = (int)($todayStudentAtt['present'] ?? 0);
$stats['today_student_pct'] = $stats['today_student_marked'] > 0 
    ? round(($stats['today_student_present'] / $stats['today_student_marked']) * 100, 1) 
    : 0;

// Today's Teacher Attendance
$stmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_marked,
                          COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                          COUNT(CASE WHEN status = 'on_leave' THEN 1 END) as on_leave
                       FROM teacher_attendance 
                       WHERE attendance_date = ?");
$stmt->execute([$today]);
$todayTeacherAtt = $stmt->fetch();
$stats['today_teacher_marked'] = (int)($todayTeacherAtt['total_marked'] ?? 0);
$stats['today_teacher_present'] = (int)($todayTeacherAtt['present'] ?? 0);
$stats['today_teacher_pct'] = $stats['today_teacher_marked'] > 0 
    ? round(($stats['today_teacher_present'] / $stats['today_teacher_marked']) * 100, 1) 
    : 0;

// Low attendance threshold
$threshold = (float)get_setting('attendance_threshold', '75');
$lowAttendanceStudents = get_low_attendance_students($threshold);

// Recent Audit Logs
$recentLogsStmt = $pdo->query("SELECT a.*, u.user_id, u.role 
                               FROM audit_logs a 
                               LEFT JOIN users u ON a.user_id = u.id 
                               ORDER BY a.id DESC LIMIT 6");
$recentLogs = $recentLogsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Administrator Dashboard</h1>
    <p class="page-subtitle">Complete overview of school attendance, academics, faculty, and student statistics.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/admin/attendance.php" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-clipboard-check me-1"></i> View Attendance
    </a>
    <a href="<?= BASE_URL ?>/admin/user_add.php" class="btn btn-primary btn-sm">
      <i class="fas fa-user-plus me-1"></i> Add New User
    </a>
  </div>
</div>

<!-- 7 Stat Cards Required by Spec -->
<div class="row g-3 mb-4">
  <!-- Total Students -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-blue">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Total Students</div>
          <div class="stat-value"><?= number_format($stats['total_students']) ?></div>
          <div class="stat-trend text-muted"><i class="fas fa-user-graduate me-1 text-primary"></i> Enrolled across all classes</div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
      </div>
    </div>
  </div>

  <!-- Total Teachers -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-purple">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Total Teachers</div>
          <div class="stat-value"><?= number_format($stats['total_teachers']) ?></div>
          <div class="stat-trend text-muted"><i class="fas fa-chalkboard-teacher me-1 text-purple"></i> Faculty members</div>
        </div>
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
      </div>
    </div>
  </div>

  <!-- Total Principals -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-indigo">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Total Principals</div>
          <div class="stat-value"><?= number_format($stats['total_principals']) ?></div>
          <div class="stat-trend text-muted"><i class="fas fa-user-tie me-1 text-indigo"></i> School Leadership</div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
      </div>
    </div>
  </div>

  <!-- Total Classes & Sections -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-amber">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Classes & Sections</div>
          <div class="stat-value"><?= $stats['total_classes'] ?> <span class="fs-6 text-muted fw-normal">/ <?= $stats['total_sections'] ?> Secs</span></div>
          <div class="stat-trend text-muted"><i class="fas fa-school me-1 text-warning"></i> Standards 1-12 & Sem 1-8</div>
        </div>
        <div class="stat-icon"><i class="fas fa-school"></i></div>
      </div>
    </div>
  </div>

  <!-- Today's Student Attendance -->
  <div class="col-xl-6 col-md-6">
    <div class="stat-card stat-green">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Today's Student Attendance</div>
          <div class="stat-value text-success">
            <?= $stats['today_student_pct'] ?>%
            <span class="fs-6 text-muted fw-normal ms-2">(<?= $stats['today_student_present'] ?> present / <?= $stats['today_student_marked'] ?> marked)</span>
          </div>
          <div class="stat-trend text-muted">
            <span class="text-danger fw-semibold"><i class="fas fa-times-circle me-1"></i><?= (int)($todayStudentAtt['absent'] ?? 0) ?> Absent</span> &bull; 
            <span class="text-warning fw-semibold"><i class="fas fa-clock me-1"></i><?= (int)($todayStudentAtt['late'] ?? 0) ?> Late</span>
          </div>
        </div>
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
      </div>
    </div>
  </div>

  <!-- Today's Teacher Attendance -->
  <div class="col-xl-6 col-md-6">
    <div class="stat-card stat-indigo">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Today's Teacher Attendance</div>
          <div class="stat-value text-primary">
            <?= $stats['today_teacher_pct'] ?>%
            <span class="fs-6 text-muted fw-normal ms-2">(<?= $stats['today_teacher_present'] ?> present / <?= $stats['total_teachers'] ?> total)</span>
          </div>
          <div class="stat-trend text-muted">
            <span class="text-danger fw-semibold"><i class="fas fa-user-times me-1"></i><?= (int)($todayTeacherAtt['absent'] ?? 0) ?> Absent</span> &bull; 
            <span class="text-info fw-semibold"><i class="fas fa-plane-departure me-1"></i><?= (int)($todayTeacherAtt['on_leave'] ?? 0) ?> On Leave</span>
          </div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-chart-area me-2 text-primary"></i> Attendance Trend (Recent 7 Days)</h5>
        <span class="badge bg-primary-subtle text-primary">Real DB Records</span>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 260px; max-height: 260px; width: 100%;">
          <canvas id="weeklyAttendanceChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i> Today's Attendance</h5>
        <span class="badge bg-success-subtle text-success"><?= date('M d, Y') ?></span>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div style="position: relative; height: 200px; max-height: 200px; width: 100%; max-width: 210px;">
          <canvas id="todayAttendanceDoughnut"></canvas>
        </div>
        <div class="d-flex justify-content-center flex-wrap gap-2 mt-3 small text-muted">
          <span><i class="fas fa-circle text-success me-1"></i>Present</span>
          <span><i class="fas fa-circle text-danger me-1"></i>Absent</span>
          <span><i class="fas fa-circle text-warning me-1"></i>Late</span>
          <span><i class="fas fa-circle text-warning-emphasis me-1"></i>Half Day</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Low Attendance Alert & Audit Logs -->
<div class="row g-4">
  <!-- Low Attendance Alert (<75%) -->
  <div class="col-lg-7">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title text-danger">
          <i class="fas fa-exclamation-triangle me-2"></i> Low Attendance Alert (&lt; <?= $threshold ?>%)
        </h5>
        <span class="badge bg-danger"><?= count($lowAttendanceStudents) ?> Students</span>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($lowAttendanceStudents)): ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>ID / Roll</th>
                  <th>Class</th>
                  <th>Present / Total</th>
                  <th>Attendance %</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lowAttendanceStudents as $low): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold text-dark"><?= e($low['full_name']) ?></div>
                    </td>
                    <td>
                      <code><?= e($low['student_id']) ?></code>
                      <span class="text-muted ms-1">#<?= $low['roll_no'] ?></span>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e($low['class_name']) ?> - <?= e($low['section_name']) ?></span></td>
                    <td><?= $low['present_count'] ?> / <?= $low['total_days'] ?> days</td>
                    <td>
                      <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 fw-bold">
                        <?= $low['percentage'] ?>%
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center p-4 text-muted">
            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
            <div>All students currently meet or exceed the <?= $threshold ?>% attendance threshold.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Audit Logs -->
  <div class="col-lg-5">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title"><i class="fas fa-history me-2 text-primary"></i> Recent Audit Logs</h5>
        <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if (!empty($recentLogs)): ?>
            <?php foreach ($recentLogs as $log): ?>
              <li class="list-group-item px-3 py-2 small">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold text-dark"><?= e($log['action']) ?></span>
                  <span class="text-muted" style="font-size: 0.75rem;"><?= format_date($log['created_at'], 'M d, h:i A') ?></span>
                </div>
                <div class="text-muted mt-1"><?= e($log['details']) ?></div>
                <div class="text-secondary" style="font-size: 0.72rem;">User: <code><?= e($log['user_id'] ?? 'System') ?></code> &bull; IP: <?= e($log['ip_address'] ?? '127.0.0.1') ?></div>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="list-group-item text-center text-muted p-3">No audit logs recorded yet.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
  // 1. Weekly Attendance Chart
  fetch(window.BASE_URL + "/api/chart_data.php?type=weekly_trend")
    .then(r => r.json())
    .then(data => {
      const ctx = document.getElementById("weeklyAttendanceChart").getContext("2d");
      new Chart(ctx, {
        type: "bar",
        data: {
          labels: data.labels,
          datasets: [
            {
              label: "Present",
              data: data.present,
              backgroundColor: "#10b981",
              borderRadius: 6
            },
            {
              label: "Absent",
              data: data.absent,
              backgroundColor: "#ef4444",
              borderRadius: 6
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, grid: { color: "#f1f5f9" } },
            x: { grid: { display: false } }
          },
          plugins: {
            legend: { position: "top" }
          }
        }
      });
    });

  // 2. Today Attendance Doughnut Chart
  fetch(window.BASE_URL + "/api/chart_data.php?type=daily_breakdown")
    .then(r => r.json())
    .then(data => {
      const ctx = document.getElementById("todayAttendanceDoughnut").getContext("2d");
      const total = data.data.reduce((a, b) => a + b, 0);
      new Chart(ctx, {
        type: "doughnut",
        data: {
          labels: data.labels,
          datasets: [{
            data: total > 0 ? data.data : [1],
            backgroundColor: total > 0 ? data.colors : ["#e2e8f0"],
            borderWidth: 2,
            borderColor: "#ffffff"
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "70%",
          plugins: {
            legend: { display: false }
          }
        }
      });
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
