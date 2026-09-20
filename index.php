<?php
require_once __DIR__ . '/includes/auth_helper.php';

// NOTE: We intentionally do NOT auto-redirect logged-in users here.
// Because the three portals (patient/doctor/admin) each use their own
// independent session, auto-redirecting based on one session would block
// logging into a second or third role in another tab. The login page
// always shows the form; users navigate to their dashboard after login.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>MediCare — Smart Healthcare System</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet"/>
<style>
/* ===== RESET & VARIABLES ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:       #0b3d6b;
  --blue:       #1565c0;
  --sky:        #1e88e5;
  --ice:        #e3f2fd;
  --mint:       #00bcd4;
  --white:      #ffffff;
  --off-white:  #f5f8fe;
  --gray-100:   #f1f5f9;
  --gray-200:   #e2e8f0;
  --gray-400:   #94a3b8;
  --gray-600:   #475569;
  --gray-800:   #1e293b;
  --success:    #16a34a;
  --error:      #dc2626;
  --warning:    #d97706;
  --shadow-sm:  0 1px 3px rgba(11,61,107,.08), 0 1px 2px rgba(11,61,107,.06);
  --shadow-md:  0 4px 16px rgba(11,61,107,.12), 0 2px 8px rgba(11,61,107,.08);
  --shadow-lg:  0 20px 60px rgba(11,61,107,.18), 0 8px 24px rgba(11,61,107,.10);
  --radius:     16px;
  --radius-sm:  8px;
  --trans:      .25s cubic-bezier(.4,0,.2,1);
}

html, body {
  height: 100%;
  font-family: 'DM Sans', sans-serif;
  background: var(--off-white);
  color: var(--gray-800);
  overflow-x: hidden;
}

/* ===== LAYOUT ===== */
.page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}

@media (max-width: 900px) {
  .page { grid-template-columns: 1fr; }
  .hero  { display: none; }
}

/* ===== HERO PANEL ===== */
.hero {
  background: linear-gradient(155deg, var(--navy) 0%, var(--blue) 50%, var(--sky) 100%);
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 60px 48px;
  overflow: hidden;
}

.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 20% 80%, rgba(0,188,212,.25) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(21,101,192,.35) 0%, transparent 45%);
}

.hero-blobs {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}
.blob {
  position: absolute;
  border-radius: 50%;
  background: rgba(255,255,255,.05);
  animation: blobFloat 8s ease-in-out infinite;
}
.blob-1 { width:300px; height:300px; top:-60px; right:-60px; animation-delay:0s; }
.blob-2 { width:200px; height:200px; bottom:60px; left:-40px; animation-delay:-3s; }
.blob-3 { width:150px; height:150px; top:45%; left:60%; animation-delay:-5s; }

@keyframes blobFloat {
  0%,100% { transform: translateY(0) scale(1); }
  50%      { transform: translateY(-20px) scale(1.05); }
}

.hero-content {
  position: relative;
  z-index: 1;
  text-align: center;
  max-width: 400px;
}

.hero-logo {
  width: 80px;
  height: 80px;
  background: rgba(255,255,255,.15);
  border-radius: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 32px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.25);
}
.hero-logo svg { width:44px; height:44px; }

.hero-title {
  font-family: 'DM Serif Display', serif;
  font-size: 42px;
  color: var(--white);
  line-height: 1.15;
  margin-bottom: 16px;
}
.hero-title em {
  font-style: italic;
  color: rgba(255,255,255,.75);
}

.hero-desc {
  color: rgba(255,255,255,.70);
  font-size: 16px;
  line-height: 1.65;
  margin-bottom: 40px;
}

.feature-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
}
.pill {
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.22);
  border-radius: 99px;
  padding: 7px 16px;
  font-size: 12px;
  color: rgba(255,255,255,.85);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  gap: 6px;
}

