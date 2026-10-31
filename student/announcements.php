<?php
// student/announcements.php - Student Notice Board
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$pageTitle = 'School Announcements';
$pdo = get_db_connection();

$stmt = $pdo->query("SELECT a.*, u.user_id as author_code 
                     FROM announcements a 
                     JOIN users u ON a.created_by = u.id 
                     WHERE a.is_active = 1 AND a.target_role IN ('all', 'student') 
                     ORDER BY a.id DESC");
$announcements = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-bullhorn me-2 text-primary"></i> School Announcements & Notices</h1>
    <p class="page-subtitle">Official notifications, holiday circulars, and exam guidelines from administration.</p>
  </div>
</div>

<div class="row g-4">
  <?php if (!empty($announcements)): ?>
    <?php foreach ($announcements as $ann): ?>
      <div class="col-md-6">
        <div class="card card-custom h-100 shadow-sm border-top border-primary border-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.72rem;">Official Notice</span>
              <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?= format_date($ann['created_at']) ?></small>
            </div>
            <h5 class="fw-bold text-dark mb-2"><?= e($ann['title']) ?></h5>
            <p class="text-secondary small mb-3"><?= nl2br(e($ann['content'])) ?></p>
            <div class="border-top pt-2 text-muted" style="font-size: 0.75rem;">
              Issued by: <strong><?= e($ann['author_code']) ?></strong>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="card card-custom text-center p-5 text-muted">
        <i class="fas fa-bullhorn fa-3x mb-3 text-secondary"></i>
        <h5>No Notices Posted</h5>
        <p>There are no active school announcements at this moment.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
