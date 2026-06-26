<?php
require_once __DIR__ . '/config.php';
$active_page = 'professors';

$db       = getDB();
$per_page = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($current_page - 1) * $per_page;

$total_items = (int)$db->query("SELECT COUNT(*) FROM professors")->fetchColumn();

$stmt = $db->prepare("
    SELECT p.*, COUNT(c.id) AS course_count, COALESCE(SUM(c.sessions),0) AS sessions_total
    FROM professors p LEFT JOIN courses c ON c.professor_id=p.id AND c.is_active=1
    GROUP BY p.id ORDER BY p.id ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$professors = $stmt->fetchAll();

$base_url = '/physics_academy/professors.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>اساتید | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.professors-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:16px 30px 8px;position:relative;z-index:1;}
.professor-card{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:16px;overflow:hidden;transition:all .25s ease;animation:fadeInUp .5s ease both;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.22);min-height:170px;display:flex;flex-direction:column;}
.professor-card:hover{border-color:rgba(245,196,81,.45);transform:translateY(-3px);}
.professor-card::after{content:'';position:absolute;inset:0;background:linear-gradient(105deg,transparent 40%,rgba(245,196,81,.03) 45%,rgba(245,196,81,.06) 50%,rgba(245,196,81,.03) 55%,transparent 60%);background-size:200% 100%;opacity:0;transition:opacity .3s;pointer-events:none;}
.professor-card:hover::after{opacity:1;animation:shimmer 2s infinite linear;}
.professor-card:hover .card-gear{opacity:.7;}
.card-sub{display:flex;align-items:center;gap:8px;font-size:.9rem;color:var(--muted);}
.card-sub svg{width:16px;height:16px;stroke:var(--gold);fill:none;stroke-width:2;flex-shrink:0;}
.card-thumb.prof-thumb{position:relative;}
.card-thumb.prof-thumb img{position:absolute;top:50%;right:50%;transform:translate(50%,-50%);width:52px;height:52px;border-radius:12px;object-fit:cover;border:2px solid rgba(245,196,81,.4);box-shadow:0 4px 12px rgba(0,0,0,.3);}
.empty-state{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--muted);}
@media(max-width:1024px){.professors-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media(max-width:768px){.navbar{padding:12px 16px;}.main-search-section{padding:24px 16px 18px;}.section-header{padding:10px 16px 12px;}.professors-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding:12px 16px 8px;gap:14px;}.auth-text{display:none;}.pagination-wrap{padding:16px 16px 36px;}}
@media(max-width:480px){.professors-grid{grid-template-columns:1fr;}}
</style>
</head>
<body data-card-selector="professor-card">
<?php include __DIR__ . '/partials/theme_init.php'; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<section class="main-search-section">
    <div class="hero-card">
        <h1 class="main-search-title">فهرست کامل اساتید</h1>
        <p class="hero-subtitle">
            <?= en_to_fa((string)$total_items) ?> استاد فعال دانشکده
            <?php if ($total_items > $per_page): ?>
            — <?= en_to_fa((string)(int)ceil($total_items/$per_page)) ?> صفحه
            <?php endif; ?>
        </p>
        <form class="main-search-form" onsubmit="return false;">
            <input type="text" placeholder="جستجو برای اساتید..." id="mainSearchInput">
            <div class="search-icon"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg></div>
        </form>
    </div>
</section>

<div class="section-header"><h2>همه اساتید<?= $total_items > $per_page ? ' — صفحه '.en_to_fa((string)$current_page) : '' ?></h2></div>

<section class="professors-grid">
<?php if (empty($professors)): ?>
    <div class="empty-state"><p>استادی ثبت نشده. <a href="/physics_academy/admin/" style="color:var(--gold)">از پنل ادمین اضافه کنید</a></p></div>
<?php else: ?>
<?php foreach ($professors as $i => $prof): ?>
    <article class="professor-card" style="animation-delay:<?= $i*.04 ?>s"
        data-professor-url="/physics_academy/professor.php?slug=<?= urlencode($prof['slug']) ?>">
        <div class="card-gear"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1.08 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1.08 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.08z"/></svg></div>
        <div class="card-thumb prof-thumb">
            <?php if (!empty($prof['photo_url'])): ?>
            <img src="<?= htmlspecialchars($prof['photo_url']) ?>" alt="<?= htmlspecialchars($prof['name']) ?>" onerror="this.style.display='none'">
            <?php else: ?>
            <div class="card-thumb-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg></div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3 style="min-height:auto"><?= htmlspecialchars($prof['name']) ?></h3>
            <div class="card-meta">
                <span class="card-sub">
                    <svg viewBox="0 0 24 24"><path d="M12 3l10 6-10 6L2 9l10-6z"/><path d="M2 17l10 6 10-6"/></svg>
                    <?= htmlspecialchars($prof['rank'] ?: 'استاد دانشکده فیزیک') ?>
                </span>
            </div>
            <div class="card-info">
                <div class="card-info-item"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg><?= en_to_fa((string)$prof['course_count']) ?> دوره</div>
                <div class="card-info-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= en_to_fa((string)$prof['sessions_total']) ?> جلسه</div>
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
