# دانشکده فیزیک — نسخه امنیتی تقویت‌شده + ادامه تماشا + محتوای جدید

این نسخه شامل ارتقای امنیتی جدی، بهینه‌سازی دیتابیس برای ۱۰۰۰+ کاربر، و قابلیت‌های جدید است.

## 🆕 تغییرات این نسخه

### 🔒 امنیت (تقویت‌شده)

| قابلیت | توضیح |
|--------|-------|
| **CSRF Protection** | همه فرم‌ها و درخواست‌های AJAX با توکن CSRF محافظت می‌شوند. درخواست‌های POST، GET تغییر وضعیت (delete/toggle/grant)، و درخواست‌های AJAX (progress/resume) همگی چک می‌شوند. |
| **رمزنگاری کد ملی** | کدهای ملی با **AES-256-GCM** در دیتابیس ذخیره می‌شوند. در صورت نشت دیتابیس، کدهای ملی افشا نمی‌شوند. ادمین می‌تواند آن‌ها را در پنل رمزگشایی و ببیند/بررسی کند. |
| **هش bcrypt رمز ادمین** | رمز عبور ادمین به‌صورت plaintext در config.php نیست — هش bcrypt است. |
| **محافظت از Brute Force** | بعد از ۵ تلاش ناموفق (به‌ازای هر username+IP)، ورود ۱۵ دقیقه قفل می‌شود. برای ادمین و کاربران جداگانه. |
| **هدرهای امنیتی** | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `HSTS` (در HTTPS), و `Content-Security-Policy`. |
| **کوکی Secure** | فلگ `Secure` روی کوکی‌های session و remember-me در HTTPS فعال می‌شود. |
| **لاگ ممیزی** | تمام عملیات ادمین (افزودن/ویرایش/حذف کاربر، دوره، استاد، محتوا، مرجع، تغییر دسترسی، ورود/خروج) در جدول `admin_audit_log` ثبت می‌شود. |

### 📊 بهینه‌سازی دیتابیس (برای ۱۰۰۰+ کاربر)

- **ایندکس‌های ترکیبی** روی همه کلیدهای پراستفاده
- **کوئری‌های بهینه**:
  - homepage: ایندکس روی `(is_active, content_updated_at DESC, id DESC)` برای مرتب‌سازی سریع
  - profile: `LEFT JOIN` به `user_course_state` به‌جای کوئری جداگانه
  - admin/courses list: `LEFT JOIN + GROUP BY` به‌جای correlated subquery
  - progress: ایندکس روی `(user_id, course_id)` برای `SUM(progress)` سریع
- جدول‌های جدید: `user_course_state`, `login_attempts`, `admin_audit_log`

### 🎬 قابلیت «ادامه تماشا» (Continue Watching)

- موقعیت دقیق ویدیو (ثانیه) برای هر کاربر/دوره/محتوا ذخیره می‌شود
- هر ۱۵ ثانیه + هنگام تعویض محتوا + هنگام خروج از صفحه ذخیره می‌شود
- در **صفحه اصلی**: بخش «ادامه تماشا» با کارت‌های دوره + دکمه پخش
- در **پروفایل**: دکمه «ادامه تماشا» روی هر دوره ذخیره‌شده + زمان باقی‌مانده
- در **صفحه دوره**: اگر از لینک resume بیایید، ویدیو از همان ثانیه پخش می‌شود + Toast اطلاع‌رسانی

### 🆕 «محتوای جدید» — دوره‌های اخیر

- هنگام افزودن/حذف محتوا یا مرجع، `content_updated_at` دوره به‌روز می‌شود
- صفحه اصلی دوره‌ها را بر اساس `content_updated_at` مرتب می‌کند (نه `created_at`)
- دوره‌هایی که در ۷ روز اخیر محتوا گرفته‌اند، بج سبز **«محتوای جدید»** روی کارت می‌گیرند

### ⏳ حالت‌های Loading

- **اسپینر ویدیو**: هنگام بارگذاری/بافرینگ ویدیو، اسپینر طلایی نمایش داده می‌شود
- **Toast**: اعلان‌های موقت برای «ادامه از MM:SS»، خطاهای بارگذاری، و غیره
- پیام‌های واضح به‌جای صفحه سفید

