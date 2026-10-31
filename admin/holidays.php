<?php
// admin/holidays.php - Holiday Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Holiday Management';
$pdo = get_db_connection();
$activeYear = get_active_academic_year();
$yearId = $activeYear['id'] ?? 1;

// POST Handlers
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid security token.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $fromDate = $_POST['from_date'] ?? '';
            $toDate = $_POST['to_date'] ?? '';
            $desc = trim($_POST['description'] ?? '');

            if (!empty($title) && !empty($fromDate) && !empty($toDate)) {
                $stmt = $pdo->prepare("INSERT INTO holidays (title, from_date, to_date, description, academic_year_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $fromDate, $toDate, $desc, $yearId]);
                log_audit('HOLIDAY_ADDED', "Added holiday: {$title} ({$fromDate} to {$toDate}).");
                flash_message('success', "Holiday '{$title}' added successfully.");
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
                $stmt->execute([$id]);
                log_audit('HOLIDAY_DELETED', "Deleted holiday ID {$id}.");
                flash_message('success', "Holiday removed.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/admin/holidays.php');
    exit;
}

$holidays = $pdo->query("SELECT * FROM holidays ORDER BY from_date ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-umbrella-beach me-2 text-primary"></i> Holiday Management</h1>
    <p class="page-subtitle">Define institution-wide breaks, festivals, and official holidays.</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
    <i class="fas fa-plus me-1"></i> Add Holiday
  </button>
</div>

<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Scheduled Academic Holidays</h5>
    <span class="badge bg-primary-subtle text-primary"><?= count($holidays) ?> Holidays</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Holiday Title</th>
            <th>From Date</th>
            <th>To Date</th>
            <th>Duration</th>
            <th>Description</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($holidays)): ?>
            <?php foreach ($holidays as $h): ?>
              <?php
                $start = new DateTime($h['from_date']);
                $end = new DateTime($h['to_date']);
                $days = $start->diff($end)->days + 1;
              ?>
              <tr>
                <td><strong><?= e($h['title']) ?></strong></td>
                <td><i class="far fa-calendar-alt text-primary me-1"></i> <?= format_date($h['from_date']) ?></td>
                <td><i class="far fa-calendar-alt text-secondary me-1"></i> <?= format_date($h['to_date']) ?></td>
                <td><span class="badge bg-info-subtle text-info-emphasis border border-info"><?= $days ?> Day<?= $days > 1 ? 's' : '' ?></span></td>
                <td><small class="text-muted"><?= e($h['description'] ?? '-') ?></small></td>
                <td class="text-end">
                  <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this holiday?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center py-4 text-muted">No holidays listed.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Add Holiday -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Official Holiday</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Holiday Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Winter Vacation" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">From Date <span class="text-danger">*</span></label>
              <input type="date" name="from_date" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">To Date <span class="text-danger">*</span></label>
              <input type="date" name="to_date" class="form-control" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Description / Circular Remarks</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional notes for school calendar..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Holiday</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
