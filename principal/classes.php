<?php
// principal/classes.php - Class Overview
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Class Overview';
$pdo = get_db_connection();

$classes = $pdo->query("SELECT c.*, 
                               COUNT(DISTINCT sec.id) as section_count,
                               COUNT(DISTINCT s.id) as student_count
                        FROM classes c
                        LEFT JOIN sections sec ON c.id = sec.class_id
                        LEFT JOIN students s ON c.id = s.class_id
                        GROUP BY c.id
                        ORDER BY c.numeric_level ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-school me-2 text-primary"></i> Academic Class Overview</h1>
    <p class="page-subtitle">Inspect enrollment metrics, sections, and division health across school and college tiers.</p>
  </div>
</div>

<div class="row g-4">
  <?php foreach ($classes as $c): ?>
    <?php
      // Fetch sections for this class
      $secStmt = $pdo->prepare("SELECT sec.*, 
                                       COUNT(DISTINCT s.id) as student_count,
                                       t.full_name as class_teacher_name
                                FROM sections sec
                                LEFT JOIN students s ON sec.id = s.section_id
                                LEFT JOIN teacher_assignments ta ON sec.id = ta.section_id AND ta.is_class_teacher = 1
                                LEFT JOIN teachers t ON ta.teacher_id = t.id
                                WHERE sec.class_id = ?
                                GROUP BY sec.id
                                ORDER BY sec.name ASC");
      $secStmt->execute([$c['id']]);
      $sections = $secStmt->fetchAll();
    ?>
    <div class="col-lg-6">
      <div class="card card-custom h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title text-dark"><?= e($c['name']) ?></h5>
            <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.7rem;"><?= e($c['type']) ?></span>
          </div>
          <span class="badge bg-primary px-3 py-2 fs-6"><?= $c['student_count'] ?> Enrolled</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-custom table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Section</th>
                  <th>Students</th>
                  <th>Class Teacher In-charge</th>
                  <th class="text-end">Attendance</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($sections)): ?>
                  <?php foreach ($sections as $s): ?>
                    <tr>
                      <td><strong class="text-primary">Section <?= e($s['name']) ?></strong></td>
                      <td><?= $s['student_count'] ?> Students</td>
                      <td>
                        <?php if (!empty($s['class_teacher_name'])): ?>
                          <i class="fas fa-chalkboard-teacher text-success me-1"></i> <?= e($s['class_teacher_name']) ?>
                        <?php else: ?>
                          <span class="text-muted small">Not Assigned</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <a href="<?= BASE_URL ?>/principal/student_attendance.php?class_id=<?= $c['id'] ?>&section_id=<?= $s['id'] ?>" class="btn btn-xs btn-outline-primary py-1 px-2 small">
                          Take Att.
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted py-2 small">No sections created for this class yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
