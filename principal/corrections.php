<?php
// principal/corrections.php - Attendance Correction Approval System
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['principal', 'admin']);

$pageTitle = 'Attendance Correction Requests';
$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user']['id'];

// Handle Approval / Rejection
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('danger', 'Invalid session token.');
    } else {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? ''; // 'approve' or 'reject'
        $remarks = trim($_POST['review_remarks'] ?? '');

        if ($requestId > 0 && in_array($decision, ['approve', 'reject'], true)) {
            $stmt = $pdo->prepare("SELECT * FROM correction_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if ($request) {
                $pdo->beginTransaction();
                try {
                    $newStatus = ($decision === 'approve') ? 'approved' : 'rejected';

                    // If approved, update actual attendance record
                    if ($decision === 'approve') {
                        if ($request['attendance_type'] === 'daily') {
                            $updateAtt = $pdo->prepare("UPDATE daily_attendance SET status = ?, remarks = CONCAT(COALESCE(remarks,''), ' [Corrected by Principal]') WHERE id = ?");
                            $updateAtt->execute([$request['requested_status'], $request['attendance_id']]);
                        } else {
                            $updateAtt = $pdo->prepare("UPDATE subject_attendance SET status = ?, remarks = CONCAT(COALESCE(remarks,''), ' [Corrected by Principal]') WHERE id = ?");
                            $updateAtt->execute([$request['requested_status'], $request['attendance_id']]);
                        }

                        log_audit(
                            'ATTENDANCE_CORRECTION_APPROVED',
                            "Correction request #{$requestId} approved. Status changed from '{$request['original_status']}' to '{$request['requested_status']}' for Attendance ID {$request['attendance_id']} ({$request['attendance_type']}). Remarks: {$remarks}",
                            $currentUserId
                        );
                    } else {
                        log_audit(
                            'ATTENDANCE_CORRECTION_REJECTED',
                            "Correction request #{$requestId} rejected. Reason: {$remarks}",
                            $currentUserId
                        );
                    }

                    // Update request status
                    $reqUpdate = $pdo->prepare("UPDATE correction_requests SET status = ?, reviewed_by = ?, review_remarks = ?, reviewed_at = NOW() WHERE id = ?");
                    $reqUpdate->execute([$newStatus, $currentUserId, $remarks, $requestId]);

                    $pdo->commit();
                    flash_message('success', "Correction request #{$requestId} has been " . ucfirst($newStatus) . ".");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash_message('danger', "Database error: " . $e->getMessage());
                }
            } else {
                flash_message('warning', "Correction request already processed or not found.");
            }
        }
    }
    header('Location: ' . BASE_URL . '/principal/corrections.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$where = ["1=1"];
$params = [];
if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where[] = "cr.status = ?";
    $params[] = $statusFilter;
}
$whereClause = implode(' AND ', $where);

$query = "SELECT cr.*, 
                 s.student_id, s.roll_no, s.full_name as student_name,
                 c.name as class_name, sec.name as section_name,
                 u.user_id as teacher_code, t.full_name as teacher_name,
                 reviewer.user_id as reviewer_code,
                 CASE 
                   WHEN cr.attendance_type = 'daily' THEN da.attendance_date 
                   ELSE sa.attendance_date 
                 END as record_date
          FROM correction_requests cr
          JOIN students s ON cr.student_id = s.id
          JOIN classes c ON s.class_id = c.id
          JOIN sections sec ON s.section_id = sec.id
          JOIN users u ON cr.requested_by = u.id
          LEFT JOIN teachers t ON u.id = t.user_id
          LEFT JOIN users reviewer ON cr.reviewed_by = reviewer.id
          LEFT JOIN daily_attendance da ON (cr.attendance_type = 'daily' AND cr.attendance_id = da.id)
          LEFT JOIN subject_attendance sa ON (cr.attendance_type = 'subject' AND cr.attendance_id = sa.id)
          WHERE {$whereClause}
          ORDER BY cr.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-spell-check me-2 text-primary"></i> Attendance Correction Requests</h1>
    <p class="page-subtitle">Review teacher justification, verify supporting evidence, and approve or reject modifications.</p>
  </div>
