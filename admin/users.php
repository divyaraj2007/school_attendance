<?php
// admin/users.php - User Management (Principals, Teachers, Students)
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'User Management';
$pdo = get_db_connection();

// Handle Actions (Activate / Deactivate / Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user->execute([$userId]);
        $targetUser = $user->fetch();

        if ($targetUser) {
            // Cannot delete or deactivate own admin account
            if ($targetUser['id'] === (int)$_SESSION['user']['id']) {
                flash_message('danger', 'You cannot modify or delete your own active administrator session.');
            } elseif ($action === 'toggle_status') {
                $newStatus = ($targetUser['status'] === 'active') ? 'inactive' : 'active';
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $userId]);
                log_audit('USER_STATUS_CHANGE', "User {$targetUser['user_id']} status updated to {$newStatus}.");
                flash_message('success', "User {$targetUser['user_id']} is now {$newStatus}.");
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                log_audit('USER_DELETED', "User {$targetUser['user_id']} ({$targetUser['role']}) was deleted.");
                flash_message('success', "User {$targetUser['user_id']} has been deleted.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Filters
$roleFilter = $_GET['role'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$classFilter = (int)($_GET['class_id'] ?? 0);

// Export Students to CSV
if (isset($_GET['export']) && $_GET['export'] === 'students') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Admission No', 'Roll No', 'Full Name', 'Gender', 'Class', 'Section', 'DOB', 'Status']);

    $sql = "SELECT s.student_id, s.admission_no, s.roll_no, s.full_name, s.gender, c.name as class_name, sec.name as section_name, s.dob, u.status 
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN classes c ON s.class_id = c.id
            JOIN sections sec ON s.section_id = sec.id
            ORDER BY c.numeric_level, sec.name, s.roll_no";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['student_id'],
            $row['admission_no'],
            $row['roll_no'],
            $row['full_name'],
            ucfirst($row['gender']),
            $row['class_name'],
            $row['section_name'],
            $row['dob'],
            ucfirst($row['status'])
        ]);
    }
    fclose($output);
    exit;
}

// Classes for filter dropdown
$classes = $pdo->query("SELECT id, name FROM classes ORDER BY numeric_level")->fetchAll();

// Build Query
$where = ["1=1"];
$params = [];

if ($roleFilter !== 'all' && in_array($roleFilter, ['admin', 'principal', 'teacher', 'student'], true)) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($search)) {
    $where[] = "(u.user_id LIKE ? OR p.full_name LIKE ? OR t.full_name LIKE ? OR s.full_name LIKE ? OR s.admission_no LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($classFilter > 0) {
    $where[] = "s.class_id = ?";
    $params[] = $classFilter;
}

$whereClause = implode(' AND ', $where);

$query = "SELECT u.id as user_row_id, u.user_id, u.role, u.status, u.created_at, u.last_login,
                 COALESCE(p.full_name, t.full_name, s.full_name, 'Admin') as display_name,
                 p.email as p_email, p.mobile as p_mobile,
                 t.email as t_email, t.mobile as t_mobile, t.qualification as t_qualification,
                 s.roll_no, s.admission_no, c.name as class_name, sec.name as section_name
          FROM users u
          LEFT JOIN principals p ON u.id = p.user_id
          LEFT JOIN teachers t ON u.id = t.user_id
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN classes c ON s.class_id = c.id
          LEFT JOIN sections sec ON s.section_id = sec.id
          WHERE {$whereClause}
          ORDER BY u.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-users-cog me-2 text-primary"></i> User Management</h1>
    <p class="page-subtitle">View, search, add, activate, deactivate, or delete Principals, Teachers, and Students.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= BASE_URL ?>/admin/users.php?export=students" class="btn btn-outline-success btn-sm">
      <i class="fas fa-file-csv me-1"></i> Export Students
    </a>
    <a href="<?= BASE_URL ?>/admin/user_add.php" class="btn btn-primary btn-sm">
      <i class="fas fa-user-plus me-1"></i> Add New User
    </a>
  </div>
</div>

