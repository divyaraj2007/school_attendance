<?php
// student/calendar.php - Interactive Attendance Calendar
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'Attendance Calendar';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$sStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$sStmt->execute([$currentUserId]);
$studentId = (int)$sStmt->fetchColumn();

// Month & Year calculation
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDayTimestamp);
$firstDayOfWeek = (int)date('w', $firstDayTimestamp); // 0 = Sunday

// Navigation URLs
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Fetch attendance for this month
$monthPrefix = sprintf("%04d-%02d", $year, $month);
$attStmt = $pdo->prepare("SELECT attendance_date, status, remarks FROM daily_attendance WHERE student_id = ? AND attendance_date LIKE ?");
$attStmt->execute([$studentId, $monthPrefix . '%']);
$attendanceData = [];
while ($row = $attStmt->fetch()) {
    $attendanceData[$row['attendance_date']] = $row;
}

// Fetch holidays for this month
$hStmt = $pdo->prepare("SELECT title, from_date, to_date FROM holidays WHERE from_date <= ? AND to_date >= ?");
$monthEnd = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);
$monthStart = sprintf("%04d-%02d-01", $year, $month);
$hStmt->execute([$monthEnd, $monthStart]);
$holidays = $hStmt->fetchAll();

function getHolidayForDate(string $date, array $holidays): ?string {
    foreach ($holidays as $h) {
        if ($date >= $h['from_date'] && $date <= $h['to_date']) {
            return $h['title'];
        }
    }
    return null;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-calendar-alt me-2 text-primary"></i> Attendance Calendar</h1>
    <p class="page-subtitle">Visual month calendar tracking attendance states and institutional breaks.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-chevron-left me-1"></i> Previous
    </a>
    <span class="fw-bold fs-6 text-dark px-2"><?= date('F Y', $firstDayTimestamp) ?></span>
    <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-outline-secondary btn-sm">
      Next <i class="fas fa-chevron-right ms-1"></i>
    </a>
  </div>
</div>

<!-- Legend -->
<div class="card card-custom mb-3 py-2 px-3">
  <div class="d-flex flex-wrap gap-3 align-items-center small text-muted">
    <strong class="text-dark">Calendar Legend:</strong>
    <span><span class="badge bg-success me-1">&nbsp;&nbsp;</span> Present</span>
    <span><span class="badge bg-danger me-1">&nbsp;&nbsp;</span> Absent</span>
    <span><span class="badge bg-warning text-dark me-1">&nbsp;&nbsp;</span> Late</span>
    <span><span class="badge bg-warning-subtle text-warning-emphasis border border-warning me-1">&nbsp;&nbsp;</span> Half Day</span>
    <span><span class="badge bg-info text-white me-1">&nbsp;&nbsp;</span> Holiday / Break</span>
  </div>
</div>

<!-- Calendar Grid -->
<div class="card card-custom">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-custom text-center align-middle mb-0" style="min-width: 700px;">
        <thead class="table-light">
          <tr>
            <th class="text-danger" style="width: 14.28%;">Sun</th>
            <th style="width: 14.28%;">Mon</th>
            <th style="width: 14.28%;">Tue</th>
            <th style="width: 14.28%;">Wed</th>
            <th style="width: 14.28%;">Thu</th>
            <th style="width: 14.28%;">Fri</th>
            <th style="width: 14.28%;">Sat</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <?php
              $cellCount = 0;
              // Empty cells before first day
              for ($i = 0; $i < $firstDayOfWeek; $i++) {
                  echo '<td class="bg-light text-muted p-3" style="height: 100px;"></td>';
                  $cellCount++;
              }

              for ($day = 1; $day <= $daysInMonth; $day++) {
                  $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
                  $dayOfWeek = ($firstDayOfWeek + $day - 1) % 7;
                  $isSunday = ($dayOfWeek === 0);
                  $holidayTitle = getHolidayForDate($currentDate, $holidays);
                  $att = $attendanceData[$currentDate] ?? null;

                  $bgClass = "bg-white";
                  $statusBadge = "";

                  if ($isSunday) {
                      $bgClass = "bg-light";
                      $statusBadge = '<span class="badge bg-secondary-subtle text-secondary small">Weekend</span>';
                  } elseif ($holidayTitle) {
                      $bgClass = "bg-info-subtle";
                      $statusBadge = '<span class="badge bg-info text-white small" title="' . e($holidayTitle) . '"><i class="fas fa-umbrella-beach me-1"></i>' . e(substr($holidayTitle, 0, 10)) . '</span>';
                  } elseif ($att) {
                      if ($att['status'] === 'present') {
                          $bgClass = "bg-success-subtle";
                          $statusBadge = '<span class="badge bg-success small"><i class="fas fa-check"></i> Present</span>';
                      } elseif ($att['status'] === 'absent') {
                          $bgClass = "bg-danger-subtle";
                          $statusBadge = '<span class="badge bg-danger small"><i class="fas fa-times"></i> Absent</span>';
                      } elseif ($att['status'] === 'late') {
                          $bgClass = "bg-warning-subtle";
                          $statusBadge = '<span class="badge bg-warning text-dark small"><i class="fas fa-clock"></i> Late</span>';
                      } elseif ($att['status'] === 'half_day') {
                          $bgClass = "bg-warning-subtle";
                          $statusBadge = '<span class="badge bg-warning text-dark small"><i class="fas fa-adjust"></i> Half Day</span>';
                      }
                  }

                  echo '<td class="' . $bgClass . ' p-2 align-top text-start" style="height: 100px;">';
                  echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                  echo '<strong class="fs-6 ' . ($isSunday ? 'text-danger' : 'text-dark') . '">' . $day . '</strong>';
                  echo '</div>';
                  if ($statusBadge) {
                      echo '<div class="mt-2">' . $statusBadge . '</div>';
                  }
                  if ($att && !empty($att['remarks'])) {
                      echo '<div class="text-muted text-truncate mt-1" style="font-size: 0.72rem;">' . e($att['remarks']) . '</div>';
                  }
                  echo '</td>';

                  $cellCount++;
                  if ($cellCount % 7 === 0 && $day < $daysInMonth) {
                      echo '</tr><tr>';
                  }
              }

              // Trailing empty cells
              while ($cellCount % 7 !== 0) {
                  echo '<td class="bg-light text-muted p-3" style="height: 100px;"></td>';
                  $cellCount++;
              }
            ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
