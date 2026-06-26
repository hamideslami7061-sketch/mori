<?php
require_once __DIR__ . '/config.php';
startUserSession();
$active_page = 'home';
$current_user = getLoggedInUser();

$db = getDB();

$courses = $db->query("
    SELECT c.*, p.name AS professor_name, p.slug AS prof_slug,
           (SELECT COUNT(*) FROM course_contents cc WHERE cc.course_id=c.id) AS content_count
    FROM courses c
    JOIN professors p ON c.professor_id = p.id
    WHERE c.is_active = 1
    ORDER BY c.content_updated_at DESC, c.id DESC
    LIMIT 12
")->fetchAll();

$total_courses_count = (int)$db->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();

// ===== دوره‌های «ادامه تماشا» برای کاربر لاگین‌شده =====
// آخرین وضعیت تماشای هر دوره ذخیره‌شده + درصد پیشرفت، مرتب بر اساس آخرین فعالیت.
$continue_courses = [];
if ($current_user) {
    $stmt = $db->prepare("
        SELECT
            c.id, c.slug, c.title, c.sessions, c.duration,
            p.name AS professor_name,
            ucs.last_content_id, ucs.last_position_seconds, ucs.updated_at,
            cc.title AS last_content_title, cc.type AS last_content_type,
            (SELECT COUNT(*) FROM course_contents cc2 WHERE cc2.course_id=c.id) AS total_contents,
            (SELECT COALESCE(SUM(ucp.progress),0) FROM user_content_progress ucp WHERE ucp.user_id=ucs.user_id AND ucp.course_id=ucs.course_id) AS progress_sum
        FROM user_course_state ucs
        JOIN courses c ON c.id = ucs.course_id
        JOIN professors p ON p.id = c.professor_id
        LEFT JOIN course_contents cc ON cc.id = ucs.last_content_id
        WHERE ucs.user_id = ? AND c.is_active = 1
        ORDER BY ucs.updated_at DESC
        LIMIT 3
    ");
    $stmt->execute([$current_user['id']]);
    $continue_courses = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.courses-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:16px 30px 16px;position:relative;z-index:1;}
.course-card{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:16px;overflow:hidden;transition:all .25s ease;animation:fadeInUp .5s ease both;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.22);min-height:170px;display:flex;flex-direction:column;}
.course-card:hover{border-color:rgba(245,196,81,.45);transform:translateY(-3px);}
.course-card::after{content:'';position:absolute;inset:0;background:linear-gradient(105deg,transparent 40%,rgba(245,196,81,.03) 45%,rgba(245,196,81,.06) 50%,rgba(245,196,81,.03) 55%,transparent 60%);background-size:200% 100%;opacity:0;transition:opacity .3s;pointer-events:none;}
.course-card:hover::after{opacity:1;animation:shimmer 2s infinite linear;}
.course-card:hover .card-gear{opacity:.7;}

/* بج «محتوای جدید» حذف شد */

/* بخش «ادامه تماشا» */
.continue-section{padding:8px 30px 4px;position:relative;z-index:1;}
.continue-section .section-header{padding:10px 0 12px;}
.continue-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;}
.continue-card{
  position:relative;background:linear-gradient(180deg,rgba(245,196,81,.07),rgba(255,255,255,.03));
  border:1px solid rgba(245,196,81,.25);border-radius:14px;overflow:hidden;
  transition:all .25s ease;cursor:pointer;display:flex;align-items:center;gap:12px;padding:12px;
  animation:fadeInUp .4s ease both;
  text-decoration:none;color:inherit; /* حذف underline و رنگ آبی پیش‌فرض <a> */
}
.continue-card:hover{border-color:rgba(245,196,81,.5);transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.3);}
.continue-thumb{
  width:54px;height:54px;border-radius:11px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(245,196,81,.22),rgba(217,168,58,.1));
  border:1px solid rgba(245,196,81,.3);display:grid;place-items:center;
}
.continue-thumb svg{width:24px;height:24px;stroke:var(--gold);fill:none;stroke-width:1.5;}
.continue-body{flex:1;min-width:0;}
.continue-body h3{font-size:.86rem;font-weight:600;color:var(--text);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none;}
.continue-body .continue-meta{font-size:.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-decoration:none;}
.continue-body .continue-meta strong{color:var(--gold);font-weight:600;text-decoration:none;}
.continue-play{
  width:38px;height:38px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  display:grid;place-items:center;box-shadow:0 4px 12px rgba(245,196,81,.3);
  transition:transform .2s;
}
.continue-card:hover .continue-play{transform:scale(1.08);}
.continue-play svg{width:16px;height:16px;stroke:#111;fill:#111;stroke-width:1;}
.continue-progress{position:absolute;bottom:0;left:0;right:0;height:3px;background:rgba(255,255,255,.08);}
.continue-progress-fill{height:100%;background:linear-gradient(90deg,var(--gold),#f0a500);transition:width .4s ease;}
.empty-state{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--muted);}
.empty-state svg{width:48px;height:48px;stroke:var(--muted);fill:none;stroke-width:1.5;margin-bottom:16px;opacity:.5;}
.view-all-wrap{text-align:center;padding:8px 0 40px;position:relative;z-index:1;}
.view-all-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border:1px solid rgba(245,196,81,.4);border-radius:11px;color:var(--gold);font-size:.9rem;font-weight:600;text-decoration:none;background:rgba(245,196,81,.07);transition:all .2s;}
.view-all-btn:hover{background:rgba(245,196,81,.15);transform:translateY(-1px);}
.view-all-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;}
@media(max-width:1024px){.courses-grid{grid-template-columns:repeat(3,minmax(0,1fr));}.continue-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:768px){.navbar{padding:12px 16px;}.main-search-section{padding:24px 16px 18px;}.section-header{padding:10px 16px 12px;}.courses-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px 16px 16px;gap:14px;}.continue-section{padding:8px 16px 4px;}.continue-grid{grid-template-columns:1fr;}.auth-text{display:none;}}
@media(max-width:480px){.courses-grid{grid-template-columns:1fr;}}
</style>
</head>
<body data-card-selector="course-card">
<?php include __DIR__ . '/partials/theme_init.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<section class="main-search-section">
    <div class="hero-card">
        <h1 class="main-search-title">به <?= htmlspecialchars(SITE_NAME) ?> خوش آمدید</h1>
        <p class="hero-subtitle">تمام دوره‌های آموزشی قابل مشاهده هستند.</p>
        <form class="main-search-form" onsubmit="return false;">
            <input type="text" placeholder="جستجو برای دوره‌ها..." id="mainSearchInput">
            <div class="search-icon">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            </div>
        </form>
    </div>