<!-- Filters Bar -->
<div class="card card-custom mb-4">
  <div class="card-body">
    <form method="GET" action="<?= BASE_URL ?>/admin/users.php" class="row g-2 align-items-center">
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary mb-1">Filter by Role</label>
        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="all" <?= ($roleFilter === 'all') ? 'selected' : '' ?>>All Roles</option>
          <option value="principal" <?= ($roleFilter === 'principal') ? 'selected' : '' ?>>Principals</option>
          <option value="teacher" <?= ($roleFilter === 'teacher') ? 'selected' : '' ?>>Teachers</option>
          <option value="student" <?= ($roleFilter === 'student') ? 'selected' : '' ?>>Students</option>
          <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>Administrators</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary mb-1">Filter by Class (Students)</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All Classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($classFilter === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold text-secondary mb-1">Search Keyword</label>
        <div class="input-group input-group-sm">
          <input type="text" name="search" class="form-control" placeholder="Search by name, ID, roll no..." value="<?= e($search) ?>">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </div>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-light btn-sm w-100 border text-secondary">
          <i class="fas fa-undo me-1"></i> Reset
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Users Table -->
<div class="card card-custom">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title">User Accounts List</h5>
    <span class="badge bg-secondary-subtle text-secondary"><?= count($users) ?> accounts found</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>User ID</th>
            <th>Name & Details</th>
            <th>Role</th>
            <th>Class / Details</th>
            <th>Status</th>
            <th>Last Login</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <code class="fw-bold fs-6"><?= e($u['user_id']) ?></code>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($u['display_name']) ?></div>
                  <?php if (!empty($u['t_email']) || !empty($u['p_email'])): ?>
                    <small class="text-muted"><i class="far fa-envelope me-1"></i><?= e($u['t_email'] ?? $u['p_email']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                    $roleBadge = match ($u['role']) {
                        'admin' => 'bg-danger text-white',
                        'principal' => 'bg-purple text-white',
                        'teacher' => 'bg-primary text-white',
                        'student' => 'bg-info text-dark',
                        default => 'bg-secondary text-white'
                    };
                  ?>
                  <span class="badge <?= $roleBadge ?> text-uppercase"><?= e($u['role']) ?></span>
                </td>
                <td>
                  <?php if ($u['role'] === 'student'): ?>
                    <span class="badge bg-light text-dark border">
                      <?= e($u['class_name'] ?? 'Class') ?> - <?= e($u['section_name'] ?? '') ?>
                    </span>
                    <small class="text-muted d-block">Roll: <?= $u['roll_no'] ?> | Adm: <?= e($u['admission_no'] ?? '') ?></small>
                  <?php elseif ($u['role'] === 'teacher'): ?>
                    <small class="text-secondary"><?= e($u['t_qualification'] ?? 'Faculty Member') ?></small>
                  <?php elseif ($u['role'] === 'principal'): ?>
                    <small class="text-secondary">School Principal</small>
                  <?php else: ?>
                    <small class="text-secondary">System Admin</small>
                  <?php endif; ?>
                </td>
                <td>
                  <?= status_badge($u['status']) ?>
                </td>
                <td>
                  <small class="text-muted"><?= format_date($u['last_login'], 'M d, Y h:i A') ?></small>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="<?= BASE_URL ?>/admin/user_edit.php?id=<?= $u['user_row_id'] ?>" class="btn btn-outline-secondary" title="Edit User">
                      <i class="fas fa-edit"></i>
                    </a>

                    <!-- Toggle Active / Inactive Form -->
                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline" onsubmit="return confirm('Change account status?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= $u['user_row_id'] ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <button type="submit" class="btn btn-outline-warning" title="<?= ($u['status'] === 'active') ? 'Deactivate' : 'Activate' ?>">
                        <i class="fas <?= ($u['status'] === 'active') ? 'fa-ban' : 'fa-check' ?>"></i>
                      </button>
                    </form>

                    <!-- Delete User Form -->
                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently remove this user? Linked attendance and profile records will be deleted.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= $u['user_row_id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="btn btn-outline-danger" title="Delete User">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
                No user accounts matched the filter criteria.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
