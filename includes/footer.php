<?php
// includes/footer.php
declare(strict_types=1);

$schoolName = get_setting('school_name', 'Smart School Attendance Management System');
$role = current_role();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
  </main>

  <footer class="py-3 px-4 bg-white border-top text-center text-muted small no-print">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>&copy; <?= date('Y') ?> <strong><?= e($schoolName) ?></strong>. All rights reserved.</div>
      <div>Designed with <i class="fas fa-heart text-danger"></i> for Smart Education &bull; v<?= APP_VERSION ?></div>
    </div>
  </footer>
</div> <!-- /app-main -->

<?php if (is_logged_in() && $role): ?>
  <!-- Native Mobile Bottom Navigation Bar (Visible on mobile screens) -->
  <nav class="app-mobile-bottom-nav d-md-none no-print">
    <?php if ($role === 'student'): ?>
      <a href="<?= BASE_URL ?>/student/index.php" class="nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="<?= BASE_URL ?>/student/attendance_daily.php" class="nav-item <?= ($currentScript === 'attendance_daily.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-check"></i>
        <span>Daily</span>
      </a>
      <a href="<?= BASE_URL ?>/student/attendance_subject.php" class="nav-item <?= ($currentScript === 'attendance_subject.php') ? 'active' : '' ?>">
        <i class="fas fa-book-open"></i>
        <span>Subjects</span>
      </a>
      <a href="<?= BASE_URL ?>/student/calendar.php" class="nav-item <?= ($currentScript === 'calendar.php') ? 'active' : '' ?>">
        <i class="far fa-calendar-alt"></i>
        <span>Calendar</span>
      </a>
      <a href="<?= BASE_URL ?>/student/profile.php" class="nav-item <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i>
        <span>Profile</span>
      </a>
    <?php elseif ($role === 'teacher'): ?>
      <a href="<?= BASE_URL ?>/teacher/index.php" class="nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="<?= BASE_URL ?>/teacher/attendance_daily.php" class="nav-item <?= ($currentScript === 'attendance_daily.php') ? 'active' : '' ?>">
        <i class="fas fa-check-circle"></i>
        <span>Daily</span>
      </a>
      <a href="<?= BASE_URL ?>/teacher/attendance_subject.php" class="nav-item <?= ($currentScript === 'attendance_subject.php') ? 'active' : '' ?>">
        <i class="fas fa-book-reader"></i>
        <span>Subject</span>
      </a>
      <a href="<?= BASE_URL ?>/teacher/timetable.php" class="nav-item <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>">
        <i class="fas fa-clock"></i>
        <span>Timetable</span>
      </a>
      <a href="<?= BASE_URL ?>/teacher/my_students.php" class="nav-item <?= in_array($currentScript, ['my_students.php', 'student_add.php', 'student_edit.php']) ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i>
        <span>Students</span>
      </a>
    <?php elseif ($role === 'principal'): ?>
      <a href="<?= BASE_URL ?>/principal/index.php" class="nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="<?= BASE_URL ?>/principal/student_attendance.php" class="nav-item <?= ($currentScript === 'student_attendance.php') ? 'active' : '' ?>">
        <i class="fas fa-user-check"></i>
        <span>Daily</span>
      </a>
      <a href="<?= BASE_URL ?>/principal/timetable.php" class="nav-item <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i>
        <span>Timetable</span>
      </a>
      <a href="<?= BASE_URL ?>/principal/low_attendance.php" class="nav-item <?= ($currentScript === 'low_attendance.php') ? 'active' : '' ?>">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Defaulters</span>
      </a>
      <a href="<?= BASE_URL ?>/principal/profile.php" class="nav-item <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i>
        <span>Profile</span>
      </a>
    <?php elseif ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/admin/index.php" class="nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="nav-item <?= in_array($currentScript, ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : '' ?>">
        <i class="fas fa-users-cog"></i>
        <span>Users</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/timetable.php" class="nav-item <?= ($currentScript === 'timetable.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i>
        <span>Timetable</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/attendance.php" class="nav-item <?= ($currentScript === 'attendance.php') ? 'active' : '' ?>">
        <i class="fas fa-clipboard-check"></i>
        <span>Attendance</span>
      </a>
      <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-item <?= ($currentScript === 'settings.php') ? 'active' : '' ?>">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

</div> <!-- /app-wrapper -->

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 4.4 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Main JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<?php if (!empty($extraScripts)): ?>
  <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
