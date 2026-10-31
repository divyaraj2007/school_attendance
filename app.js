const path = require("path");
const express = require("express");
const session = require("express-session");
const bcrypt = require("bcryptjs");
const { db, initDb } = require("./db");

initDb();

const app = express();
const PORT = process.env.PORT || 3000;
const DEFAULT_TEACHER_PASSWORD = "123456";

app.set("view engine", "ejs");
app.set("views", path.join(__dirname, "views"));

app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, "public")));
app.use(
  session({
    secret: "school-attendance-secret",
    resave: false,
    saveUninitialized: false,
  })
);

app.use((req, res, next) => {
  res.locals.currentUser = req.session.user || null;
  next();
});

app.get("/health", (_req, res) => {
  // Allow launcher pages opened from file:// or another local origin to probe health.
  res.set("Access-Control-Allow-Origin", "*");
  res.json({ ok: true, service: "school-attendance" });
});

function normalizeText(value) {
  return String(value || "").trim();
}

function normalizeEmail(value) {
  return normalizeText(value).toLowerCase();
}

function toPositiveInt(value) {
  const parsed = Number(value);
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function isIsoDate(value) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ""));
}

function getTodayLocalDate() {
  const now = new Date();
  const offsetMs = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - offsetMs).toISOString().slice(0, 10);
}

function logoutWithError(req, res, message) {
  req.session.destroy(() => {
    res.redirect(`/signin?error=${encodeURIComponent(message)}`);
  });
}

function isAuthenticated(req, res, next) {
  if (!req.session.user) {
    return res.redirect("/signin?error=Please login first");
  }
  next();
}

function requireRole(role) {
  return (req, res, next) => {
    if (!req.session.user) {
      return res.redirect("/signin?error=Please login first");
    }
    if (req.session.user.role !== role) {
      return res.redirect("/dashboard?error=Access denied for this page");
    }
    next();
  };
}

app.get("/", (req, res) => {
  if (req.session.user) {
    return res.redirect("/dashboard");
  }
  res.redirect("/signin");
});

app.get("/signin", (req, res) => {
  if (req.session.user) return res.redirect("/dashboard");
  res.render("login", {
    error: req.query.error || "",
    success: req.query.success || "",
  });
});

app.get("/login", (_req, res) => {
  res.redirect("/signin");
});

app.get("/signup", (req, res) => {
  if (req.session.user) return res.redirect("/dashboard");

  const classes = db.prepare("SELECT id, standard_name FROM classes ORDER BY standard_name").all();

  res.render("signup", {
    classes,
    error: req.query.error || "",
    success: req.query.success || "",
  });
});

app.post("/signup", (req, res) => {
  const name = normalizeText(req.body.name);
  const rollNo = toPositiveInt(req.body.roll_no);
  const classId = toPositiveInt(req.body.class_id);
  const email = normalizeEmail(req.body.email);
  const password = String(req.body.password || "");

  if (!name || !rollNo || !classId || !email || !password) {
    return res.redirect("/signup?error=All fields are required");
  }
  if (password.length < 4) {
    return res.redirect("/signup?error=Password must be at least 4 characters");
  }

  const classExists = db.prepare("SELECT id FROM classes WHERE id = ?").get(classId);
  if (!classExists) {
    return res.redirect("/signup?error=Selected class does not exist");
  }

  const userExists = db.prepare("SELECT id FROM users WHERE email = ?").get(email);
  if (userExists) {
    return res.redirect("/signup?error=Email already registered");
  }

  const hash = bcrypt.hashSync(password, 10);
  const tx = db.transaction(() => {
    const userResult = db
      .prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')")
      .run(name, email, hash);
    db.prepare("INSERT INTO students (user_id, roll_no, class_id) VALUES (?, ?, ?)")
      .run(userResult.lastInsertRowid, rollNo, classId);
  });

  try {
    tx();
    res.redirect("/signin?success=Account created. Please sign in.");
  } catch (err) {
    if (String(err.message).includes("UNIQUE")) {
      return res.redirect("/signup?error=Roll number already used in this class");
    }
    console.error("Signup failed:", err.message);
    res.redirect("/signup?error=Failed to create account");
  }
});

