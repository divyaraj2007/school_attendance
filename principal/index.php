<?php
// principal/index.php - Principal Dashboard
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Principal Dashboard';
$pdo = get_db_connection();
$today = date('Y-m-d');
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// 1. Overall Totals
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

// 2. Today's Student Attendance
$stmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_marked,
                          COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                          COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                          COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count
                       FROM daily_attendance 
                       WHERE attendance_date = ?");
$stmt->execute([$today]);
$studentToday = $stmt->fetch();
$studentMarked = (int)($studentToday['total_marked'] ?? 0);
$presentStudents = (int)($studentToday['present_count'] ?? 0);
$absentStudents = (int)($studentToday['absent_count'] ?? 0);
$studentPct = $studentMarked > 0 ? round(($presentStudents / $studentMarked) * 100, 1) : 0;

// 3. Today's Teacher Attendance
$tStmt = $pdo->prepare("SELECT 
                          COUNT(*) as total_marked,
                          COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                          COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                          COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count,
                          COUNT(CASE WHEN status = 'on_leave' THEN 1 END) as on_leave_count
                       FROM teacher_attendance 
                       WHERE attendance_date = ?");
$tStmt->execute([$today]);
$teacherToday = $tStmt->fetch();
$teacherMarked = (int)($teacherToday['total_marked'] ?? 0);
$presentTeachers = (int)($teacherToday['present_count'] ?? 0);
$absentTeachers = (int)($teacherToday['absent_count'] ?? 0);
$teacherPct = $totalTeachers > 0 ? round(($presentTeachers / $totalTeachers) * 100, 1) : 0;

// 4. Pending Correction Requests
$cStmt = $pdo->query("SELECT cr.*, s.full_name as student_name, s.student_id, u.user_id as teacher_user_id, t.full_name as teacher_name
                      FROM correction_requests cr
                      JOIN students s ON cr.student_id = s.id
                      JOIN users u ON cr.requested_by = u.id
                      LEFT JOIN teachers t ON u.id = t.user_id
                      WHERE cr.status = 'pending'
                      ORDER BY cr.id DESC LIMIT 4");
$pendingCorrections = $cStmt->fetchAll();

// 5. Low Attendance Alert
$threshold = (float)get_setting('attendance_threshold', '75');
$lowStudents = get_low_attendance_students($threshold);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-user-tie me-2 text-primary"></i> Principal Executive Dashboard</h1>
    <p class="page-subtitle">Monitor institutional attendance, class operations, teacher status, and correction reviews.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/principal/teacher_attendance.php" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-chalkboard-teacher me-1"></i> Mark Teacher Attendance
    </a>
    <a href="<?= BASE_URL ?>/principal/student_attendance.php" class="btn btn-primary btn-sm">
      <i class="fas fa-calendar-check me-1"></i> Take Student Attendance
    </a>
  </div>
</div>

<!-- 8 Specific Dashboard Statistics Required by Prompt -->
<div class="row g-3 mb-4">
  <!-- 1. Total Students -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-blue">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Total Students</div>
          <div class="stat-value"><?= number_format($totalStudents) ?></div>
          <div class="stat-trend text-muted"><i class="fas fa-user-graduate me-1 text-primary"></i> Across all standards</div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
      </div>
    </div>
  </div>

  <!-- 2. Total Teachers -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-purple">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Total Teachers</div>
          <div class="stat-value"><?= number_format($totalTeachers) ?></div>
          <div class="stat-trend text-muted"><i class="fas fa-chalkboard-teacher me-1 text-purple"></i> Teaching Faculty</div>
        </div>
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
      </div>
    </div>
  </div>

  <!-- 3. Today's Student Attendance -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-green">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Today's Student Att.</div>
          <div class="stat-value text-success"><?= $studentPct ?>%</div>
          <div class="stat-trend text-muted"><?= $studentMarked ?> records logged today</div>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
      </div>
    </div>
  </div>

  <!-- 4. Today's Teacher Attendance -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-indigo">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Today's Teacher Att.</div>
          <div class="stat-value text-primary"><?= $teacherPct ?>%</div>
          <div class="stat-trend text-muted"><?= $presentTeachers ?> of <?= $totalTeachers ?> faculty on campus</div>
        </div>
        <div class="stat-icon"><i class="fas fa-id-badge"></i></div>
      </div>
    </div>
  </div>

  <!-- 5. Present Students -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-green">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Present Students</div>
          <div class="stat-value text-success"><?= $presentStudents ?></div>
          <div class="stat-trend text-success"><i class="fas fa-check-circle me-1"></i> Active in classroom</div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      </div>
    </div>
  </div>

  <!-- 6. Absent Students -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-red">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Absent Students</div>
          <div class="stat-value text-danger"><?= $absentStudents ?></div>
          <div class="stat-trend text-danger"><i class="fas fa-user-times me-1"></i> Today's absences</div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
      </div>
    </div>
  </div>

  <!-- 7. Present Teachers -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-blue">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Present Teachers</div>
          <div class="stat-value text-primary"><?= $presentTeachers ?></div>
          <div class="stat-trend text-primary"><i class="fas fa-check me-1"></i> Reporting to duty</div>
        </div>
        <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
      </div>
    </div>
  </div>

  <!-- 8. Absent Teachers -->
  <div class="col-xl-3 col-md-6">
    <div class="stat-card stat-amber">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-title">Absent / On Leave</div>
          <div class="stat-value text-danger"><?= $absentTeachers + (int)($teacherToday['on_leave_count'] ?? 0) ?></div>
          <div class="stat-trend text-muted"><?= (int)($teacherToday['on_leave_count'] ?? 0) ?> approved leave</div>
        </div>
        <div class="stat-icon"><i class="fas fa-plane-departure"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2 text-primary"></i> 7-Day School Attendance Pattern</h5>
        <span class="badge bg-primary-subtle text-primary">Live Database</span>
      </div>
      <div class="card-body">
        <div style="position: relative; height: 260px; max-height: 260px; width: 100%;">
          <canvas id="principalWeeklyChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i> Attendance Distribution</h5>
        <span class="badge bg-success-subtle text-success">Today</span>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div style="position: relative; height: 200px; max-height: 200px; width: 100%; max-width: 210px;">
          <canvas id="principalDoughnutChart"></canvas>
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

<!-- Pending Correction Requests & Low Attendance Alerts -->
<div class="row g-4">
  <!-- Correction Requests -->
  <div class="col-lg-6">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title text-primary"><i class="fas fa-spell-check me-2"></i> Pending Correction Requests</h5>
        <a href="<?= BASE_URL ?>/principal/corrections.php" class="btn btn-sm btn-link p-0 text-decoration-none">Manage All</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($pendingCorrections)): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($pendingCorrections as $pc): ?>
              <li class="list-group-item p-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <strong><?= e($pc['student_name']) ?></strong> <code><?= e($pc['student_id']) ?></code>
                    <div class="small text-muted mt-1">
                      Submitted by: <strong><?= e($pc['teacher_name'] ?? $pc['teacher_user_id']) ?></strong>
                    </div>
                  </div>
                  <div>
                    <span class="badge bg-secondary"><?= ucfirst($pc['original_status']) ?></span>
                    <i class="fas fa-arrow-right mx-1 text-muted"></i>
                    <span class="badge bg-success"><?= ucfirst($pc['requested_status']) ?></span>
                  </div>
                </div>
                <div class="small bg-light p-2 rounded mt-2 border">
                  <em>"<?= e($pc['reason']) ?>"</em>
                </div>
                <div class="mt-2 text-end">
                  <a href="<?= BASE_URL ?>/principal/corrections.php" class="btn btn-xs btn-outline-primary py-1 px-2 small">Review & Decide</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="p-4 text-center text-muted">
            <i class="fas fa-check-double text-success fa-2x mb-2 d-block"></i>
            All attendance correction requests have been resolved.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Low Attendance (<75%) -->
  <div class="col-lg-6">
    <div class="card card-custom h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Low Attendance (&lt; <?= $threshold ?>%)</h5>
        <a href="<?= BASE_URL ?>/principal/low_attendance.php" class="btn btn-sm btn-link text-danger p-0 text-decoration-none">View All (<?= count($lowStudents) ?>)</a>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($lowStudents)): ?>
          <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Class</th>
                  <th>Attendance %</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($lowStudents, 0, 5) as $ls): ?>
                  <tr>
                    <td>
                      <strong><?= e($ls['full_name']) ?></strong>
                      <small class="text-muted d-block"><?= e($ls['student_id']) ?> &bull; Roll #<?= $ls['roll_no'] ?></small>
                    </td>
                    <td><?= e($ls['class_name']) ?> - <?= e($ls['section_name']) ?></td>
                    <td>
                      <span class="badge bg-danger-subtle text-danger border border-danger fw-bold">
                        <?= $ls['percentage'] ?>%
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-4 text-center text-muted">
            <i class="fas fa-user-check text-success fa-2x mb-2 d-block"></i>
            No students are currently below the <?= $threshold ?>% attendance threshold.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
  fetch(window.BASE_URL + "/api/chart_data.php?type=weekly_trend")
    .then(r => r.json())
    .then(data => {
      const ctx = document.getElementById("principalWeeklyChart").getContext("2d");
      new Chart(ctx, {
        type: "bar",
        data: {
          labels: data.labels,
          datasets: [
            { label: "Present", data: data.present, backgroundColor: "#10b981", borderRadius: 5, maxBarThickness: 36 },
            { label: "Absent", data: data.absent, backgroundColor: "#ef4444", borderRadius: 5, maxBarThickness: 36 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "top",
              labels: { boxWidth: 12, padding: 12, font: { size: 12 } }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 }
            },
            x: {
              grid: { display: false }
            }
          }
        }
      });
    });

  fetch(window.BASE_URL + "/api/chart_data.php?type=daily_breakdown")
    .then(r => r.json())
    .then(data => {
      const ctx = document.getElementById("principalDoughnutChart").getContext("2d");
      new Chart(ctx, {
        type: "doughnut",
        data: {
          labels: data.labels,
          datasets: [{
            data: data.data,
            backgroundColor: data.colors,
            borderWidth: 2,
            borderColor: "#ffffff"
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "70%",
          plugins: { legend: { display: false } }
        }
      });
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
