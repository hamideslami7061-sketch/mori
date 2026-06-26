<?php
// ===================================================================
//  دانشکده فیزیک — config.php (نسخه امنیتی تقویت‌شده)
//  شامل: CSRF + رمزنگاری کد ملی + هدرهای امنیتی + سیستم تلاش لاگین
// ===================================================================

// ===== تنظیمات دیتابیس =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'physics_academy');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ===== تنظیمات سایت =====
define('SITE_NAME', 'دانشکده فیزیک');

// ===== مدت اعتبار remember me (روز) =====
define('REMEMBER_ME_DAYS', 30);

// ===== کلید رمزنگاری کد ملی (AES-256-GCM) =====
// این کلید باید ۳۲ بایت (۶۴ کاراکتر هگز) باشد.
// ⚠️ برای امنیت، این مقدار را به یک رشته تصادفی تغییر دهید و آن را مخفی نگه دارید.
//    دستور تولید کلید جدید در PHP CLI:  php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
// اگر کلید تغییر کند، کدهای ملی قبلی غیرقابل رمزگشایی می‌شوند — پس آن را در جای امن نگه دارید.
define('NATIONAL_CODE_KEY_HEX', 'a1b2c3d4e5f67890abcdef1234567890abcdef1234567890abcdef1234567890');

// ===== تنظیمات ادمین =====
// نام کاربری ادمین
define('ADMIN_USERNAME', 'admin');
// رمز عبور ادمین به‌صورت هش bcrypt ذخیره می‌شود.
// برای تولید هش رمز جدید:  php -r 'echo password_hash("YOUR_PASSWORD", PASSWORD_DEFAULT).PHP_EOL;'
// پیش‌فرض: رمز «admin123» هش‌شده. حتماً قبل از استقرار تغییر دهید.
// برای تولید هش جدید: php -r 'echo password_hash("NEW_PASSWORD", PASSWORD_DEFAULT).PHP_EOL;'
// (PHP با password_verify با پیشوندهای $2y$، $2b$ و $2a$ سازگار است.)
define('admin123', '$2b$10$aAyg5yW1EkUmxrPmcRQ0z.JpJWU1dJ0ZJ/Nj10gzQOmsCUMUpXO0e');

// ===== تنظیمات لاگین امن =====
define('MAX_LOGIN_ATTEMPTS', 5);        // حداکثر تلاش ناموفق
define('LOGIN_LOCKOUT_MINUTES', 15);    // مدت قفل‌شدن (دقیقه)

// ===================================================================
//  هدرهای امنیتی (پیش از هر خروجی)
// ===================================================================
function applySecurityHeaders(): void {
    // فقط اگر هدرها هنوز ارسال نشده‌اند
    if (headers_sent()) return;

    // جلوگیری از Clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // جلوگیری از MIME-sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer Policy — حداقل نشت اطلاعات
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // HSTS — فقط در HTTPS فعال شود (در محیط توسعه HTTP تداخل ایجاد نکند)
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // CSP — سیاست امن محتوا (اجازه به اسکریپت‌های inline و same-origin)
    header("Content-Security-Policy: default-src 'self'; ".
           "script-src 'self' 'unsafe-inline' https://www.youtube.com https://player.vimeo.com; ".
           "frame-src 'self' https://www.youtube.com https://player.vimeo.com; ".
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
           "font-src 'self' https://fonts.gstatic.com; ".
           "img-src 'self' data: https:; ".
           "media-src 'self' https: blob:; ".
           "connect-src 'self'");
}
applySecurityHeaders();

// ===== اتصال به دیتابیس =====
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:30px;background:#1a1a2e;color:#ff6b6b;border-radius:12px;margin:40px auto;max-width:600px;border:1px solid #ff6b6b33;">
                <h2>خطای اتصال به دیتابیس</h2>
                <p>لطفاً بعداً تلاش کنید. اگر مشکل ادامه داشت با مدیر سایت تماس بگیرید.</p>
            </div>');
        }
    }
    return $pdo;
}

// ===================================================================
//  آیا در حال اجرا روی HTTPS هستیم؟
// ===================================================================
function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

// ===================================================================
//  session کاربر (با کوکی امن)
// ===================================================================
function startUserSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('pa_user');
        $https = isHttps();
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,  // فقط در HTTPS
        ]);
        session_start();
    }
}

// ===================================================================
//  سیستم CSRF (Cross-Site Request Forgery)
// ===================================================================
//  توکن در session ذخیره می‌شود و در هر فرم به‌صورت فیلد مخفی قرار می‌گیرد.
//  در هر درخواست POST، توکن فرم با توکن session مقایسه می‌شود.
//  با استفاده از hash_equals از حمله timing-safe محافظت می‌شود.

