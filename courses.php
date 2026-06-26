<?php
require_once __DIR__ . '/config.php';
$active_page = 'courses';

$db       = getDB();
$per_page = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($current_page - 1) * $per_page;

$total_items = (int)$db->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();

$stmt = $db->prepare("
    SELECT c.*, p.name AS professor_name, p.slug AS prof_slug
    FROM courses c JOIN professors p ON c.professor_id=p.id
    WHERE c.is_active=1
    ORDER BY c.sort_order ASC, c.id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$courses = $stmt->fetchAll();

$base_url = '/physics_academy/courses.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>دوره‌ها | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.courses-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:16px 30px 8px;position:relative;z-index:1;}
.course-card{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:16px;overflow:hidden;transition:all .25s ease;animation:fadeInUp .5s ease both;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.22);min-height:170px;display:flex;flex-direction:column;}
.course-card:hover{border-color:rgba(245,196,81,.45);transform:translateY(-3px);}
.course-card::after{content:'';position:absolute;inset:0;background:linear-gradient(105deg,transparent 40%,rgba(245,196,81,.03) 45%,rgba(245,196,81,.06) 50%,rgba(245,196,81,.03) 55%,transparent 60%);background-size:200% 100%;opacity:0;transition:opacity .3s;pointer-events:none;}
.course-card:hover::after{opacity:1;animation:shimmer 2s infinite linear;}
.course-card:hover .card-gear{opacity:.7;}
.empty-state{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--muted);}
.empty-state svg{width:44px;height:44px;stroke:var(--muted);fill:none;stroke-width:1.5;margin-bottom:14px;opacity:.5;}
@media(max-width:1024px){.courses-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media(max-width:768px){.navbar{padding:12px 16px;}.main-search-section{padding:24px 16px 18px;}.section-header{padding:10px 16px 12px;}.courses-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px 16px 8px;gap:14px;}.auth-text{display:none;}.pagination-wrap{padding:16px 16px 36px;}}
@media(max-width:480px){.courses-grid{grid-template-columns:1fr;}}
</style>
</head>
<body data-card-selector="course-card">
<?php include __DIR__ . '/partials/theme_init.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<section class="main-search-section">
    <div class="hero-card">
        <h1 class="main-search-title">فهرست کامل دوره‌های <?= htmlspecialchars(SITE_NAME) ?></h1>
        <p class="hero-subtitle">
            <?= en_to_fa((string)$total_items) ?> دوره
            <?php if ($total_items > $per_page): ?>
            در <?= en_to_fa((string)(int)ceil($total_items/$per_page)) ?> صفحه
            <?php endif; ?>
        </p>
        <form class="main-search-form" onsubmit="return false;">
            <input type="text" placeholder="جستجو در دوره‌ها..." id="mainSearchInput">
            <div class="search-icon"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
        </form>
    </div>
</section>

<div class="section-header"><h2>همه دوره‌ها<?= $total_items > $per_page ? ' — صفحه '.en_to_fa((string)$current_page) : '' ?></h2></div>

<section class="courses-grid" id="coursesGrid">
<?php if (empty($courses)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>دوره‌ای یافت نشد. <a href="/physics_academy/admin/" style="color:var(--gold)">از پنل ادمین اضافه کنید</a></p>
    </div>
<?php else: ?>
<?php foreach ($courses as $i => $course): ?>
    <article class="course-card" style="animation-delay:<?= $i*.04 ?>s"
        data-course-url="/physics_academy/course.php?slug=<?= urlencode($course['slug']) ?>"
        tabindex="0" role="link" aria-label="مشاهده دوره <?= htmlspecialchars($course['title']) ?>">
        <div class="card-gear"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1.08 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1.08 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.08z"/></svg></div>
        <div class="card-thumb"><div class="card-thumb-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg></div></div>
        <div class="card-body">
            <h3><?= htmlspecialchars($course['title']) ?></h3>
            <div class="card-meta">
                <a class="card-professor professor-link" href="/physics_academy/professor.php?slug=<?= urlencode($course['prof_slug']) ?>">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                    <?= htmlspecialchars($course['professor_name']) ?>
                </a>
            </div>
            <div class="card-info">
                <div class="card-info-item"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg><?= en_to_fa((string)$course['sessions']) ?> جلسه</div>
                <div class="card-info-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= htmlspecialchars($course['duration']) ?></div>
            </div>
        </div>
    </article>
<?php endforeach; ?>
<?php endif; ?>
</section>

<?php include __DIR__ . '/partials/pagination.php'; ?>
<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
</body>
</html>
