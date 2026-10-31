<?php
// principal/teachers.php - Teacher Overview
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Teacher Overview';
$pdo = get_db_connection();

$teachers = $pdo->query("SELECT t.*, u.status as account_status, u.last_login
                        FROM teachers t
                        JOIN users u ON t.user_id = u.id
                        ORDER BY t.full_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-id-badge me-2 text-primary"></i> Faculty & Teacher Overview</h1>
    <p class="page-subtitle">Directory of academic teachers, contact channels, and classroom teaching assignments.</p>
  </div>
</div>

<div class="row g-4">
  <?php foreach ($teachers as $t): ?>
    <?php
      $assignStmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sec.name as section_name, sub.name as subject_name
                                   FROM teacher_assignments ta
                                   JOIN classes c ON ta.class_id = c.id
                                   JOIN sections sec ON ta.section_id = sec.id
                                   LEFT JOIN subjects sub ON ta.subject_id = sub.id
                                   WHERE ta.teacher_id = ?");
      $assignStmt->execute([$t['id']]);
      $assignments = $assignStmt->fetchAll();
    ?>
    <div class="col-lg-6">
      <div class="card card-custom h-100">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-4 flex-shrink-0" style="width: 60px; height: 60px;">
              <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h5 class="fw-bold text-dark mb-0"><?= e($t['full_name']) ?></h5>
                  <code><?= e($t['teacher_id']) ?></code> &bull; <small class="text-muted"><?= e($t['qualification'] ?? 'Faculty') ?></small>
                </div>
                <?= status_badge($t['account_status']) ?>
              </div>

              <div class="mt-2 text-secondary small">
                <div><i class="far fa-envelope me-1 text-primary"></i> <?= e($t['email']) ?></div>
                <div><i class="fas fa-phone-alt me-1 text-primary"></i> <?= e($t['mobile']) ?></div>
                <div><i class="far fa-calendar-alt me-1 text-primary"></i> Joined: <?= format_date($t['joining_date']) ?></div>
              </div>

              <div class="mt-3 pt-2 border-top">
                <div class="small fw-bold text-dark mb-1">Class & Subject Allocations:</div>
                <div class="d-flex flex-wrap gap-1">
                  <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $as): ?>
                      <span class="badge bg-light text-dark border">
                        <?= e($as['class_name']) ?> - Sec <?= e($as['section_name']) ?>: <?= e($as['subject_name'] ?? 'Class Incharge') ?>
                        <?= ($as['is_class_teacher'] == 1) ? ' ★' : '' ?>
                      </span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-muted small">No class assignments yet</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
