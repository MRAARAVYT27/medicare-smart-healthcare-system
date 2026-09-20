<?php
/**
 * Determines the session name based on the calling script's path.
 * patient/* → MEDICARE_PATIENT
 * doctor/*  → MEDICARE_DOCTOR
 * admin/*   → MEDICARE_ADMIN
 * auth/*    → derived from 'role' GET/POST param or stored pending-role cookie
 * Defaults  → MEDICARE_PATIENT
 */
function _medicare_session_name(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/admin/')   !== false) return 'MEDICARE_ADMIN';
    if (strpos($script, '/doctor/')  !== false) return 'MEDICARE_DOCTOR';
    if (strpos($script, '/patient/') !== false) return 'MEDICARE_PATIENT';

    // For auth/ files (login_step1, login_step2, register, logout, send_otp)
    // The role is passed as JSON body or stored in a temp cookie set at step1
    if (strpos($script, '/auth/') !== false) {
        // Try temp cookie first (set during login step1 / register step1)
        if (!empty($_COOKIE['mc_pending_role'])) {
            $r = $_COOKIE['mc_pending_role'];
            if ($r === 'admin')  return 'MEDICARE_ADMIN';
            if ($r === 'doctor') return 'MEDICARE_DOCTOR';
            return 'MEDICARE_PATIENT';
        }
        // Try reading role from JSON body (login_step1, register)
        $raw = file_get_contents('php://input');
        if ($raw) {
            $data = json_decode($raw, true) ?? [];
            $role = $data['role'] ?? '';
            if ($role === 'admin')  return 'MEDICARE_ADMIN';
            if ($role === 'doctor') return 'MEDICARE_DOCTOR';
        }
    }
    return 'MEDICARE_PATIENT';
}

// Start the correct session for this request.
// Skip entirely for the public login page (index.php at app root) — it must
// never read an existing portal session, or it can't show the login form
// while another role is already logged in elsewhere.
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$__isLoginPage = (basename($__script) === 'index.php');

if (!$__isLoginPage && session_status() === PHP_SESSION_NONE) {
    // Compute the app root path so every portal shares the SAME cookie path.
    // Without this, PHP defaults the cookie path to each script's own folder
    // (/healthcare/patient/, /healthcare/doctor/, ...) which causes the auth/
    // flow to write all cookies under /healthcare/auth/ and collide.
    $script = $__script;
    // app root = the folder that contains index.php (one level above patient/doctor/admin/auth)
    $appRoot = '/';
    foreach (['/patient/', '/doctor/', '/admin/', '/auth/'] as $seg) {
        $pos = strpos($script, $seg);
        if ($pos !== false) { $appRoot = substr($script, 0, $pos); break; }
    }
    if ($appRoot === '' ) $appRoot = '/';

    session_name(_medicare_session_name());
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $appRoot ?: '/',   // e.g. /healthcare  — shared by all three portals
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireRole(string $role, string $redirectTo = '/index.php'): void {
    if (!isLoggedIn()) {
        $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        header("Location: " . $base . "/index.php");
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        $dashboards = [
            'patient' => $base . '/patient/dashboard.php',
            'doctor'  => $base . '/doctor/dashboard.php',
            'admin'   => $base . '/admin/dashboard.php',
        ];
        header("Location: " . ($dashboards[$_SESSION['role']] ?? $base . '/index.php'));
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'name'  => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email']     ?? '',
        'role'  => $_SESSION['role']      ?? '',
        'photo' => $_SESSION['photo']     ?? '',
    ];
}

function setUserSession(array $user): void {
    // Ensure we're writing into the session named for THIS user's role,
    // regardless of how the auth request started. Critical for keeping all
    // three portals independent in separate tabs.
    $target = 'MEDICARE_PATIENT';
    if (($user['role'] ?? '') === 'admin')  $target = 'MEDICARE_ADMIN';
    if (($user['role'] ?? '') === 'doctor') $target = 'MEDICARE_DOCTOR';

    if (session_name() !== $target) {
        // Close whatever session auto-started, then open the correct one
        session_write_close();
        $script  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $appRoot = '/';
        foreach (['/patient/', '/doctor/', '/admin/', '/auth/'] as $seg) {
            $pos = strpos($script, $seg);
            if ($pos !== false) { $appRoot = substr($script, 0, $pos); break; }
        }
        session_name($target);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $appRoot ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['photo']     = $user['profile_photo'] ?? '';
}

/**
 * Sets a short-lived cookie so that auth/* files (step2, send_otp, logout)
 * know which session to open for this user's login flow.
 */
function setPendingRoleCookie(string $role): void {
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    setcookie('mc_pending_role', $role, [
        'expires'  => time() + 600,   // 10 minutes — enough to finish OTP flow
        'path'     => $base ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearPendingRoleCookie(): void {
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    setcookie('mc_pending_role', '', [
        'expires'  => time() - 3600,
        'path'     => $base ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function logout(): void {
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    session_unset();
    session_destroy();
    clearPendingRoleCookie();
    header("Location: " . $base . "/index.php");
    exit;
}

function generateOTP(int $length = 6): string {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>