app.post("/login", (req, res) => {
  const email = normalizeEmail(req.body.email);
  const password = String(req.body.password || "");
  const role = normalizeText(req.body.role).toLowerCase();

  if (!email || !password) {
    return res.redirect("/signin?error=Please enter valid login details");
  }

  const user = db
    .prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ?")
    .get(email);

  if (!user) {
    return res.redirect("/signin?error=Account not found");
  }

  const valid = bcrypt.compareSync(password, user.password_hash);
  if (!valid) {
    return res.redirect("/signin?error=Invalid credentials");
  }

  if (role && ["principal", "teacher", "student"].includes(role) && user.role !== role) {
    return res.redirect("/signin?error=Selected role does not match this account");
  }

  if (user.role === "teacher") {
    const teacherProfile = db.prepare("SELECT id FROM teachers WHERE user_id = ?").get(user.id);
    if (!teacherProfile) {
      return res.redirect(
        "/signin?error=Teacher profile is missing. Ask principal to re-add this teacher."
      );
    }
  }

  if (user.role === "student") {
    const studentProfile = db.prepare("SELECT id FROM students WHERE user_id = ?").get(user.id);
    if (!studentProfile) {
      return res.redirect(
        "/signin?error=Student profile is missing. Ask principal to re-add this student."
      );
    }
  }

  req.session.user = {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
  };
  res.redirect("/dashboard");
});

app.post("/logout", isAuthenticated, (req, res) => {
  req.session.destroy(() => res.redirect("/signin?success=Logged out"));
});

app.get("/dashboard", isAuthenticated, (req, res) => {
  const errorText = normalizeText(req.query.error);
  const errorQuery = errorText ? `?error=${encodeURIComponent(errorText)}` : "";
  const role = req.session.user.role;
  if (role === "principal") return res.redirect(`/principal${errorQuery}`);
  if (role === "teacher") return res.redirect(`/teacher${errorQuery}`);
  return res.redirect(`/student${errorQuery}`);
});

app.get("/principal", isAuthenticated, requireRole("principal"), (req, res) => {
  const classes = db.prepare("SELECT * FROM classes ORDER BY standard_name").all();
  const teachers = db
    .prepare(
      `SELECT t.id, u.name, u.email, t.subject, c.standard_name
       FROM teachers t
       JOIN users u ON t.user_id = u.id
       JOIN classes c ON t.class_id = c.id
       ORDER BY c.standard_name, u.name`
    )
    .all();
  const students = db
    .prepare(
      `SELECT s.id, u.name, u.email, s.roll_no, c.standard_name
       FROM students s
       JOIN users u ON s.user_id = u.id
       JOIN classes c ON s.class_id = c.id
       ORDER BY c.standard_name, s.roll_no`
    )
    .all();

  const stats = {
    classCount: classes.length,
    teacherCount: teachers.length,
    studentCount: students.length,
  };

  res.render("principal", {
    classes,
    teachers,
    students,
    stats,
    error: req.query.error || "",
    success: req.query.success || "",
  });
});

app.post("/principal/classes/add", isAuthenticated, requireRole("principal"), (req, res) => {
  const standardName = normalizeText(req.body.standard_name);
  if (!standardName) return res.redirect("/principal?error=Class name is required");
  if (standardName.length > 60) {
    return res.redirect("/principal?error=Class name is too long");
  }

  try {
    db.prepare("INSERT INTO classes (standard_name) VALUES (?)").run(standardName);
    res.redirect("/principal?success=Class added");
  } catch (err) {
    res.redirect("/principal?error=Class already exists");
  }
});

