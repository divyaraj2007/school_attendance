# Installation & Setup Guide: Smart School Attendance Management System

Follow these instructions to run the application on **XAMPP** (Windows) or any local Apache/PHP server.

---

## Prerequisites

1. **XAMPP** installed (recommended PHP 8.1 or PHP 8.2 with MariaDB/MySQL).
2. Web browser (Chrome, Edge, Firefox).

---

## Step 1: Place Project Files in XAMPP `htdocs`

You can run the project either from `htdocs` or directly from your current workspace:

### Option A: Running inside XAMPP `htdocs` (Standard)
1. Copy or move this folder (`school att`) into:
   ```
   C:\xampp\htdocs\school_attendance
   ```

### Option B: Running via PHP Built-in Server
Open PowerShell in this folder (`d:\VS_Code_project\school att`) and run:
```powershell
& "C:\xampp\php\php.exe" -S localhost:8000
```
Then access `http://localhost:8000/`.

---

## Step 2: Start Apache and MySQL in XAMPP

1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both services should show green status.

---

## Step 3: Import the MySQL Database

### Method 1: Using phpMyAdmin (Web GUI)
1. Open your browser and go to `http://localhost/phpmyadmin/`.
2. Click on **New** in the left sidebar to create a database.
3. Database name: `school_attendance_db`  
   Collation: `utf8mb4_unicode_ci`  
   Click **Create**.
4. Click on the newly created `school_attendance_db` database.
5. Click on the **Import** tab at the top.
6. Click **Choose File** and select:
   ```
   database/schema.sql
   ```
7. Click **Import** at the bottom.

### Method 2: Using Command Prompt / PowerShell
Open PowerShell and run:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS school_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\xampp\mysql\bin\mysql.exe" -u root school_attendance_db -e "source d:/VS_Code_project/school att/database/schema.sql"
```

---

## Step 4: Database Configuration Verification

The database settings in `config/database.php` are set to XAMPP defaults:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'school_attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```
If your local MySQL root user has a password, update `DB_PASS` accordingly.

---

## Step 5: Launch the Application

Open your browser to:
* For XAMPP htdocs: `http://localhost/school_attendance/`
* For PHP built-in server: `http://localhost:8000/`

---

## Step 6: Test All 4 Roles

Log in using any of the credentials below (or use the one-click prefill buttons on the login page):

1. **Administrator**:
   * User ID: `admin`
   * Password: `password123`
   * Features: Full control of user accounts, standards 1-12 & semesters 1-8, timetable scheduling, reports, holidays, settings, and one-click database backup.

2. **Principal**:
   * User ID: `PRN101`
   * Password: `password123`
   * Features: Executive dashboard, student daily & subject attendance, faculty attendance, correction approvals, defaulter monitor (<75%), announcements.

3. **Teacher**:
   * User ID: `TCH1001` (Prof. Sarah Jenkins)
   * Password: `password123`
   * Features: Assigned classes, daily attendance, subject-wise attendance with live percentages, past attendance history, submit correction request, timetable.

4. **Student**:
   * User ID: `STU2026001` (Alex Morgan)
   * Password: `password123`
   * Features: Personal attendance stats, subject percentage breakdown, interactive color-coded monthly calendar, circulars & notices, password change.

---

## Troubleshooting

* **MySQL Connection Refused (10061)**:
  Ensure MySQL is started in XAMPP Control Panel.
* **Access Denied for user 'root'@'localhost'**:
  Open `config/database.php` and set your MySQL password in `DB_PASS`.
* **PowerShell Execution Policy Error**:
  Run PowerShell commands by prefixing with `& "C:\xampp\php\php.exe" ...`.