---

## 📁 فایل‌های تغییر‌کرده

| فایل | تغییرات |
|------|---------|
| `config.php` | ⭐ بازنویسی کامل: CSRF، رمزنگاری AES-256-GCM، throttling، audit log، هدرهای امنیتی، `isHttps()` |
| `auth.php` | CSRF + throttling + تطبیق کد ملی رمزگشایی‌شده + پیام قفل |
| `admin/auth.php` | هش bcrypt + throttling ادمین + audit log ورود/خروج |
| `admin/login.php` | CSRF + پیام قفل + disable هنگام قفل |
| `admin/users.php` | CSRF + رمزنگاری کد ملی + audit log + رمزگشایی برای نمایش |
| `admin/courses.php` | CSRF + audit log + bump `content_updated_at` + کوئری بهینه list |
| `admin/professors.php` | CSRF + audit log |
| `admin/content_add.php` | CSRF + audit log + bump `content_updated_at` ⭐ |
| `admin/content_delete.php` | CSRF + audit log + bump `content_updated_at` |
| `admin/reference_add.php` | CSRF + audit log + bump `content_updated_at` |
| `admin/reference_delete.php` | CSRF + audit log + bump `content_updated_at` |
| `admin/index.php` | کوئری اخیر بر اساس `content_updated_at` |
| `profile.php` | CSRF + کوئری بهینه (LEFT JOIN به user_course_state) + دکمه «ادامه تماشا» |
| `course.php` | CSRF + اسپینر لودینگ + Toast + resume از URL + ذخیره دوره‌ای position + CSRF برای AJAX |
| `index.php` | بخش «ادامه تماشا» + بج «محتوای جدید» + مرتب‌سازی بر اساس `content_updated_at` |
| `progress_mark.php` | CSRF برای AJAX (هدر X-CSRF-Token) |
| `resume_save.php` | **جدید** — endpoint ذخیره/بازخوانی وضعیت ادامه تماشا |
| `database.sql` | ⭐ بازنویسی کامل: ۳ جدول جدید + ۷ ایندکس + مهاجرت ستون‌های idempotent |

---

## 🚀 نصب تازه

1. دیتابیس MySQL `physics_academy` بسازید.
2. فایل **`database.sql`** را در phpMyAdmin اجرا کنید.
3. **مهم**: در `config.php`:
   - `ADMIN_PASSWORD_HASH` را با هش رمز دلخواه خود جایگزین کنید:
     ```bash
     php -r 'echo password_hash("YOUR_PASSWORD", PASSWORD_DEFAULT).PHP_EOL;'
     ```
   - `NATIONAL_CODE_KEY_HEX` را با کلید تصادفی ۳۲ بایتی جایگزین کنید:
     ```bash
     php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
     ```
     ⚠️ این کلید را در جای امن نگه دارید — اگر تغییر کند، کدهای ملی قبلی غیرقابل رمزگشایی می‌شوند.
4. تنظیمات DB در `config.php` را بررسی کنید.
5. کل پوشه را در `/physics_academy` وب‌سرور قرار دهید.
6. وارد پنل ادمین شوید (`/physics_academy/admin/login.php`) و کاربران را اضافه کنید.

---

## 🔄 ارتقا از نسخه قبلی

اگر نسخه قبلی را نصب کرده‌اید:

1. فایل‌های PHP را با این نسخه جایگزین کنید.
2. فایل **`database.sql`** را در phpMyAdmin اجرا کنید:
   - جداول موجود دست‌نخورده می‌مانند (`CREATE TABLE IF NOT EXISTS`).
   - ستون‌های جدید (`progress`, `content_updated_at`, `national_code` گسترش‌یافته) به‌صورت ایمن اضافه می‌شوند.
   - ۷ ایندکس جدید اضافه می‌شود.
   - ۳ جدول جدید (`user_course_state`, `login_attempts`, `admin_audit_log`) ساخته می‌شود.