.hero-stats {
  display: grid;
  grid-template-columns: repeat(3,1fr);
  gap: 16px;
  margin-top: 48px;
}
.stat {
  background: rgba(255,255,255,.10);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: var(--radius-sm);
  padding: 16px;
  backdrop-filter: blur(8px);
}
.stat-num  { font-family:'DM Serif Display',serif; font-size:28px; color:#fff; }
.stat-label{ font-size:11px; color:rgba(255,255,255,.6); margin-top:2px; text-transform:uppercase; letter-spacing:.06em; }

/* ===== FORM PANEL ===== */
.form-panel {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 48px 56px;
  overflow-y: auto;
  background: var(--white);
}

@media (max-width:1100px) { .form-panel { padding: 40px 36px; } }

.mobile-brand {
  display: none;
  align-items: center;
  gap: 10px;
  margin-bottom: 36px;
}
@media (max-width:900px) { .mobile-brand { display:flex; } }
.mobile-brand-icon {
  width:40px; height:40px;
  background: linear-gradient(135deg,var(--navy),var(--sky));
  border-radius:10px;
  display:flex; align-items:center; justify-content:center;
}
.mobile-brand-icon svg { width:22px; height:22px; }
.mobile-brand-name { font-family:'DM Serif Display',serif; font-size:22px; color:var(--navy); }

/* Tab switcher */
.tabs {
  display: flex;
  background: var(--gray-100);
  border-radius: var(--radius-sm);
  padding: 4px;
  margin-bottom: 32px;
  gap: 4px;
}
.tab-btn {
  flex: 1;
  padding: 10px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-family: 'DM Sans',sans-serif;
  font-size: 14px;
  font-weight: 500;
  color: var(--gray-600);
  cursor: pointer;
  transition: var(--trans);
}
.tab-btn.active {
  background: var(--white);
  color: var(--navy);
  font-weight: 600;
  box-shadow: var(--shadow-sm);
}

/* Heading */
.form-heading { margin-bottom: 28px; }
.form-heading h2 {
  font-family: 'DM Serif Display',serif;
  font-size: 30px;
  color: var(--navy);
  margin-bottom: 6px;
}
.form-heading p { font-size: 14px; color: var(--gray-400); }

/* Steps indicator */
.steps {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 28px;
}
.step {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--gray-400);
  font-weight: 500;
}
.step.active { color: var(--blue); }
.step.done   { color: var(--success); }
.step-num {
  width: 26px; height: 26px;
  border-radius: 50%;
  background: var(--gray-200);
  display: flex; align-items:center; justify-content:center;
  font-size: 11px; font-weight: 700;
  transition: var(--trans);
}
.step.active .step-num { background: var(--blue); color:#fff; }
.step.done .step-num   { background: var(--success); color:#fff; }
.step-line { flex:1; height:2px; background:var(--gray-200); margin:0 6px; }
.step.done ~ .step-line { background: var(--success); }

/* Form groups */
.form-group {
  margin-bottom: 18px;
  position: relative;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width:520px) { .form-row { grid-template-columns:1fr; } }

label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-600);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.input-wrap {
  position: relative;
}
.input-wrap .ico {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
  display: flex;
}
.input-wrap .toggle-pw {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--gray-400);
  display: flex;
  padding: 4px;
  transition: color var(--trans);
}
.input-wrap .toggle-pw:hover { color: var(--blue); }

input[type="text"],
input[type="email"],
input[type="password"],
input[type="tel"],
input[type="date"],
select {
  width: 100%;
  height: 48px;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-sm);
  padding: 0 14px 0 42px;
  font-family: 'DM Sans',sans-serif;
  font-size: 14px;
  color: var(--gray-800);
  background: var(--white);
  transition: border-color var(--trans), box-shadow var(--trans);
  outline: none;
  -webkit-appearance: none;
}
input:focus, select:focus {
  border-color: var(--sky);
  box-shadow: 0 0 0 3px rgba(30,136,229,.12);
}
input.error { border-color: var(--error); }
input.success { border-color: var(--success); }

/* OTP single text field */
.otp-single {
  width: 100%;
  height: 64px;
  text-align: center;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: 0.5em;
  padding: 0 0 0 0.5em;
  border: 2px solid var(--g200);
  border-radius: var(--radius-sm);
  font-family: 'DM Sans', sans-serif;
  color: var(--g800);
  background: var(--white);
  outline: none;
  transition: border-color .25s, box-shadow .25s;
}
.otp-single:focus {
  border-color: var(--sky);
  box-shadow: 0 0 0 3px rgba(30,136,229,.12);
}
.otp-single::placeholder {
  color: var(--g300);
  letter-spacing: 0.3em;
  font-weight: 400;
}

