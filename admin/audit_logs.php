<?php
// admin/audit_logs.php - Audit Logs Viewer
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'System Audit Logs';
$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($actionFilter)) {
    $where[] = "a.action = ?";
    $params[] = $actionFilter;
}

if (!empty($search)) {
    $where[] = "(a.details LIKE ? OR u.user_id LIKE ? OR a.ip_address LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

$whereClause = implode(' AND ', $where);

$query = "SELECT a.*, u.user_id, u.role
          FROM audit_logs a
          LEFT JOIN users u ON a.user_id = u.id
          WHERE {$whereClause}
          ORDER BY a.id DESC LIMIT 150";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions for dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-history me-2 text-primary"></i> System Audit Logs</h1>
    <p class="page-subtitle">Security trail tracking authentication, attendance updates, user modifications, and administrative events.</p>
  </div>
</div>

<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="" class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small fw-bold mb-1">Filter by Action</label>
        <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Actions</option>
          <?php foreach ($actions as $act): ?>
            <option value="<?= e($act) ?>" <?= ($actionFilter === $act) ? 'selected' : '' ?>><?= e($act) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-bold mb-1">Search Keywords</label>
        <div class="input-group input-group-sm">
          <input type="text" name="search" class="form-control" placeholder="Search by detail text, User ID, IP address..." value="<?= e($search) ?>">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </div>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-light btn-sm w-100 border text-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">Audit Log Entries (Latest 150)</h5>
    <span class="badge bg-secondary-subtle text-secondary"><?= count($logs) ?> Events</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Action</th>
            <th>Initiator</th>
            <th>Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><small class="text-muted"><?= format_date($l['created_at'], 'M d, Y h:i:s A') ?></small></td>
                <td><span class="badge bg-dark-subtle text-dark border"><?= e($l['action']) ?></span></td>
                <td>
                  <?php if (!empty($l['user_id'])): ?>
                    <code><?= e($l['user_id']) ?></code>
                    <small class="text-secondary">(<?= e($l['role']) ?>)</small>
                  <?php else: ?>
                    <span class="text-muted small">System / Guest</span>
                  <?php endif; ?>
                </td>
                <td><?= e($l['details']) ?></td>
                <td><small class="text-secondary"><?= e($l['ip_address'] ?? '127.0.0.1') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No audit logs found matching criteria.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
