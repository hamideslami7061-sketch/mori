-- ================================================================
-- دانشکده فیزیک — دیتابیس یکپارچه (نسخه نهایی + بهینه‌سازی برای ۱۰۰۰+ کاربر)
-- ================================================================
-- این فایل شامل تمام جداول، مهاجرت‌ها و ایندکس‌های بهینه است.
-- جایگزین فایل‌های قبلی: database.sql, migration_add_references.sql,
-- migration_add_remember_and_progress.sql, migration_users_studentid.sql
--
-- نحوه استفاده:
--   ◦ نصب تازه: کل این فایل را در phpMyAdmin اجرا کنید.
--   ◦ ارتقا از نسخه قبلی: کل این فایل را اجرا کنید. جداول موجود
--     دست‌نخورده می‌مانند (CREATE TABLE IF NOT EXISTS) و فقط ستون‌ها/
--     ایندکس‌های جدید اضافه می‌شوند. همه دستورات idempotent هستند.
--
-- ⭐ بهینه‌سازی برای ۱۰۰۰+ کاربر:
--   - ایندکس‌های ترکیبی روی همه کلیدهای پراستفاده
--   - کوئری‌های profile و homepage با JOIN به‌جای subqueryهای همبسته
--   - ایندکس روی content_updated_at برای مرتب‌سازی سریع «دوره‌های اخیر»
-- ================================================================

CREATE DATABASE IF NOT EXISTS `physics_academy`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_persian_ci;

USE `physics_academy`;

-- =============================
-- بخش ۱: جداول اصلی
-- =============================

-- ----- جدول اساتید -----
CREATE TABLE IF NOT EXISTS `professors` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(120)  NOT NULL,
  `slug`         VARCHAR(160)  NOT NULL UNIQUE,
  `photo_url`    VARCHAR(500)  NOT NULL DEFAULT '',
  `field`        VARCHAR(200)  NOT NULL DEFAULT '',
  `degree`       VARCHAR(200)  NOT NULL DEFAULT '',
  `rank`         VARCHAR(100)  NOT NULL DEFAULT '',
  `bio`          TEXT          NOT NULL,
  `research`     TEXT          NOT NULL DEFAULT '',
  `awards`       TEXT          NOT NULL DEFAULT '',
  `publications` TEXT          NOT NULL DEFAULT '',
  `url`          VARCHAR(1000) NOT NULL DEFAULT '',
  `sort_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `experience`   TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول دوره‌ها -----
-- content_updated_at: زمان آخرین افزودن/حذف محتوا یا مرجع.
--   برای مرتب‌سازی «دوره‌های اخیر» در صفحه اصلی استفاده می‌شود.
CREATE TABLE IF NOT EXISTS `courses` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`               VARCHAR(200)  NOT NULL UNIQUE,
  `title`              VARCHAR(200)  NOT NULL,
  `professor_id`       INT UNSIGNED  NOT NULL,
  `sessions`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `url`                VARCHAR(1000) NOT NULL DEFAULT '',
  `sort_order`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `duration`           VARCHAR(60)   NOT NULL DEFAULT '',
  `description`        TEXT          NOT NULL DEFAULT '',
  `topics`             TEXT          NOT NULL DEFAULT '',
  `is_active`          TINYINT(1)    NOT NULL DEFAULT 1,
  `content_updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`professor_id`) REFERENCES `professors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول محتوای دوره -----