</div>

<!-- Filter Tabs -->
<div class="card card-custom mb-4">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <ul class="nav nav-pills">
        <li class="nav-item">
          <a class="nav-link <?= ($statusFilter === 'all') ? 'active' : '' ?>" href="?status=all">All Requests</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($statusFilter === 'pending') ? 'active' : '' ?>" href="?status=pending">Pending Review</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($statusFilter === 'approved') ? 'active' : '' ?>" href="?status=approved">Approved</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($statusFilter === 'rejected') ? 'active' : '' ?>" href="?status=rejected">Rejected</a>
        </li>
      </ul>
      <span class="badge bg-secondary-subtle text-secondary"><?= count($requests) ?> Requests</span>
    </div>
  </div>
</div>

<!-- Requests Table -->
<div class="card card-custom">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Type & Date</th>
            <th>Requested By</th>
            <th>Status Change</th>
            <th>Teacher's Reason</th>
            <th>Decision Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($requests)): ?>
            <?php foreach ($requests as $r): ?>
              <tr>
                <td><strong>#<?= $r['id'] ?></strong></td>
                <td>
                  <strong><?= e($r['student_name']) ?></strong>
                  <small class="text-muted d-block"><?= e($r['student_id']) ?> &bull; <?= e($r['class_name']) ?> - <?= e($r['section_name']) ?></small>
                </td>
                <td>
                  <span class="badge bg-light text-dark border text-uppercase"><?= e($r['attendance_type']) ?></span>
                  <div class="small text-muted mt-1"><?= format_date($r['record_date']) ?></div>
                </td>
                <td>
                  <strong><?= e($r['teacher_name'] ?? $r['teacher_code']) ?></strong>
                  <small class="text-muted d-block"><?= format_date($r['created_at'], 'M d, h:i A') ?></small>
                </td>
                <td>
                  <?= status_badge($r['original_status']) ?>
                  <i class="fas fa-long-arrow-alt-right mx-1 text-muted"></i>
                  <?= status_badge($r['requested_status']) ?>
                </td>
                <td>
                  <div class="small text-dark p-2 bg-light rounded border text-wrap" style="max-width: 250px;">
                    <?= e($r['reason']) ?>
                  </div>
                </td>
                <td>
                  <?= status_badge($r['status']) ?>
                  <?php if (!empty($r['review_remarks'])): ?>
                    <small class="text-muted d-block mt-1"><em>"<?= e($r['review_remarks']) ?>"</em></small>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#decisionModal_<?= $r['id'] ?>">
                      <i class="fas fa-gavel me-1"></i> Review
                    </button>

                    <!-- Decision Modal -->
                    <div class="modal fade" id="decisionModal_<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog text-start">
                        <div class="modal-content">
                          <form method="POST" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title fw-bold">Correction Decision: Request #<?= $r['id'] ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <div class="alert alert-light border small mb-3">
                                <div><strong>Student:</strong> <?= e($r['student_name']) ?> (<?= e($r['student_id']) ?>)</div>
                                <div><strong>Date:</strong> <?= format_date($r['record_date']) ?></div>
                                <div><strong>Change:</strong> <?= ucfirst($r['original_status']) ?> &rarr; <span class="text-success fw-bold"><?= ucfirst($r['requested_status']) ?></span></div>
                                <div class="mt-2"><strong>Reason:</strong> <em>"<?= e($r['reason']) ?>"</em></div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label small fw-bold">Decision <span class="text-danger">*</span></label>
                                <select name="decision" class="form-select" required>
                                  <option value="approve">Approve Correction (Update Attendance Record)</option>
                                  <option value="reject">Reject Request</option>
                                </select>
                              </div>

                              <div class="mb-3">
                                <label class="form-label small fw-bold">Principal Remarks</label>
                                <textarea name="review_remarks" class="form-control" rows="2" placeholder="e.g. Verified with clinical excuse note / transport log..."></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary">Submit Decision</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <small class="text-muted">Completed</small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">No attendance correction requests recorded.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
