<?php
require_once __DIR__ . '/config.php';
startUserSession();

// اگر لاگین است، به پروفایل برو
if (getLoggedInUser()) {
    header('Location: /physics_academy/profile.php');
    exit;
}

$error = '';
$locked = false;

// ===== ورود =====
// کاربران فقط توسط ادمین با شماره دانشجویی + کد ملی ساخته می‌شوند.
// ثبت‌نام عمومی وجود ندارد. کد ملی در دیتابیس به‌صورت رمزنگاری‌شده (AES-256-GCM)
// ذخیره شده، بنابراین برای تطبیق، ردیف کاربر را بر اساس student_id می‌گیریم
// و سپس کد ملی رمزگشایی‌شده را با ورودی مقایسه می‌کنیم (hash_equals برای timing-safe).
// در صورت تیک خوردن «مرا به خاطر بسپار»، کوکی ۳۰ روزه صادر می‌شود.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $student_id    = trim($_POST['student_id'] ?? '');
    $national_code = trim($_POST['national_code'] ?? '');
    $remember_me   = !empty($_POST['remember_me']);

    $lockKey = 'user:' . $student_id . ':' . ($_SERVER['REMOTE_ADDR'] ?? '');

    if (isLoginLocked($lockKey)) {
        $locked = true;
        $error = 'به دلیل تلاش‌های ناموفق متعدد، ورود به مدت ' . LOGIN_LOCKOUT_MINUTES . ' دقیقه مسدود شده است. بعداً تلاش کنید.';
    } elseif (!$student_id || !$national_code) {
        $error = 'شماره دانشجویی و کد ملی را وارد کنید.';
    } else {
        $db = getDB();
        $s  = $db->prepare("SELECT id, national_code, is_active FROM users WHERE student_id = ? LIMIT 1");
        $s->execute([$student_id]);
        $u = $s->fetch();

        // تطبیق کد ملی: کد ملی دیتابیس رمزگشایی و با ورودی مقایسه می‌شود.
        $codeMatch = false;
        if ($u) {
            $decrypted = decryptNationalCode($u['national_code'] ?? '');
            $codeMatch = hash_equals($decrypted, $national_code);
        }

        if (!$u || !$codeMatch) {
            recordFailedLogin($lockKey);
            $error = 'شماره دانشجویی یا کد ملی اشتباه است.';
            if (isLoginLocked($lockKey)) {
                $locked = true;
                $error = 'به دلیل تلاش‌های ناموفق متعدد، ورود به مدت ' . LOGIN_LOCKOUT_MINUTES . ' دقیقه مسدود شده است.';
            }
        } elseif (!$u['is_active']) {
            $error = 'حساب شما غیرفعال شده است.';
        } else {
            clearFailedLogins($lockKey);
            $_SESSION['user_id'] = $u['id'];

            // صدور کوکی remember me در صورت درخواست کاربر
            if ($remember_me) {
                setRememberMeToken($u['id']);
            }

            $redirect = $_GET['redirect'] ?? '/physics_academy/profile.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:'Vazirmatn',Tahoma,sans-serif;min-height:100vh;
  display:grid;place-items:center;padding:20px;
  background:
    radial-gradient(1200px 600px at 90% -10%,rgba(245,196,81,.14),transparent 60%),
    radial-gradient(900px 500px at -10% 110%,rgba(217,168,58,.12),transparent 60%),
    linear-gradient(180deg,#0b0f15 0%,#0d1117 100%);
  color:#e6edf3;
}
.auth-box{
  width:100%;max-width:420px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.03));
  border:1px solid var(--stroke);border-radius:22px;
  box-shadow:0 20px 60px rgba(0,0,0,.5);overflow:hidden;
}
.auth-logo{text-align:center;padding:32px 32px 0;}
.auth-logo svg{width:50px;height:50px;stroke:var(--gold);fill:none;stroke-width:1.3;}
.auth-logo h1{font-size:1.2rem;font-weight:800;color:var(--text);margin-top:10px;}
.auth-logo p{font-size:.83rem;color:var(--muted);margin-top:4px;}
.auth-body{padding:28px 28px 32px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:.82rem;color:var(--muted);margin-bottom:5px;font-weight:500;}
.field input{
  width:100%;padding:10px 13px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.15);
  border-radius:10px;color:#e6edf3;font-family:inherit;font-size:.9rem;
  outline:none;transition:border-color .25s,box-shadow .25s;direction:ltr;text-align:right;
}
.field input:focus{border-color:rgba(245,196,81,.5);box-shadow:0 0 0 3px rgba(245,196,81,.15);}
.field input::placeholder{color:var(--muted);}
.field input:disabled{opacity:.5;cursor:not-allowed;}
.remember-row{display:flex;align-items:center;gap:8px;margin-bottom:4px;margin-top:-4px;}
.remember-row input[type=checkbox]{width:16px;height:16px;accent-color:var(--gold);cursor:pointer;flex-shrink:0;}
.remember-row label{font-size:.82rem;color:var(--muted);cursor:pointer;user-select:none;transition:color .2s;}
.remember-row:hover label{color:var(--text);}
.submit-btn{
  width:100%;padding:12px;margin-top:8px;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  border:none;border-radius:11px;color:#111;
  font-family:inherit;font-size:.95rem;font-weight:800;
  cursor:pointer;transition:all .3s;box-shadow:0 6px 18px rgba(245,196,81,.28);
}
.submit-btn:hover:not(:disabled){transform:translateY(-1px);filter:saturate(1.08);}
.submit-btn:disabled{opacity:.5;cursor:not-allowed;}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.28);border-radius:9px;padding:9px 13px;font-size:.85rem;color:#f87171;margin-bottom:14px;}
.info-msg{background:rgba(245,196,81,.07);border:1px solid rgba(245,196,81,.22);border-radius:9px;padding:9px 13px;font-size:.8rem;color:var(--muted);margin-bottom:16px;line-height:1.7;}
.back-link{display:block;text-align:center;margin-top:18px;font-size:.83rem;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--gold);}
body.light-mode .auth-box{background:#fff;border-color:rgba(70,65,55,.15);}
body.light-mode .field input{background:rgba(70,65,55,.04);border-color:rgba(70,65,55,.2);color:var(--text);}
body.light-mode .remember-row label{color:var(--muted);}
</style>
</head>
<body>
<?php include __DIR__ . "/partials/theme_init.php"; ?>
<div class="auth-box">
    <div class="auth-logo">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg>
        <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
        <p>ورود به پنل کاربری</p>
    </div>
    <div class="auth-body">
        <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="info-msg">
            حساب کاربری شما توسط دانشکده ساخته شده است. برای ورود، شماره دانشجویی و کد ملی خود را وارد کنید.
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label>شماره دانشجویی</label>
                <input type="text" name="student_id" placeholder="مثلاً: 1" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required autofocus <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="field">
                <label>کد ملی</label>
                <input type="text" name="national_code" placeholder="مثلاً: 25" required <?= $locked ? 'disabled' : '' ?>>
            </div>
            <div class="remember-row">
                <input type="checkbox" id="rememberMe" name="remember_me" value="1" <?= $locked ? 'disabled' : '' ?>>
                <label for="rememberMe">مرا به خاطر بسپار (۳۰ روز)</label>
            </div>
            <button type="submit" class="submit-btn" <?= $locked ? 'disabled' : '' ?>>ورود به حساب</button>
        </form>
        <a href="/physics_academy/" class="back-link">← بازگشت به سایت</a>
    </div>
</div>
<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
</body>
</html>
