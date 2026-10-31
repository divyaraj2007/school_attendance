<?php
// principal/announcements.php - School Announcements Management
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'School Announcements';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $target = in_array($_POST['target_role'] ?? '', ['all', 'teacher', 'student']) ? $_POST['target_role'] : 'all';

            if (!empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, target_role, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, $target, $currentUserId]);
                log_audit('ANNOUNCEMENT_CREATED', "New announcement published: '{$title}' target: {$target}.", $currentUserId);
                flash_message('success', "Announcement published successfully.");
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                log_audit('ANNOUNCEMENT_DELETED', "Deleted announcement ID {$id}.", $currentUserId);
                flash_message('success', "Announcement removed.");
            }
        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
                $stmt->execute([$id]);
                flash_message('success', "Announcement visibility toggled.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/principal/announcements.php');
    exit;
}

$announcements = $pdo->query("SELECT a.*, u.user_id as author_code 
                              FROM announcements a
                              JOIN users u ON a.created_by = u.id
                              ORDER BY a.id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-bullhorn me-2 text-primary"></i> School Announcements & Notices</h1>
    <p class="page-subtitle">Publish official circulars, exam schedules, and holiday notices for faculty and students.</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
    <i class="fas fa-plus me-1"></i> New Announcement
  </button>
</div>

<div class="row g-4">
  <div class="col-12">
    <div class="card card-custom">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title">Notice Board Entries</h5>
        <span class="badge bg-primary-subtle text-primary"><?= count($announcements) ?> Circulars</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Title & Content</th>
                <th>Audience</th>
                <th>Author</th>
                <th>Date Published</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $ann): ?>
                  <tr>
                    <td style="max-width: 400px;">
                      <div class="fw-bold text-dark fs-6"><?= e($ann['title']) ?></div>
                      <div class="text-muted small text-truncate mt-1"><?= e($ann['content']) ?></div>
                    </td>
                    <td>
                      <span class="badge bg-info-subtle text-info-emphasis border border-info text-uppercase">
                        <?= e($ann['target_role']) ?>
                      </span>
                    </td>
                    <td><code><?= e($ann['author_code']) ?></code></td>
                    <td><small class="text-muted"><?= format_date($ann['created_at'], 'M d, Y h:i A') ?></small></td>
                    <td>
                      <?= ($ann['is_active'] == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>' ?>
                    </td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <form method="POST" action="" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle_active">
                          <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                          <button type="submit" class="btn btn-outline-secondary" title="Toggle visibility">
                            <i class="fas <?= ($ann['is_active'] == 1) ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                          </button>
                        </form>

                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No announcements posted yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: New Announcement -->
<div class="modal fade" id="createNoticeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Publish New Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Announcement Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Schedule for Sports Week" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Target Audience</label>
            <select name="target_role" class="form-select">
              <option value="all">Everyone (All Students & Teachers)</option>
              <option value="teacher">Teachers / Faculty Only</option>
              <option value="student">Students Only</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Notice Content <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control" rows="4" placeholder="Enter full announcement details..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Publish Circular</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
