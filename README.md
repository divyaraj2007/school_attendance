# Smart School Attendance Management System (Web & Mobile Application)

[![GitHub Repository](https://img.shields.io/badge/GitHub-divyaraj2007%2Fschool__attendance-181717?style=flat&logo=github)](https://github.com/divyaraj2007/school_attendance)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![PWA Ready](https://img.shields.io/badge/PWA-Mobile_App_Ready-5A0FC8?style=flat&logo=pwa&logoColor=white)](https://web.dev/progressive-web-apps/)

A complete, modern, responsive, and professional **School & College Attendance Management System** featuring a **Web Application**, a **Touch-First Mobile Application (PWA & Mobile SPA)**, and a **JSON REST API** suite.

Developed in **PHP 8+ (PDO)**, **MySQL**, and **Bootstrap 5**, supporting both School (Standard 1–12) and College (Semester 1–8) academic structures.

---

## 📱 Mobile App & Web Application Features

### 1. Web Application
* **4 Role-Based Portals**:
  * 👑 **Administrator Portal**: User management, Standards 1–12 & Semesters 1–8, timetable scheduling, reports, holidays, settings, audit logs, and SQL database backup.
  * 🎓 **Principal Portal**: Institutional monitoring, daily & subject attendance overview, faculty attendance, correction approvals, defaulter monitor (<75%), and official announcements.
  * 👨‍🏫 **Teacher Portal**: Assigned classes roster, daily attendance marking, subject-wise attendance with live percentages, past attendance history, submit correction request, and personal lecture timetable.
  * 🎒 **Student Portal**: Personal attendance percentage, subject breakdown, interactive color-coded monthly calendar, circulars & notices.
* **Dynamic Timetable Management**:
  * Fully changeable by **Admin**, **Principal**, and **Teachers**.
  * Quick period slot booking, room allocations, timings, and conflict detection.

### 2. Mobile Application (PWA & Touch-First SPA)
* **Installable as Native Mobile App (PWA)**:
  * Includes `manifest.json` and Service Worker (`sw.js`).
  * Installable directly on **Android** (via Chrome / Edge) and **iOS** (via Safari "Add to Home Screen") as a standalone full-screen mobile app with home screen icon and splash screen.
  * Offline asset caching for instant mobile load times.
* **Dedicated Mobile App Portal (`/mobile/index.php`)**:
  * Native app header with profile avatar and quick status.
  * **Touch-Optimized Attendance Punching**: One-tap Present / Absent / Late toggles for teachers on phones.
  * **Today's Timeline Feed**: Periods, timings, room numbers, and subject details.
  * **Native Mobile Bottom Navigation Bar**: Seamless navigation between Home, Schedule, Attendance, and Profile.

### 3. REST API Suite (`/api/`)
* `POST /api/auth.php`: Mobile authentication and token validation.
* `GET /api/student.php`: Student attendance percentage, stats, today's schedule, and subject breakdown.
* `GET / POST /api/teacher.php`: Assigned classes, student rosters, and 1-tap mobile attendance submission.
* `GET /api/timetable.php`: Weekly period schedules filtered by day, class, section, or teacher.
* `GET /api/chart_data.php`: Live JSON feeds for Chart.js weekly trend and daily breakdown charts.

---

## 🔑 Login Roles & Default Credentials

All seed accounts are pre-configured with the default password: `password123`

| Role | User ID | Password | Portal Scope |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` (or `ADM001`) | `password123` | Full system control: Users, Academics, Timetable, Attendance, Reports, Settings & Database Backup |
| **Principal** | `PRN101` | `password123` | Institutional monitoring: Daily & Subject student attendance, Teacher attendance, Correction approvals, Defaulters (<75%) |
| **Teacher** | `TCH1001` | `password123` | Assigned classes only: Daily & Subject attendance, Roster management, Correction requests, Timetable |
| **Student** | `STU2026001` | `password123` | Personal records only: Daily & Subject attendance percentages, Interactive visual calendar, History |

*Additional Seed Teachers:* `TCH1002`, `TCH1003` (Password: `password123`)  
*Additional Seed Students:* `STU2026002`, `STU2026003` (Defaulter <75%), `STU2026004`, `STU2026005`, `STU2026006`

---

## 🚀 Quick Start / Installation Guide

### Prerequisites
* **XAMPP** (PHP 8.1+ with MariaDB/MySQL).
* Web Browser (Chrome, Edge, Firefox, Safari).

### Step 1: Clone the Repository
```bash
git clone https://github.com/divyaraj2007/school_attendance.git
```
Or place the files in your XAMPP web directory:
```
C:\xampp\htdocs\school_attendance
```

### Step 2: Start Apache and MySQL in XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**.

### Step 3: Import the Database
Run the following in PowerShell:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS school_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\xampp\mysql\bin\mysql.exe" -u root school_attendance_db -e "source d:/VS_Code_project/school_attendance/database/schema.sql"
```
*(Or import `database/schema.sql` via phpMyAdmin at `http://localhost/phpmyadmin/`)*.

### Step 4: Launch the Web & Mobile App
* **Web Application Portal**:
  ```
  http://localhost/school_attendance/
  ```
  *(Or with PHP built-in server: `php -S localhost:8000` &rarr; `http://localhost:8000/`)*
* **Dedicated Mobile App**:
  ```
  http://localhost/school_attendance/mobile/
  ```
  *(On Android or iPhone, tap **Install App** or **Add to Home Screen** to use it as a native mobile application).*

---

## 📂 Project Structure

```
school_attendance/
├── manifest.json              # PWA Mobile Web App Manifest
├── sw.js                      # PWA Service Worker for offline caching & fast loading
├── mobile/
│   └── index.php              # Dedicated Touch-First Mobile Application (SPA)
├── api/
│   ├── auth.php               # Mobile & Web REST API Authentication
│   ├── student.php            # Student Mobile Data (Stats, Schedule, Breakdown)
│   ├── teacher.php            # Teacher Mobile Data & Mobile Attendance Submission
│   ├── timetable.php          # Timetable Schedule API (Class & Teacher)
│   ├── chart_data.php         # Chart.js live analytics feed
│   ├── get_sections.php       # Dynamic sections AJAX endpoint
│   └── get_subjects.php       # Dynamic subjects AJAX endpoint
├── assets/
│   ├── css/style.css          # Modern theme, responsive layout & mobile bottom navigation
│   ├── js/main.js             # UI helpers & PWA Service Worker registration
│   ├── img/logo.svg           # Institutional vector shield emblem & app icon
│   └── uploads/profiles/      # Profile picture storage
├── config/
│   ├── database.php           # PDO database singleton connection
│   └── app.php                # Base URL configuration & session initialization
├── database/
│   └── schema.sql             # Complete database schema + seed data (19 tables)
├── includes/
│   ├── auth.php               # RBAC authorization guards & remember-me
│   ├── functions.php          # Security sanitization, stats & audit helpers
│   ├── header.php             # HTML head, Bootstrap, PWA meta links
│   ├── sidebar.php            # Dynamic role-based navigation sidebar
│   ├── navbar.php             # Top navigation bar & notifications
│   └── footer.php             # Footer & Native Mobile Bottom Navigation Bar
├── admin/                     # Administrator Portal (12 management modules)
├── principal/                 # Principal Portal (11 oversight & approval modules)
├── teacher/                   # Teacher Portal (12 academic & attendance modules)
├── student/                   # Student Portal (8 student tracking modules)
├── index.php                  # Multi-role Login page with 1-click demo prefill
├── logout.php                 # Secure session destruction
├── forgot_password.php        # Password recovery assistance
└── INSTALLATION.md            # Detailed installation walkthrough
```

---

## 🛡️ Security Features
* **Role-Based Access Control (RBAC)**: Strict server-side route guards enforcing roles (`admin`, `principal`, `teacher`, `student`).
* **Prepared Statements (PDO)**: Complete protection against SQL Injection across all queries.
* **CSRF Protection**: Cryptographic token generation and verification on all form and action POST submissions.
* **Password Hashing**: Industry-standard `bcrypt` via PHP `password_hash()` and `password_verify()`.
* **Audit Trail**: Every attendance change, approval, slot update, and login is recorded in `audit_logs`.

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