CREATE TABLE IF NOT EXISTS `course_contents` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id`  INT UNSIGNED NOT NULL,
  `type`       ENUM('video','pdf') NOT NULL DEFAULT 'video',
  `title`      VARCHAR(255)  NOT NULL,
  `url`        VARCHAR(1000) NOT NULL DEFAULT '',
  `duration`   VARCHAR(60)   NOT NULL DEFAULT '',
  `file_size`  VARCHAR(60)   NOT NULL DEFAULT '',
  `file_path`  VARCHAR(500)  NOT NULL DEFAULT '',
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول مراجع دوره -----
CREATE TABLE IF NOT EXISTS `course_references` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id`  INT UNSIGNED NOT NULL,
  `title`      VARCHAR(255)  NOT NULL,
  `file_url`   VARCHAR(1000) NOT NULL DEFAULT '',
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول کاربران -----
-- فقط با شماره دانشجویی + کد ملی — بدون ثبت‌نام عمومی.
-- national_code به‌صورت رمزنگاری‌شده (AES-256-GCM) ذخیره می‌شود.
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120) NOT NULL,
  `student_id`    VARCHAR(50)  NOT NULL UNIQUE,
  `national_code` VARCHAR(500) NOT NULL DEFAULT '',  -- طول بیشتر برای متن رمزنگاری‌شده
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول دسترسی کاربران به دوره‌ها (قدیمی — دیگر استفاده نمی‌شود) -----
-- نگه داشته شده برای سازگاری با نسخه‌های قبلی. در نسخه جدید، مدیریت دسترسی
-- از طریق جدول user_course_blocks (blocklist) انجام می‌شود.
CREATE TABLE IF NOT EXISTS `user_courses` (
  `user_id`    INT UNSIGNED NOT NULL,
  `course_id`  INT UNSIGNED NOT NULL,
  `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول دوره‌های مسدودشده برای کاربر (Blocklist) -----
-- مدل دسترسی: پیش‌فرض همه کاربران به همه دوره‌ها دسترسی دارند.
-- ادمین می‌تواند با افزودن ردیف به این جدول، دسترسی کاربر خاص به دوره خاص را قطع کند.
-- اگر ردیفی برای (user_id, course_id) وجود داشته باشد، دسترسی مسدود است.
CREATE TABLE IF NOT EXISTS `user_course_blocks` (
  `user_id`    INT UNSIGNED NOT NULL,
  `course_id`  INT UNSIGNED NOT NULL,
  `blocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول ذخیره دوره توسط کاربر -----
CREATE TABLE IF NOT EXISTS `user_saved_courses` (
  `user_id`   INT UNSIGNED NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `saved_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول توکن‌های «مرا به خاطر بسپار» -----
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(128) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول پیشرفت کاربر در محتوای دوره -----
-- progress: درصد تماشای هر محتوا (۰ تا ۱۰۰).
CREATE TABLE IF NOT EXISTS `user_content_progress` (
  `user_id`    INT UNSIGNED NOT NULL,
  `content_id` INT UNSIGNED NOT NULL,
  `course_id`  INT UNSIGNED NOT NULL,
  `progress`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `content_id`),
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)           ON DELETE CASCADE,
  FOREIGN KEY (`content_id`) REFERENCES `course_contents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول وضعیت «ادامه تماشا» هر کاربر برای هر دوره -----
-- last_content_id: آخرین محتوایی که کاربر باز کرده
-- last_position_seconds: ثانیه‌ای که ویدیو در آن متوقف شده (برای resume)
CREATE TABLE IF NOT EXISTS `user_course_state` (
  `user_id`               INT UNSIGNED NOT NULL,
  `course_id`             INT UNSIGNED NOT NULL,
  `last_content_id`       INT UNSIGNED NULL,
  `last_position_seconds` FLOAT NOT NULL DEFAULT 0,
  `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول ثبت تلاش‌های لاگین ناموفق (Rate Limiting / Anti-Brute-Force) -----
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `attempt_key`   VARCHAR(255) NOT NULL,  -- مثلاً 'user:STUDENT_ID:IP' یا 'admin:USERNAME:IP'
  `ip_address`    VARCHAR(45)  NOT NULL DEFAULT '',
  `attempted_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_attempt_key_time` (`attempt_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- ----- جدول لاگ ممیزی ادمین (Audit Log) -----
CREATE TABLE IF NOT EXISTS `admin_audit_log` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_user`   VARCHAR(120) NOT NULL,
  `action`       VARCHAR(50)  NOT NULL,
  `entity_type`  VARCHAR(50)  NOT NULL DEFAULT '',
  `entity_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `details`      TEXT         NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admin_action_time` (`admin_user`, `created_at`),
  INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- =============================
-- بخش ۲: مهاجرت ستون‌های جدید (idempotent — ایمن برای اجرای مجدد)
-- =============================

-- اضافه‌کردن ستون progress به user_content_progress موجود (نسخه‌های قدیمی)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'user_content_progress' AND column_name = 'progress');
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE `user_content_progress` ADD COLUMN `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `course_id`',
    'SELECT "progress column exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- اضافه‌کردن ستون content_updated_at به courses موجود
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'content_updated_at');
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE `courses` ADD COLUMN `content_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `is_active`',
    'SELECT "content_updated_at column exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- گسترش ستون national_code برای ذخیره متن رمزنگاری‌شده (تا ۵۰۰ کاراکتر)
SET @col_type = (SELECT column_type FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'national_code');
-- فقط اگر ستون وجود دارد و از نوع VARCHAR با طول کمتر از ۵۰۰ است
SET @ddl = IF(@col_type IS NOT NULL AND @col_type NOT LIKE '%500%',
    'ALTER TABLE `users` MODIFY `national_code` VARCHAR(500) NOT NULL DEFAULT ""',
    'SELECT "national_code column ok" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- =============================
-- بخش ۳: ایندکس‌های بهینه‌سازی (برای ۱۰۰۰+ کاربر)
-- =============================
-- این ایندکس‌ها برای کوئری‌های پراستفاده در homepage, profile, course اضافه شده‌اند.
-- وجود ایندکس با CREATE INDEX IF NOT EXISTS چک نمی‌شود (MySQL قدیمی)،
-- پس از یک ترفند information_schema استفاده می‌کنیم.

-- helper: ایجاد ایندکس فقط اگر وجود ندارد
-- (هر کدام در یک prepared statement جداگانه)

-- courses: مرتب‌سازی «دوره‌های اخیر» بر اساس content_updated_at
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'courses' AND index_name = 'idx_courses_active_updated');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_courses_active_updated` ON `courses` (`is_active`, `content_updated_at` DESC, `id` DESC)',
    'SELECT "idx_courses_active_updated exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- course_contents: گرفتن محتوای یک دوره با ترتیب صحیح
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'course_contents' AND index_name = 'idx_cc_course_sort');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_cc_course_sort` ON `course_contents` (`course_id`, `sort_order`, `id`)',
    'SELECT "idx_cc_course_sort exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- course_references: گرفتن مراجع یک دوره
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'course_references' AND index_name = 'idx_cref_course_sort');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_cref_course_sort` ON `course_references` (`course_id`, `sort_order`, `id`)',
    'SELECT "idx_cref_course_sort exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- user_content_progress: کوئری درصد پیشرفت (SUM(progress) WHERE user_id AND course_id)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'user_content_progress' AND index_name = 'idx_ucp_user_course');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_ucp_user_course` ON `user_content_progress` (`user_id`, `course_id`)',
    'SELECT "idx_ucp_user_course exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- user_saved_courses: کوئری دوره‌های ذخیره‌شده یک کاربر
-- (PK روی user_id+course_id هست، ولی ایندکس اضافی روی user_id برای sort_by saved_at مفید است)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'user_saved_courses' AND index_name = 'idx_usc_user_saved');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_usc_user_saved` ON `user_saved_courses` (`user_id`, `saved_at` DESC)',
    'SELECT "idx_usc_user_saved exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- user_courses: کوئری دسترسی کاربر (PK کافی است ولی برای شمارش دوره‌های کاربر)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'user_courses' AND index_name = 'idx_uc_user');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_uc_user` ON `user_courses` (`user_id`)',
    'SELECT "idx_uc_user exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- remember_tokens: کوئری بررسی توکن (user_id + token_hash)
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'remember_tokens' AND index_name = 'idx_rt_user_token');
SET @ddl = IF(@idx_exists = 0,
    'CREATE INDEX `idx_rt_user_token` ON `remember_tokens` (`user_id`, `token_hash`)',
    'SELECT "idx_rt_user_token exists" AS status');
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- =============================
-- بخش ۴: مهاجرت از نسخه ۱ (ایمیل/رمز عبور → شماره دانشجویی/کد ملی)
-- =============================
-- ⚠️ فقط برای کاربرانی که قبلاً نسخه ۱ را نصب کرده‌اند.
-- این بخش به‌صورت comment است — برای اجرا، خطوط زیر را از comment خارج کنید.
--
-- TRUNCATE TABLE `user_saved_courses`;
-- TRUNCATE TABLE `user_courses`;
-- TRUNCATE TABLE `users`;
-- ALTER TABLE `users` DROP INDEX `email`;
-- ALTER TABLE `users` DROP COLUMN `email`;
-- ALTER TABLE `users` DROP COLUMN `password`;
-- ALTER TABLE `users`
--   ADD COLUMN `student_id`    VARCHAR(50) NOT NULL DEFAULT '' AFTER `name`,
--   ADD COLUMN `national_code` VARCHAR(500) NOT NULL DEFAULT '' AFTER `student_id`;
-- ALTER TABLE `users` ADD UNIQUE KEY `student_id` (`student_id`);