app.post("/principal/teachers/add", isAuthenticated, requireRole("principal"), (req, res) => {
  const name = normalizeText(req.body.name);
  const subject = normalizeText(req.body.subject);
  const email = normalizeEmail(req.body.email);
  const classId = toPositiveInt(req.body.class_id);

  if (!name || !subject || !email || !classId) {
    return res.redirect("/principal?error=All teacher fields are required");
  }

  const classExists = db.prepare("SELECT id FROM classes WHERE id = ?").get(classId);
  if (!classExists) return res.redirect("/principal?error=Selected class does not exist");

  const userExists = db.prepare("SELECT id FROM users WHERE email = ?").get(email);
  if (userExists) return res.redirect("/principal?error=Email already in use");

  const hash = bcrypt.hashSync(DEFAULT_TEACHER_PASSWORD, 10);
  const tx = db.transaction(() => {
    const userResult = db
      .prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'teacher')")
      .run(name, email, hash);
    db.prepare("INSERT INTO teachers (user_id, subject, class_id) VALUES (?, ?, ?)")
      .run(userResult.lastInsertRowid, subject, classId);
  });

  try {
    tx();
    res.redirect("/principal?success=Teacher added. Default password is 123456");
  } catch (err) {
    res.redirect("/principal?error=Failed to add teacher");
  }
});

app.post("/principal/students/add", isAuthenticated, requireRole("principal"), (req, res) => {
  const name = normalizeText(req.body.name);
  const rollNo = toPositiveInt(req.body.roll_no);
  const classId = toPositiveInt(req.body.class_id);
  const email = normalizeEmail(req.body.email);
  const password = String(req.body.password || "");

  if (!name || !rollNo || !classId || !email || !password) {
    return res.redirect("/principal?error=All student fields are required");
  }
  if (password.length < 4) {
    return res.redirect("/principal?error=Password must be at least 4 characters");
  }

  const classExists = db.prepare("SELECT id FROM classes WHERE id = ?").get(classId);
  if (!classExists) return res.redirect("/principal?error=Selected class does not exist");

  const userExists = db.prepare("SELECT id FROM users WHERE email = ?").get(email);
  if (userExists) return res.redirect("/principal?error=Email already in use");

  const hash = bcrypt.hashSync(password, 10);
  const tx = db.transaction(() => {
    const userResult = db
      .prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')")
      .run(name, email, hash);
    db.prepare("INSERT INTO students (user_id, roll_no, class_id) VALUES (?, ?, ?)")
      .run(userResult.lastInsertRowid, rollNo, classId);
  });

  try {
    tx();
    res.redirect("/principal?success=Student added");
  } catch (err) {
    if (String(err.message).includes("UNIQUE")) {
      return res.redirect("/principal?error=Roll number already used in this class");
    }
    res.redirect("/principal?error=Failed to add student");
  }
});

app.post("/principal/teachers/:id/delete", isAuthenticated, requireRole("principal"), (req, res) => {
  const teacherId = toPositiveInt(req.params.id);
  if (!teacherId) return res.redirect("/principal?error=Invalid teacher id");

  const teacher = db.prepare("SELECT user_id FROM teachers WHERE id = ?").get(teacherId);
  if (!teacher) return res.redirect("/principal?success=Teacher already removed");

  const tx = db.transaction(() => {
    db.prepare("DELETE FROM attendance WHERE marked_by = ?").run(teacher.user_id);
    db.prepare("DELETE FROM users WHERE id = ?").run(teacher.user_id);
  });

  try {
    tx();
    res.redirect("/principal?success=Teacher deleted");
  } catch (err) {
    console.error("Teacher delete failed:", err.message);
    res.redirect("/principal?error=Failed to delete teacher");
  }
});

app.post("/principal/students/:id/delete", isAuthenticated, requireRole("principal"), (req, res) => {
  const studentId = toPositiveInt(req.params.id);
  if (!studentId) return res.redirect("/principal?error=Invalid student id");

  const student = db
    .prepare("SELECT id, user_id FROM students WHERE id = ?")
    .get(studentId);
  if (!student) return res.redirect("/principal?success=Student already removed");

  const tx = db.transaction(() => {
    db.prepare("DELETE FROM attendance WHERE student_id = ?").run(student.id);
    db.prepare("DELETE FROM users WHERE id = ?").run(student.user_id);
  });

  try {
    tx();
    res.redirect("/principal?success=Student deleted");
  } catch (err) {
    console.error("Student delete failed:", err.message);
    res.redirect("/principal?error=Failed to delete student");
  }
});

