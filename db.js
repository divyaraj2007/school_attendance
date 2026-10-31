const path = require("path");
const Database = require("better-sqlite3");
const bcrypt = require("bcryptjs");

const dbPath = path.join(__dirname, "school_attendance.db");
const db = new Database(dbPath);
const DEFAULT_TEACHER_PASSWORD = "123456";

db.pragma("journal_mode = WAL");
db.pragma("foreign_keys = ON");
db.pragma("busy_timeout = 5000");

function initDb() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL CHECK(role IN ('principal', 'teacher', 'student')),
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS classes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      standard_name TEXT NOT NULL UNIQUE,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS teachers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL UNIQUE,
      subject TEXT NOT NULL,
      class_id INTEGER NOT NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS students (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL UNIQUE,
      roll_no INTEGER NOT NULL,
      class_id INTEGER NOT NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
      UNIQUE (roll_no, class_id)
    );

    CREATE TABLE IF NOT EXISTS attendance (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      student_id INTEGER NOT NULL,
      class_id INTEGER NOT NULL,
      date TEXT NOT NULL,
      status TEXT NOT NULL CHECK(status IN ('present', 'absent')),
      marked_by INTEGER NOT NULL,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
      FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
      FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
      UNIQUE(student_id, date)
    );
  `);

  const principalExists = db
    .prepare("SELECT id FROM users WHERE role = 'principal' LIMIT 1")
    .get();

  if (!principalExists) {
    const hash = bcrypt.hashSync("admin123", 10);
    db.prepare(
      "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
    ).run("Principal", "principal@school.com", hash, "principal");
    console.log("Seeded principal user: principal@school.com / admin123");
  }

  // Enforce a single password for all teachers, as requested by project rules.
  const teacherHash = bcrypt.hashSync(DEFAULT_TEACHER_PASSWORD, 10);
  const syncedTeachers = db
    .prepare("UPDATE users SET password_hash = ? WHERE role = 'teacher'")
    .run(teacherHash).changes;
  if (syncedTeachers > 0) {
    console.log(
      `Synced ${syncedTeachers} teacher password(s) to default: ${DEFAULT_TEACHER_PASSWORD}`
    );
  }

  // Seed one demo class/teacher/student so teacher attendance works out-of-the-box.
  const demoClassName = "Standard Demo";
  let demoClass = db
    .prepare("SELECT id FROM classes WHERE standard_name = ?")
    .get(demoClassName);
  if (!demoClass) {
    const classResult = db
      .prepare("INSERT INTO classes (standard_name) VALUES (?)")
      .run(demoClassName);
    demoClass = { id: classResult.lastInsertRowid };
    console.log(`Seeded demo class: ${demoClassName}`);
  }

  const demoTeacherEmail = "demo.teacher@school.com";
  let demoTeacherUser = db
    .prepare("SELECT id FROM users WHERE email = ?")
    .get(demoTeacherEmail);
  if (!demoTeacherUser) {
    const userResult = db
      .prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'teacher')")
      .run("Demo Teacher", demoTeacherEmail, teacherHash);
    demoTeacherUser = { id: userResult.lastInsertRowid };
    console.log(`Seeded demo teacher: demo.teacher@school.com / ${DEFAULT_TEACHER_PASSWORD}`);
  }

  const demoTeacherProfile = db
    .prepare("SELECT id FROM teachers WHERE user_id = ?")
    .get(demoTeacherUser.id);
  if (!demoTeacherProfile) {
    db.prepare("INSERT INTO teachers (user_id, subject, class_id) VALUES (?, ?, ?)")
      .run(demoTeacherUser.id, "General", demoClass.id);
  }

  const demoStudentEmail = "demo.student@school.com";
  let demoStudentUser = db
    .prepare("SELECT id FROM users WHERE email = ?")
    .get(demoStudentEmail);
  if (!demoStudentUser) {
    const hash = bcrypt.hashSync("student123", 10);
    const userResult = db
      .prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')")
      .run("Demo Student", demoStudentEmail, hash);
    demoStudentUser = { id: userResult.lastInsertRowid };
    console.log("Seeded demo student: demo.student@school.com / student123");
  }

  const demoStudentProfile = db
    .prepare("SELECT id FROM students WHERE user_id = ?")
    .get(demoStudentUser.id);
  if (!demoStudentProfile) {
    const nextRoll = db
      .prepare("SELECT COALESCE(MAX(roll_no), 0) + 1 AS next_roll FROM students WHERE class_id = ?")
      .get(demoClass.id).next_roll;
    db.prepare("INSERT INTO students (user_id, roll_no, class_id) VALUES (?, ?, ?)")
      .run(demoStudentUser.id, nextRoll, demoClass.id);
  }
}

module.exports = { db, initDb };
