<?php
require_once __DIR__ . '/config.php';
startUserSession();
requireUser();

$user    = getLoggedInUser();
$db      = getDB();
$active_page = 'profile';
$welcome = isset($_GET['welcome']);

// ===== پارامترهای صفحه‌بندی (۹ دوره در هر صفحه) =====
$per_page      = 9;
$current_page  = max(1, (int)($_GET['page'] ?? 1));
$base_url      = '/physics_academy/profile.php';

// ===== تعداد کل دوره‌های ذخیره‌شده =====
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM user_saved_courses usc
    JOIN courses c ON c.id = usc.course_id
    WHERE usc.user_id = ? AND c.is_active = 1
");
$countStmt->execute([$user['id']]);
$total_items = (int)$countStmt->fetchColumn();

$offset = ($current_page - 1) * $per_page;

// ===== دوره‌های ذخیره‌شده (صفحه فعلی) + درصد پیشرفت + وضعیت ادامه تماشا =====
// یک کوئری بهینه‌شده با LEFT JOIN به user_course_state برای گرفتن
// آخرین محتوای دیده‌شده و موقعیت آن (برای دکمه «ادامه»).
// درصد کل دوره = SUM(progress هر محتوا) / تعداد کل محتوا.
$saved = $db->prepare("
    SELECT
        c.id, c.slug, c.title, c.sessions, c.duration,
        p.name  AS professor_name,
        p.slug  AS prof_slug,
        (SELECT COUNT(*) FROM course_contents cc WHERE cc.course_id = c.id) AS total_contents,
        (SELECT COALESCE(SUM(ucp.progress), 0) FROM user_content_progress ucp
         WHERE ucp.user_id = :uid AND ucp.course_id = c.id) AS progress_sum,
        ucs.last_content_id,
        ucs.last_position_seconds,
        lc.title AS last_content_title,
        lc.type  AS last_content_type
    FROM user_saved_courses usc
    JOIN courses c ON c.id = usc.course_id
    JOIN professors p ON p.id = c.professor_id
    LEFT JOIN user_course_state ucs ON ucs.user_id = usc.user_id AND ucs.course_id = usc.course_id
    LEFT JOIN course_contents lc ON lc.id = ucs.last_content_id
    WHERE usc.user_id = :uid2 AND c.is_active = 1
    ORDER BY usc.saved_at DESC
    LIMIT :limit OFFSET :offset
");
$saved->bindValue(':uid',   $user['id'], PDO::PARAM_INT);
$saved->bindValue(':uid2',  $user['id'], PDO::PARAM_INT);
$saved->bindValue(':limit', $per_page,   PDO::PARAM_INT);
$saved->bindValue(':offset',$offset,     PDO::PARAM_INT);
$saved->execute();
$saved_courses = $saved->fetchAll();

// درصد پیشرفت برای هر دوره: SUM(progress) / total_contents
foreach ($saved_courses as &$sc) {
    $sc['progress'] = $sc['total_contents'] > 0
        ? (int)round($sc['progress_sum'] / $sc['total_contents'])
        : 0;
}
unset($sc);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>پروفایل من | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.page-wrapper{max-width:900px;margin:0 auto;padding:30px 24px 60px;position:relative;z-index:1;}

/* ===== پروفایل هدر ===== */
.profile-hero{
  background:linear-gradient(135deg,rgba(255,255,255,.07),rgba(255,255,255,.03));
  border:1px solid var(--stroke);border-radius:22px;padding:28px;
  margin-bottom:24px;display:flex;align-items:center;gap:20px;
  position:relative;overflow:hidden;box-shadow:var(--shadow);
  animation:fadeInUp .4s ease both;
}
.profile-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(245,196,81,.12),transparent 70%);pointer-events:none;}
.profile-avatar{
  width:72px;height:72px;border-radius:18px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(245,196,81,.25),rgba(217,168,58,.1));
  border:1px solid rgba(245,196,81,.4);display:grid;place-items:center;
}
.profile-avatar svg{width:34px;height:34px;stroke:var(--gold);fill:none;stroke-width:1.5;}
.profile-info h1{font-size:1.35rem;font-weight:800;color:var(--text);margin-bottom:4px;}
.profile-info p{font-size:.85rem;color:var(--muted);direction:ltr;text-align:right;}
.profile-info .student-id-badge{
  display:inline-flex;align-items:center;gap:6px;margin-top:6px;
  padding:4px 10px;border-radius:8px;font-size:.8rem;
  background:rgba(245,196,81,.1);border:1px solid rgba(245,196,81,.25);color:var(--gold);
  direction:ltr;
}
.profile-actions{margin-right:auto;display:flex;gap:8px;}
.btn-outline{
  display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
  border:1px solid var(--stroke);border-radius:10px;
  color:var(--muted);font-family:inherit;font-size:.83rem;font-weight:600;
  text-decoration:none;transition:all .2s;background:rgba(255,255,255,.03);
  cursor:pointer;
}
.btn-outline:hover{border-color:rgba(245,196,81,.4);color:var(--gold);}
.btn-outline svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}
.btn-red{border-color:rgba(239,68,68,.3);color:#f87171;}
.btn-red:hover{border-color:rgba(239,68,68,.6);background:rgba(239,68,68,.08);}

.section-title{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.section-title::before{content:'';width:7px;height:7px;background:var(--gold);border-radius:50%;box-shadow:0 0 0 4px rgba(245,196,81,.18);}
.section-title h2{font-size:1rem;font-weight:700;color:var(--text);}

.welcome-banner{
  background:linear-gradient(135deg,rgba(245,196,81,.15),rgba(217,168,58,.08));
  border:1px solid rgba(245,196,81,.3);border-radius:14px;padding:14px 18px;
  margin-bottom:20px;font-size:.9rem;color:var(--gold);display:flex;align-items:center;gap:10px;
  animation:fadeInUp .3s ease both;
}
.welcome-banner svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}

/* ===== گرید دوره‌های ذخیره‌شده ===== */
.saved-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}

