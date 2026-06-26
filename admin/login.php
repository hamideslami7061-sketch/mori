<?php
require_once __DIR__ . '/auth.php';

if (isAdminLoggedIn()) {
    header('Location: /physics_academy/admin/');
    exit;
}

$error = '';
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if (isAdminLoginLocked($u)) {
        $locked = true;
        $error = 'به دلیل تلاش‌های ناموفق متعدد، حساب ادمین به مدت ' . LOGIN_LOCKOUT_MINUTES . ' دقیقه قفل شده است. بعداً تلاش کنید.';
    } elseif (adminLogin($u, $p)) {
        header('Location: /physics_academy/admin/');
        exit;
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
        // اگر بعد از این تلاش قفل شد، پیام را به‌روز کن
        if (isAdminLoginLocked($u)) {
            $locked = true;
            $error = 'به دلیل تلاش‌های ناموفق متعدد، حساب ادمین به مدت ' . LOGIN_LOCKOUT_MINUTES . ' دقیقه قفل شده است.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود ادمین | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap');
:root{--gold:#f5c451;--gold-2:#d9a83a;--stroke:rgba(255,255,255,.14);--muted:#9aa4b2;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Vazirmatn',Tahoma,sans-serif;min-height:100vh;display:grid;place-items:center;
  background:radial-gradient(1200px 600px at 90% -10%,rgba(245,196,81,.14),transparent 60%),
             radial-gradient(900px 500px at -10% 110%,rgba(217,168,58,.12),transparent 60%),
             linear-gradient(180deg,#0b0f15 0%,#0d1117 100%);
  color:#e6edf3;}
.login-box{
  width:100%;max-width:420px;padding:40px 36px;
  background:linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.03));
  border:1px solid var(--stroke);border-radius:22px;
  box-shadow:0 20px 60px rgba(0,0,0,.5);
}
.login-logo{text-align:center;margin-bottom:28px;}
.login-logo svg{width:54px;height:54px;stroke:var(--gold);fill:none;stroke-width:1.3;}
.login-logo h1{font-size:1.3rem;font-weight:800;color:#fff;margin-top:12px;}
.login-logo p{font-size:.85rem;color:var(--muted);margin-top:4px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:.85rem;color:var(--muted);margin-bottom:6px;font-weight:500;}
.field input{
  width:100%;padding:11px 14px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.16);
  border-radius:11px;color:#e6edf3;font-family:inherit;font-size:.95rem;
  outline:none;transition:border-color .25s,box-shadow .25s;direction:rtl;
}
.field input:focus{border-color:rgba(245,196,81,.55);box-shadow:0 0 0 3px rgba(245,196,81,.18);}
.field input::placeholder{color:var(--muted);}
.login-btn{
  width:100%;padding:13px;margin-top:8px;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  border:none;border-radius:12px;color:#111;
  font-family:inherit;font-size:1rem;font-weight:800;
  cursor:pointer;transition:all .3s;
  box-shadow:0 8px 22px rgba(245,196,81,.3);
}
.login-btn:hover:not(:disabled){transform:translateY(-1px);filter:saturate(1.08);}
.login-btn:disabled{opacity:.5;cursor:not-allowed;}
.error-msg{
  background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);
  border-radius:10px;padding:10px 14px;font-size:.88rem;color:#f87171;
  margin-bottom:16px;text-align:center;
}
.back-link{display:block;text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted);text-decoration:none;}
.back-link:hover{color:var(--gold);}
</style>
</head>
<body>
<div class="login-box">
    <div class="login-logo">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg>
        <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
        <p>پنل مدیریت</p>
    </div>

    <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">نام کاربری</label>
            <input type="text" id="username" name="username" placeholder="admin" autocomplete="username" required <?= $locked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
            <label for="password">رمز عبور</label>
            <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required <?= $locked ? 'disabled' : '' ?>>
        </div>
        <button type="submit" class="login-btn" <?= $locked ? 'disabled' : '' ?>>ورود به پنل</button>
    </form>
    <a href="/physics_academy/" class="back-link">← بازگشت به سایت</a>
</div>
</body>
</html>