/** تولید یا بازگرداندن توکن CSRF session. */
function csrf_token(): string {
    startUserSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** رندر فیلد مخفی CSRF برای فرم‌ها. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * بررسی توکن CSRF برای درخواست‌های POST.
 * اگر توکن نامعتبر باشد، درخواست رد می‌شود.
 */
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    startUserSession();
    $token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
        http_response_code(419);
        die('<div style="font-family:sans-serif;padding:30px;background:#1a1a2e;color:#ff6b6b;border-radius:12px;margin:40px auto;max-width:600px;border:1px solid #ff6b6b33;text-align:center;">
            <h2>خطای امنیتی</h2>
            <p>توکن امنیتی نامعتبر است. لطفاً صفحه را تازه‌سازی و دوباره تلاش کنید.</p>
            <p><a href="javascript:history.back()" style="color:#f5c451">← بازگشت</a></p>
        </div>');
    }
}

// ===================================================================
//  رمزنگاری کد ملی (AES-256-GCM)
// ===================================================================
//  کد ملی به‌صورت متن رمزگذاری‌شده در دیتابیس ذخیره می‌شود
//  تا در صورت نشت دیتابیس، کدهای ملی افشا نشوند.
//  الگوریتم: AES-256-GCM (تأیید شده‌ی همزمان + رمزنگاری).
//  فرمت ذخیره: base64( IV (12B) || ciphertext || tag (16B) )

/** رمزنگاری کد ملی (یا هر متن حساس). */
function encryptNationalCode(string $plaintext): string {
    $key = hex2bin(NATIONAL_CODE_KEY_HEX);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('کلید رمزنگاری نامعتبر است. NATIONAL_CODE_KEY_HEX باید ۶۴ کاراکتر هگز باشد.');
    }
    $iv  = random_bytes(12); // 96-bit IV برای GCM
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag
    );
    if ($ciphertext === false) {
        throw new RuntimeException('رمزنگاری ناموفق بود.');
    }
    // IV || ciphertext || tag — سپس base64
    return base64_encode($iv . $ciphertext . $tag);
}

/** رمزگشایی کد ملی (یا هر متن حساس). */
function decryptNationalCode(string $encrypted): string {
    $key = hex2bin(NATIONAL_CODE_KEY_HEX);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('کلید رمزنگاری نامعتبر است.');
    }
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 28) { // 12 IV + 16 tag حداقل
        return ''; // مقدار نامعتبر
    }
    $iv         = substr($raw, 0, 12);
    $tag        = substr($raw, -16);
    $ciphertext = substr($raw, 12, -16);
    $plaintext  = openssl_decrypt(
        $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag
    );
    return $plaintext !== false ? $plaintext : '';
}

// ===================================================================
//  سیستم تلاش لاگین (Rate Limiting) — مبتنی بر دیتابیس
// ===================================================================
//  جلوگیری از Brute Force با ثبت تعداد تلاش‌های ناموفق در session+DB.

