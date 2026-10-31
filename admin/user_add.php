<?php
// admin/user_add.php - Create New User (Principal, Teacher, Student)
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Add New User';
$pdo = get_db_connection();

$activeYear = get_active_academic_year();
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

$selectedRole = $_GET['role'] ?? 'student';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security session token. Please try again.';
    } else {
        $role = trim($_POST['role'] ?? 'student');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $gender = trim($_POST['gender'] ?? 'male');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $address = trim($_POST['address'] ?? '');

        if (empty($fullName)) {
            $errors[] = 'Full Name is required.';
        }
        if (empty($password) || strlen($password) < 4) {
            $errors[] = 'Password must be at least 4 characters.';
        }

        // Process based on role
        if ($role === 'student') {
            $studentId = trim($_POST['student_id'] ?? '');
            if (empty($studentId)) {
                $studentId = generate_student_id();
            }
            $admissionNo = trim($_POST['admission_no'] ?? '');
            $rollNo = (int)($_POST['roll_no'] ?? 0);
            $classId = (int)($_POST['class_id'] ?? 0);
            $sectionId = (int)($_POST['section_id'] ?? 0);
            $yearId = (int)($_POST['academic_year_id'] ?? ($activeYear['id'] ?? 1));
            $admissionDate = !empty($_POST['admission_date']) ? $_POST['admission_date'] : date('Y-m-d');

            if (empty($admissionNo)) $errors[] = 'Admission Number is required.';
            if ($rollNo <= 0) $errors[] = 'Valid Roll Number is required.';
            if ($classId <= 0) $errors[] = 'Please select a Class.';
            if ($sectionId <= 0) $errors[] = 'Please select a Section.';

            // Check duplicate user_id
            $check = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
            $check->execute([$studentId]);
            if ($check->fetch()) {
                $errors[] = "Student ID '{$studentId}' is already registered.";
            }

            // Check duplicate roll in same class & section
            $checkRoll = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND section_id = ? AND roll_no = ?");
            $checkRoll->execute([$classId, $sectionId, $rollNo]);
            if ($checkRoll->fetch()) {
                $errors[] = "Roll number {$rollNo} is already assigned in this class and section.";
            }

            if (empty($errors)) {
                $photoPath = null;
                if (!empty($_FILES['photo']['name'])) {
                    $photoPath = handle_profile_upload($_FILES['photo']);
                }

                $pdo->beginTransaction();
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, password_hash, role, status) VALUES (?, ?, 'student', 'active')");
                    $stmt->execute([$studentId, $hash]);
                    $userId = (int)$pdo->lastInsertRowid(); // or lastInsertId()

                    $stmt2 = $pdo->prepare("INSERT INTO students (user_id, student_id, admission_no, roll_no, full_name, photo, dob, gender, address, class_id, section_id, academic_year_id, admission_date) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $studentId, $admissionNo, $rollNo, $fullName, $photoPath, $dob, $gender, $address, $classId, $sectionId, $yearId, $admissionDate]);

                    $pdo->commit();
                    log_audit('STUDENT_CREATED', "New student created: {$fullName} ({$studentId}).");
                    flash_message('success', "Student {$fullName} ({$studentId}) successfully registered.");
                    header('Location: ' . BASE_URL . '/admin/users.php?role=student');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }

        } elseif ($role === 'teacher') {
            $teacherId = trim($_POST['teacher_id'] ?? '');
            if (empty($teacherId)) {
                $teacherId = generate_teacher_id();
            }
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $qualification = trim($_POST['qualification'] ?? '');
            $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');
            $classId = (int)($_POST['assigned_class_id'] ?? 0);
            $sectionId = (int)($_POST['assigned_section_id'] ?? 0);
            $subjectId = (int)($_POST['assigned_subject_id'] ?? 0);
            $isClassTeacher = !empty($_POST['is_class_teacher']) ? 1 : 0;

            if (empty($email)) $errors[] = 'Teacher Email Address is required.';
            if (empty($mobile)) $errors[] = 'Teacher Mobile Number is required.';

            $check = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
            $check->execute([$teacherId]);
            if ($check->fetch()) {
                $errors[] = "Teacher ID '{$teacherId}' already exists.";
            }

            if (empty($errors)) {
                $photoPath = null;
                if (!empty($_FILES['photo']['name'])) {
                    $photoPath = handle_profile_upload($_FILES['photo']);
                }

                $pdo->beginTransaction();
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, password_hash, role, status) VALUES (?, ?, 'teacher', 'active')");
                    $stmt->execute([$teacherId, $hash]);
                    $userId = (int)$pdo->lastInsertId();

                    $stmt2 = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, full_name, photo, dob, gender, email, mobile, qualification, address, joining_date)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $teacherId, $fullName, $photoPath, $dob, $gender, $email, $mobile, $qualification, $address, $joiningDate]);
                    $teacherRowId = (int)$pdo->lastInsertId();

                    // Assign to Class & Subject if selected
                    if ($classId > 0 && $sectionId > 0) {
                        $yearId = $activeYear['id'] ?? 1;
                        $stmt3 = $pdo->prepare("INSERT INTO teacher_assignments (teacher_id, class_id, section_id, subject_id, academic_year_id, is_class_teacher)
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt3->execute([$teacherRowId, $classId, $sectionId, ($subjectId > 0 ? $subjectId : null), $yearId, $isClassTeacher]);
                    }

                    $pdo->commit();
                    log_audit('TEACHER_CREATED', "New teacher created: {$fullName} ({$teacherId}).");
                    flash_message('success', "Teacher {$fullName} ({$teacherId}) successfully registered.");
                    header('Location: ' . BASE_URL . '/admin/users.php?role=teacher');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }

        } elseif ($role === 'principal') {
            $principalId = trim($_POST['principal_id'] ?? '');
            if (empty($principalId)) {
                $principalId = generate_principal_id();
            }
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');

            if (empty($email)) $errors[] = 'Principal Email Address is required.';

            $check = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
            $check->execute([$principalId]);
            if ($check->fetch()) {
                $errors[] = "Principal ID '{$principalId}' already exists.";
            }

            if (empty($errors)) {
                $photoPath = null;
                if (!empty($_FILES['photo']['name'])) {
                    $photoPath = handle_profile_upload($_FILES['photo']);
                }

                $pdo->beginTransaction();
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, password_hash, role, status) VALUES (?, ?, 'principal', 'active')");
                    $stmt->execute([$principalId, $hash]);
                    $userId = (int)$pdo->lastInsertId();

                    $stmt2 = $pdo->prepare("INSERT INTO principals (user_id, principal_id, full_name, photo, dob, gender, email, mobile, address, joining_date)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt2->execute([$userId, $principalId, $fullName, $photoPath, $dob, $gender, $email, $mobile, $address, $joiningDate]);

                    $pdo->commit();
                    log_audit('PRINCIPAL_CREATED', "New principal created: {$fullName} ({$principalId}).");
                    flash_message('success', "Principal {$fullName} ({$principalId}) successfully registered.");
                    header('Location: ' . BASE_URL . '/admin/users.php?role=principal');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fas fa-user-plus me-2 text-primary"></i> Register New User</h1>
    <p class="page-subtitle">Add a new Principal, Teacher, or Student to the system.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back to User List
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger shadow-sm">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card card-custom">
  <div class="card-header bg-light">
    <!-- Role Switcher Tabs -->
    <ul class="nav nav-pills card-header-pills" id="rolePills">
      <li class="nav-item">
        <a class="nav-link <?= ($selectedRole === 'student') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/user_add.php?role=student">
          <i class="fas fa-user-graduate me-1"></i> Add Student
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($selectedRole === 'teacher') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/user_add.php?role=teacher">
          <i class="fas fa-chalkboard-teacher me-1"></i> Add Teacher
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($selectedRole === 'principal') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/user_add.php?role=principal">
          <i class="fas fa-user-tie me-1"></i> Add Principal
        </a>
      </li>
    </ul>
  </div>

  <div class="card-body p-4">
    <form method="POST" action="<?= BASE_URL ?>/admin/user_add.php?role=<?= e($selectedRole) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="role" value="<?= e($selectedRole) ?>">

      <!-- Common Fields -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Emily Watson" required value="<?= e($_POST['full_name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small fw-bold">Initial Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" name="password" id="userPass" class="form-control" placeholder="Minimum 4 characters" required value="<?= e($_POST['password'] ?? 'password123') ?>">
            <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="userPass">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="form-text small">Default pre-filled: <code>password123</code></div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Gender</label>
          <select name="gender" class="form-select">
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Date of Birth</label>
          <input type="date" name="dob" class="form-control" value="<?= e($_POST['dob'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label small fw-bold">Profile Picture</label>
          <input type="file" name="photo" class="form-control" accept="image/*">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-bold">Address</label>
        <textarea name="address" class="form-control" rows="2" placeholder="Residential street address..."><?= e($_POST['address'] ?? '') ?></textarea>
      </div>

      <hr class="my-4">

      <!-- STUDENT SPECIFIC FIELDS -->
      <?php if ($selectedRole === 'student'): ?>
        <h5 class="fw-bold text-primary mb-3"><i class="fas fa-id-card me-2"></i> Student Enrollment Details</h5>
        <div class="alert alert-info py-2 px-3 small mb-3">
          <i class="fas fa-info-circle me-1"></i> As per system guidelines, students do not require an email address or mobile phone number.
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Student ID (Auto-Generated)</label>
            <input type="text" name="student_id" class="form-control bg-light" value="<?= e($_POST['student_id'] ?? generate_student_id()) ?>">
            <div class="form-text small">Used by student to sign in.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Admission Number <span class="text-danger">*</span></label>
            <input type="text" name="admission_no" class="form-control" placeholder="e.g. ADM-2026-042" required value="<?= e($_POST['admission_no'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Roll Number <span class="text-danger">*</span></label>
            <input type="number" name="roll_no" class="form-control" min="1" placeholder="e.g. 15" required value="<?= e($_POST['roll_no'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Class / Standard / Semester <span class="text-danger">*</span></label>
            <select name="class_id" id="classSelect" class="form-select" required>
              <option value="">-- Select Class --</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= ucfirst($c['type']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Section <span class="text-danger">*</span></label>
            <select name="section_id" id="sectionSelect" class="form-select" required>
              <option value="">-- Select Class First --</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Academic Year</label>
            <select name="academic_year_id" class="form-select">
              <?php foreach ($academicYears as $y): ?>
                <option value="<?= $y['id'] ?>" <?= ($y['is_current'] == 1) ? 'selected' : '' ?>>
                  <?= e($y['name']) ?> <?= ($y['is_current'] == 1) ? '(Current)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

      <!-- TEACHER SPECIFIC FIELDS -->
      <?php elseif ($selectedRole === 'teacher'): ?>
        <h5 class="fw-bold text-primary mb-3"><i class="fas fa-id-badge me-2"></i> Faculty Details & Class Assignment</h5>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Teacher ID (Auto-Generated)</label>
            <input type="text" name="teacher_id" class="form-control bg-light" value="<?= e($_POST['teacher_id'] ?? generate_teacher_id()) ?>">
            <div class="form-text small">Used by teacher to log in.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="teacher@school.com" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Mobile Phone <span class="text-danger">*</span></label>
            <input type="tel" name="mobile" class="form-control" placeholder="+1 (555) 000-0000" required value="<?= e($_POST['mobile'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Academic Qualification</label>
            <input type="text" name="qualification" class="form-control" placeholder="e.g. M.Sc. Mathematics, B.Ed." value="<?= e($_POST['qualification'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label small fw-bold">Joining Date</label>
            <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="card p-3 bg-light border mb-3">
          <h6 class="fw-bold text-dark mb-2"><i class="fas fa-chalkboard me-1 text-primary"></i> Primary Class & Subject Assignment (Optional)</h6>
          <div class="row g-2">
            <div class="col-md-4">
              <select name="assigned_class_id" id="classSelect" class="form-select form-select-sm">
                <option value="">-- Assign Class --</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="assigned_section_id" id="sectionSelect" class="form-select form-select-sm">
                <option value="">-- Section --</option>
              </select>
            </div>
            <div class="col-md-3">
              <select name="assigned_subject_id" id="subjectSelect" class="form-select form-select-sm">
                <option value="">-- Subject --</option>
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_class_teacher" id="isClassTeacher" value="1">
                <label class="form-check-label small" for="isClassTeacher">Class Teacher</label>
              </div>
            </div>
          </div>
        </div>

      <!-- PRINCIPAL SPECIFIC FIELDS -->
      <?php elseif ($selectedRole === 'principal'): ?>
        <h5 class="fw-bold text-primary mb-3"><i class="fas fa-user-tie me-2"></i> Principal Contact & Institutional Details</h5>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label small fw-bold">Principal ID (Auto-Generated)</label>
            <input type="text" name="principal_id" class="form-control bg-light" value="<?= e($_POST['principal_id'] ?? generate_principal_id()) ?>">
            <div class="form-text small">Used for principal login.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="principal@school.com" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label small fw-bold">Mobile Phone</label>
            <input type="tel" name="mobile" class="form-control" placeholder="+1 (555) 000-0000" value="<?= e($_POST['mobile'] ?? '') ?>">
          </div>
        </div>
      <?php endif; ?>

      <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-light border">Cancel</a>
        <button type="submit" class="btn btn-primary px-4 fw-semibold">
          <i class="fas fa-check-circle me-1"></i> Create <?= ucfirst($selectedRole) ?> Account
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