</section>

<?php if ($current_user && !empty($continue_courses)): ?>
<!-- ===== بخش «ادامه تماشا» ===== -->
<section class="continue-section">
    <div class="section-header"><h2>ادامه تماشا</h2></div>
    <div class="continue-grid">
    <?php foreach ($continue_courses as $i => $cc):
        $pct = $cc['total_contents'] > 0 ? (int)round($cc['progress_sum'] / $cc['total_contents']) : 0;
        $resume_url = '/physics_academy/course.php?slug=' . urlencode($cc['slug']) . '&resume=' . (int)$cc['last_content_id'] . '&t=' . (int)round($cc['last_position_seconds']);
    ?>
        <a href="<?= $resume_url ?>" class="continue-card" style="animation-delay:<?= $i*.04 ?>s">
            <div class="continue-thumb">
                <?php if ($cc['last_content_type'] === 'pdf'): ?>
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <?php endif; ?>
            </div>
            <div class="continue-body">
                <h3><?= htmlspecialchars($cc['title']) ?></h3>
                <div class="continue-meta">
                    <strong><?= htmlspecialchars($cc['last_content_title'] ?? '—') ?></strong>
                    <?php if ($cc['last_content_type'] === 'video' && $cc['last_position_seconds'] > 0): ?>
                    • <?= gmdate('i:s', (int)$cc['last_position_seconds']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="continue-play">
                <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
            <div class="continue-progress"><div class="continue-progress-fill" style="width:<?= $pct ?>%"></div></div>
        </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="section-header"><h2>دوره‌های اخیر</h2></div>

<section class="courses-grid" id="coursesGrid">
<?php if (empty($courses)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>هنوز دوره‌ای اضافه نشده.</p>
        <p style="font-size:.85rem;margin-top:8px;"><a href="/physics_academy/admin/" style="color:var(--gold);">از پنل ادمین دوره اضافه کنید</a></p>
    </div>
<?php else: ?>
<?php foreach ($courses as $i => $course): ?>
    <article class="course-card" style="animation-delay:<?= $i * 0.05 ?>s"
        data-course-url="/physics_academy/course.php?slug=<?= urlencode($course['slug']) ?>"
        tabindex="0" role="link"
        aria-label="مشاهده دوره <?= htmlspecialchars($course['title']) ?>">
        <div class="card-gear">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1.08 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1.08 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.08z"/></svg>
        </div>
        <div class="card-thumb">
            <div class="card-thumb-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg>
            </div>
        </div>
        <div class="card-body">
            <h3><?= htmlspecialchars($course['title']) ?></h3>
            <div class="card-meta">
                <a class="card-professor professor-link" href="/physics_academy/professor.php?slug=<?= urlencode($course['prof_slug']) ?>">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                    <?= htmlspecialchars($course['professor_name']) ?>
                </a>
            </div>
            <div class="card-info">
                <div class="card-info-item">
                    <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
                    <?= en_to_fa((string)$course['sessions']) ?> جلسه
                </div>
                <div class="card-info-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= htmlspecialchars($course['duration']) ?>
                </div>
            </div>
        </div>
    </article>
<?php endforeach; ?>
<?php endif; ?>
</section>

<?php if ($total_courses_count > 12): ?>
<div class="view-all-wrap">
    <a href="/physics_academy/courses.php" class="view-all-btn">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        مشاهده همه دوره‌ها (<?= en_to_fa((string)$total_courses_count) ?> دوره)
    </a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
</body>
</html>