-- =============================
-- بخش ۵: مهاجرت کدهای ملی موجود به فرمت رمزنگاری‌شده (یک‌بار)
-- =============================
-- ⚠️ این بخش فقط یک بار و تنها اگر کدهای ملی فعلی به‌صورت plaintext هستند اجرا شود.
-- پس از فعال‌سازی رمزنگاری در config.php، کدهای ملی موجود باید رمزنگاری شوند.
-- این کار را نمی‌توان در SQL انجام داد (نیاز به کلید PHP دارد) — به‌جای آن:
--   1. فایل migrate_encrypt_codes.php موقتاً در root پروژه بسازید:
--      <?php
--      require_once __DIR__.'/config.php';
--      $db=getDB();
--      $rows=$db->query("SELECT id, national_code FROM users WHERE national_code != ''")->fetchAll();
--      $st=$db->prepare("UPDATE users SET national_code=? WHERE id=?");
--      foreach($rows as $r){
--          // اگر قبلاً رمزنگاری شده (base64 طولانی)، رد کن
--          if(strlen($r['national_code'])>60) continue;
--          $st->execute([encryptNationalCode($r['national_code']), $r['id']]);
--      }
--      echo "Done. ".$db->query("SELECT COUNT(*) FROM users")->fetchColumn()." users.";
--   2. در مرورگر یا CLI اجرا کنید.
--   3. فایل را حذف کنید.
-- (در نصب تازه نیازی به این نیست — کدهای ملی از ابتدا رمزنگاری‌شده ذخیره می‌شوند.)
