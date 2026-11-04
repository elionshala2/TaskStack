# 📌 TaskStack

TaskStack is a modern, secure, full-stack task management web application built with **PHP 8+**, **MySQL**, and vanilla **HTML/CSS/JavaScript**. It features robust authentication (including Passkeys/WebAuthn and Google OAuth), automated email notifications/reminders, customized task categories, and enterprise-grade security practices.

---

## ✨ Features

- **Task Management:**
  - Create, view, edit, filter, search, and delete tasks.
  - Set priorities (`Low`, `Medium`, `High`), tags, and deadlines.
  - Due-date badges: visual warnings for overdue, today's, and tomorrow's tasks.
  - Color-coded task categories (create, edit, organize).
  - Dashboard analytics and progress tracking.

- **Authentication & Security:**
  - Standard email & password registration/login with bcrypt hashing.
  - **Passkeys (WebAuthn / FIDO2):** Passwordless login via biometric auth (fingerprint, Face ID, Windows Hello) or physical security keys.
  - **Google OAuth 2.0:** One-click signup and login, with options to link/unlink accounts.
  - **Two-Factor Authentication (2FA):** Email-based OTP codes.
  - **Session Management:** Centralized session invalidation across all devices via `session_version` bumping.
  - **Defense-in-Depth:**
    - Strict CSP (Content Security Policy) without inline scripts.
    - Full CSRF token protection on state-changing requests.
    - Prepared statements (`PDO`/`MySQLi`) preventing SQL injection.
    - Output sanitization against XSS.
    - Rate limiting per IP and per account against brute-force attacks.
    - `HttpOnly`, `SameSite=Lax`, and `Secure` session cookies.

- **Automated Email Reminders & Notifications:**
  - Daily digests for overdue, upcoming, and due-today tasks powered by background cron jobs.
  - Transactional emails for email verification, password resets, and suspicious session changes.

- **UX & Accessibility:**
  - Light / Dark theme toggles.
  - Multi-language support (Albanian & English).
  - Fast page loads (< 2s) and full mobile/tablet responsiveness.

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.0+
- **Database:** MySQL / MariaDB (utf8mb4)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Libraries & Dependencies:**
  - `lbuchs/WebAuthn` (FIDO2 / WebAuthn passwordless authentication)
  - `PHPMailer` / Composer dependencies

---

## 🚀 Getting Started

### Prerequisites

- **PHP 8.0 or newer** (with `openssl`, `mbstring`, `pdo_mysql` extensions enabled)
- **MySQL 5.7+** or MariaDB
- **Composer** ([Download Composer](https://getcomposer.org/))
- Local server environment (e.g., **XAMPP**, **Laragon**, or native Apache/Nginx)

---

### Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/elionshala2/TaskStack.git
   cd TaskStack
   ```

2. **Move to web server directory:**
   If using XAMPP, place the project inside your htdocs directory:
   ```text
   htdocs/TaskStack/
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Database Configuration**
   Open phpMyAdmin (or your MySQL client) and create a database named `TaskStack` with `utf8mb4_univode_ci` collation.
Import `database.sql` into the created database.

5. Setup Environment Variables
   Copy .env.example to .env:
   ```bash
   cp .env.example .env
   ```
   Open `.env` and fill in your settings

6. Run the Project:
   Start Apache and MySQL in your panel, and navigate to:
   ```text
   http://localhost/TaskStack/
   ```

---

### Background Cron Jobs (Deadline Reminders)

To automatically send daily reminder emails:

- Via PHP CLI (Recommended):
Add a cron job to trigger once per day (e.g., at 08:00 AM):

```bash
0 8 * * * php /path/to/TaskStack/cron/send_reminders.php
```

Via Webhook / HTTP GET:
If your host does not provide CLI access, configure `CRON_KEY` in `.env` and execute via cURL:

```bash
0 8 * * * curl -fsS "https://yourdomain.com/cron/send_reminders.php?key=YOUR_CRON_KEY"
```

---

### Project Structure

```text
TaskStack/
├── assets/              # Static styling, UI scripts, icons
├── cron/                # Scheduled background automation scripts
├── includes/            # Core backend utilities, auth helpers, DB connections
├── tasks/               # Task management logic and handlers
├── .env.example         # Template for environment variables
├── database.sql         # Base database schema
├── migration.sql        # Migration script for schema updates
├── dashboard.php        # Main task dashboard
├── index.php            # Landing page
└── settings.php         # User preferences, Passkeys & profile management
```

---

### Security Best Practises

Strict CSP: No inline script injection allowed. Dynamic click confirmations use data-confirm="Message" attributes handled globally by assets/script.js.
Sensitive Operations: Passkey modification, email changes, and account deletion require re-authentication with current credentials.
Production Tip: Ensure APP_DEBUG=0 is configured in production to prevent leaking sensitive call stacks.

---

### License

This project is open-source and licensed under the GPL-3.0 license