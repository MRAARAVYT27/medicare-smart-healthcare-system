# 🏥 MediCare — Smart Healthcare System
## Setup Guide for XAMPP + VS Code

---

## ✅ PHASE 1 — Login & Registration (This Build)

Included in this phase:
- Beautiful login/register page with role selection (Patient / Doctor / Admin)
- 3-step registration (Account → Profile → Email OTP verification)
- 2-step login (Email+Password → OTP verification)
- Email OTP via PHPMailer (Gmail SMTP)
- Session-based authentication
- Role-based dashboard redirects
- MySQL database with full schema
- Password strength meter, show/hide password
- Forgot password flow (OTP-based)

---

## 📦 FOLDER STRUCTURE

```
healthcare/
├── index.php                    ← Main login/register page
├── composer.json                ← PHPMailer dependency
├── config/
│   ├── db.php                   ← Database connection
│   ├── email_config.php         ← SMTP settings (EDIT THIS)
│   └── schema.sql               ← Run this in phpMyAdmin
├── includes/
│   ├── auth_helper.php          ← Sessions, OTP, sanitization helpers
│   └── mailer.php               ← Email sending functions
├── auth/
│   ├── send_otp.php             ← OTP generation & email
│   ├── check_email.php          ← Email availability check
│   ├── register.php             ← Registration endpoint
│   ├── login_step1.php          ← Password verification + send OTP
│   ├── login_step2.php          ← OTP verification + session
│   └── logout.php               ← Session destroy
├── patient/
│   └── dashboard.php            ← Patient portal (placeholder)
├── doctor/
│   └── dashboard.php            ← Doctor portal (placeholder)
└── admin/
    └── dashboard.php            ← Admin console (placeholder)
```

---

## 🚀 SETUP STEPS

### Step 1 — Copy files to XAMPP

```
Copy the entire `healthcare` folder to:
C:\xampp\htdocs\healthcare\
```

### Step 2 — Install PHPMailer

Open a terminal in the `healthcare` folder and run:

```bash
composer install
```

> If you don't have Composer: download from https://getcomposer.org

This creates `vendor/phpmailer/phpmailer/` which `mailer.php` needs.

### Step 3 — Create the database

1. Start XAMPP → Start **Apache** and **MySQL**
2. Open http://localhost/phpmyadmin
3. Click **SQL** tab at the top
4. Copy+paste the contents of `config/schema.sql`
5. Click **Go**

This creates the `healthcare_db` database with all tables and a default admin account.

### Step 4 — Configure Gmail SMTP

Edit `config/email_config.php`:

```php
define('SMTP_USER', 'your_gmail@gmail.com');
define('SMTP_PASS', 'your_app_password_here');  // NOT your Gmail password
define('SMTP_FROM', 'your_gmail@gmail.com');
```

**How to get a Gmail App Password:**
1. Go to https://myaccount.google.com
2. Security → 2-Step Verification (enable it)
3. Security → App passwords
4. Create one → select "Mail" → copy the 16-character password
5. Paste it into `SMTP_PASS`

### Step 5 — Open in browser

```
http://localhost/healthcare/
```

---

## 🔐 DEFAULT ADMIN LOGIN

| Field    | Value                |
|----------|----------------------|
| Email    | admin@medicare.com   |
| Password | password             |

> ⚠️ Change this password after your first login!
>
> Note: Admin login also requires OTP, so make sure SMTP is configured first.
> To skip OTP for initial testing, you can temporarily comment out the
> "Send OTP" step in `login_step1.php`.

---

## 🏗️ WHAT'S COMING NEXT (Phase 2+)

Tell me to continue and I'll build:

**Patient Dashboard**
- Book appointments with doctor search & available slots
- Real-time email notifications on booking/cancellation
- Appointment tracking & history
- Cancel appointments
- View prescriptions (PDF/image)
- View billing receipts
- Symptom Analyser (Gemini API) with disease prediction & doctor type suggestion
- API key popup (🔑 icon bottom-right)

**Doctor Dashboard**
- View pending/confirmed appointments
- Upload prescriptions (PDF or image) per appointment
- Patient history per appointment
- Set availability schedule

**Admin Dashboard**
- List/add/edit doctors with specialization & fees
- Manage all appointments
- Upload billing receipts for patients
- Prescription database view
- User management

---

## ⚙️ TECH STACK

| Layer       | Technology        |
|-------------|-------------------|
| Frontend    | HTML5, CSS3, Vanilla JS |
| Backend     | PHP 8.x           |
| Database    | MySQL (via XAMPP) |
| Email       | PHPMailer + Gmail SMTP |
| AI          | Google Gemini API (Phase 2) |
| Server      | Apache (XAMPP)    |

---

## 🐛 TROUBLESHOOTING

**"Database connection failed"**
→ Make sure MySQL is running in XAMPP and you ran `schema.sql`

**"Failed to send email"**
→ Check `email_config.php` settings. Use an App Password, not your Gmail login.
→ Make sure "Less secure app access" or 2FA+App Password is configured.

**OTP not arriving**
→ Check spam folder. Try a different email service if needed.

**Blank page / 500 error**
→ Check `C:\xampp\php\php.ini` — enable `display_errors = On` for development
→ Check Apache error logs in XAMPP control panel

**`vendor` folder missing**
→ Run `composer install` in the healthcare directory

---

## 📝 NOTES

- Sessions expire when browser closes. Add `session.gc_maxlifetime` in php.ini for persistent sessions.
- All passwords are bcrypt-hashed (never stored plain text)
- OTPs expire after 10 minutes and are single-use
- Rate-limited to 5 OTPs per email per hour
