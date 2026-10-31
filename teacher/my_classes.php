<?php
// teacher/my_classes.php - Assigned Classes List
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$pageTitle = 'My Classes';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

$tStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$tStmt->execute([$currentUserId]);
$teacherId = (int)$tStmt->fetchColumn();

$assignments = $pdo->prepare("SELECT ta.*, c.name as class_name, c.type as class_type, sec.name as section_name, sub.name as subject_name, sub.code as subject_code,
                                     COUNT(DISTINCT s.id) as student_count
                              FROM teacher_assignments ta
                              JOIN classes c ON ta.class_id = c.id
                              JOIN sections sec ON ta.section_id = sec.id
                              LEFT JOIN subjects sub ON ta.subject_id = sub.id
                              LEFT JOIN students s ON (ta.class_id = s.class_id AND ta.section_id = s.section_id)
                              WHERE ta.teacher_id = ?
                              GROUP BY ta.id
                              ORDER BY c.numeric_level, sec.name");
$assignments->execute([$teacherId]);
$classes = $assignments->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-chalkboard me-2 text-primary"></i> My Assigned Classes</h1>
    <p class="page-subtitle">Roster of divisions, subjects, and student cohorts officially delegated to your faculty profile.</p>
  </div>
</div>

<div class="row g-4">
  <?php if (!empty($classes)): ?>
    <?php foreach ($classes as $c): ?>
      <div class="col-lg-6">
        <div class="card card-custom h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="card-title text-dark mb-0"><?= e($c['class_name']) ?> - Section <?= e($c['section_name']) ?></h5>
              <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.7rem;"><?= e($c['class_type']) ?></span>
            </div>
            <?php if ($c['is_class_teacher'] == 1): ?>
              <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                <i class="fas fa-star me-1"></i> Class Teacher
              </span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6">
                <span class="text-muted small d-block">Subject Taught</span>
                <strong class="text-dark fs-6"><?= e($c['subject_name'] ?? 'Class In-charge') ?></strong>
                <?php if (!empty($c['subject_code'])): ?>
                  <small class="text-muted d-block"><?= e($c['subject_code']) ?></small>
                <?php endif; ?>
              </div>
              <div class="col-6 text-end">
                <span class="text-muted small d-block">Enrolled Students</span>
                <span class="fs-4 fw-bold text-primary"><?= $c['student_count'] ?></span>
              </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end">
              <a href="<?= BASE_URL ?>/teacher/my_students.php?class_id=<?= $c['class_id'] ?>&section_id=<?= $c['section_id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-users me-1"></i> View Students
              </a>
              <a href="<?= BASE_URL ?>/teacher/attendance_daily.php?class_id=<?= $c['class_id'] ?>&section_id=<?= $c['section_id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-check-circle me-1"></i> Take Attendance
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="card card-custom text-center p-5 text-muted">
        <i class="fas fa-chalkboard fa-3x mb-3 text-secondary"></i>
        <h5>No Assigned Classes</h5>
        <p>You have not been assigned to any class or subject yet. Please contact the administrator.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
