<?php
// admin/settings.php - System & School Settings
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Institutional Settings';
$pdo = get_db_connection();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid security token.');
    } else {
        $schoolName = trim($_POST['school_name'] ?? '');
        $schoolTagline = trim($_POST['school_tagline'] ?? '');
        $schoolEmail = trim($_POST['school_email'] ?? '');
        $schoolPhone = trim($_POST['school_phone'] ?? '');
        $schoolAddress = trim($_POST['school_address'] ?? '');
        $threshold = (float)($_POST['attendance_threshold'] ?? 75);

        if ($threshold < 1 || $threshold > 100) {
            $threshold = 75;
        }

        update_setting('school_name', $schoolName);
        update_setting('school_tagline', $schoolTagline);
        update_setting('school_email', $schoolEmail);
        update_setting('school_phone', $schoolPhone);
        update_setting('school_address', $schoolAddress);
        update_setting('attendance_threshold', (string)$threshold);

        log_audit('SETTINGS_UPDATED', "School configuration and attendance threshold ({$threshold}%) updated.");
        flash_message('success', 'School configuration updated successfully.');
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit;
    }
}

$schoolName = get_setting('school_name', 'St. Xavier International Academy & College');
$schoolTagline = get_setting('school_tagline', 'Excellence in Digital Education');
$schoolEmail = get_setting('school_email', 'admin@stxavier.edu');
$schoolPhone = get_setting('school_phone', '+1 (555) 019-2834');
$schoolAddress = get_setting('school_address', '100 University Avenue, CA');
$threshold = get_setting('attendance_threshold', '75');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-cog me-2 text-primary"></i> Institutional Settings</h1>
    <p class="page-subtitle">Configure school identity, attendance compliance thresholds, and database maintenance.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card card-custom">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-school me-2 text-primary"></i> School Profile & Configuration</h5>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label small fw-bold">Institution / School Name <span class="text-danger">*</span></label>
            <input type="text" name="school_name" class="form-control" value="<?= e($schoolName) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Motto / Tagline</label>
            <input type="text" name="school_tagline" class="form-control" value="<?= e($schoolTagline) ?>">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Official Email</label>
              <input type="email" name="school_email" class="form-control" value="<?= e($schoolEmail) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Official Phone</label>
              <input type="text" name="school_phone" class="form-control" value="<?= e($schoolPhone) ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Campus Address</label>
            <textarea name="school_address" class="form-control" rows="2"><?= e($schoolAddress) ?></textarea>
          </div>

          <hr class="my-4">

          <h5 class="fw-bold text-dark mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Attendance Compliance Rules</h5>

          <div class="mb-4">
            <label class="form-label small fw-bold">Minimum Attendance Threshold (%) <span class="text-danger">*</span></label>
            <div class="input-group" style="max-width: 200px;">
              <input type="number" step="0.5" min="1" max="100" name="attendance_threshold" class="form-control" value="<?= e($threshold) ?>" required>
              <span class="input-group-text">%</span>
            </div>
            <div class="form-text small">Default: 75%. Students below this threshold will be flagged in Defaulter lists and reports.</div>
          </div>

          <button type="submit" class="btn btn-primary px-4 fw-semibold">
            <i class="fas fa-save me-1"></i> Save Configuration
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Database Backup Box -->
    <div class="card card-custom mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-database me-2 text-primary"></i> Database Backup</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small">Generate and download an immediate SQL backup of your entire MySQL database (<code>school_attendance_db</code>).</p>
        <div class="alert alert-light border small py-2 mb-3">
          <i class="fas fa-info-circle text-primary me-1"></i> Includes all 19 tables, student records, assignments, and attendance logs.
        </div>
        <a href="<?= BASE_URL ?>/admin/backup.php" class="btn btn-outline-primary w-100 fw-semibold">
          <i class="fas fa-download me-1"></i> Download Database SQL Dump
        </a>
      </div>
    </div>

    <!-- School Logo Info -->
    <div class="card card-custom text-center p-3">
      <h6 class="fw-bold text-dark mb-2">School Crest</h6>
      <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo" class="mx-auto my-2" style="width: 80px; height: 80px;">
      <div class="text-muted small">Official vector emblem displayed across headers, login portal, and printed reports.</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
