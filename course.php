<?php
require_once __DIR__ . '/config.php';
startUserSession();
$active_page  = 'courses';
$current_user = getLoggedInUser();

$db   = getDB();
$slug = $_GET['slug'] ?? '';

$course = $db->prepare("
    SELECT c.*, p.name AS professor_name, p.slug AS prof_slug,
           p.bio AS professor_bio, p.field AS professor_field,
           p.photo_url AS professor_photo
    FROM courses c JOIN professors p ON c.professor_id = p.id
    WHERE c.slug = ? AND c.is_active = 1 LIMIT 1
");
$course->execute([$slug]);
$course = $course->fetch();

if (!$course) {
    $course = $db->query("
        SELECT c.*, p.name AS professor_name, p.slug AS prof_slug,
               p.bio AS professor_bio, p.field AS professor_field, p.photo_url AS professor_photo
        FROM courses c JOIN professors p ON c.professor_id = p.id
        WHERE c.is_active = 1 ORDER BY c.id LIMIT 1
    ")->fetch();
}
if (!$course) {
    echo '<div style="font-family:sans-serif;padding:40px;color:#aaa;text-align:center;background:#0d1117;min-height:100vh;"><h2>دوره‌ای یافت نشد</h2><a href="/physics_academy/" style="color:#f5c451">بازگشت</a></div>';
    exit;
}

// NOTE: ذخیره/لغو دوره حالت به‌صورت AJAX انجام می‌شود (ajax_toggle_save.php).
// دیگر فرم POST در این صفحه وجود ندارد — دکمه به‌صورت async عمل می‌کند.

$is_saved = false;
$is_blocked = false;
if ($current_user) {
    $chk = $db->prepare("SELECT 1 FROM user_saved_courses WHERE user_id=? AND course_id=?");
    $chk->execute([$current_user['id'], $course['id']]);
    $is_saved = (bool)$chk->fetchColumn();

    // بررسی blocklist — آیا ادمین دسترسی این کاربر به این دوره را قطع کرده؟
    $blk = $db->prepare("SELECT 1 FROM user_course_blocks WHERE user_id=? AND course_id=? LIMIT 1");
    $blk->execute([$current_user['id'], $course['id']]);
    $is_blocked = (bool)$blk->fetchColumn();
}

$topics = json_decode($course['topics'] ?? '[]', true) ?: [];

// مراجع از جدول course_references
$refs_stmt = $db->prepare("SELECT * FROM course_references WHERE course_id=? ORDER BY sort_order ASC, id ASC");
$refs_stmt->execute([$course['id']]);
$refs = $refs_stmt->fetchAll();

$contents_stmt = $db->prepare("SELECT * FROM course_contents WHERE course_id=? ORDER BY sort_order ASC, id ASC");
$contents_stmt->execute([$course['id']]);
$contents = $contents_stmt->fetchAll();
$total_contents = count($contents);

// ===== درصد پیشرفت هر محتوا برای کاربر فعلی =====
// برای نمایش نشانگر درصد در لیست محتوا (سایدبار).
// اگر کاربر لاگین نیست، آرایه خالی می‌ماند.
$content_progress = []; // content_id => percent (0-100)
if ($current_user) {
    $pstmt = $db->prepare("SELECT content_id, progress FROM user_content_progress WHERE user_id=? AND course_id=?");
    $pstmt->execute([$current_user['id'], $course['id']]);
    foreach ($pstmt->fetchAll() as $row) {
        $content_progress[(int)$row['content_id']] = (int)$row['progress'];
    }
}

// تابع کمکی: تشخیص پسوند و آیکون
function getRefIcon(string $url): string {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'                => 'pdf',
        'doc','docx'         => 'doc',
        'epub'               => 'epub',
        'djvu'               => 'djvu',
        'ppt','pptx'         => 'ppt',
        'zip','rar','7z'     => 'zip',
        default              => 'link',
    };
}
function getRefLabel(string $url): string {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'                => 'PDF',
        'doc'                => 'DOC',
        'docx'               => 'DOCX',
        'epub'               => 'EPUB',
        'djvu'               => 'DJVU',
        'ppt'                => 'PPT',
        'pptx'               => 'PPTX',
        'zip'                => 'ZIP',
        'rar'                => 'RAR',
        '7z'                 => '7Z',
        default              => 'لینک',
    };
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($course['title']) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="/physics_academy/assets/shared.css">
<style>
.page-wrapper{max-width:860px;margin:0 auto;padding:30px 30px 60px;position:relative;z-index:1;}

/* ===== hero ===== */
.course-hero{
  background:linear-gradient(135deg,rgba(255,255,255,.07),rgba(255,255,255,.03));
  border:1px solid var(--stroke);border-radius:22px;padding:32px;
  margin-bottom:24px;position:relative;overflow:hidden;
  box-shadow:var(--shadow);animation:fadeInUp .4s ease .05s both;
}
.course-hero::before{
  content:'';position:absolute;top:-80px;right:-80px;
  width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(245,196,81,.12),transparent 70%);
  pointer-events:none;
}
.course-hero-top{display:flex;align-items:flex-start;gap:20px;}
.course-icon-wrap{
  width:70px;height:70px;border-radius:16px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(245,196,81,.2),rgba(217,168,58,.1));
  border:1px solid rgba(245,196,81,.3);display:grid;place-items:center;
}
.course-icon-wrap svg{width:34px;height:34px;stroke:var(--gold);fill:none;stroke-width:1.2;}
.course-hero-info{flex:1;}
.course-hero-info h1{font-size:1.65rem;font-weight:800;color:var(--text);line-height:1.5;margin-bottom:10px;}
.course-badges{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}

/* دکمه ذخیره در hero */
.hero-save-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 14px;border-radius:10px;
  font-family:inherit;font-size:.83rem;font-weight:700;
  cursor:pointer;transition:all .25s;
  border:1px solid rgba(245,196,81,.45);
  background:rgba(245,196,81,.1);color:var(--gold);
}
.hero-save-btn:hover{background:rgba(245,196,81,.18);border-color:rgba(245,196,81,.65);}
.hero-save-btn.saved{background:rgba(245,196,81,.2);border-color:rgba(245,196,81,.7);}
.hero-save-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}
.hero-save-btn.saved svg{fill:currentColor;}
/* دکمه استاد */
.hero-prof-btn{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:7px 14px;
  border-radius:10px;
  font-size:.83rem;
  font-weight:700;
  text-decoration:none;
  cursor:pointer;
  transition:all .25s;
  border:1px solid rgba(245,196,81,.45);
  background:rgba(245,196,81,.1);
  color:var(--gold);
}

.hero-prof-btn:hover{
  background:rgba(245,196,81,.18);
  border-color:rgba(245,196,81,.65);
  transform:translateY(-1px);
}

.hero-prof-btn svg{
  width:14px;
  height:14px;
  stroke:currentColor;
  fill:none;
  stroke-width:2;
}

/* ===== accordion ===== */
.section-card{
  background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.03));
  border:1px solid var(--stroke);border-radius:18px;overflow:hidden;
  box-shadow:0 6px 20px rgba(0,0,0,.22);animation:fadeInUp .5s ease both;
  margin-bottom:20px;
}
.section-card-header{
  display:flex;align-items:center;gap:10px;padding:18px 20px;
  border-bottom:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.03);cursor:pointer;user-select:none;
}
.section-card-header .dot{
  width:8px;height:8px;background:var(--gold);border-radius:50%;
  box-shadow:0 0 0 4px rgba(245,196,81,.18);flex-shrink:0;
}
.section-card-header h2{font-size:1rem;font-weight:700;color:var(--text);}
.accordion-chevron{
  margin-right:auto;width:18px;height:18px;
  color:var(--gold);transition:transform .28s ease;flex-shrink:0;
}
.section-card-body{
  padding:0 20px;max-height:0;overflow:hidden;opacity:0;
  transition:max-height .4s ease,opacity .3s ease,padding .3s ease;
}
.section-card.active .section-card-body{opacity:1;padding-top:20px;padding-bottom:24px;}
.section-card.active .section-card-header .accordion-chevron{transform:rotate(180deg);}

/* درباره دوره */
.course-desc{font-size:.95rem;color:#c9d2dc;line-height:1.85;margin-bottom:20px;}
.topics-list{display:flex;flex-direction:column;gap:8px;}
.topic-item{
  display:flex;align-items:center;gap:10px;padding:9px 12px;
  background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);
  border-radius:10px;font-size:.9rem;color:#d4dbe6;transition:all .2s;
}
.topic-item:hover{border-color:rgba(245,196,81,.3);background:rgba(245,196,81,.04);}
.topic-item::before{content:'';width:6px;height:6px;background:var(--gold);border-radius:50%;flex-shrink:0;opacity:.7;}

