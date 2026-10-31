<?php
// includes/navbar.php
declare(strict_types=1);

$user = current_user();
$pdo = get_db_connection();
$role = current_role() ?? 'student';

// Recent announcements for notification dropdown
$targetCondition = match ($role) {
    'teacher' => "target_role IN ('all', 'teacher')",
    'student' => "target_role IN ('all', 'student')",
    default => "1=1"
};
$announcementsStmt = $pdo->query("SELECT id, title, created_at FROM announcements WHERE is_active = 1 AND $targetCondition ORDER BY id DESC LIMIT 5");
$recentAnnouncements = $announcementsStmt->fetchAll();
$unreadCount = count($recentAnnouncements);

$profileLink = match ($role) {
    'admin' => BASE_URL . '/admin/profile.php',
    'principal' => BASE_URL . '/principal/profile.php',
    'teacher' => BASE_URL . '/teacher/profile.php',
    'student' => BASE_URL . '/student/profile.php',
    default => '#'
};
?>
<div class="app-main">
  <header class="app-navbar no-print">
    <div class="d-flex align-items-center gap-3">
      <button class="navbar-toggle-btn" id="sidebarToggleBtn" type="button" aria-label="Toggle navigation">
        <i class="fas fa-bars"></i>
      </button>
      <div class="d-none d-md-block">
        <span class="text-secondary small d-block">Today's Date</span>
        <strong class="text-dark small"><i class="far fa-calendar-alt me-1 text-primary"></i> <?= date('l, F j, Y') ?></strong>
      </div>
    </div>

    <div class="d-flex align-items-center gap-3">
      <!-- Announcements Dropdown -->
      <div class="dropdown">
        <button class="btn btn-light position-relative rounded-circle p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
          <i class="fas fa-bell text-secondary"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="width: 300px;">
          <li class="dropdown-header d-flex justify-content-between align-items-center pb-2 border-bottom">
            <span class="fw-bold text-dark">Notifications</span>
            <span class="badge bg-primary-subtle text-primary"><?= $unreadCount ?> new</span>
          </li>
          <?php if (!empty($recentAnnouncements)): ?>
            <?php foreach ($recentAnnouncements as $ann): ?>
              <li>
                <a class="dropdown-item py-2 px-2 rounded small text-wrap" href="#">
                  <div class="fw-semibold text-dark text-truncate"><?= e($ann['title']) ?></div>
                  <div class="text-muted" style="font-size: 0.75rem;"><?= format_date($ann['created_at'], 'M d, Y') ?></div>
                </a>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="p-3 text-center text-muted small">No new notifications</li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- User Profile Dropdown -->
      <div class="dropdown">
        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle user-profile-menu" data-bs-toggle="dropdown" aria-expanded="false">
          <?php if (!empty($user['photo'])): ?>
            <img src="<?= BASE_URL ?>/<?= e($user['photo']) ?>" alt="Avatar">
          <?php else: ?>
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
              <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
          <?php endif; ?>
          <div class="d-none d-sm-block text-start">
            <div class="fw-semibold text-dark text-truncate" style="max-width: 140px; font-size: 0.88rem;"><?= e($user['full_name'] ?? 'User') ?></div>
            <span class="badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.68rem;"><?= e($user['role'] ?? 'guest') ?></span>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-2" style="min-width: 200px;">
          <li class="px-2 py-1 text-muted small border-bottom mb-1">
            Signed in as <strong><?= e($user['user_id'] ?? '') ?></strong>
          </li>
          <li>
            <a class="dropdown-item rounded py-2 small" href="<?= $profileLink ?>">
              <i class="fas fa-user-circle me-2 text-primary"></i> My Profile
            </a>
          </li>
          <?php if ($role === 'student'): ?>
            <li>
              <a class="dropdown-item rounded py-2 small" href="<?= BASE_URL ?>/student/change_password.php">
                <i class="fas fa-key me-2 text-warning"></i> Change Password
              </a>
            </li>
          <?php endif; ?>
          <li><hr class="dropdown-divider my-1"></li>
          <li>
            <a class="dropdown-item rounded py-2 small text-danger" href="<?= BASE_URL ?>/logout.php">
              <i class="fas fa-sign-out-alt me-2"></i> Log Out
            </a>
          </li>
        </ul>
      </div>
    </div>
  </header>

  <main class="app-content">
    <?= render_flash_messages() ?>