.course-card-wrap{
  position:relative;
  border-radius:18px;
  animation:fadeInUp .5s ease both;
}

.course-card{
  position:relative;
  z-index:1;
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));
  border:1px solid var(--stroke);
  border-radius:16px;
  overflow:hidden;
  transition:border-color .25s,transform .25s,box-shadow .25s;
  cursor:pointer;
  box-shadow:0 6px 20px rgba(0,0,0,.22);
  display:flex;flex-direction:column;
  height:100%;
}
.course-card:hover{
  border-color:rgba(245,196,81,.35);
  transform:translateY(-3px);
  box-shadow:0 12px 28px rgba(0,0,0,.32);
}

.card-thumb{width:100%;height:60px;background:radial-gradient(220px 70px at 80% -20%,rgba(245,196,81,.35),transparent 60%),linear-gradient(180deg,rgba(245,196,81,.08),rgba(255,255,255,.02));position:relative;overflow:hidden;border-bottom:1px solid rgba(255,255,255,.08);}
.card-thumb-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:38px;height:38px;opacity:.18;}
.card-thumb-icon svg{width:100%;height:100%;stroke:var(--gold);fill:none;stroke-width:1;}

.card-body{padding:12px;flex:1;display:flex;flex-direction:column;gap:7px;}
.card-body h3{font-size:.9rem;font-weight:600;color:var(--text);line-height:1.5;}
.card-body .card-prof{font-size:.78rem;color:var(--muted);display:flex;align-items:center;gap:5px;}
.card-body .card-prof svg{width:12px;height:12px;stroke:var(--gold);fill:none;stroke-width:2;}