app.post("/principal/classes/:id/delete", isAuthenticated, requireRole("principal"), (req, res) => {
  const classId = toPositiveInt(req.params.id);
  if (!classId) return res.redirect("/principal?error=Invalid class id");

  const classRow = db.prepare("SELECT id FROM classes WHERE id = ?").get(classId);
  if (!classRow) return res.redirect("/principal?success=Class already removed");

  const deleteClassTx = db.transaction(() => {
    const teacherUsers = db
      .prepare("SELECT user_id FROM teachers WHERE class_id = ?")
      .all(classId);
    const studentUsers = db
      .prepare("SELECT user_id FROM students WHERE class_id = ?")
      .all(classId);

    db.prepare("DELETE FROM attendance WHERE class_id = ?").run(classId);

    const deleteByMarker = db.prepare("DELETE FROM attendance WHERE marked_by = ?");
    const deleteUser = db.prepare("DELETE FROM users WHERE id = ?");
    teacherUsers.forEach((row) => deleteByMarker.run(row.user_id));
    teacherUsers.forEach((row) => deleteUser.run(row.user_id));
    studentUsers.forEach((row) => deleteUser.run(row.user_id));

    db.prepare("DELETE FROM classes WHERE id = ?").run(classId);
  });

  try {
    deleteClassTx();
    res.redirect("/principal?success=Class deleted with linked records");
  } catch (err) {
    console.error("Class delete failed:", err.message);
    res.redirect("/principal?error=Failed to delete class");
  }
});

app.get("/principal/reports", isAuthenticated, requireRole("principal"), (req, res) => {
  const classId = toPositiveInt(req.query.class_id) || "";
  const month = /^\d{4}-\d{2}$/.test(String(req.query.month || ""))
    ? String(req.query.month)
    : "";

  const classes = db.prepare("SELECT id, standard_name FROM classes ORDER BY standard_name").all();

  let filters = [];
  let params = [];

  if (classId) {
    filters.push("a.class_id = ?");
    params.push(classId);
  }
  if (month) {
    filters.push("substr(a.date, 1, 7) = ?");
    params.push(month);
  }

  const whereClause = filters.length ? `WHERE ${filters.join(" AND ")}` : "";
  const rows = db
    .prepare(
      `SELECT a.date, c.standard_name, s.roll_no, u.name AS student_name, a.status
       FROM attendance a
       JOIN students s ON a.student_id = s.id
       JOIN users u ON s.user_id = u.id
       JOIN classes c ON a.class_id = c.id
       ${whereClause}
       ORDER BY a.date DESC, c.standard_name, s.roll_no`
    )
    .all(...params);

  const summary = db
    .prepare(
      `SELECT c.standard_name,
              COUNT(CASE WHEN a.status = 'present' THEN 1 END) AS present_count,
              COUNT(CASE WHEN a.status = 'absent' THEN 1 END) AS absent_count,
              COUNT(*) AS total
       FROM attendance a
       JOIN classes c ON a.class_id = c.id
       ${whereClause}
       GROUP BY c.id
       ORDER BY c.standard_name`
    )
    .all(...params);

  res.render("principal-reports", { rows, classes, classId, month, summary });
});

