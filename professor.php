<?php
require_once __DIR__ . '/config.php';
startUserSession();
$active_page = 'professors';

$db   = getDB();
$slug = $_GET['slug'] ?? '';

$stmt = $db->prepare("SELECT * FROM professors WHERE slug=? LIMIT 1");
$stmt->execute([$slug]);
$professor = $stmt->fetch();

if (!$professor) {
    $professor = $db->query("SELECT * FROM professors ORDER BY id LIMIT 1")->fetch();
}
if (!$professor) {
    echo '<div style="font-family:sans-serif;padding:40px;color:#aaa;text-align:center;background:#0d1117;min-height:100vh;"><h2>استادی یافت نشد</h2><a href="/physics_academy/" style="color:#f5c451">بازگشت</a></div>';
    exit;
}

$prof_courses = $db->prepare("SELECT * FROM courses WHERE professor_id=? AND is_active=1 ORDER BY sort_order ASC, id ASC");
$prof_courses->execute([$professor['id']]);
$prof_courses = $prof_courses->fetchAll();

$total_sessions = array_sum(array_column($prof_courses,'sessions'));
$research_tags  = array_filter(array_map('trim', explode('،', $professor['research'] ?? '')));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($professor['name']) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.page-wrapper{max-width:var(--max);margin:0 auto;padding:30px 30px 60px;position:relative;z-index:1;}
.prof-hero{background:linear-gradient(135deg,rgba(255,255,255,.07),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:22px;padding:32px;margin-bottom:24px;position:relative;overflow:hidden;box-shadow:var(--shadow);animation:fadeInUp .4s ease .05s both;}
.prof-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(245,196,81,.12),transparent 70%);pointer-events:none;}
.prof-hero-inner{display:flex;align-items:flex-start;gap:24px;}
.prof-hero-avatar{width:96px;height:96px;border-radius:20px;flex-shrink:0;background:linear-gradient(135deg,rgba(245,196,81,.22),rgba(217,168,58,.1));border:1px solid rgba(245,196,81,.35);display:grid;place-items:center;overflow:hidden;}
.prof-hero-avatar img{width:100%;height:100%;object-fit:cover;}
.prof-hero-avatar svg{width:44px;height:44px;stroke:var(--gold);fill:none;stroke-width:1.3;}
.prof-hero-info{flex:1;}
.prof-hero-info h1{font-size:1.65rem;font-weight:800;color:var(--text);margin-bottom:6px;}
.prof-field-badge{display:inline-block;padding:4px 12px;background:rgba(245,196,81,.1);border:1px solid rgba(245,196,81,.3);border-radius:8px;font-size:.82rem;color:var(--gold);margin-bottom:14px;}
.prof-hero-badges{display:flex;flex-wrap:wrap;gap:8px;}

.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;animation:fadeInUp .4s ease .1s both;}
.stat-card{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:16px;padding:18px 16px;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.18);}
.stat-card-val{font-size:1.6rem;font-weight:800;color:var(--gold);line-height:1.2;margin-bottom:4px;}
.stat-card-label{font-size:.8rem;color:var(--muted);}

.main-cols{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;}
.section-card{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));border:1px solid var(--stroke);border-radius:18px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.22);animation:fadeInUp .5s ease both;}
.section-card:nth-child(1){animation-delay:.12s;}.section-card:nth-child(2){animation-delay:.20s;}
.section-card-header{display:flex;align-items:center;gap:10px;padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);}
.section-card-header .dot{width:8px;height:8px;background:var(--gold);border-radius:50%;box-shadow:0 0 0 4px rgba(245,196,81,.18);flex-shrink:0;}
.section-card-header h2{font-size:1rem;font-weight:700;color:var(--text);}
.section-card-body{padding:20px;}