3. **مهم — رمزنگاری کدهای ملی موجود**:
   - در `config.php` کلید `NATIONAL_CODE_KEY_HEX` را تنظیم کنید.
   - چون کدهای ملی فعلی plaintext هستند، باید یک‌بار رمزنگاری شوند. فایل موقت زیر را در root پروژه بسازید و در مرورگر باز کنید:
     ```php
     <?php
     // migrate_encrypt_codes.php — بعد از اجرا حذف کنید!
     require_once __DIR__.'/config.php';
     $db = getDB();
     $rows = $db->query("SELECT id, national_code FROM users WHERE national_code != ''")->fetchAll();
     $st = $db->prepare("UPDATE users SET national_code=? WHERE id=?");
     $count = 0;
     foreach ($rows as $r) {
         // اگر قبلاً رمزنگاری شده (base64 طولانی)، رد کن
         if (strlen($r['national_code']) > 60) continue;
         $st->execute([encryptNationalCode($r['national_code']), $r['id']]);
         $count++;
     }
     echo "Done. $count codes encrypted.";
     ```
   - بعد از اجرا، **فایل را حذف کنید**.
4. **رمز ادمین**: در `config.php` مقدار `ADMIN_PASSWORD_HASH` را با هش رمز دلخواه جایگزین کنید.

---

## 🔐 نکات امنیتی

- **کلید رمزنگاری** (`NATIONAL_CODE_KEY_HEX`) را هرگز commit نکنید و در جای امن نگه دارید.
- **رمز ادمین** پیش‌فرض `admin123` است — حتماً قبل از استقرار تغییر دهید.
- کوکی‌ها در HTTPS فلگ `Secure` می‌گیرند.
- CSRF token در session ذخیره و با `hash_equals` (timing-safe) مقایسه می‌شود.
- throttling: ۵ تلاش ناموفق → ۱۵ دقیقه قفل (به‌ازای username+IP).
- توکن‌های remember-me هش SHA-256 + چرخشی + حداکثر ۵ دستگاه.
- CSP از اجرای اسکریپت‌های خارجی (به‌جز YouTube/Vimeo) جلوگیری می‌کند.

---

## 📊 ایندکس‌های دیتابیس (بهینه برای ۱۰۰۰+ کاربر)

| جدول | ایندکس | کاربرد |
|------|--------|--------|
| `courses` | `(is_active, content_updated_at DESC, id DESC)` | homepage «دوره‌های اخیر» |
| `course_contents` | `(course_id, sort_order, id)` | گرفتن محتوای دوره |
| `course_references` | `(course_id, sort_order, id)` | گرفتن مراجع دوره |
| `user_content_progress` | `(user_id, course_id)` | کوئری درصد پیشرفت |
| `user_saved_courses` | `(user_id, saved_at DESC)` | دوره‌های ذخیره‌شده کاربر |
| `user_courses` | `(user_id)` | شمارش دوره‌های کاربر |
| `remember_tokens` | `(user_id, token_hash)` | بررسی کوکی remember-me |
| `login_attempts` | `(attempt_key, attempted_at)` | throttling |
| `admin_audit_log` | `(admin_user, created_at)`, `(entity_type, entity_id)` | جستجوی لاگ |

---

## 🗂 ساختار فایل‌ها

```
physics_academy_merged/
├── config.php                    # ⭐ امنیتی: CSRF + AES-256-GCM + throttling + audit
├── auth.php                      # ورود کاربر (CSRF + throttling)
├── logout.php                    # خروج + پاک کردن کوکی
├── progress_mark.php             # AJAX ثبت درصد پیشرفت (CSRF هدر)
├── resume_save.php               # ⭐ جدید: AJAX ذخیره/بازخوانی ادامه تماشا
├── profile.php                   # پروفایل + دکمه ادامه تماشا
├── course.php                    # صفحه دوره + اسپینر + Toast + resume
├── courses.php                   # لیست دوره‌ها
├── professors.php / professor.php
├── index.php                     # ⭐ بخش ادامه تماشا + بج محتوای جدید
├── database.sql                  # ⭐ یکپارچه: ۳ جدول جدید + ۷ ایندکس
├── assets/ (shared.css, shared.js)
├── partials/ (navbar, theme_toggle, ...)
└── admin/ (پنل مدیریت: CSRF + audit log در همه)
```
#   m o r i  
 