/* نوار پیشرفت داخل کارت */
.progress-bar-wrap{
  margin:0 12px 10px;
  display:flex;align-items:center;gap:8px;
}
.progress-bar-track{
  flex:1;height:4px;border-radius:4px;
  background:rgba(255,255,255,.08);overflow:hidden;
}
.progress-bar-fill{
  height:100%;border-radius:4px;
  background:linear-gradient(90deg,var(--gold),#f0a500);
  transition:width .6s ease;
}
.progress-pct{
  font-size:.7rem;font-weight:700;color:var(--gold);
  min-width:28px;text-align:left;flex-shrink:0;
}

.unsave-btn{
  display:flex;align-items:center;justify-content:center;gap:5px;
  margin:0 12px 12px;padding:7px;
  border:1px solid rgba(239,68,68,.25);border-radius:9px;
  background:rgba(239,68,68,.06);color:#f87171;
  font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;
  transition:all .2s;
}

/* دکمه «ادامه تماشا» داخل کارت دوره */
.continue-btn{
  display:flex;align-items:center;justify-content:center;gap:6px;
  margin:0 12px 6px;padding:8px;
  border:1px solid rgba(245,196,81,.35);border-radius:9px;
  background:linear-gradient(135deg,rgba(245,196,81,.12),rgba(217,168,58,.06));
  color:var(--gold);
  font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;
  text-decoration:none;transition:all .2s;
}
.continue-btn:hover{
  background:linear-gradient(135deg,rgba(245,196,81,.22),rgba(217,168,58,.12));
  border-color:rgba(245,196,81,.55);
}
.continue-btn svg{width:13px;height:13px;stroke:currentColor;fill:currentColor;stroke-width:1;}
.unsave-btn:hover{background:rgba(239,68,68,.14);border-color:rgba(239,68,68,.45);}
.unsave-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;}

/* حالت خالی */
.empty-saved{text-align:center;padding:48px 20px;color:var(--muted);}
.empty-saved svg{width:44px;height:44px;stroke:var(--muted);fill:none;stroke-width:1.5;margin-bottom:14px;opacity:.4;}
.empty-saved p{margin-bottom:16px;}
.empty-saved a{
  display:inline-flex;align-items:center;gap:6px;padding:10px 18px;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  border-radius:11px;color:#111;font-size:.9rem;font-weight:700;
  text-decoration:none;box-shadow:0 6px 16px rgba(245,196,81,.28);
}

