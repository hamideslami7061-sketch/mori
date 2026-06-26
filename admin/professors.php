<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$msgType= 'success';

// CSRF برای POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
// CSRF برای GET state-changing (delete)
if ($action === 'delete') {
    startUserSession();
    $token = $_GET['csrf'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('دسترسی غیرمجاز: توکن امنیتی نامعتبر.');
    }
}

if ($action === 'delete' && $id) {
    $has = $db->prepare("SELECT COUNT(*) FROM courses WHERE professor_id=?");
    $has->execute([$id]);
    if ($has->fetchColumn() > 0) {
        $msg = 'این استاد دوره دارد. ابتدا دوره‌هایش را حذف یا انتقال دهید.';
        $msgType = 'error';
        $action = 'list';
    } else {
        $db->prepare("DELETE FROM professors WHERE id=?")->execute([$id]);
        auditLog('professor_delete', 'professor', $id);
        header('Location: /physics_academy/admin/professors.php?msg=deleted');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    $name      = trim($_POST['name'] ?? '');
    $field     = trim($_POST['field'] ?? '');
    $degree    = trim($_POST['degree'] ?? '');
    $rank      = trim($_POST['rank'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $research  = trim($_POST['research'] ?? '');
    $photo_url = trim($_POST['photo_url'] ?? '');

    if (!$name) {
        $msg = 'نام استاد الزامی است.';
        $msgType = 'error';
    } else {
        $slug = slugify_fa($name);
        if ($action === 'add') {
            $chk = $db->prepare("SELECT COUNT(*) FROM professors WHERE slug=?");
            $chk->execute([$slug]);
            if ($chk->fetchColumn()) $slug .= '-' . time();
            $db->prepare("INSERT INTO professors (name,slug,photo_url,field,degree,rank,bio,research,awards,publications,experience)
                          VALUES (?,?,?,?,?,?,?,?,'[]',0,0)")
               ->execute([$name,$slug,$photo_url,$field,$degree,$rank,$bio,$research]);
            auditLog('professor_add', 'professor', (int)$db->lastInsertId(), ['name' => $name]);
            header('Location: /physics_academy/admin/professors.php?msg=added');
            exit;
        } else {
            $db->prepare("UPDATE professors SET name=?,slug=?,photo_url=?,field=?,degree=?,rank=?,bio=?,research=? WHERE id=?")
               ->execute([$name,$slug,$photo_url,$field,$degree,$rank,$bio,$research,$id]);
            auditLog('professor_edit', 'professor', $id, ['name' => $name]);
            header('Location: /physics_academy/admin/professors.php?msg=updated');
            exit;
        }
    }
}

if (!$msg && isset($_GET['msg'])) {
    $msgs = ['added'=>'استاد اضافه شد.','updated'=>'استاد به‌روزرسانی شد.','deleted'=>'استاد حذف شد.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

$prof = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM professors WHERE id=?");
    $s->execute([$id]);
    $prof = $s->fetch();
    if (!$prof) { header('Location: /physics_academy/admin/professors.php'); exit; }
}

$professors = $db->query("SELECT p.*, COUNT(c.id) AS course_count FROM professors p LEFT JOIN courses c ON c.professor_id=p.id GROUP BY p.id ORDER BY p.id DESC")->fetchAll();

$page_title = match($action){'add'=>'افزودن استاد','edit'=>'ویرایش استاد',default=>'مدیریت اساتید'};
$active_nav = 'professors';
$topbar_actions = ($action==='list')
    ? '<a href="?action=add" class="topbar-btn primary"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>استاد جدید</a>'
    : '<a href="professors.php" class="topbar-btn ghost">انصراف</a>';
include __DIR__ . '/partials/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?>">
    <svg viewBox="0 0 24 24"><?= $msgType==='success'?'<polyline points="20 6 9 17 4 12"/>':'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>' ?></svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div class="admin-breadcrumb">
    <a href="professors.php">اساتید</a><span>›</span>
    <span><?= $action==='add'?'افزودن':'ویرایش: '.htmlspecialchars($prof['name']) ?></span>
</div>
<form method="POST">
<?= csrf_field() ?>
<div class="form-card">
    <h2>اطلاعات استاد</h2>
    <div class="form-grid">
        <div class="form-field">
            <label>نام کامل <span class="req">*</span></label>
            <input type="text" name="name" value="<?= htmlspecialchars($prof['name']??'') ?>" placeholder="دکتر احمدی" required>
        </div>
        <div class="form-field">
            <label>مرتبه علمی</label>
            <select name="rank">
                <?php foreach (['','استاد تمام','دانشیار','استادیار','مربی'] as $r): ?>
                <option value="<?= $r ?>" <?= ($prof['rank']??'')===$r?'selected':'' ?>><?= $r?:'-- انتخاب کنید --' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field span2">
            <label>آدرس عکس استاد (URL)</label>
            <input type="url" name="photo_url" value="<?= htmlspecialchars($prof['photo_url']??'') ?>"
                   placeholder="https://example.com/photo.jpg"
                   dir="ltr" oninput="previewPhoto(this.value)">
            <span class="form-hint">لینک مستقیم عکس پروفایل استاد را وارد کنید.</span>
        </div>
        <!-- پیش‌نمایش عکس -->
        <div class="form-field span2" id="photoPreviewWrap" style="<?= empty($prof['photo_url'])?'display:none':'' ?>">
            <label>پیش‌نمایش</label>
            <img id="photoPreview" src="<?= htmlspecialchars($prof['photo_url']??'') ?>"
                 style="width:80px;height:80px;border-radius:12px;object-fit:cover;border:1px solid var(--stroke);"
                 onerror="this.style.display='none'">
        </div>
        <div class="form-field span2">
            <label>تخصص / حوزه</label>
            <input type="text" name="field" value="<?= htmlspecialchars($prof['field']??'') ?>" placeholder="فیزیک نظری - مکانیک کوانتومی">
        </div>
        <div class="form-field span2">
            <label>مدرک تحصیلی</label>
            <input type="text" name="degree" value="<?= htmlspecialchars($prof['degree']??'') ?>" placeholder="دکترا از MIT">
        </div>
        <div class="form-field span2">
            <label>بیوگرافی</label>
            <textarea name="bio" rows="5" placeholder="معرفی کوتاه استاد..."><?= htmlspecialchars($prof['bio']??'') ?></textarea>
        </div>
        <div class="form-field span2">
            <label>حوزه‌های تحقیقاتی</label>
            <input type="text" name="research" value="<?= htmlspecialchars($prof['research']??'') ?>"
                   placeholder="با ویرگول جدا کنید: نظریه میدان، گرانش کوانتومی، ...">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="topbar-btn primary">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $action==='add'?'افزودن استاد':'ذخیره تغییرات' ?>
        </button>
        <a href="professors.php" class="topbar-btn ghost">انصراف</a>
    </div>
</div>
</form>
<script>
function previewPhoto(url) {
    const wrap = document.getElementById('photoPreviewWrap');
    const img  = document.getElementById('photoPreview');
    if (url) {
        img.src = url;
        img.style.display = 'block';
        wrap.style.display = '';
    } else {
        wrap.style.display = 'none';
    }
}
</script>

<?php else: ?>
<div class="table-card">
    <div class="table-card-header"><h2>فهرست اساتید (<?= count($professors) ?>)</h2></div>
    <?php if (empty($professors)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>استادی ثبت نشده. <a href="?action=add" style="color:var(--gold)">اضافه کنید</a></p>
    </div>
    <?php else: ?>
    <table>
        <thead><tr><th>عکس</th><th>نام</th><th>تخصص</th><th>مرتبه</th><th>دوره‌ها</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($professors as $p): ?>
        <tr>
            <td>
                <?php if (!empty($p['photo_url'])): ?>
                <img src="<?= htmlspecialchars($p['photo_url']) ?>" style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid var(--stroke);" onerror="this.style.opacity='.3'">
                <?php else: ?>
                <div style="width:38px;height:38px;border-radius:8px;background:rgba(245,196,81,.1);border:1px solid rgba(245,196,81,.2);display:grid;place-items:center;">
                    <svg style="width:18px;height:18px;stroke:var(--gold);fill:none;stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                </div>
                <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td style="color:var(--muted);font-size:.82rem;"><?= htmlspecialchars($p['field']) ?></td>
            <td><?= htmlspecialchars($p['rank']) ?></td>
            <td><span class="badge-active on"><?= $p['course_count'] ?> دوره</span></td>
            <td>
                <div class="td-actions">
                    <a href="?action=edit&id=<?= $p['id'] ?>" class="btn-icon edit" title="ویرایش">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <a href="?action=delete&id=<?= $p['id'] ?>&csrf=<?= csrf_token() ?>" class="btn-icon del" title="حذف"
                       onclick="return confirm('استاد «<?= addslashes($p['name']) ?>» حذف شود؟')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    </a>
                    <a href="/physics_academy/professor.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn-icon edit" title="مشاهده">
                        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/layout_end.php'; ?>