/* ===== مراجع دوره (اصلاح شده برای هم‌سانی با محتوا) ===== */
.refs-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ref-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px; /* هم‌اندازه با محتوای دوره */
    background: rgba(255, 255, 255, .03);
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 12px;
    transition: all .22s;
    text-decoration: none;
    cursor: pointer;
}

.ref-item:hover {
    border-color: rgba(245, 196, 81, .35);
    background: rgba(245, 196, 81, .05);
    transform: translateX(-3px);
}

/* آیکون نوع فایل - کوچک شده */
.ref-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    background: rgba(239, 68, 68, .12);
    border: 1px solid rgba(239, 68, 68, .22);
}

.ref-icon svg {
    width: 16px;
    height: 16px;
    stroke: #f87171;
    fill: none;
    stroke-width: 2;
}

.ref-icon.link { background: rgba(59, 130, 246, .1); border-color: rgba(59, 130, 246, .22); }
.ref-icon.link svg { stroke: #93c5fd; }

.ref-icon.doc { background: rgba(37, 99, 235, .12); border-color: rgba(37, 99, 235, .22); }
.ref-icon.doc svg { stroke: #60a5fa; }

.ref-icon.epub, .ref-icon.djvu { background: rgba(168, 85, 247, .1); border-color: rgba(168, 85, 247, .22); }
.ref-icon.epub svg, .ref-icon.djvu svg { stroke: #c084fc; }

.ref-icon.ppt { background: rgba(234, 88, 12, .1); border-color: rgba(234, 88, 12, .22); }
.ref-icon.ppt svg { stroke: #fb923c; }

.ref-icon.zip { background: rgba(234, 179, 8, .1); border-color: rgba(234, 179, 8, .2); }
.ref-icon.zip svg { stroke: #facc15; }

.ref-text {
    flex: 1;
    min-width: 0;
}

.ref-title {
    font-size: .88rem; /* کوچک‌تر شده */
    font-weight: 600;
    color: #d4dbe6;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ref-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 5px;
    font-size: .65rem;
    font-weight: 700;
    margin-top: 4px;
    background: rgba(239, 68, 68, .12);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, .2);
}

.ref-action {
    color: rgba(212, 219, 230, .4);
    transition: color .2s;
}

.ref-item:hover .ref-action {
    color: var(--gold);
}

.refs-empty {
    padding: 20px;
    text-align: center;
    color: rgba(212, 219, 230, .5);
    font-size: .9rem;
    background: rgba(255, 255, 255, .02);
    border-radius: 12px;
    border: 1px dashed rgba(255, 255, 255, .1);
}


/* آیکون دانلود سمت چپ */
.ref-dl-icon{
  flex-shrink:0;width:32px;height:32px;border-radius:9px;
  display:grid;place-items:center;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  transition:all .2s;
}
.ref-item:hover .ref-dl-icon{
  background:rgba(239,68,68,.15);
  border-color:rgba(239,68,68,.35);
}
.ref-dl-icon svg{width:14px;height:14px;stroke:#c9d2dc;fill:none;stroke-width:2;}
.ref-item:hover .ref-dl-icon svg{stroke:#f87171;}

.refs-empty{
  text-align:center;padding:28px 20px;color:var(--muted);font-size:.9rem;
}
.refs-empty svg{
  width:36px;height:36px;stroke:var(--muted);fill:none;stroke-width:1.5;
  margin-bottom:10px;opacity:.4;display:block;margin-left:auto;margin-right:auto;
}

/* قفل مراجع برای مهمان */
.refs-locked{
  text-align:center;padding:28px 20px;
  background:rgba(245,196,81,.04);border:1px dashed rgba(245,196,81,.25);
  border-radius:14px;
}
.refs-locked svg{width:34px;height:34px;stroke:var(--gold);fill:none;stroke-width:1.5;margin-bottom:10px;opacity:.7;}
.refs-locked p{font-size:.9rem;color:var(--muted);margin-bottom:14px;}
.refs-locked a{
  display:inline-flex;align-items:center;gap:6px;padding:9px 18px;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  border-radius:10px;color:#111;font-size:.88rem;font-weight:800;
  text-decoration:none;box-shadow:0 4px 14px rgba(245,196,81,.28);
}

/* محتوای دوره */
.content-list{display:flex;flex-direction:column;gap:8px;}
.section-card.scroll-limited .section-card-body{
  scrollbar-width:thin;scrollbar-color:var(--gold-2) rgba(255,255,255,.04);
}
.section-card.scroll-limited .section-card-body::-webkit-scrollbar{width:6px;}
.section-card.scroll-limited .section-card-body::-webkit-scrollbar-track{background:rgba(255,255,255,.04);border-radius:6px;}
.section-card.scroll-limited .section-card-body::-webkit-scrollbar-thumb{background:var(--gold-2);border-radius:6px;}

.content-item{
  display:flex;align-items:center;gap:12px;padding:11px 14px;
  background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);
  border-radius:12px;transition:all .22s;cursor:pointer;
}
.content-item:hover{border-color:rgba(245,196,81,.35);background:rgba(245,196,81,.04);transform:translateX(-3px);}
.content-item.playing{border-color:rgba(245,196,81,.7);background:rgba(245,196,81,.1);}
.content-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;}
.content-icon.video{background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.25);}
.content-icon.video svg{width:16px;height:16px;stroke:#818cf8;fill:none;stroke-width:2;}
.content-icon.pdf{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2);}
.content-icon.pdf svg{width:16px;height:16px;stroke:#f87171;fill:none;stroke-width:2;}
.content-text{flex:1;min-width:0;}
.content-title{font-size:.9rem;font-weight:500;color:#d4dbe6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.content-meta{font-size:.78rem;color:var(--muted);margin-top:2px;}
.content-num{font-size:.75rem;color:rgba(255,255,255,.3);flex-shrink:0;width:24px;text-align:center;}

/* نشانگر درصد پیشرفت در لیست محتوا */
.content-progress-badge{
  flex-shrink:0;min-width:30px;padding:3px 7px;border-radius:8px;
  font-size:.7rem;font-weight:700;text-align:center;
  background:rgba(255,255,255,.06);color:var(--muted);
  border:1px solid rgba(255,255,255,.08);
  transition:all .2s;
}
.content-progress-badge.done{
  background:rgba(34,197,94,.12);color:#4ade80;
  border-color:rgba(34,197,94,.3);
}
.content-item[data-progress]:not([data-progress="0"]):not([data-progress=""]) .content-progress-badge{
  background:rgba(245,196,81,.1);color:var(--gold);
  border-color:rgba(245,196,81,.25);
}
body.light-mode .content-progress-badge{background:rgba(70,65,55,.06);border-color:rgba(70,65,55,.1);}
body.light-mode .content-progress-badge.done{background:rgba(34,197,94,.08);color:#16a34a;border-color:rgba(34,197,94,.25);}

/* قفل محتوا */
.content-locked{
  text-align:center;padding:32px 20px;
  background:rgba(245,196,81,.04);border:1px dashed rgba(245,196,81,.25);border-radius:14px;
}
.content-locked svg{width:38px;height:38px;stroke:var(--gold);fill:none;stroke-width:1.5;margin-bottom:12px;opacity:.7;}
.content-locked p{font-size:.92rem;color:var(--muted);margin-bottom:16px;line-height:1.7;}
.content-locked a{
  display:inline-flex;align-items:center;gap:7px;padding:11px 20px;
  background:linear-gradient(135deg,var(--gold),var(--gold-2));
  border-radius:11px;color:#111;font-size:.9rem;font-weight:800;
  text-decoration:none;box-shadow:0 6px 18px rgba(245,196,81,.3);transition:all .25s;
}
.content-locked a:hover{transform:translateY(-1px);}
.content-locked a svg{stroke:#111;}

/* ===== ویدیو پلیر ===== */
.player-wrap{
  position:relative;background:#000;border:1px solid var(--stroke);
  border-radius:20px;overflow:hidden;
  box-shadow:var(--shadow),0 24px 70px rgba(0,0,0,.55);
  margin-bottom:14px;display:none;direction:ltr;
}
.player-wrap.show{display:block;animation:fadeInUp .35s cubic-bezier(.22,.68,0,1.2) both;}
.player-video-wrap{position:relative;background:#000;overflow:hidden;touch-action:manipulation;}
.player-screen{position:relative;background:#000;cursor:pointer;line-height:0;touch-action:manipulation;}
.player-screen iframe,.player-screen video{width:100%;aspect-ratio:16/9;display:block;border:none;}

/* ===== اسپینر لودینگ ویدیو ===== */
.player-loading{
  position:absolute;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;
  background:rgba(0,0,0,.65);z-index:5;pointer-events:none;gap:12px;
}
.player-loading.show{display:flex;}
.player-loading-spinner{
  width:48px;height:48px;border-radius:50%;
  border:3px solid rgba(245,196,81,.2);border-top-color:var(--gold);
  animation:spin 0.9s linear infinite;
}
.player-loading p{font-size:.82rem;color:var(--muted);margin:0;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ===== سیستم Toast (اعلان موقت) ===== */
.toast-container{
  position:fixed;top:20px;right:50%;transform:translateX(50%);
  z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;
}
.toast{
  background:linear-gradient(135deg,rgba(245,196,81,.15),rgba(217,168,58,.08));
  border:1px solid rgba(245,196,81,.4);border-radius:11px;
  padding:10px 16px;font-size:.85rem;color:var(--gold);
  box-shadow:0 8px 24px rgba(0,0,0,.4);backdrop-filter:blur(10px);
  display:flex;align-items:center;gap:8px;min-width:200px;max-width:380px;
  animation:toastIn .3s ease both;pointer-events:auto;
}
.toast.error{background:linear-gradient(135deg,rgba(239,68,68,.15),rgba(239,68,68,.08));border-color:rgba(239,68,68,.4);color:#f87171;}
.toast.success{background:linear-gradient(135deg,rgba(34,197,94,.15),rgba(34,197,94,.08));border-color:rgba(34,197,94,.4);color:#4ade80;}
.toast.hide{animation:toastOut .3s ease both;}
.toast svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
@keyframes toastIn{from{opacity:0;transform:translateY(-12px);}to{opacity:1;transform:translateY(0);}}
@keyframes toastOut{from{opacity:1;transform:translateY(0);}to{opacity:0;transform:translateY(-12px);}}
body.light-mode .toast{background:#fff;backdrop-filter:none;}
.player-video-wrap::after{
  content:'';position:absolute;inset:0;pointer-events:none;z-index:1;opacity:0;
  background:linear-gradient(to bottom,rgba(0,0,0,.15) 0%,transparent 28%,transparent 52%,rgba(0,0,0,.55) 78%,rgba(0,0,0,.86) 100%);
  transition:opacity .28s ease;
}
.player-wrap.controls-visible .player-video-wrap::after,
.player-wrap.fs-controls-visible .player-video-wrap::after{opacity:1;}

.player-actions-bar{
  display:none;align-items:center;gap:10px;
  margin-top:14px;margin-bottom:20px;padding:12px 18px;
  background:linear-gradient(135deg,rgba(245,196,81,.05),rgba(217,168,58,.02));
  border:1px solid rgba(245,196,81,.13);border-radius:16px;
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  direction:ltr;
}
.player-actions-bar.show{display:flex;}
.float-spacer{flex:1;}
.float-counter{font-size:.72rem;color:rgba(245,196,81,.45);padding:5px 13px;border-radius:20px;background:rgba(245,196,81,.04);border:1px solid rgba(245,196,81,.1);font-variant-numeric:tabular-nums;}
.float-btn{
  display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:22px;
  background:rgba(255,255,255,.06);border:1px solid rgba(245,196,81,.18);
  color:rgba(245,196,81,.72);font-family:inherit;font-size:.78rem;font-weight:700;
  cursor:pointer;transition:all .22s;text-decoration:none;
}
.float-btn:hover:not(:disabled){background:rgba(245,196,81,.11);border-color:rgba(245,196,81,.32);color:rgba(245,196,81,.92);transform:translateY(-2px);}
.float-btn:disabled{opacity:.28;cursor:not-allowed;}
.float-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0;}
.float-btn.hidden{display:none!important;}
a.float-btn[id="btnDownload"]{background:rgba(245,196,81,.08);border-color:rgba(245,196,81,.22);}
a.float-btn[id="btnDownload"]:hover{background:rgba(245,196,81,.14);border-color:rgba(245,196,81,.38);}

.player-controls-overlay{
  position:absolute;bottom:0;left:0;right:0;padding:0 18px 16px;z-index:20;
  opacity:0;transform:translateY(6px);transition:opacity .28s ease,transform .28s ease;
  pointer-events:none;direction:ltr;
}
.player-wrap.controls-visible .player-controls-overlay{opacity:1;transform:translateY(0);pointer-events:auto;}
.player-controls-bar.hidden{display:none;}
.player-progress-wrap{padding:0 0 10px;direction:ltr;}
.player-progress{position:relative;height:3px;background:rgba(255,255,255,.22);border-radius:10px;cursor:pointer;transition:height .18s ease;}
.player-progress:hover{height:5px;}
.player-progress-fill{height:100%;background:linear-gradient(to right,var(--gold),#f0a500);border-radius:10px;width:0%;pointer-events:none;box-shadow:0 0 8px rgba(245,196,81,.5);}
.player-progress-thumb{position:absolute;top:50%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:var(--gold);left:0%;pointer-events:none;box-shadow:0 0 0 3px rgba(245,196,81,.3),0 2px 8px rgba(0,0,0,.5);opacity:0;transition:opacity .18s;}
.player-progress:hover .player-progress-thumb{opacity:1;}
.player-controls-bar{display:flex;align-items:center;gap:4px;direction:ltr;}
.ctrl-btn{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.82);display:flex;align-items:center;justify-content:center;transition:color .18s,transform .15s;padding:7px;border-radius:8px;flex-shrink:0;}
.ctrl-btn:hover{color:#fff;transform:scale(1.1);}
.ctrl-btn:active{transform:scale(.95);}
.ctrl-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;}
.ctrl-btn.play-pause{width:38px;height:38px;background:rgba(255,255,255,.12);border-radius:50%;border:1px solid rgba(255,255,255,.15);}
.ctrl-btn.play-pause:hover{background:rgba(245,196,81,.25);border-color:rgba(245,196,81,.5);color:var(--gold);transform:scale(1.08);}
.ctrl-btn.play-pause svg{width:16px;height:16px;}
.ctrl-btn.skip-btn{position:relative;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);}
.ctrl-btn.skip-btn svg{width:20px;height:20px;}
.skip-num{position:absolute;top:53%;left:50%;transform:translate(-50%,-50%);font-family:inherit;font-size:7.5px;font-weight:800;letter-spacing:-.2px;color:currentColor;pointer-events:none;line-height:1;direction:ltr;}
.ctrl-time{font-size:.7rem;color:rgba(255,255,255,.65);white-space:nowrap;direction:ltr;font-variant-numeric:tabular-nums;padding:0 6px;}
.ctrl-vol{display:flex;align-items:center;gap:4px;direction:ltr;}
.vol-slider{width:60px;height:3px;background:rgba(255,255,255,.22);border-radius:10px;cursor:pointer;-webkit-appearance:none;appearance:none;outline:none;direction:ltr;transition:width .2s;}
.ctrl-vol:hover .vol-slider{width:72px;}
.vol-slider::-webkit-slider-thumb{-webkit-appearance:none;width:12px;height:12px;border-radius:50%;background:var(--gold);cursor:pointer;}
.speed-select{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:20px;color:rgba(255,255,255,.8);font-family:inherit;font-size:.7rem;font-weight:700;padding:4px 10px;cursor:pointer;outline:none;direction:ltr;transition:background .2s;}
.speed-select:hover{background:rgba(255,255,255,.16);}
.speed-select option{background:#141d2b;color:#e8edf4;}
.ctrl-spacer{flex:1;}
.ctrl-btn.fs-btn{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 8px;}
.ctrl-btn.fs-btn:hover{background:rgba(255,255,255,.15);}
.player-info-counter{font-size:.72rem;color:rgba(255,255,255,.4);white-space:nowrap;padding:3px 10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:20px;direction:rtl;}
.player-nav{display:flex;gap:6px;direction:ltr;}
.player-nav-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:20px;font-family:inherit;font-size:.76rem;font-weight:700;cursor:pointer;transition:all .2s;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:rgba(255,255,255,.6);}
.player-nav-btn:hover:not(:disabled){border-color:rgba(245,196,81,.5);color:var(--gold);background:rgba(245,196,81,.08);}
.player-nav-btn:disabled{opacity:.25;cursor:not-allowed;}
.player-nav-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5;}

/* PDF viewer */
.pdf-viewer-wrap{background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.18);border-radius:14px;padding:24px;text-align:center;margin-bottom:20px;display:none;}
.pdf-viewer-wrap.show{display:block;animation:fadeInUp .3s ease both;}
.pdf-viewer-wrap a{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.35);border-radius:11px;color:#f87171;font-size:.9rem;font-weight:600;text-decoration:none;transition:all .2s;}
.pdf-viewer-wrap a:hover{background:rgba(239,68,68,.25);}
.pdf-viewer-wrap a svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;}
.pdf-viewer-wrap p{font-size:.82rem;color:var(--muted);margin-top:10px;}

/* Fullscreen */
.player-wrap:-webkit-full-screen,.player-wrap:-moz-full-screen,.player-wrap:fullscreen{border-radius:0;border:none;display:flex;flex-direction:column;width:100%;height:100%;}
.player-wrap:fullscreen .player-video-wrap,.player-wrap:-webkit-full-screen .player-video-wrap{flex:1;min-height:0;position:relative;border-radius:0;}
.player-wrap:fullscreen .player-screen,.player-wrap:-webkit-full-screen .player-screen{height:100%;}
.player-wrap:fullscreen .player-screen video,.player-wrap:-webkit-full-screen .player-screen video{width:100%;height:100%;aspect-ratio:unset;object-fit:contain;}
.player-wrap.fs-controls-visible .player-controls-overlay{opacity:1;transform:translateY(0);pointer-events:auto;}
.player-wrap.fs-controls-visible .player-video-wrap::after{opacity:1;}
.player-wrap.fs-cursor-hidden{cursor:none;}
.player-wrap.fs-cursor-hidden *{cursor:none;}
.player-wrap:fullscreen,.player-wrap:-webkit-full-screen{width:100vw;height:100vh;background:#000;}
.player-wrap:fullscreen video,.player-wrap:-webkit-full-screen video{width:100%;height:100%;object-fit:contain;}

/* Mobile */
@media(max-width:768px){
  .page-wrapper{padding:20px 16px 40px;}
  .course-hero{padding:20px;}
  .course-hero-info h1{font-size:1.25rem;}
  .course-hero-top{flex-direction:column;gap:14px;}
  .auth-text{display:none;}
  .player-controls-bar{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;}
  .ctrl-btn{min-width:38px;min-height:38px;padding:6px;}
  .ctrl-btn.play-pause{width:42px;height:42px;flex-shrink:0;}
  .ctrl-btn.skip-btn{width:36px;height:36px;flex-shrink:0;}
  .ctrl-time{font-size:.66rem;padding:0 4px;flex-shrink:0;}
  .ctrl-spacer{flex:1 1 8px;min-width:4px;}
  .ctrl-vol{flex-shrink:0;}
  .ctrl-vol .vol-slider{display:none;}
  .speed-select{padding:4px 7px;font-size:.68rem;flex-shrink:0;}
  .ctrl-btn.fs-btn{padding:6px 7px;flex-shrink:0;}
  .player-actions-bar{flex-wrap:wrap;gap:8px;padding:12px;}
  .float-spacer{display:none;}
  .float-btn{flex:1 1 calc(50% - 4px);justify-content:center;}
  .float-counter{width:100%;text-align:center;order:-1;}
}
@media(max-width:420px){.speed-select{display:none;}}
@media(max-width:480px){
  .player-screen iframe,.player-screen video{aspect-ratio:16/10;}
  .player-progress{height:8px;}
  .player-progress-thumb{opacity:1;width:16px;height:16px;}
  .player-controls-overlay{background:linear-gradient(to top,rgba(0,0,0,.72),transparent);}
}

/* Light mode */
body.light-mode .ref-item{background:rgba(70,65,55,.03);border-color:rgba(70,65,55,.14);}
body.light-mode .ref-item:hover{border-color:rgba(160,122,31,.35);background:rgba(160,122,31,.06);}
body.light-mode .ref-title{color:var(--text);}
body.light-mode .ref-icon{background:rgba(180,40,30,.08);border-color:rgba(180,40,30,.18);}
body.light-mode .ref-icon svg{stroke:#dc2626;}
body.light-mode .ref-dl-icon{background:rgba(70,65,55,.05);border-color:rgba(70,65,55,.14);}
body.light-mode .ref-item:hover .ref-dl-icon{background:rgba(180,40,30,.1);border-color:rgba(180,40,30,.3);}
body.light-mode .ref-item:hover .ref-dl-icon svg{stroke:#dc2626;}
body.light-mode .hero-save-btn{border-color:rgba(160,122,31,.4);background:rgba(160,122,31,.08);color:var(--gold-2);}
body.light-mode .hero-save-btn:hover{background:rgba(160,122,31,.15);}
body.light-mode .hero-save-btn.saved{background:rgba(160,122,31,.18);}
body.light-mode .hero-prof-btn{
  border-color:rgba(160,122,31,.4);
  background:rgba(160,122,31,.08);
  color:var(--gold-2);
}

body.light-mode .hero-prof-btn:hover{
  background:rgba(160,122,31,.15);
}
body.light-mode .player-actions-bar{background:linear-gradient(135deg,rgba(160,122,31,.08),rgba(132,100,26,.04));border-color:rgba(160,122,31,.22);}
body.light-mode .float-btn{background:rgba(255,255,255,.85);border-color:rgba(160,122,31,.3);color:var(--gold-2);}
body.light-mode .float-btn:hover:not(:disabled){background:rgba(160,122,31,.1);border-color:rgba(160,122,31,.5);}
body.light-mode .float-counter{color:rgba(132,100,26,.6);background:rgba(160,122,31,.07);border-color:rgba(160,122,31,.18);}
</style>
</head>
<body>
<?php include __DIR__ . "/partials/theme_init.php"; ?>
<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="page-wrapper">
    <nav class="breadcrumb">
        <a href="/physics_academy/">خانه</a><span>›</span>
        <a href="/physics_academy/courses.php">دوره‌ها</a><span>›</span>
        <span class="current"><?= htmlspecialchars($course['title']) ?></span>
    </nav>

    <!-- ===== Hero ===== -->
    <div class="course-hero">
        <div class="course-hero-top">
            <div class="course-icon-wrap">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg>
            </div>
            <div class="course-hero-info">
                <h1><?= htmlspecialchars($course['title']) ?></h1>
                <div class="course-badges">
                    <a class="hero-prof-btn" href="/physics_academy/professor.php?slug=<?= urlencode($course['prof_slug']) ?>">
    <svg viewBox="0 0 24 24">
        <circle cx="12" cy="7" r="4"/>
        <path d="M5.5 21a6.5 6.5 0 0 1 13 0"/>
    </svg>
    <?= htmlspecialchars($course['professor_name']) ?>
</a>
                    <span class="badge"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg><?= en_to_fa((string)$course['sessions']) ?> جلسه</span>
                    <span class="badge"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= htmlspecialchars($course['duration']) ?></span>

                    <?php if ($current_user): ?>
                    <button type="button" class="hero-save-btn <?= $is_saved ? 'saved' : '' ?>" id="heroSaveBtn"
                            data-course-id="<?= (int)$course['id'] ?>" data-saved="<?= $is_saved ? '1' : '0' ?>"
                            onclick="toggleSaveCourse(this)">
                        <svg viewBox="0 0 24 24" id="heroSaveIcon"><?= $is_saved
                            ? '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="currentColor"/>'
                            : '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>' ?></svg>
                        <span id="heroSaveText"><?= $is_saved ? 'حذف از لیست' : 'ذخیره دوره' ?></span>
                    </button>
                    <?php else: ?>
                    <a href="/physics_academy/auth.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                       class="badge" style="border-color:rgba(245,196,81,.4);background:rgba(245,196,81,.08);color:var(--gold);text-decoration:none;cursor:pointer;">
                        <svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        ذخیره دوره
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_blocked): ?>
    <!-- ===== دسترسی مسدود شده توسط ادمین ===== -->
    <div class="access-blocked">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1" fill="currentColor"/></svg>
        <h2>دسترسی شما به این دوره قطع شده است</h2>
        <p>مدیر سایت دسترسی شما به محتوای دوره «<?= htmlspecialchars($course['title']) ?>» را قطع کرده است.</p>
        <p style="font-size:.82rem;color:var(--muted);margin-top:8px;">در صورت نیاز، با مدیر دانشکده تماس بگیرید.</p>
        <a href="/physics_academy/" class="back-home-btn">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            بازگشت به خانه
        </a>
    </div>
    <style>
    .access-blocked{
      text-align:center;padding:48px 24px;margin:20px 0;
      background:linear-gradient(135deg,rgba(239,68,68,.08),rgba(239,68,68,.03));
      border:1px solid rgba(239,68,68,.25);border-radius:18px;
      animation:fadeInUp .4s ease both;
    }
    .access-blocked svg{width:56px;height:56px;stroke:#f87171;fill:none;stroke-width:1.3;margin-bottom:16px;}
    .access-blocked h2{font-size:1.15rem;font-weight:700;color:#f87171;margin-bottom:10px;}
    .access-blocked p{font-size:.92rem;color:var(--muted);line-height:1.7;}
    .access-blocked .back-home-btn{
      display:inline-flex;align-items:center;gap:7px;margin-top:20px;padding:11px 22px;
      background:linear-gradient(135deg,var(--gold),var(--gold-2));
      border-radius:11px;color:#111;font-size:.9rem;font-weight:700;text-decoration:none;
      box-shadow:0 6px 16px rgba(245,196,81,.28);transition:transform .2s;
    }
    .access-blocked .back-home-btn:hover{transform:translateY(-1px);}
    .access-blocked .back-home-btn svg{width:15px;height:15px;stroke:#111;fill:none;stroke-width:2;margin:0;}
    body.light-mode .access-blocked{background:#fff;border-color:rgba(239,68,68,.2);}
    </style>

    <?php else: ?>

    <!-- ===== پلیر ===== -->
    <div class="player-wrap" id="playerWrap">
        <div class="player-video-wrap" id="playerVideoWrap">
            <div class="player-screen" id="playerScreen"></div>
            <!-- اسپینر لودینگ ویدیو -->
            <div class="player-loading" id="playerLoading">
                <div class="player-loading-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>
            <div class="player-controls-overlay hidden" id="controlsOverlay">
                <div class="player-progress-wrap">
                    <div class="player-progress" id="progressBar">
                        <div class="player-progress-fill" id="progressFill"></div>
                        <div class="player-progress-thumb" id="progressThumb"></div>
                    </div>
                </div>
                <div class="player-controls-bar" id="customControls">
                    <button class="ctrl-btn skip-btn" id="btnRewind" title="۱۵ ثانیه عقب">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
                        <span class="skip-num">15</span>
                    </button>
                    <button class="ctrl-btn play-pause" id="btnPlayPause">
                        <svg id="iconPlay" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="6 3 20 12 6 21 6 3"/></svg>
                        <svg id="iconPause" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:none"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                    </button>
                    <button class="ctrl-btn skip-btn" id="btnForward" title="۱۵ ثانیه جلو">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.49-3.51"/></svg>
                        <span class="skip-num">15</span>
                    </button>
                    <span class="ctrl-time" id="ctrlTime">0:00 / 0:00</span>
                    <span class="ctrl-spacer"></span>
                    <div class="ctrl-vol">
                        <button class="ctrl-btn" id="btnMute">
                            <svg id="iconVol" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        </button>
                        <input type="range" class="vol-slider" id="volSlider" min="0" max="1" step="0.05" value="1">
                    </div>
                    <select class="speed-select" id="speedSelect">
                        <option value="0.5">0.5×</option>
                        <option value="0.75">0.75×</option>
                        <option value="1" selected>1×</option>
                        <option value="1.25">1.25×</option>
                        <option value="1.5">1.5×</option>
                        <option value="2">2×</option>
                    </select>
                    <button class="ctrl-btn fs-btn" id="btnFullscreen">
                        <svg viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="player-actions-bar" id="playerActionsBar">
        <button class="float-btn" id="btnPrev" disabled><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>قبلی</button>
        <button class="float-btn" id="btnNext" disabled>بعدی<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
        <span class="float-counter" id="playerCounter"></span>
        <span class="float-spacer"></span>
        <a id="btnDownload" href="#" download class="float-btn hidden">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>دانلود
        </a>
    </div>

    <div class="pdf-viewer-wrap" id="pdfViewerWrap">
        <a id="pdfOpenLink" href="#" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            باز کردن PDF در تب جدید
        </a>
        <a id="pdfDlLink" href="#" download style="display:inline-flex;align-items:center;gap:8px;padding:11px 20px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);border-radius:11px;color:#4ade80;font-size:.9rem;font-weight:600;text-decoration:none;margin-top:10px;">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            دانلود PDF
        </a>
        <p id="pdfFileName"></p>
        <div class="player-nav" style="justify-content:center;margin-top:14px;">
            <button class="player-nav-btn" id="pdfBtnNext" disabled><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>بعدی</button>
            <span class="player-info-counter" id="pdfCounter" style="align-self:center;"></span>
            <button class="player-nav-btn" id="pdfBtnPrev" disabled>قبلی<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
        </div>
    </div>

    <!-- درباره دوره -->
    <div class="section-card active">
        <div class="section-card-header" role="button" tabindex="0" aria-expanded="true">
            <span class="dot"></span><h2>درباره دوره</h2>
            <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="section-card-body">
            <p class="course-desc"><?= nl2br(htmlspecialchars($course['description'])) ?></p>
            <?php if (!empty($topics)): ?>
            <p style="font-size:.88rem;color:var(--muted);margin-bottom:12px;font-weight:600;">سرفصل‌های دوره:</p>
            <div class="topics-list">
                <?php foreach ($topics as $t): ?>
                <div class="topic-item"><?= htmlspecialchars($t) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- مراجع دوره -->
    <div class="section-card">
        <div class="section-card-header" role="button" tabindex="0" aria-expanded="false">
            <span class="dot"></span>
            <h2>مراجع دوره</h2>
            <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="section-card-body">
            <?php if (empty($refs)): ?>
            <div class="refs-empty">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                هنوز مرجعی برای این دوره ثبت نشده است.
            </div>
            <?php elseif (!$current_user): ?>
            <div class="refs-locked">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <p>برای دانلود مراجع باید وارد حساب کاربری خود شوید.</p>
                <a href="/physics_academy/auth.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                    <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    ورود
                </a>
            </div>
            <?php else: ?>
            <div class="refs-list">
                <?php foreach ($refs as $i => $ref):
                    $icon_type  = getRefIcon($ref['file_url']);
                    $label      = getRefLabel($ref['file_url']);
                    $is_upload  = str_starts_with($ref['file_url'], '/physics_academy/uploads/references/');
                    // فایل‌های آپلودشده رو مستقیم دانلود، لینک‌های خارجی رو در تب جدید باز کن
                    $dl_attr    = $is_upload ? 'download' : 'target="_blank" rel="noopener"';
                ?>
                <a class="ref-item"
                   href="<?= htmlspecialchars($ref['file_url']) ?>"
                   <?= $dl_attr ?>>
                    <div class="ref-icon <?= $icon_type ?>">
                        <?php if ($icon_type === 'link'): ?>
                        <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <?php elseif (in_array($icon_type, ['zip'])): ?>
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="ref-text">
                        <div class="ref-title"><?= htmlspecialchars($ref['title']) ?></div>
                        <span class="ref-badge <?= $icon_type ?>"><?= $label ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- محتوای دوره -->
    <div class="section-card scroll-limited">
        <div class="section-card-header" role="button" tabindex="0" aria-expanded="false">
            <span class="dot"></span><h2>محتوای دوره</h2>
            <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="section-card-body">
            <?php if (empty($contents)): ?>
            <p style="color:var(--muted);font-size:.9rem;text-align:center;padding:16px 0;">محتوایی هنوز اضافه نشده.</p>

            <?php elseif (!$current_user): ?>
            <div class="content-locked">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <p>برای مشاهده محتوای این دوره باید وارد حساب کاربری خود شوید.</p>
                <a href="/physics_academy/auth.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                    <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    ورود
                </a>
            </div>

            <?php else: ?>
            <div class="content-list" id="contentList">
                <?php foreach ($contents as $idx => $item): ?>
                <div class="content-item"
                     data-idx="<?= $idx ?>"
                     data-cid="<?= (int)$item['id'] ?>"
                     data-type="<?= $item['type'] ?>"
                     data-url="<?= htmlspecialchars($item['url']) ?>"
                     data-title="<?= htmlspecialchars($item['title']) ?>"
                     <?php if ($current_user && isset($content_progress[(int)$item['id']])): ?>
                     data-progress="<?= (int)$content_progress[(int)$item['id']] ?>"
                     <?php endif; ?>
                     onclick="playItem(<?= $idx ?>)">
                    <span class="content-num"><?= en_to_fa((string)($idx + 1)) ?></span>
                    <div class="content-icon <?= $item['type'] ?>">
                        <?php if ($item['type'] === 'video'): ?>
                        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="content-text">
                        <div class="content-title"><?= htmlspecialchars($item['title']) ?></div>
                        <div class="content-meta">
                            <?php if ($item['type'] === 'video'): ?>
                            ویدیو<?= $item['duration'] ? ' • '.htmlspecialchars($item['duration']) : '' ?>
                            <?php else: ?>
                            PDF<?= $item['file_size'] ? ' • '.htmlspecialchars($item['file_size']) : '' ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($current_user): ?>
                    <?php
                        $pct = $content_progress[(int)$item['id']] ?? 0;
                        $is_done = $pct >= 100;
                    ?>
                    <span class="content-progress-badge <?= $is_done ? 'done' : '' ?>"><?= $is_done ? '✓' : ($pct > 0 ? $pct . '%' : '—') ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; // end !$is_blocked ?>

</div>

<!-- کانتینر Toast (اعلان‌های موقت) -->
<div class="toast-container" id="toastContainer"></div>

<script>
const CONTENTS = <?= json_encode(array_values(array_map(fn($c) => [
    'content_id'=> (int)$c['id'],
    'type'      => $c['type'],
    'url'       => $c['url'],
    'title'     => $c['title'],
    'duration'  => $c['duration'],
    'file_size' => $c['file_size'],
], $contents)), JSON_UNESCAPED_UNICODE) ?>;
const TOTAL     = CONTENTS.length;
const COURSE_ID = <?= (int)$course['id'] ?>;
<?php if ($current_user): ?>const CAN_TRACK = true;<?php else: ?>const CAN_TRACK = false;<?php endif; ?>
// توکن CSRF برای درخواست‌های AJAX (progress_mark و resume_save و toggle_save)
const CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
const COURSE_ID_FOR_SAVE = <?= (int)$course['id'] ?>;
let currentIdx = -1, videoEl = null, rafId = null, isDragging = false;

// ===== ذخیره / حذف دوره به‌صورت AJAX (بدون رفرش صفحه) =====
function toggleSaveCourse(btn) {
    if (btn.disabled) return;
    const courseId = parseInt(btn.getAttribute('data-course-id') || COURSE_ID_FOR_SAVE, 10);
    const isSaved = btn.getAttribute('data-saved') === '1';
    const action = isSaved ? 'unsave' : 'save';

    btn.disabled = true;
    btn.style.opacity = '0.6';

    fetch('/physics_academy/ajax_toggle_save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ course_id: courseId, action: action })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.style.opacity = '1';
        if (data.ok) {
            const newSaved = data.saved; // true=save, false=unsave
            btn.setAttribute('data-saved', newSaved ? '1' : '0');
            btn.classList.toggle('saved', newSaved);
            const icon = document.getElementById('heroSaveIcon');
            const text = document.getElementById('heroSaveText');
            if (icon) icon.innerHTML = newSaved
                ? '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="currentColor"/>'
                : '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>';
            if (text) text.textContent = newSaved ? 'حذف از لیست' : 'ذخیره دوره';
            if (typeof showToast === 'function') {
                showToast(newSaved ? 'دوره ذخیره شد.' : 'دوره از لیست حذف شد.', 'success');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'خطا', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
        if (typeof showToast === 'function') showToast('خطای شبکه', 'error');
    });
}

// ===== وضعیت «ادامه تماشا» =====
// پارامترهای resume و t از URL خوانده می‌شوند (از صفحه اصلی یا پروفایل).
// اگر resume=ID موجود بود، آن محتوا به‌جای اولین محتوا باز می‌شود.
// اگر t=SECONDS موجود بود، ویدیو از آن ثانیه پخش می‌شود.
const URL_PARAMS = new URLSearchParams(window.location.search);
const RESUME_CONTENT_ID = parseInt(URL_PARAMS.get('resume') || '0', 10) || 0;
const RESUME_POSITION   = parseFloat(URL_PARAMS.get('t') || '0') || 0;
let pendingResumePosition = 0; // ثانیه‌ای که باید بعد از loadedmetadata ویدیو فعال شود

// ===== ثبت پیشرفت (نسبی به زمان تماشای ویدیو) =====
// برای ویدیوها: درصد بر اساس currentTime/duration محاسبه می‌شود.
//   مثلاً تماشای ۵۰٪ ویدیو → progress=50، پایان → progress=100.
// برای PDFها: هنگام باز کردن progress=100 ارسال می‌شود.
// پیشرفت فقط افزایش می‌یابد (سرور GREATEST می‌گیرد) و در کلاینت هم
// فقط در صورت افزایش حداقل ۵٪ یا رسیدن به ۱۰۰ درخواست ارسال می‌شود
// تا از ارسال درخواست‌های بی‌مورد جلوگیری شود.
const _lastSentProgress = {}; // content_id => آخرین درصد ارسال‌شده

function markProgress(contentIdx, percent) {
    if (!CAN_TRACK || contentIdx < 0 || contentIdx >= CONTENTS.length) return;
    const item = CONTENTS[contentIdx];
    if (!item || !item.content_id) return;

    percent = Math.max(0, Math.min(100, Math.round(percent)));

    // فقط ارسال کن اگر درصد بیشتر از آخرین ارسال شده باشد
    const last = _lastSentProgress[item.content_id] || 0;
    if (percent <= last) return;
    // حداقل ۵٪ افزایش یا رسیدن به ۱۰۰ — تا از ارسال بی‌مورد جلوگیری شود
    if (percent < 100 && percent < last + 5) return;

    _lastSentProgress[item.content_id] = percent;

    fetch('/physics_academy/progress_mark.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
            content_id: item.content_id,
            course_id:  COURSE_ID,
            progress:   percent
        })
    }).then(r => r.json()).then(data => {
        // به‌روزرسانی نشانگر درصد در سایدبار پس از پاسخ سرور
        if (data && data.ok) {
            updateContentProgressIndicator(item.content_id, percent);
        }
    }).catch(() => {});
}

// ===== ذخیره وضعیت «ادامه تماشا» =====
// هر ۱۰ ثانیه (یا هنگام تعویض محتوا/خروج) آخرین content_id و position ذخیره می‌شود
// تا کاربر دفعه بعد از همان نقطه ادامه دهد.
let _resumeSaveTimer = null;
function saveResumeState() {
    if (!CAN_TRACK || currentIdx < 0) return;
    const item = CONTENTS[currentIdx];
    if (!item || !item.content_id) return;
    const position = (videoEl && videoEl.duration) ? Math.floor(videoEl.currentTime) : 0;
    fetch('/physics_academy/resume_save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
            course_id: COURSE_ID,
            content_id: item.content_id,
            position_seconds: position
        })
    }).catch(() => {});
}
// ذخیره دوره‌ای هر ۱۵ ثانیه + هنگام تعویض محتوا + هنگام unload صفحه
if (CAN_TRACK) {
    _resumeSaveTimer = setInterval(saveResumeState, 15000);
    window.addEventListener('beforeunload', saveResumeState);
    document.addEventListener('visibilitychange', () => { if (document.hidden) saveResumeState(); });
}

// ===== اسپینر لودینگ پلیر =====
function showPlayerLoading() {
    const el = document.getElementById('playerLoading');
    if (el) el.classList.add('show');
}
function hidePlayerLoading() {
    const el = document.getElementById('playerLoading');
    if (el) el.classList.remove('show');
}

// ===== سیستم Toast =====
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    const icons = {
        success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        info:    '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    toast.innerHTML = (icons[type] || icons.info) + '<span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

// به‌روزرسانی بصری درصد پیشرفت یک محتوا در لیست سایدبار
function updateContentProgressIndicator(contentId, percent) {
    const item = document.querySelector('.content-item[data-cid="' + contentId + '"]');
    if (!item) return;
    item.setAttribute('data-progress', percent);
    const badge = item.querySelector('.content-progress-badge');
    if (badge) {
        badge.textContent = percent >= 100 ? '✓' : percent + '%';
        badge.classList.toggle('done', percent >= 100);
    }
}

function isMobilePlayer(){ return window.matchMedia('(max-width:768px)').matches; }

function toggleFullscreen(){
  const wrap = document.getElementById('playerWrap');
  if(!wrap) return;
  const fs = document.fullscreenElement || document.webkitFullscreenElement;
  if(fs){ (document.exitFullscreen||document.webkitExitFullscreen||function(){}).call(document); return; }
  if(wrap.requestFullscreen) wrap.requestFullscreen().catch(()=>{ if(videoEl&&videoEl.webkitEnterFullscreen) videoEl.webkitEnterFullscreen(); });
  else if(wrap.webkitRequestFullscreen) wrap.webkitRequestFullscreen();
  else if(videoEl&&videoEl.webkitEnterFullscreen) videoEl.webkitEnterFullscreen();
}

// Accordion
(function(){
  const MAX_H = 420;
  document.querySelectorAll('.section-card').forEach(card=>{
    const header = card.querySelector('.section-card-header');
    const body   = card.querySelector('.section-card-body');
    const scroll = card.classList.contains('scroll-limited');
    if(!header||!body) return;
    const open=()=>{
      card.classList.add('active');
      header.setAttribute('aria-expanded','true');
      if(scroll){ body.style.maxHeight=MAX_H+'px'; body.style.overflowY='auto'; }
      else{
        body.style.maxHeight=body.scrollHeight+'px';
        body.addEventListener('transitionend',function e(){
          if(card.classList.contains('active')) body.style.maxHeight='none';
          body.removeEventListener('transitionend',e);
        });
      }
    };
    const close=()=>{
      if(body.style.maxHeight==='none') body.style.maxHeight=body.scrollHeight+'px';
      requestAnimationFrame(()=>{
        card.classList.remove('active');
        header.setAttribute('aria-expanded','false');
        body.style.maxHeight='0px'; body.style.overflowY='hidden';
      });
    };
    const toggle=()=>card.classList.contains('active')?close():open();
    if(card.classList.contains('active')) requestAnimationFrame(()=>open());
    else body.style.maxHeight='0px';
    header.addEventListener('click',toggle);
    header.addEventListener('keydown',e=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggle(); } });
  });
  window.addEventListener('resize',()=>{
    document.querySelectorAll('.section-card.active').forEach(card=>{
      const body=card.querySelector('.section-card-body');
      if(!body||card.classList.contains('scroll-limited')) return;
      body.style.maxHeight='none';
    });
  });
})();

function playItem(idx){
  if(idx<0||idx>=TOTAL) return;
  // ذخیره وضعیت محتوای قبلی قبل از تعویض
  if (currentIdx >= 0 && CAN_TRACK) saveResumeState();
  currentIdx=idx;
  const item=CONTENTS[idx];
  document.querySelectorAll('.content-item').forEach(el=>el.classList.remove('playing'));
  const el=document.querySelector(`.content-item[data-idx="${idx}"]`);
  if(el){ el.classList.add('playing'); el.scrollIntoView({behavior:'smooth',block:'nearest'}); }
  document.getElementById('btnPrev').disabled=(idx===0);
  document.getElementById('btnNext').disabled=(idx===TOTAL-1);
  document.getElementById('playerCounter').textContent=`${idx+1} / ${TOTAL}`;
  // اگر این محتوا همان resume-target است، position را برای اعمال بعد از loadedmetadata نگه می‌داریم
  if (RESUME_CONTENT_ID && item.content_id === RESUME_CONTENT_ID && RESUME_POSITION > 0) {
    pendingResumePosition = RESUME_POSITION;
  } else {
    pendingResumePosition = 0;
  }
  if(item.type==='video'){
    // پیشرفت ویدیو توسط رویداد timeupdate و ended ثبت می‌شود
    // (نه هنگام شروع) تا درصد واقعی تماشا ذخیره شود.
    document.getElementById('pdfViewerWrap').classList.remove('show');
    document.getElementById('playerWrap').classList.add('show');
    document.getElementById('playerActionsBar').classList.add('show');
    renderVideo(item.url,item.title);
    document.getElementById('playerWrap').scrollIntoView({behavior:'smooth',block:'start'});
  } else {
    // PDF مدت زمان ندارد — هنگام باز کردن ۱۰۰٪ ثبت می‌شود
    markProgress(idx, 100);
    stopVideo();
    document.getElementById('playerWrap').classList.remove('show');
    document.getElementById('playerActionsBar').classList.remove('show');
    const pw=document.getElementById('pdfViewerWrap');
    pw.classList.add('show');
    document.getElementById('pdfOpenLink').href=item.url;
    document.getElementById('pdfDlLink').href=item.url;
    document.getElementById('pdfFileName').textContent=item.title;
    document.getElementById('pdfCounter').textContent=`${idx+1} / ${TOTAL}`;
    document.getElementById('pdfBtnPrev').disabled=(idx===0);
    document.getElementById('pdfBtnNext').disabled=(idx===TOTAL-1);
    pw.scrollIntoView({behavior:'smooth',block:'start'});
  }
}

function stopVideo(){
  if(rafId){ cancelAnimationFrame(rafId); rafId=null; }
  if(tapTimer){ clearTimeout(tapTimer); tapTimer=null; }
  if(videoEl){
    videoEl.pause();
    if(videoEl._keyHandler) document.removeEventListener('keydown',videoEl._keyHandler);
    videoEl=null;
  }
  document.getElementById('playerScreen').innerHTML='';
  const ov=document.getElementById('controlsOverlay');
  if(ov) ov.classList.add('hidden');
  const pw=document.getElementById('playerWrap');
  if(pw) pw.classList.remove('controls-visible','fs-controls-visible','fs-cursor-hidden');
}

function renderVideo(url,titleText){
  stopVideo();
  showPlayerLoading(); // نمایش اسپینر لودینگ
  const screen=document.getElementById('playerScreen');
  const overlay=document.getElementById('controlsOverlay');
  const dlBtn=document.getElementById('btnDownload');
  if(/youtube\.com|youtu\.be/.test(url)){
    const m=url.match(/(?:v=|youtu\.be\/)([a-zA-Z0-9_\-]{11})/);
    screen.innerHTML=`<iframe src="https://www.youtube.com/embed/${m?m[1]:''}?autoplay=1&rel=0" allow="autoplay;encrypted-media;fullscreen" allowfullscreen></iframe>`;
    overlay.classList.add('hidden'); dlBtn.classList.add('hidden');
    hidePlayerLoading(); // iframe خودش لودینگ نشان می‌دهد
  } else if(/vimeo\.com/.test(url)){
    const m=url.match(/vimeo\.com\/(\d+)/);
    screen.innerHTML=`<iframe src="https://player.vimeo.com/video/${m?m[1]:''}?autoplay=1&color=f5c451" allow="autoplay;fullscreen" allowfullscreen></iframe>`;
    overlay.classList.add('hidden'); dlBtn.classList.add('hidden');
    hidePlayerLoading();
  } else {
    const v=document.createElement('video');
    v.style.cssText='width:100%;aspect-ratio:16/9;background:#000;display:block;';
    v.setAttribute('playsinline',''); v.preload='metadata';
    const s=document.createElement('source'); s.src=url; v.appendChild(s);
    screen.appendChild(v); videoEl=v;
    dlBtn.classList.remove('hidden'); dlBtn.href=url; dlBtn.download=titleText||'video';
    // لودینگ تا زمانی که متادیتا ویدیو لود شود
    v.addEventListener('loadedmetadata', () => {
      hidePlayerLoading();
      // اگر position resume در انتظار است، اعمال کن
      if (pendingResumePosition > 0 && v.duration && pendingResumePosition < v.duration - 2) {
        v.currentTime = pendingResumePosition;
      }
      pendingResumePosition = 0;
    });
    v.addEventListener('waiting', showPlayerLoading);   // بافرینگ → اسپینر
    v.addEventListener('canplay', hidePlayerLoading);   // آماده پخش → مخفی
    v.addEventListener('error', () => {
      hidePlayerLoading();
      showToast('خطا در بارگذاری ویدیو. لطفاً دوباره تلاش کنید.', 'error');
    });
    if(isMobilePlayer()){ v.setAttribute('controls',''); v.controls=true; overlay.classList.add('hidden'); }
    else { overlay.classList.remove('hidden'); initCustomControls(v); }
    v.play().catch(()=>{ hidePlayerLoading(); });
  }
}

function initCustomControls(v){
  const btnPP=document.getElementById('btnPlayPause');
  const iconPlay=document.getElementById('iconPlay'),iconPause=document.getElementById('iconPause');
  const ctrlTime=document.getElementById('ctrlTime');
  const pFill=document.getElementById('progressFill'),pThumb=document.getElementById('progressThumb');
  const progBar=document.getElementById('progressBar');
  const btnMute=document.getElementById('btnMute'),volSlider=document.getElementById('volSlider');
  const speedSel=document.getElementById('speedSelect'),btnFS=document.getElementById('btnFullscreen');
  const overlay=document.getElementById('controlsOverlay'),pw=document.getElementById('playerWrap');
  let hideTimer=null;
  const showC=()=>{ pw.classList.add('controls-visible'); overlay.classList.remove('hidden'); clearTimeout(hideTimer); hideTimer=setTimeout(()=>{ if(!videoEl||videoEl.paused) return; pw.classList.remove('controls-visible'); },2800); };
  const showP=()=>{ pw.classList.add('controls-visible'); overlay.classList.remove('hidden'); clearTimeout(hideTimer); };
  pw.addEventListener('mousemove',showC);
  pw.addEventListener('mouseleave',()=>{ clearTimeout(hideTimer); if(videoEl&&!videoEl.paused) pw.classList.remove('controls-visible'); });
  pw.addEventListener('touchstart',showC,{passive:true});
  v.addEventListener('pause',showP); v.addEventListener('play',showC); v.addEventListener('ended',showP);
  speedSel.value='1'; volSlider.value='1'; v.playbackRate=1; v.volume=1;
  ctrlTime.textContent='0:00 / 0:00'; pFill.style.width='0%'; pThumb.style.left='0%';
  showC();
  const fmt=s=>{ if(!s||isNaN(s)||!isFinite(s)) return '0:00'; s=Math.floor(s); return `${Math.floor(s/60)}:${(s%60).toString().padStart(2,'0')}`; };
  const tick=()=>{ if(!videoEl||isDragging){ rafId=requestAnimationFrame(tick); return; } const d=v.duration; if(d&&isFinite(d)){ const p=(v.currentTime/d)*100; pFill.style.width=p+'%'; pThumb.style.left=p+'%'; ctrlTime.textContent=`${fmt(v.currentTime)} / ${fmt(d)}`; } rafId=requestAnimationFrame(tick); };
  rafId=requestAnimationFrame(tick);
  const syncUI=()=>{ iconPlay.style.display=v.paused?'':'none'; iconPause.style.display=v.paused?'none':''; };
  v.addEventListener('play',syncUI); v.addEventListener('pause',syncUI);
  // ثبت ۱۰۰٪ پیشرفت هنگام پایان ویدیو
  v.addEventListener('ended',()=>{ markProgress(currentIdx, 100); if(currentIdx<TOTAL-1) playItem(currentIdx+1); });
  // ثبت درصد پیشرفت بر اساس زمان تماشا (هر بار که زمان تغییر می‌کند)
  v.addEventListener('timeupdate',()=>{
    if(v.duration && isFinite(v.duration) && v.duration > 0){
      const pct = (v.currentTime / v.duration) * 100;
      markProgress(currentIdx, pct);
    }
  });
  const getPos=x=>{ const r=progBar.getBoundingClientRect(); return Math.max(0,Math.min(1,(x-r.left)/r.width)); };
  const applySeek=x=>{ if(!v.duration) return; const p=getPos(x); pFill.style.width=(p*100)+'%'; pThumb.style.left=(p*100)+'%'; v.currentTime=p*v.duration; };
  progBar.addEventListener('mousedown',e=>{ isDragging=true; applySeek(e.clientX); const mv=e2=>applySeek(e2.clientX),up=()=>{ isDragging=false; window.removeEventListener('mousemove',mv); window.removeEventListener('mouseup',up); }; window.addEventListener('mousemove',mv); window.addEventListener('mouseup',up); e.preventDefault(); });
  progBar.addEventListener('click',e=>applySeek(e.clientX));
  progBar.addEventListener('touchstart',e=>{ isDragging=true; applySeek(e.touches[0].clientX); e.preventDefault(); },{passive:false});
  progBar.addEventListener('touchmove',e=>{ applySeek(e.touches[0].clientX); e.preventDefault(); },{passive:false});
  progBar.addEventListener('touchend',()=>{ isDragging=false; });
  btnPP.onclick=()=>v.paused?v.play():v.pause();
  btnMute.onclick=()=>{ v.muted=!v.muted; volSlider.value=v.muted?'0':String(v.volume); };
  volSlider.oninput=e=>{ v.volume=+e.target.value; v.muted=v.volume===0; };
  speedSel.onchange=()=>{ v.playbackRate=parseFloat(speedSel.value); };
  document.getElementById('btnRewind').onclick=()=>{ if(v.duration) v.currentTime=Math.max(0,v.currentTime-15); };
  document.getElementById('btnForward').onclick=()=>{ if(v.duration) v.currentTime=Math.min(v.duration,v.currentTime+15); };
  btnFS.onclick=()=>toggleFullscreen();
  const handleKey=e=>{ if(!videoEl) return; if(['INPUT','SELECT','TEXTAREA'].includes(document.activeElement?.tagName)) return; switch(e.key){ case ' ': case 'k': e.preventDefault(); videoEl.paused?videoEl.play():videoEl.pause(); break; case 'ArrowRight': e.preventDefault(); videoEl.currentTime=Math.min((videoEl.duration||0),videoEl.currentTime+5); break; case 'ArrowLeft': e.preventDefault(); videoEl.currentTime=Math.max(0,videoEl.currentTime-5); break; case 'ArrowUp': e.preventDefault(); videoEl.volume=Math.min(1,videoEl.volume+0.1); volSlider.value=String(videoEl.volume); break; case 'ArrowDown': e.preventDefault(); videoEl.volume=Math.max(0,videoEl.volume-0.1); volSlider.value=String(videoEl.volume); break; case 'f': case 'F': e.preventDefault(); toggleFullscreen(); break; case 'm': case 'M': e.preventDefault(); videoEl.muted=!videoEl.muted; volSlider.value=videoEl.muted?'0':String(videoEl.volume); break; } };
  document.addEventListener('keydown',handleKey); v._keyHandler=handleKey;
  let fsHideTimer=null;
  const showFS=()=>{ if(!document.fullscreenElement) return; pw.classList.add('fs-controls-visible','controls-visible'); overlay.classList.remove('hidden'); pw.classList.remove('fs-cursor-hidden'); clearTimeout(fsHideTimer); fsHideTimer=setTimeout(()=>{ if(videoEl&&!videoEl.paused){ pw.classList.remove('fs-controls-visible','controls-visible'); pw.classList.add('fs-cursor-hidden'); } },3000); };
  document.addEventListener('fullscreenchange',()=>{ if(!document.fullscreenElement){ pw.classList.remove('fs-controls-visible','fs-cursor-hidden'); clearTimeout(fsHideTimer); } else showFS(); });
  const origMM=()=>{ if(document.fullscreenElement) showFS(); else showC(); };
  pw.removeEventListener('mousemove',showC); pw.addEventListener('mousemove',origMM);
}

const pvw=document.getElementById('playerVideoWrap');
const DBL_MS=300; let tapTimer=null;
function getTapZone(x){ const r=pvw.getBoundingClientRect(); return (x-r.left)/r.width; }
if (pvw) {
pvw.addEventListener('click',e=>{
  if(!videoEl) return;
  if(videoEl.hasAttribute('controls')) return;
  if(e.target.closest('.player-controls-overlay')) return;
  if(tapTimer){ clearTimeout(tapTimer); tapTimer=null; const z=getTapZone(e.clientX); if(z<0.35) videoEl.currentTime=Math.max(0,videoEl.currentTime-10); else if(z>0.65) videoEl.currentTime=Math.min(videoEl.duration||videoEl.currentTime,videoEl.currentTime+10); else toggleFullscreen(); return; }
  tapTimer=setTimeout(()=>{ tapTimer=null; if(videoEl) videoEl.paused?videoEl.play():videoEl.pause(); },DBL_MS);
});
}

// navigation listeners — فقط اگر پلیر وجود داشته باشد (دسترسی مسدود نباشد)
if (document.getElementById('btnPrev')) {
  document.getElementById('btnPrev').addEventListener('click',()=>playItem(currentIdx-1));
  document.getElementById('btnNext').addEventListener('click',()=>playItem(currentIdx+1));
  document.getElementById('pdfBtnPrev').addEventListener('click',()=>playItem(currentIdx-1));
  document.getElementById('pdfBtnNext').addEventListener('click',()=>playItem(currentIdx+1));
}

// ===== اتوریزوم: اگر پارامتر resume در URL باشد، آن محتوا را باز کن =====
(function autoResume(){
  // اگر پلیر وجود ندارد (مثلاً دسترسی مسدود است)، کاری نکن
  if (!document.getElementById('playerWrap')) return;
  if (!RESUME_CONTENT_ID || !CONTENTS.length) return;
  const idx = CONTENTS.findIndex(c => c.content_id === RESUME_CONTENT_ID);
  if (idx >= 0) {
    playItem(idx);
    if (RESUME_POSITION > 0) {
      const mins = Math.floor(RESUME_POSITION / 60);
      const secs = Math.floor(RESUME_POSITION % 60);
      showToast('ادامه از ' + mins + ':' + String(secs).padStart(2,'0') + ' — از جایی که قطع کرده بودید', 'info');
    }
  }
})();
</script>
<?php include __DIR__ . '/partials/theme_toggle.php'; ?>
<script src="/physics_academy/assets/shared.js"></script>
</body>
</html>