/* light mode */
body.light-mode .course-card{background:#fff;border-color:rgba(70,65,55,.12);}
body.light-mode .card-thumb{background:radial-gradient(220px 70px at 80% -20%,rgba(160,122,31,.14),transparent 60%),linear-gradient(180deg,rgba(160,122,31,.05),rgba(70,65,55,.02));}
body.light-mode .progress-bar-track{background:rgba(70,65,55,.1);}
body.light-mode .progress-pct{color:var(--gold-2);}

@media(max-width:768px){
  .saved-grid{grid-template-columns:repeat(2,1fr);}
  .profile-hero{flex-direction:column;align-items:flex-start;}
  .profile-actions{margin-right:0;}
  .auth-text{display:none;}
}
@media(max-width:480px){.saved-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php include __DIR__ . "/partials/theme_init.php"; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="page-wrapper">
    <?php if ($welcome): ?>
    <div class="welcome-banner">
        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        خوش آمدید <?= htmlspecialchars($user['name']) ?> عزیز! حساب شما با موفقیت ساخته شد.
    </div>
    <?php endif; ?>

    <!-- پروفایل هدر -->
    <div class="profile-hero">
        <div class="profile-avatar">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
        </div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['name']) ?></h1>
            <span class="student-id-badge">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                شماره دانشجویی: <?= htmlspecialchars($user['student_id']) ?>
            </span>
        </div>
        <div class="profile-actions">
            <a href="/physics_academy/logout.php" class="btn-outline btn-red">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                خروج
            </a>
        </div>
    </div>

    <!-- دوره‌های ذخیره‌شده -->
    <div class="section-title">
        <h2>دوره‌های ذخیره‌شده (<?= en_to_fa((string)$total_items) ?>)</h2>
    </div>

    <?php if (empty($saved_courses)): ?>
    <div class="empty-saved">
        <svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <p>هنوز دوره‌ای ذخیره نکرده‌اید.</p>
        <a href="/physics_academy/courses.php">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            مشاهده دوره‌ها
        </a>
    </div>
    <?php else: ?>
    <div class="saved-grid">
        <?php foreach ($saved_courses as $i => $c): ?>
        <?php $pct = $c['progress']; ?>
        <div class="course-card-wrap" style="animation-delay:<?= $i*.07 ?>s">

            

            <div class="course-card"
                 onclick="window.location='/physics_academy/course.php?slug=<?= urlencode($c['slug']) ?>'">
                <div class="card-thumb">
                    <div class="card-thumb-icon">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg>
                    </div>
                </div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <span class="card-prof">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                        <?= htmlspecialchars($c['professor_name']) ?>
                    </span>
                    <span style="font-size:.75rem;color:var(--muted);">
                        <?= en_to_fa((string)$c['sessions']) ?> جلسه • <?= htmlspecialchars($c['duration']) ?>
                    </span>
                </div>

                <!-- نوار پیشرفت داخل کارت -->
                <div class="progress-bar-wrap">
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="progress-pct"><?= $pct ?>%</span>
                </div>

                <?php if (!empty($c['last_content_id'])): ?>
                <!-- دکمه «ادامه تماشا» — مستقیم به آخرین محتوای دیده‌شده با timestamp -->
                <a href="/physics_academy/course.php?slug=<?= urlencode($c['slug']) ?>&resume=<?= (int)$c['last_content_id'] ?>&t=<?= (int)round($c['last_position_seconds'] ?? 0) ?>"
                   class="continue-btn" onclick="event.stopPropagation()">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    ادامه تماشا
                    <?php if ($c['last_content_type'] === 'video' && ($c['last_position_seconds'] ?? 0) > 0): ?>
                    <span style="opacity:.7;font-weight:500;margin-right:2px;"><?= gmdate('i:s', (int)$c['last_position_seconds']) ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <button type="button" class="unsave-btn" data-course-id="<?= $c['id'] ?>" onclick="unsaveCourse(this)">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    حذف از لیست
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php include __DIR__ . '/partials/pagination.php'; ?>
    <?php endif; ?>
</div>

<!-- کانتینر Toast -->
<div class="toast-container" id="toastContainer"></div>

<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
<script>
// توکن CSRF برای درخواست‌های AJAX
const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

// ===== حذف دوره از لیست ذخیره‌شده‌ها به‌صورت AJAX (بدون رفرش صفحه) =====
function unsaveCourse(btn) {
    if (btn.disabled) return;
    const courseId = btn.getAttribute('data-course-id');
    if (!courseId) return;

    // تایید حذف
    if (!confirm('این دوره از لیست ذخیره‌شده‌ها حذف شود؟')) return;

    btn.disabled = true;
    btn.style.opacity = '0.5';

    fetch('/physics_academy/ajax_toggle_save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ course_id: parseInt(courseId), action: 'unsave' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // حذف کارت از DOM با انیمیشن
            const card = btn.closest('.course-card-wrap');
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(.9)';
                setTimeout(() => {
                    card.remove();
                    // اگر هیچ کارتی نماند، صفحه را رفرش کن تا empty-state نشان داده شود
                    if (!document.querySelector('.course-card-wrap')) {
                        window.location.reload();
                    }
                }, 300);
            }
            showToast('دوره از لیست حذف شد.', 'success');
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            showToast(data.error || 'خطا در حذف.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
        showToast('خطای شبکه.', 'error');
    });
}

// ===== Toast ساده (در صورت نبود در shared.js) =====
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) { alert(message); return; }
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    const icons = {
        success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
    };
    toast.innerHTML = (icons[type] || icons.success) + '<span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    }, 2800);
}
</script>
<style>
.toast-container{position:fixed;top:20px;right:50%;transform:translateX(50%);z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.toast{background:linear-gradient(135deg,rgba(245,196,81,.15),rgba(217,168,58,.08));border:1px solid rgba(245,196,81,.4);border-radius:11px;padding:10px 16px;font-size:.85rem;color:var(--gold);box-shadow:0 8px 24px rgba(0,0,0,.4);backdrop-filter:blur(10px);display:flex;align-items:center;gap:8px;min-width:200px;max-width:380px;animation:toastIn .3s ease both;pointer-events:auto;}
.toast.error{background:linear-gradient(135deg,rgba(239,68,68,.15),rgba(239,68,68,.08));border-color:rgba(239,68,68,.4);color:#f87171;}
.toast.hide{animation:toastOut .3s ease both;}
.toast svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
@keyframes toastIn{from{opacity:0;transform:translateY(-12px);}to{opacity:1;transform:translateY(0);}}
@keyframes toastOut{from{opacity:1;transform:translateY(0);}to{opacity:0;transform:translateY(-12px);}}
body.light-mode .toast{background:#fff;backdrop-filter:none;}
</style>
</body>
</html>