<?php
// ============================================================
// EMAIL CONFIGURATION — Edit these values with your SMTP info
// ============================================================
define('SMTP_HOST',     'smtp.gmail.com');   // e.g. smtp.gmail.com
define('SMTP_USER',     'your_email@gmail.com');  // Your Gmail address
define('SMTP_PASS',     'your_app_password');     // Gmail App Password (NOT your Gmail login password)
// To get a Gmail App Password:
// 1. Enable 2-Step Verification on your Google account
// 2. Go to myaccount.google.com → Security → App Passwords
// 3. Create one for "Mail" and paste it above
define('SMTP_PORT',     587);
define('SMTP_FROM',     'your_email@gmail.com');
define('SMTP_FROM_NAME','MediCare Health System');
define('SMTP_SECURE',   'tls');  // 'tls' for port 587, 'ssl' for port 465
?>
