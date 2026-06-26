<?php
require_once __DIR__ . '/../config.php';

// session ادمین کاملاً جداست از session کاربر
if (session_status() === PHP_SESSION_NONE) {
    session_name('pa_admin');
    $https = isHttps();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /physics_academy/admin/login.php');
        exit;
    }
}

/**
 * لاگین ادمین با بررسی هش bcrypt + محدودسازی تلاش.
 * کلید throttling: نام کاربری + IP (تا هم brute-force روی یک کاربر و هم روی یک IP را مسدود کند).
 */
function adminLogin(string $username, string $password): bool {
    // throttling بر اساس username + IP
    $lockKey = 'admin:' . $username . ':' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (isLoginLocked($lockKey)) {
        return false; // قفل است — پیام خطا توسط caller نمایش داده می‌شود
    }

    // بررسی تطابق نام کاربری
    if (!hash_equals(ADMIN_USERNAME, $username)) {
        recordFailedLogin($lockKey);
        return false;
    }

    // بررسی رمز عبور با password_verify (timing-safe)
    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        recordFailedLogin($lockKey);
        return false;
    }

    // لاگین موفق
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user']      = $username;
    clearFailedLogins($lockKey);
    auditLog('login', 'admin', 0, ['username' => $username]);
    return true;
}

/** آیا قفل تلاش ادمین فعال است؟ (برای پیام خطای مناسب در login.php) */
function isAdminLoginLocked(string $username): bool {
    return isLoginLocked('admin:' . $username . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function adminLogout(): void {
    auditLog('logout', 'admin', 0, ['username' => $_SESSION['admin_user'] ?? '']);
    $_SESSION = [];
    session_destroy();
    header('Location: /physics_academy/admin/login.php');
    exit;
}
