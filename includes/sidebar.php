<?php
// includes/sidebar.php
declare(strict_types=1);

$role = current_role() ?? 'student';
$currentScript = basename($_SERVER['PHP_SELF']);
$pdo = get_db_connection();

// Pending corrections badge
$pendingCorrections = 0;
if (in_array($role, ['admin', 'principal'], true)) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM correction_requests WHERE status = 'pending'");
    $pendingCorrections = (int)$stmt->fetchColumn();
}
?>
<aside class="app-sidebar" id="appSidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo">
    <div class="brand-title">Smart Attendance</div>
  </div>

  <ul class="sidebar-menu">
    <?php if ($role === 'admin'): ?>
      <li class="menu-header">Administrator</li>
      
      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
          <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= in_array($currentScript, ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
          <i class="fas fa-users-cog"></i> <span>User Management</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'academics.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/academics.php">
          <i class="fas fa-graduation-cap"></i> <span>Academic Management</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/timetable.php">
          <i class="fas fa-calendar-alt"></i> <span>Timetable</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/attendance.php">
          <i class="fas fa-clipboard-check"></i> <span>Attendance Overview</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">
          <i class="fas fa-chart-line"></i> <span>Reports & Analytics</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'holidays.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/holidays.php">
          <i class="fas fa-umbrella-beach"></i> <span>Holiday Management</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'audit_logs.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/audit_logs.php">
          <i class="fas fa-history"></i> <span>Audit Logs</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/profile.php">
          <i class="fas fa-user-circle"></i> <span>My Profile</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'settings.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/settings.php">
          <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
      </li>

    <?php elseif ($role === 'principal'): ?>
      <li class="menu-header">Principal Portal</li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/index.php">
          <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'student_attendance.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/student_attendance.php">
          <i class="fas fa-user-check"></i> <span>Student Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'teacher_attendance.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/teacher_attendance.php">
          <i class="fas fa-chalkboard-teacher"></i> <span>Teacher Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'classes.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/classes.php">
          <i class="fas fa-school"></i> <span>Class Overview</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'teachers.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/teachers.php">
          <i class="fas fa-id-badge"></i> <span>Teacher Overview</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/timetable.php">
          <i class="fas fa-calendar-alt"></i> <span>Timetable</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'subject_attendance.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/subject_attendance.php">
          <i class="fas fa-book-reader"></i> <span>Subject Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'corrections.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/corrections.php">
          <i class="fas fa-spell-check"></i> <span>Correction Requests</span>
          <?php if ($pendingCorrections > 0): ?>
            <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingCorrections ?></span>
          <?php endif; ?>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'low_attendance.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/low_attendance.php">
          <i class="fas fa-exclamation-triangle text-warning"></i> <span>Low Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/reports.php">
          <i class="fas fa-file-invoice"></i> <span>Reports & Analytics</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'announcements.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/announcements.php">
          <i class="fas fa-bullhorn"></i> <span>Announcements</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/principal/profile.php">
          <i class="fas fa-user-circle"></i> <span>My Profile</span>
        </a>
      </li>

    <?php elseif ($role === 'teacher'): ?>
      <li class="menu-header">Teacher Portal</li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/index.php">
          <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'my_classes.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/my_classes.php">
          <i class="fas fa-chalkboard"></i> <span>My Classes</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= in_array($currentScript, ['my_students.php', 'student_add.php', 'student_edit.php']) ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/my_students.php">
          <i class="fas fa-user-graduate"></i> <span>My Students</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance_daily.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/attendance_daily.php">
          <i class="fas fa-calendar-check"></i> <span>Daily Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance_subject.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/attendance_subject.php">
          <i class="fas fa-book-open"></i> <span>Subject Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance_history.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/attendance_history.php">
          <i class="fas fa-history"></i> <span>Attendance History</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'correction_request.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/correction_request.php">
          <i class="fas fa-edit"></i> <span>Correction Requests</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/reports.php">
          <i class="fas fa-chart-pie"></i> <span>Attendance Reports</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/timetable.php">
          <i class="fas fa-clock"></i> <span>My Timetable</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/teacher/profile.php">
          <i class="fas fa-user-circle"></i> <span>My Profile</span>
        </a>
      </li>

    <?php elseif ($role === 'student'): ?>
      <li class="menu-header">Student Portal</li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/index.php">
          <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance_daily.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/attendance_daily.php">
          <i class="fas fa-calendar-check"></i> <span>Daily Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'attendance_subject.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/attendance_subject.php">
          <i class="fas fa-book-open"></i> <span>Subject Attendance</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'calendar.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/calendar.php">
          <i class="fas fa-calendar-alt"></i> <span>Attendance Calendar</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'history.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/history.php">
          <i class="fas fa-history"></i> <span>Attendance History</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'announcements.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/announcements.php">
          <i class="fas fa-bullhorn"></i> <span>Announcements</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/profile.php">
          <i class="fas fa-user-circle"></i> <span>My Profile</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= ($currentScript === 'change_password.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/change_password.php">
          <i class="fas fa-key"></i> <span>Change Password</span>
        </a>
      </li>
    <?php endif; ?>

    <li class="nav-item mt-3">
      <a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
      </a>
    </li>
  </ul>

  <div class="sidebar-footer text-center">
    <small class="text-secondary d-block">Academic Year</small>
    <span class="badge bg-primary-subtle text-primary mt-1">
      <i class="fas fa-calendar-day me-1"></i> <?= e(get_active_academic_year()['name'] ?? '2026-2027') ?>
    </span>
  </div>
</aside>