/** ثبت یک تلاش ناموفق لاگین برای کلید داده‌شده (مثلاً student_id یا IP). */
function recordFailedLogin(string $key): void {
    $db = getDB();
    // پاکسازی تلاش‌های قدیمی (قدیمی‌تر از ۱ ساعت)
    $db->prepare("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 HOUR)")
       ->execute();
    // ثبت تلاش ناموفق
    $db->prepare("INSERT INTO login_attempts (attempt_key, ip_address, attempted_at) VALUES (?,?,NOW())")
       ->execute([$key, $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** آیا کلید داده‌شده قفل است (تلاش‌های ناموفق بیش از حد مجاز)؟ */
function isLoginLocked(string $key): bool {
    $db = getDB();
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND attempted_at > ?");
    $stmt->execute([$key, $since]);
    return ((int)$stmt->fetchColumn()) >= MAX_LOGIN_ATTEMPTS;
}

/** پاکسازی تلاش‌های ناموفق پس از لاگین موفق. */
function clearFailedLogins(string $key): void {
    getDB()->prepare("DELETE FROM login_attempts WHERE attempt_key=?")->execute([$key]);
}

// ===================================================================
//  لاگ ممیزی ادمین (Audit Log)
// ===================================================================
function auditLog(string $action, string $entityType = '', int $entityId = 0, array $details = []): void {
    try {
        $db = getDB();
        $adminUser = $_SESSION['admin_user'] ?? 'unknown';
        $db->prepare("INSERT INTO admin_audit_log (admin_user, action, entity_type, entity_id, details, ip_address, created_at)
                      VALUES (?,?,?,?,?,?,NOW())")
           ->execute([
               $adminUser,
               $action,
               $entityType,
               $entityId,
               json_encode($details, JSON_UNESCAPED_UNICODE),
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

// ===================================================================
//  سیستم "مرا به خاطر بسپار" (remember me)
// ===================================================================

/** ست کردن توکن در کوکی و DB. */
function setRememberMeToken(int $user_id): void {
    $db      = getDB();
    $token   = bin2hex(random_bytes(32)); // 64-char hex
    $hash    = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + REMEMBER_ME_DAYS * 86400);

    // پاک کردن توکن‌های منقضی‌شده این کاربر
    $db->prepare("DELETE FROM remember_tokens WHERE user_id=? AND expires_at < NOW()")
       ->execute([$user_id]);

    // محدود کردن به ۵ دستگاه همزمان (قدیمی‌ترین حذف می‌شود)
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id=?");
    $cntStmt->execute([$user_id]);
    $count = (int)$cntStmt->fetchColumn();
    if ($count >= 5) {
        $db->prepare("DELETE FROM remember_tokens WHERE user_id=? ORDER BY created_at ASC LIMIT 1")
           ->execute([$user_id]);
    }

    $db->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)")
       ->execute([$user_id, $hash, $expires]);

    $cookie_val = $user_id . '|' . $token;
    setcookie('pa_remember', $cookie_val, [
        'expires'  => time() + REMEMBER_ME_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttps(),
    ]);
}

/** پاک کردن توکن (هنگام خروج). */
function clearRememberMeToken(): void {
    if (!empty($_COOKIE['pa_remember'])) {
        $parts = explode('|', $_COOKIE['pa_remember'], 2);
        if (count($parts) === 2) {
            $hash = hash('sha256', $parts[1]);
            getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash=?")->execute([$hash]);
        }
    }
    setcookie('pa_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isHttps(),
    ]);
}

/** بررسی کوکی و لاگین خودکار. */
function loginFromRememberMe(): bool {
    if (empty($_COOKIE['pa_remember'])) return false;
    $parts = explode('|', $_COOKIE['pa_remember'], 2);
    if (count($parts) !== 2) return false;

    [$uid_str, $token] = $parts;
    $uid  = (int)$uid_str;
    $hash = hash('sha256', $token);

    $db  = getDB();
    $row = $db->prepare("
        SELECT rt.*, u.id AS uid, u.is_active
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.user_id = ? AND rt.token_hash = ? AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $row->execute([$uid, $hash]);
    $r = $row->fetch();

    if (!$r || !$r['is_active']) {
        clearRememberMeToken();
        return false;
    }

    $_SESSION['user_id'] = $r['uid'];

    // توکن چرخشی: توکن قدیمی حذف، توکن جدید صادر می‌شود
    $db->prepare("DELETE FROM remember_tokens WHERE token_hash=?")->execute([$hash]);
    setRememberMeToken($r['uid']);

    return true;
}

// ===================================================================
//  کاربر لاگین‌شده
// ===================================================================
//  کاربران فقط توسط ادمین ساخته می‌شوند (شماره دانشجویی + کد ملی، بدون ثبت‌نام)
//  ابتدا session چک می‌شود، سپس کوکی remember me.
//  کد ملی به‌صورت رمزنگاری‌شده در DB ذخیره شده و در اینجا رمزگشایی می‌شود
//  (فقط برای تطبیق با ورودی کاربر — در عموم صفحات نیازی به نمایش آن نیست).
function getLoggedInUser(): ?array {
    startUserSession();

    // ۱) ابتدا session را چک کن
    if (!empty($_SESSION['user_id'])) {
        static $cache = null;
        if ($cache === null) {
            $s = getDB()->prepare("
                SELECT id, name, student_id, national_code, is_active
                FROM users WHERE id = ? AND is_active = 1
            ");
            $s->execute([$_SESSION['user_id']]);
            $cache = $s->fetch() ?: null;
            if (!$cache) {
                session_destroy();
                clearRememberMeToken();
            }
        }
        return $cache;
    }

    // ۲) session خالی است — کوکی remember me را امتحان کن
    if (!empty($_COOKIE['pa_remember'])) {
        if (loginFromRememberMe()) {
            static $rm_cache = null;
            if ($rm_cache === null) {
                $s = getDB()->prepare("
                    SELECT id, name, student_id, national_code, is_active
                    FROM users WHERE id = ? AND is_active = 1
                ");
                $s->execute([$_SESSION['user_id']]);
                $rm_cache = $s->fetch() ?: null;
            }
            return $rm_cache;
        }
    }

    return null;
}

function requireUser(): void {
    if (!getLoggedInUser()) {
        header('Location: /physics_academy/auth.php');
        exit;
    }
}

// ===================================================================
//  توابع کمکی
// ===================================================================

function slugify_fa(string $text): string {
    $map = [
        'آ'=>'a','ا'=>'a','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s','ج'=>'j','چ'=>'ch','ح'=>'h','خ'=>'kh',
        'د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'z','ط'=>'t',
        'ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'gh','ک'=>'k','گ'=>'g','ل'=>'l','م'=>'m','ن'=>'n',
        'و'=>'v','ه'=>'h','ی'=>'y','ئ'=>'y','ء'=>'','‌'=>'-',' '=>'-','ـ'=>'-',
    ];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\-]+/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function en_to_fa(string $num): string {
    return str_replace(
        ['0','1','2','3','4','5','6','7','8','9'],
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        $num
    );
}

function detectVideoType(string $url): string {
    if (preg_match('/youtube\.com|youtu\.be/', $url)) return 'youtube';
    if (preg_match('/vimeo\.com/', $url))            return 'vimeo';
    return 'direct';
}

function getYoutubeId(string $url): string {
    preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/', $url, $m);
    return $m[1] ?? '';
}

function getVimeoId(string $url): string {
    preg_match('/vimeo\.com\/(\d+)/', $url, $m);
    return $m[1] ?? '';
}