app.get("/teacher", isAuthenticated, requireRole("teacher"), (req, res) => {
  const teacher = db
    .prepare(
      `SELECT t.id, t.class_id, t.subject, c.standard_name
       FROM teachers t
       JOIN classes c ON t.class_id = c.id
       WHERE t.user_id = ?`
    )
    .get(req.session.user.id);

  if (!teacher) {
    return logoutWithError(
      req,
      res,
      "Teacher profile not found. Ask principal to assign your class again."
    );
  }

  const selectedDate = isIsoDate(req.query.date) ? req.query.date : getTodayLocalDate();
  const students = db
    .prepare(
      `SELECT s.id, s.roll_no, u.name
       FROM students s
       JOIN users u ON s.user_id = u.id
       WHERE s.class_id = ?
       ORDER BY s.roll_no`
    )
    .all(teacher.class_id);

  const attendanceRows = db
    .prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?")
    .all(teacher.class_id, selectedDate);

  const attendanceMap = {};
  attendanceRows.forEach((row) => {
    attendanceMap[row.student_id] = row.status;
  });

  const records = db
    .prepare(
      `SELECT a.date, s.roll_no, u.name AS student_name, a.status
       FROM attendance a
       JOIN students s ON a.student_id = s.id
       JOIN users u ON s.user_id = u.id
       WHERE a.class_id = ?
       ORDER BY a.date DESC, s.roll_no
       LIMIT 200`
    )
    .all(teacher.class_id);

  res.render("teacher", {
    teacher,
    students,
    selectedDate,
    attendanceMap,
    records,
    success: req.query.success || "",
    error: req.query.error || "",
  });
});

app.get("/teacher/dashboard", isAuthenticated, requireRole("teacher"), (_req, res) => {
  res.redirect("/teacher");
});

app.get("/teacher/deskboard", isAuthenticated, requireRole("teacher"), (_req, res) => {
  res.redirect("/teacher");
});

app.post("/teacher/attendance/save", isAuthenticated, requireRole("teacher"), (req, res) => {
  const teacher = db
    .prepare("SELECT class_id FROM teachers WHERE user_id = ?")
    .get(req.session.user.id);

  if (!teacher) return res.redirect("/teacher?error=Teacher profile not found");

  const date = normalizeText(req.body.date);
  if (!isIsoDate(date)) return res.redirect("/teacher?error=Invalid attendance date");

  const students = db
    .prepare("SELECT id FROM students WHERE class_id = ?")
    .all(teacher.class_id);

  if (!students.length) {
    return res.redirect(`/teacher?date=${date}&error=No students found in your class`);
  }

  const upsert = db.prepare(
    `INSERT INTO attendance (student_id, class_id, date, status, marked_by)
     VALUES (?, ?, ?, ?, ?)
     ON CONFLICT(student_id, date) DO UPDATE SET
       status = excluded.status,
       marked_by = excluded.marked_by`
  );

  const tx = db.transaction(() => {
    students.forEach((student) => {
      const raw = req.body[`status_${student.id}`];
      const status = raw === "absent" ? "absent" : "present";
      upsert.run(student.id, teacher.class_id, date, status, req.session.user.id);
    });
  });

  try {
    tx();
    res.redirect(`/teacher?date=${date}&success=Attendance saved`);
  } catch (err) {
    res.redirect(`/teacher?date=${date}&error=Failed to save attendance`);
  }
});

app.get("/student", isAuthenticated, requireRole("student"), (req, res) => {
  const student = db
    .prepare(
      `SELECT s.id, s.roll_no, c.standard_name, u.name
       FROM students s
       JOIN classes c ON s.class_id = c.id
       JOIN users u ON s.user_id = u.id
       WHERE s.user_id = ?`
    )
    .get(req.session.user.id);

  if (!student) {
    return logoutWithError(
      req,
      res,
      "Student profile not found. Ask principal to re-add your student profile."
    );
  }

  const summary = db
    .prepare(
      `SELECT
          COUNT(*) AS total,
          COUNT(CASE WHEN status = 'present' THEN 1 END) AS present_count,
          COUNT(CASE WHEN status = 'absent' THEN 1 END) AS absent_count
       FROM attendance
       WHERE student_id = ?`
    )
    .get(student.id);

  const percentage =
    summary.total > 0 ? ((summary.present_count / summary.total) * 100).toFixed(2) : "0.00";

  const history = db
    .prepare("SELECT date, status FROM attendance WHERE student_id = ? ORDER BY date DESC")
    .all(student.id);

  res.render("student", {
    student,
    summary,
    percentage,
    history,
    error: req.query.error || "",
    success: req.query.success || "",
  });
});

if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
  });
}

module.exports = app;