.about-bio{font-size:.95rem;color:#c9d2dc;line-height:1.9;margin-bottom:16px;}
.about-sub-title{font-size:.85rem;color:var(--muted);font-weight:600;margin-bottom:8px;margin-top:16px;}
.research-tags{display:flex;flex-wrap:wrap;gap:8px;}
.research-tag{padding:5px 12px;border-radius:10px;font-size:.82rem;background:rgba(245,196,81,.07);border:1px solid rgba(245,196,81,.2);color:#d4dbe6;}

/* ===== لیست دوره‌های استاد — حداکثر ۳ آیتم نمایش، باقی با اسکرول داخلی (مثل بخش محتوای دوره) ===== */
.courses-list{
  display:flex;
  flex-direction:column;
  gap:12px;
  max-height:455px; /* ارتفاع تقریبی ۳ کارت دوره + gap */
  overflow-y:auto;
  overflow-x:hidden;
  padding-left:6px;
  margin-left:-6px;
  padding-bottom:2px;
  scrollbar-width:thin;
  scrollbar-color:var(--gold-2) rgba(255,255,255,.04);
}
.courses-list::-webkit-scrollbar{width:6px;}
.courses-list::-webkit-scrollbar-track{background:rgba(255,255,255,.04);border-radius:6px;}
.courses-list::-webkit-scrollbar-thumb{background:var(--gold-2);border-radius:6px;}
.courses-list::-webkit-scrollbar-thumb:hover{background:var(--gold);}

.course-item{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.02));border:1px solid var(--stroke);border-radius:14px;overflow:hidden;transition:all .25s ease;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.18);flex-shrink:0;}
.course-item:hover{border-color:rgba(245,196,81,.45);transform:translateY(-2px);}
.course-item-thumb{height:52px;background:radial-gradient(180px 60px at 80% -20%,rgba(245,196,81,.3),transparent 60%),linear-gradient(180deg,rgba(245,196,81,.07),rgba(255,255,255,.01));border-bottom:1px solid rgba(255,255,255,.07);position:relative;overflow:hidden;}
.course-item-thumb-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:36px;height:36px;opacity:.16;}
.course-item-thumb-icon svg{width:100%;height:100%;stroke:var(--gold);fill:none;stroke-width:1;}
.course-item-body{padding:12px 14px;}
.course-item-title{font-size:.95rem;font-weight:600;color:var(--text);margin-bottom:8px;line-height:1.5;}
.course-item-meta{display:flex;gap:8px;flex-wrap:wrap;}
.course-item-badge{display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03);border-radius:8px;padding:4px 8px;font-size:.8rem;color:#c9d2dc;white-space:nowrap;}
.course-item-badge svg{width:12px;height:12px;stroke:#c9d2dc;fill:none;stroke-width:2;}

@media(max-width:900px){.main-cols{grid-template-columns:1fr;}.stats-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:768px){.navbar{padding:12px 16px;}.page-wrapper{padding:20px 16px 40px;}.prof-hero{padding:20px;}.prof-hero-info h1{font-size:1.25rem;}.auth-text{display:none;}.stats-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:520px){.prof-hero-inner{flex-direction:column;gap:16px;}.stats-row{grid-template-columns:1fr 1fr;}}
/* اصلاح رنگ‌بندی Light Mode برای تگ‌های تحقیقاتی و بج‌های دوره */
body.light-mode .research-tag {
    background: rgba(160, 122, 31, .08);
    border-color: rgba(160, 122, 31, .25);
    color: var(--text);
}

body.light-mode .course-item-badge {
    background: rgba(70, 65, 55, .04);
    border-color: rgba(70, 65, 55, .16);
    color: var(--text);
}

body.light-mode .course-item-badge svg {
    stroke: var(--text);
}

</style>
</head>
<body>
<?php include __DIR__ . "/partials/theme_init.php"; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="page-wrapper">
    <nav class="breadcrumb">
        <a href="/physics_academy/">خانه</a><span>›</span>
        <a href="/physics_academy/professors.php">اساتید</a><span>›</span>
        <span class="current"><?= htmlspecialchars($professor['name']) ?></span>
    </nav>

    <div class="prof-hero">
        <div class="prof-hero-inner">
            <div class="prof-hero-avatar">
                <?php if (!empty($professor['photo_url'])): ?>
                <img src="<?= htmlspecialchars($professor['photo_url']) ?>" alt="<?= htmlspecialchars($professor['name']) ?>">
                <?php else: ?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                <?php endif; ?>
            </div>
            <div class="prof-hero-info">
                <h1><?= htmlspecialchars($professor['name']) ?></h1>
                <?php if ($professor['field']): ?>
                <div class="prof-field-badge"><?= htmlspecialchars($professor['field']) ?></div>
                <?php endif; ?>
                <div class="prof-hero-badges">
                    <?php if ($professor['rank']): ?>
                    <span class="badge">
                        <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        <?= htmlspecialchars($professor['rank']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($professor['degree']): ?>
                    <span class="badge">
                        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?= htmlspecialchars($professor['degree']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="badge">دانشکده فیزیک</span>
                </div>
            </div>
        </div>
    </div>

    <!-- آمار ساده -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-val"><?= en_to_fa((string)count($prof_courses)) ?></div>
            <div class="stat-card-label">دوره تدریس‌شده</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-val"><?= en_to_fa((string)$total_sessions) ?></div>
            <div class="stat-card-label">کل جلسات</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-val"><?= en_to_fa((string)count($prof_courses)) ?></div>
            <div class="stat-card-label">دوره فعال</div>
        </div>
    </div>

    <div class="main-cols">
        <!-- درباره استاد -->
        <div class="section-card">
            <div class="section-card-header"><span class="dot"></span><h2>درباره استاد</h2></div>
            <div class="section-card-body">
                <?php if ($professor['bio']): ?>
                <p class="about-bio"><?= nl2br(htmlspecialchars($professor['bio'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($research_tags)): ?>
                <p class="about-sub-title">حوزه‌های تحقیقاتی</p>
                <div class="research-tags">
                    <?php foreach ($research_tags as $tag): ?>
                    <span class="research-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!$professor['bio'] && empty($research_tags)): ?>
                <p style="color:var(--muted);font-size:.9rem;">اطلاعاتی ثبت نشده.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- دوره‌ها -->
        <div class="section-card">
            <div class="section-card-header"><span class="dot"></span><h2>دوره‌های استاد (<?= en_to_fa((string)count($prof_courses)) ?>)</h2></div>
            <div class="section-card-body">
                <?php if (empty($prof_courses)): ?>
                <p style="color:var(--muted);font-size:.9rem;text-align:center;padding:20px 0;">دوره‌ای ثبت نشده.</p>
                <?php else: ?>
                <div class="courses-list">
                    <?php foreach ($prof_courses as $c): ?>
                    <article class="course-item" onclick="window.location='/physics_academy/course.php?slug=<?= urlencode($c['slug']) ?>'" role="link" tabindex="0">
                        <div class="course-item-thumb">
                            <div class="course-item-thumb-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg></div>
                        </div>
                        <div class="course-item-body">
                            <div class="course-item-title"><?= htmlspecialchars($c['title']) ?></div>
                            <div class="course-item-meta">
                                <span class="course-item-badge"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg><?= en_to_fa((string)$c['sessions']) ?> جلسه</span>
                                <span class="course-item-badge"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= htmlspecialchars($c['duration']) ?></span>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// مدیریت کلیک روی کارت دوره استاد (data-url به جای data-course-url چون ساختار متفاوت دارد)
document.querySelectorAll('.course-item').forEach(function (item) {
  item.addEventListener('click', function () {
    var url = item.getAttribute('data-url');
    if (url) window.location.href = url;
  });
  item.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      var url = item.getAttribute('data-url');
      if (url) window.location.href = url;
    }
  });
});
</script>
<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
</body>
</html>