/* Role selector */
.role-selector {
  display: grid;
  grid-template-columns: repeat(3,1fr);
  gap: 10px;
  margin-bottom: 18px;
}
.role-card {
  border: 2px solid var(--gray-200);
  border-radius: var(--radius-sm);
  padding: 14px 8px;
  text-align: center;
  cursor: pointer;
  transition: var(--trans);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.role-card:hover { border-color: var(--sky); background: var(--ice); }
.role-card.selected { border-color: var(--blue); background: var(--ice); }
.role-card .icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items:center; justify-content:center;
  font-size: 20px;
}
.role-card[data-role="patient"] .icon { background: #fef3c7; }
.role-card[data-role="doctor"]  .icon { background: #dcfce7; }
.role-card[data-role="admin"]   .icon { background: #ede9fe; }
.role-card span { font-size: 12px; font-weight: 600; color: var(--gray-600); }
.role-card.selected span { color: var(--blue); }

/* Password strength */
.pw-strength { margin-top: 6px; }
.pw-strength-bar {
  height: 4px;
  background: var(--gray-200);
  border-radius: 99px;
  overflow: hidden;
}
.pw-strength-fill {
  height: 100%;
  border-radius: 99px;
  transition: width .4s, background .4s;
  width: 0%;
}
.pw-strength-text { font-size: 11px; color: var(--gray-400); margin-top: 4px; }

/* Alert */
.alert {
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  margin-bottom: 18px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  animation: slideIn .3s ease;
}
.alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
.alert-error   { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.alert-info    { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }

@keyframes slideIn {
  from { opacity:0; transform:translateY(-8px); }
  to   { opacity:1; transform:translateY(0); }
}

/* Button */
.btn-primary {
  width: 100%;
  height: 50px;
  background: linear-gradient(135deg, var(--navy), var(--sky));
  color: var(--white);
  border: none;
  border-radius: var(--radius-sm);
  font-family: 'DM Sans',sans-serif;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--trans);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
  overflow: hidden;
  letter-spacing: .02em;
}
.btn-primary::after {
  content:'';
  position:absolute;
  inset:0;
  background:rgba(255,255,255,0);
  transition: background var(--trans);
}
.btn-primary:hover::after { background:rgba(255,255,255,.08); }
.btn-primary:active        { transform:scale(.98); }
.btn-primary:disabled      { opacity:.65; cursor:not-allowed; }
.btn-primary .spinner {
  width:18px; height:18px;
  border:2px solid rgba(255,255,255,.4);
  border-top-color:#fff;
  border-radius:50%;
  animation: spin .6s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

.btn-ghost {
  background: none;
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-sm);
  padding: 8px 20px;
  font-family:'DM Sans',sans-serif;
  font-size:13px;
  color: var(--gray-600);
  cursor:pointer;
  transition: var(--trans);
}
.btn-ghost:hover { border-color:var(--sky); color:var(--blue); }

.link-btn {
  background:none; border:none; cursor:pointer;
  color:var(--blue); font-size:13px; font-family:'DM Sans',sans-serif;
  font-weight:500; text-decoration:underline; padding:0;
}
.link-btn:hover { color:var(--navy); }

/* Helper text */
.hint { font-size:12px; color:var(--gray-400); margin-top:5px; }
.error-msg { font-size:12px; color:var(--error); margin-top:4px; }

/* Divider */
.divider {
  display:flex; align-items:center; gap:12px;
  margin: 20px 0; color:var(--gray-400); font-size:12px;
}
.divider::before, .divider::after {
  content:''; flex:1; height:1px; background:var(--gray-200);
}

/* Resend timer */
.resend-row {
  text-align:center; margin-top:10px; font-size:13px; color:var(--gray-400);
}
#resend-timer { font-weight:600; color:var(--blue); }

/* Checkbox */
.check-row {
  display:flex; align-items:flex-start; gap:10px; font-size:13px; color:var(--gray-600);
}
.check-row input[type=checkbox] { width:16px; height:16px; margin-top:2px; flex-shrink:0; }
.check-row a { color:var(--blue); }

/* Form sections (hide/show) */
.form-section { display:none; }
.form-section.active { display:block; animation:fadeIn .35s ease; }
@keyframes fadeIn {
  from { opacity:0; transform:translateY(6px); }
  to   { opacity:1; transform:translateY(0); }
}

/* Progress bar */
.progress-bar {
  height:3px; background:var(--gray-200); border-radius:99px;
  margin-bottom:32px; overflow:hidden;
}
.progress-fill {
  height:100%;
  background:linear-gradient(90deg,var(--navy),var(--sky));
  border-radius:99px;
  transition:width .4s ease;
}

/* Form footer */
.form-footer {
  margin-top:24px; text-align:center;
  font-size:13px; color:var(--gray-400);
}
.form-footer a, .form-footer button { color:var(--blue); font-weight:600; }
</style>
</head>
<body>
<div class="page">

  <!-- ===== HERO ===== -->
  <aside class="hero">
    <div class="hero-blobs">
      <div class="blob blob-1"></div>
      <div class="blob blob-2"></div>
      <div class="blob blob-3"></div>
    </div>
    <div class="hero-content">
      <div class="hero-logo">
        <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="4" y="4" width="36" height="36" rx="8" fill="white" fill-opacity=".15"/>
          <path d="M22 10v24M10 22h24" stroke="white" stroke-width="4" stroke-linecap="round"/>
          <circle cx="22" cy="22" r="8" stroke="white" stroke-width="2" fill="none"/>
        </svg>
      </div>
      <h1 class="hero-title">MediCare <em>Smart</em> Healthcare</h1>
      <p class="hero-desc">A unified platform for patients, doctors, and administrators — from booking appointments to AI-powered symptom analysis.</p>
      <div class="feature-pills">
        <span class="pill"><span>🗓️</span> Smart Booking</span>
        <span class="pill"><span>🤖</span> AI Symptom Analysis</span>
        <span class="pill"><span>💊</span> e-Prescriptions</span>
        <span class="pill"><span>📧</span> Real-time Notifications</span>
        <span class="pill"><span>📊</span> Admin Dashboard</span>
      </div>
      <div class="hero-stats">
        <div class="stat"><div class="stat-num">3</div><div class="stat-label">User Roles</div></div>
        <div class="stat"><div class="stat-num">AI</div><div class="stat-label">Powered</div></div>
        <div class="stat"><div class="stat-num">24/7</div><div class="stat-label">Access</div></div>
      </div>
    </div>
  </aside>

  <!-- ===== FORM PANEL ===== -->
  <main class="form-panel">

    <div class="mobile-brand">
      <div class="mobile-brand-icon">
        <svg viewBox="0 0 22 22" fill="none">
          <path d="M11 2v18M2 11h18" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="mobile-brand-name">MediCare</span>
    </div>

    <!-- Tab switcher -->
    <div class="tabs" role="tablist">
      <button class="tab-btn active" id="tab-login"    onclick="switchTab('login')"   role="tab">Sign In</button>
      <button class="tab-btn"        id="tab-register" onclick="switchTab('register')" role="tab">Create Account</button>
    </div>

    <!-- ============================
         LOGIN FORM
    ============================= -->
    <div id="login-form">

      <!-- Step 1: Email + Password -->
      <div class="form-section active" id="login-step-1">
        <div class="form-heading">
          <h2>Welcome back</h2>
          <p>Sign in to your MediCare account</p>
        </div>
        <div id="login-alert"></div>
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('mail') ?></span>
            <input type="email" id="login-email" placeholder="you@example.com" autocomplete="email"/>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('lock') ?></span>
            <input type="password" id="login-password" placeholder="Enter your password" autocomplete="current-password"/>
            <button type="button" class="toggle-pw" onclick="togglePw('login-password',this)"><?= svgIcon('eye') ?></button>
          </div>
        </div>
        <div style="text-align:right;margin-bottom:20px;">
          <button class="link-btn" onclick="switchTab('forgot')">Forgot password?</button>
        </div>
        <button class="btn-primary" id="login-btn" onclick="loginStep1()">
          <span>Continue</span>
        </button>
        <div class="form-footer">
          Don't have an account? <button class="link-btn" onclick="switchTab('register')">Create one</button>
        </div>
      </div>

      <!-- Step 2: OTP verification (login) -->
      <div class="form-section" id="login-step-2">
        <div class="form-heading">
          <h2>Verify it's you</h2>
          <p>We sent a 6-digit code to <strong id="login-email-display"></strong></p>
        </div>
        <div id="login-otp-alert"></div>
        <div class="form-group">
          <label>Enter OTP</label>
          <input type="text" id="login-otp-single" class="otp-single" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="• • • • • •" autocomplete="one-time-code"/>
        </div>
        <div class="resend-row">
          <span id="resend-login-text">Resend code in <span id="resend-login-timer">60</span>s</span>
          <button class="link-btn" id="resend-login-btn" style="display:none" onclick="resendOTP('login')">Resend OTP</button>
        </div>
        <br/>
        <button class="btn-primary" id="login-otp-btn" onclick="loginStep2()">
          <span>Verify &amp; Sign In</span>
        </button>
        <div class="form-footer">
          <button class="link-btn" onclick="goBack('login')">← Use a different email</button>
        </div>
      </div>
    </div>

    <!-- ============================
         REGISTER FORM
    ============================= -->
    <div id="register-form" style="display:none">
      <div class="progress-bar"><div class="progress-fill" id="reg-progress" style="width:33%"></div></div>

      <div class="steps" id="reg-steps">
        <div class="step active" id="rstep-1"><div class="step-num">1</div><span>Account</span></div>
        <div class="step-line"></div>
        <div class="step" id="rstep-2"><div class="step-num">2</div><span>Profile</span></div>
        <div class="step-line"></div>
        <div class="step" id="rstep-3"><div class="step-num">3</div><span>Verify</span></div>
      </div>

      <!-- Register Step 1: Role, email, password -->
      <div class="form-section active" id="reg-step-1">
        <div class="form-heading">
          <h2>Create your account</h2>
          <p>Choose your role to get started</p>
        </div>
        <div id="reg-alert-1"></div>
        <div>
          <input type="hidden" id="reg-role" value="patient"/>
          <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 16px;font-size:13px;color:#1e40af;margin-bottom:16px">
            👤 Registrations are open for <strong>Patients only</strong>. Doctors &amp; Admins are added by the system administrator.
          </div>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('mail') ?></span>
            <input type="email" id="reg-email" placeholder="you@example.com" autocomplete="email"/>
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('lock') ?></span>
            <input type="password" id="reg-password" placeholder="Min 8 chars" oninput="checkPwStrength(this.value)" autocomplete="new-password"/>
            <button type="button" class="toggle-pw" onclick="togglePw('reg-password',this)"><?= svgIcon('eye') ?></button>
          </div>
          <div class="pw-strength">
            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
            <div class="pw-strength-text" id="pw-text">Enter a password</div>
          </div>
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('lock') ?></span>
            <input type="password" id="reg-confirm-pw" placeholder="Re-enter password" autocomplete="new-password"/>
            <button type="button" class="toggle-pw" onclick="togglePw('reg-confirm-pw',this)"><?= svgIcon('eye') ?></button>
          </div>
        </div>
        <div class="check-row" style="margin-bottom:20px">
          <input type="checkbox" id="agree-terms"/>
          <label for="agree-terms" style="text-transform:none;letter-spacing:0;font-weight:400">
            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
          </label>
        </div>
        <button class="btn-primary" onclick="regStep1()"><span>Next: Profile Details →</span></button>
      </div>

      <!-- Register Step 2: Personal info -->
      <div class="form-section" id="reg-step-2">
        <div class="form-heading">
          <h2>Personal details</h2>
          <p>Tell us a little about yourself</p>
        </div>
        <div id="reg-alert-2"></div>
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('user') ?></span>
              <input type="text" id="reg-fname" placeholder="John" autocomplete="given-name"/>
            </div>
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('user') ?></span>
              <input type="text" id="reg-lname" placeholder="Doe" autocomplete="family-name"/>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Phone Number</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('phone') ?></span>
              <input type="tel" id="reg-phone" placeholder="+91 98765 43210" autocomplete="tel"/>
            </div>
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('calendar') ?></span>
              <input type="date" id="reg-dob" autocomplete="bday"/>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label>Gender</label>
          <div class="input-wrap">
            <span class="ico"><?= svgIcon('user') ?></span>
            <select id="reg-gender">
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other / Prefer not to say</option>
            </select>
          </div>
        </div>

        <!-- Doctor-only fields (hidden - doctors added by admin only) -->
        <div id="doctor-fields" style="display:none">
          <div class="divider">Doctor Details</div>
          <div class="form-group">
            <label>Specialization</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('stethoscope') ?></span>
              <select id="reg-specialization">
                <option value="">Select specialization</option>
                <option>General Medicine</option><option>Cardiology</option>
                <option>Dermatology</option><option>Neurology</option>
                <option>Orthopedics</option><option>Pediatrics</option>
                <option>Psychiatry</option><option>Gynecology</option>
                <option>Ophthalmology</option><option>ENT</option>
                <option>Oncology</option><option>Endocrinology</option>
                <option>Nephrology</option><option>Gastroenterology</option>
                <option>Pulmonology</option><option>Urology</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Qualification</label>
              <div class="input-wrap">
                <span class="ico"><?= svgIcon('certificate') ?></span>
                <input type="text" id="reg-qualification" placeholder="MBBS, MD…"/>
              </div>
            </div>
            <div class="form-group">
              <label>Experience (years)</label>
              <div class="input-wrap">
                <span class="ico"><?= svgIcon('calendar') ?></span>
                <input type="text" id="reg-experience" placeholder="e.g. 5"/>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Consultation Fee (₹)</label>
            <div class="input-wrap">
              <span class="ico"><?= svgIcon('rupee') ?></span>
              <input type="text" id="reg-fee" placeholder="e.g. 500"/>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:4px">
          <button class="btn-ghost" onclick="goRegBack()">← Back</button>
          <button class="btn-primary" style="flex:1" onclick="regStep2()"><span>Next: Verify Email →</span></button>
        </div>
      </div>

      <!-- Register Step 3: OTP -->
      <div class="form-section" id="reg-step-3">
        <div class="form-heading">
          <h2>Verify your email</h2>
          <p>We sent a 6-digit code to <strong id="reg-email-display"></strong></p>
        </div>
        <div id="reg-alert-3"></div>
        <div class="form-group">
          <label>Enter Verification Code</label>
          <input type="text" id="reg-otp-single" class="otp-single" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="• • • • • •" autocomplete="one-time-code"/>
        </div>
        <div class="resend-row">
          <span id="resend-reg-text">Resend code in <span id="resend-reg-timer">60</span>s</span>
          <button class="link-btn" id="resend-reg-btn" style="display:none" onclick="resendOTP('register')">Resend OTP</button>
        </div>
        <br/>
        <button class="btn-primary" id="reg-otp-btn" onclick="regStep3()">
          <span>Verify &amp; Create Account</span>
        </button>
        <div class="form-footer">
          <button class="link-btn" onclick="goRegBack()">← Change email</button>
        </div>
      </div>
    </div>

    <!-- Forgot password (minimal, extend later) -->
    <div id="forgot-form" style="display:none">
      <div class="form-heading">
        <h2>Reset password</h2>
        <p>Enter your email and we'll send a reset OTP</p>
      </div>
      <div id="forgot-alert"></div>
      <div class="form-group">
        <label>Email Address</label>
        <div class="input-wrap">
          <span class="ico"><?= svgIcon('mail') ?></span>
          <input type="email" id="forgot-email" placeholder="you@example.com"/>
        </div>
      </div>
      <button class="btn-primary" onclick="sendForgotOTP()"><span>Send Reset Code</span></button>
      <div class="form-footer">
        <button class="link-btn" onclick="switchTab('login')">← Back to sign in</button>
      </div>
    </div>

  </main>
</div>

<script>
// ===== HELPERS =====
const $ = id => document.getElementById(id);

function showAlert(id, msg, type='error') {
  $(id).innerHTML = `<div class="alert alert-${type}">
    <span>${type==='success'?'✅':type==='info'?'ℹ️':'⚠️'}</span>
    <span>${msg}</span>
  </div>`;
}

function clearAlert(id) { $(id).innerHTML = ''; }

function setLoading(btnId, loading) {
  const btn = $(btnId);
  if (!btn) return;
  if (loading) {
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div>';
  } else {
    btn.disabled = false;
  }
}

async function post(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  return r.json();
}

// ===== TAB SWITCHING =====
function switchTab(tab) {
  $('login-form').style.display    = 'none';
  $('register-form').style.display = 'none';
  $('forgot-form').style.display   = 'none';
  $('tab-login').classList.remove('active');
  $('tab-register').classList.remove('active');

  if (tab === 'login') {
    $('login-form').style.display = 'block';
    $('tab-login').classList.add('active');
  } else if (tab === 'register') {
    $('register-form').style.display = 'block';
    $('tab-register').classList.add('active');
  } else {
    $('forgot-form').style.display = 'block';
  }
}

// ===== ROLE SELECTOR =====
function selectRole(role) {
  document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
  document.querySelector(`[data-role="${role}"]`).classList.add('selected');
  $('reg-role').value = role;
  $('doctor-fields').style.display = role === 'doctor' ? 'block' : 'none';
}
selectRole('patient');

// ===== PASSWORD STRENGTH =====
function checkPwStrength(pw) {
  let score = 0;
  if (pw.length >= 8)             score++;
  if (/[A-Z]/.test(pw))          score++;
  if (/[0-9]/.test(pw))          score++;
  if (/[^A-Za-z0-9]/.test(pw))   score++;

  const fill  = $('pw-fill');
  const text  = $('pw-text');
  const levels = [
    { w:'0%',   bg:'transparent', t:'Enter a password' },
    { w:'25%',  bg:'#dc2626',     t:'Weak' },
    { w:'50%',  bg:'#d97706',     t:'Fair' },
    { w:'75%',  bg:'#2563eb',     t:'Good' },
    { w:'100%', bg:'#16a34a',     t:'Strong 💪' },
  ];
  const l = levels[pw.length === 0 ? 0 : score];
  fill.style.width = l.w;
  fill.style.background = l.bg;
  text.textContent = l.t;
  text.style.color = l.bg || '#94a3b8';
}

// ===== TOGGLE PASSWORD =====
function togglePw(inputId, btn) {
  const inp = $(inputId);
  const isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? `<?= addslashes(svgIcon('eye')) ?>`
    : `<?= addslashes(svgIcon('eye-off')) ?>`;
}

// ===== OTP INPUT HANDLING (single text field) =====
function initOTPInput(inputId) {
  const inp = $(inputId);
  if (!inp) return;
  inp.addEventListener('input', () => {
    // Strip non-digits, keep max 6
    inp.value = inp.value.replace(/[^0-9]/g, '').slice(0, 6);
  });
}
initOTPInput('login-otp-single');
initOTPInput('reg-otp-single');

function getOTPValue(inputId) {
  const inp = $(inputId);
  return inp ? inp.value.trim() : '';
}
function clearOTP(inputId) {
  const inp = $(inputId);
  if (inp) { inp.value = ''; inp.focus(); }
}

// ===== COUNTDOWN TIMER =====
function startTimer(timerElId, textElId, resendBtnId, seconds=60) {
  let s = seconds;
  $(timerElId).textContent = s;
  $(textElId).style.display  = 'inline';
  $(resendBtnId).style.display = 'none';
  const iv = setInterval(() => {
    s--;
    $(timerElId).textContent = s;
    if (s <= 0) {
      clearInterval(iv);
      $(textElId).style.display   = 'none';
      $(resendBtnId).style.display = 'inline';
    }
  }, 1000);
}

// ===== LOGIN STEP 1 =====
async function loginStep1() {
  clearAlert('login-alert');
  const email    = $('login-email').value.trim();
  const password = $('login-password').value;
  if (!email || !password) return showAlert('login-alert','Please enter your email and password.');

  setLoading('login-btn', true);
  try {
    const res = await post('auth/login_step1.php', {email, password});
    if (res.success) {
      // OTP disabled for this user — go straight to dashboard
      if (res.skip_otp && res.redirect) {
        showAlert('login-alert','✅ Login successful! Redirecting…','success');
        setTimeout(() => { window.location.href = res.redirect; }, 800);
        return;
      }
      $('login-email-display').textContent = email;
      showSection('login-step-2', 'login-step-1');
      startTimer('resend-login-timer','resend-login-text','resend-login-btn');
    } else {
      showAlert('login-alert', res.message || 'Invalid credentials.');
    }
  } catch(e) { showAlert('login-alert','Server error. Please try again.'); }
  finally { $('login-btn').innerHTML = '<span>Continue</span>'; $('login-btn').disabled = false; }
}

// ===== LOGIN STEP 2 =====
async function loginStep2() {
  clearAlert('login-otp-alert');
  const otp = getOTPValue('login-otp-single');
  if (otp.length < 6) return showAlert('login-otp-alert','Please enter the full 6-digit code.');
  const email = $('login-email').value.trim();

  setLoading('login-otp-btn', true);
  try {
    const res = await post('auth/login_step2.php', {email, otp});
    if (res.success) {
      showAlert('login-otp-alert','Login successful! Redirecting…','success');
      setTimeout(() => { window.location.href = res.redirect; }, 1200);
    } else {
      showAlert('login-otp-alert', res.message || 'Invalid or expired OTP.');
      clearOTP('login-otp-single');
      $('login-otp-btn').innerHTML='<span>Verify &amp; Sign In</span>';
      $('login-otp-btn').disabled=false;
    }
  } catch(e) {
    showAlert('login-otp-alert','Server error.');
    $('login-otp-btn').innerHTML='<span>Verify &amp; Sign In</span>';
    $('login-otp-btn').disabled=false;
  }
}

// ===== REG STEP 1 =====
async function regStep1() {
  clearAlert('reg-alert-1');
  const email   = $('reg-email').value.trim();
  const pw      = $('reg-password').value;
  const cpw     = $('reg-confirm-pw').value;
  const agreed  = $('agree-terms').checked;

  if (!email) return showAlert('reg-alert-1','Please enter your email address.');
  if (pw.length < 8) return showAlert('reg-alert-1','Password must be at least 8 characters.');
  if (pw !== cpw)    return showAlert('reg-alert-1','Passwords do not match.');
  if (!agreed)       return showAlert('reg-alert-1','Please agree to the Terms of Service.');

  // Check email not taken (quick check)
  const btn = document.querySelector('#reg-step-1 .btn-primary');
  btn.disabled=true; btn.innerHTML='<div class="spinner"></div>';
  try {
    const res = await post('auth/check_email.php', {email});
    if (!res.available) {
      showAlert('reg-alert-1', res.message || 'Email already registered.');
      btn.innerHTML='<span>Next: Profile Details →</span>'; btn.disabled=false;
      return;
    }
    showSection('reg-step-2','reg-step-1');
    updateRegProgress(2);
  } catch(e) { showAlert('reg-alert-1','Server error.'); }
  finally { btn.innerHTML='<span>Next: Profile Details →</span>'; btn.disabled=false; }
}

// ===== REG STEP 2 =====
async function regStep2() {
  clearAlert('reg-alert-2');
  const fname = $('reg-fname').value.trim();
  const lname = $('reg-lname').value.trim();
  const phone = $('reg-phone').value.trim();
  const role  = $('reg-role').value;

  if (!fname || !lname) return showAlert('reg-alert-2','Please enter your first and last name.');
  if (!phone) return showAlert('reg-alert-2','Please enter your phone number.');

  if (role === 'doctor') {
    if (!$('reg-specialization').value) return showAlert('reg-alert-2','Please select a specialization.');
    if (!$('reg-qualification').value.trim()) return showAlert('reg-alert-2','Please enter your qualification.');
  }

  // Send OTP
  const btn = document.querySelector('#reg-step-2 .btn-primary');
  btn.disabled=true; btn.innerHTML='<div class="spinner"></div>';
  const email = $('reg-email').value.trim();
  try {
    const res = await post('auth/send_otp.php', {email, purpose:'registration', name: fname+' '+lname});
    if (res.success) {
      $('reg-email-display').textContent = email;
      showSection('reg-step-3','reg-step-2');
      updateRegProgress(3);
      startTimer('resend-reg-timer','resend-reg-text','resend-reg-btn');
    } else {
      showAlert('reg-alert-2', res.message || 'Could not send OTP. Check email config.');
    }
  } catch(e) { showAlert('reg-alert-2','Server error.'); }
  finally { btn.innerHTML='<span>Next: Verify Email →</span>'; btn.disabled=false; }
}

// ===== REG STEP 3 =====
async function regStep3() {
  clearAlert('reg-alert-3');
  const otp = getOTPValue('reg-otp-single');
  if (otp.length < 6) return showAlert('reg-alert-3','Please enter all 6 digits.');

  const payload = {
    otp,
    email:         $('reg-email').value.trim(),
    password:      $('reg-password').value,
    role:          $('reg-role').value,
    full_name:     ($('reg-fname').value.trim()+' '+$('reg-lname').value.trim()).trim(),
    phone:         $('reg-phone').value.trim(),
    dob:           $('reg-dob').value,
    gender:        $('reg-gender').value,
    specialization:$('reg-specialization')?.value || '',
    qualification: $('reg-qualification')?.value?.trim() || '',
    experience:    $('reg-experience')?.value?.trim() || '0',
    fee:           $('reg-fee')?.value?.trim() || '0',
  };

  setLoading('reg-otp-btn', true);
  try {
    const res = await post('auth/register.php', payload);
    if (res.success) {
      showAlert('reg-alert-3','Account created! Redirecting…','success');
      setTimeout(() => { window.location.href = res.redirect; }, 1400);
    } else {
      showAlert('reg-alert-3', res.message || 'Registration failed.');
      clearOTP('reg-otp-single');
      $('reg-otp-btn').innerHTML='<span>Verify &amp; Create Account</span>';
      $('reg-otp-btn').disabled=false;
    }
  } catch(e) {
    showAlert('reg-alert-3','Server error.');
    $('reg-otp-btn').innerHTML='<span>Verify &amp; Create Account</span>';
    $('reg-otp-btn').disabled=false;
  }
}

// ===== FORGOT PASSWORD =====
async function sendForgotOTP() {
  clearAlert('forgot-alert');
  const email = $('forgot-email').value.trim();
  if (!email) return showAlert('forgot-alert','Please enter your email.');
  const btn = document.querySelector('#forgot-form .btn-primary');
  btn.disabled=true; btn.innerHTML='<div class="spinner"></div>';
  try {
    const res = await post('auth/send_otp.php',{email,purpose:'password_reset',name:'User'});
    if (res.success) showAlert('forgot-alert','Reset code sent! Check your inbox.','success');
    else showAlert('forgot-alert', res.message || 'Email not found.');
  } catch(e) { showAlert('forgot-alert','Server error.'); }
  finally { btn.innerHTML='<span>Send Reset Code</span>'; btn.disabled=false; }
}

// ===== RESEND OTP =====
async function resendOTP(context) {
  const email = context==='login' ? $('login-email').value.trim() : $('reg-email').value.trim();
  const name  = context==='register' ? ($('reg-fname').value.trim()||'User') : 'User';
  const purpose = context==='login' ? 'login' : 'registration';
  try {
    const res = await post('auth/send_otp.php',{email,purpose,name});
    if (res.success) startTimer(`resend-${context}-timer`,`resend-${context}-text`,`resend-${context}-btn`);
  } catch(e) {}
}

// ===== UI HELPERS =====
function showSection(showId, hideId) {
  $(hideId).classList.remove('active');
  $(showId).classList.add('active');
}
function goBack(form) {
  if(form==='login') { showSection('login-step-1','login-step-2'); clearOTP('login-otp-single'); }
}
function goRegBack() {
  if ($('reg-step-3').classList.contains('active')) {
    showSection('reg-step-2','reg-step-3'); updateRegProgress(2);
  } else if ($('reg-step-2').classList.contains('active')) {
    showSection('reg-step-1','reg-step-2'); updateRegProgress(1);
  }
}
function updateRegProgress(step) {
  const pct = {1:33,2:66,3:100};
  $('reg-progress').style.width = pct[step]+'%';
  [1,2,3].forEach(s => {
    const el = $('rstep-'+s);
    el.classList.remove('active','done');
    if (s < step) el.classList.add('done');
    else if (s === step) el.classList.add('active');
  });
}
</script>
</body>
</html>
<?php

// ===== SVG ICON HELPER =====
function svgIcon($name) {
    $icons = [
        'mail'       => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>',
        'lock'       => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
        'user'       => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0116 0v2"/></svg>',
        'phone'      => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.01 1.18a2 2 0 012-2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 6.91a16 16 0 006.29 6.29l1.28-1.28a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>',
        'calendar'   => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'stethoscope'=> '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4.8 2.3A.3.3 0 105 2H4a2 2 0 00-2 2v5a6 6 0 006 6v0a6 6 0 006-6V4a2 2 0 00-2-2h-1a.2.2 0 100 .3"/><path d="M8 15v1a6 6 0 006 6v0a6 6 0 006-6v-4"/><circle cx="20" cy="10" r="2"/></svg>',
        'certificate'=> '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>',
        'rupee'      => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3h12M6 8h12M12 21L6 8M12 21l6-13M12 21v0"/></svg>',
        'eye'        => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'eye-off'    => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>
