<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$msg    = '';
$msgType= 'success';

// ===== CSRF برای همه درخواست‌های POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
// ===== CSRF برای درخواست‌های GET تغییر وضعیت =====
$statefulActions = ['delete','toggle'];
if (in_array($action, $statefulActions, true)) {
    startUserSession();
    $token = $_GET['csrf'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('دسترسی غیرمجاز: توکن امنیتی نامعتبر.');
    }
}

// ===== TOGGLE ACTIVE =====
if ($action === 'toggle' && $id) {
    $db->prepare("UPDATE courses SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    auditLog('course_toggle', 'course', $id);
    header('Location: /physics_academy/admin/courses.php?msg=updated');
    exit;
}

// ===== DELETE COURSE =====
if ($action === 'delete' && $id) {
    $row = $db->prepare("SELECT title FROM courses WHERE id = ?");
    $row->execute([$id]);
    $ctitle = $row->fetchColumn();
    $db->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
    auditLog('course_delete', 'course', $id, ['title' => $ctitle]);
    header('Location: /physics_academy/admin/courses.php?msg=deleted');
    exit;
}

// ===== SAVE COURSE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    $title      = trim($_POST['title'] ?? '');
    $prof_id    = (int)($_POST['professor_id'] ?? 0);
    $sessions   = (int)($_POST['sessions'] ?? 0);
    $duration   = trim($_POST['duration'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    $topics_raw = trim($_POST['topics'] ?? '');
    $topics     = array_values(array_filter(array_map('trim', explode("\n", $topics_raw))));
    $topics_json= json_encode($topics, JSON_UNESCAPED_UNICODE);

    if (!$title || !$prof_id) {
        $msg = 'عنوان دوره و استاد الزامی هستند.';
        $msgType = 'error';
    } else {
        $slug = slugify_fa($title);
        if ($action === 'add') {
            $check = $db->prepare("SELECT COUNT(*) FROM courses WHERE slug = ?");
            $check->execute([$slug]);
            if ($check->fetchColumn()) $slug .= '-' . time();
            $db->prepare("INSERT INTO courses (slug,title,professor_id,sessions,duration,description,topics,is_active,sort_order)
                          VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$slug,$title,$prof_id,$sessions,$duration,$desc,$topics_json,$is_active,$sort_order]);
            $newId = (int)$db->lastInsertId();
            // محتوای جدید → به‌روزرسانی زمان محتوای دوره برای نمایش در «دوره‌های اخیر»
            $db->prepare("UPDATE courses SET content_updated_at = NOW() WHERE id = ?")->execute([$newId]);
            auditLog('course_add', 'course', $newId, ['title' => $title]);
            header('Location: /physics_academy/admin/courses.php?msg=added');
            exit;
        } else {
            $db->prepare("UPDATE courses SET slug=?,title=?,professor_id=?,sessions=?,duration=?,description=?,topics=?,is_active=?,sort_order=? WHERE id=?")
               ->execute([$slug,$title,$prof_id,$sessions,$duration,$desc,$topics_json,$is_active,$sort_order,$id]);
            auditLog('course_edit', 'course', $id, ['title' => $title]);
            header("Location: /physics_academy/admin/courses.php?action=edit&id={$id}&msg=updated");
            exit;
        }
    }
}

// ===== MESSAGES =====
if (!$msg && isset($_GET['msg'])) {
    $msgs = [
        'added'           => 'دوره اضافه شد.',
        'updated'         => 'دوره به‌روزرسانی شد.',
        'deleted'         => 'دوره حذف شد.',
        'content_added'   => 'محتوا اضافه شد.',
        'content_deleted' => 'محتوا حذف شد.',
        'ref_added'       => 'مرجع اضافه شد.',
        'ref_deleted'     => 'مرجع حذف شد.',
        'ref_error'       => 'خطا: عنوان و فایل/لینک الزامی هستند.',
        'ref_badtype'     => 'خطا: نوع فایل مجاز نیست.',
        'ref_toolarge'    => 'خطا: حجم فایل بیش از ۱۰۰ مگابایت است.',
        'ref_upload_failed'=> 'خطا: آپلود فایل ناموفق بود.',
    ];
    $msg     = $msgs[$_GET['msg']] ?? '';
    $msgType = str_starts_with($_GET['msg'] ?? '', 'ref_error') || str_starts_with($_GET['msg'] ?? '', 'ref_bad') || str_starts_with($_GET['msg'] ?? '', 'ref_too') || str_starts_with($_GET['msg'] ?? '', 'ref_upload') ? 'error' : 'success';
}

// ===== LOAD FOR EDIT =====
$course          = null;
$course_topics   = '';
$course_contents = [];
$course_refs     = [];
if ($action === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $s->execute([$id]);
    $course = $s->fetch();
    if (!$course) { header('Location: /physics_academy/admin/courses.php'); exit; }
    $t = json_decode($course['topics'] ?? '[]', true) ?: [];
    $course_topics = implode("\n", $t);

    $cs = $db->prepare("SELECT * FROM course_contents WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $cs->execute([$id]);
    $course_contents = $cs->fetchAll();

    $rs = $db->prepare("SELECT * FROM course_references WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $rs->execute([$id]);
    $course_refs = $rs->fetchAll();
}

// ===== PROFESSORS for dropdown =====
$professors = $db->query("SELECT id, name FROM professors ORDER BY name")->fetchAll();

// ===== LIST ===== (بهینه‌شده: LEFT JOIN + GROUP BY به‌جای correlated subquery)
$courses = $db->query("
    SELECT c.*, p.name AS prof_name, COUNT(cc.id) AS content_count
    FROM courses c
    JOIN professors p ON c.professor_id=p.id
    LEFT JOIN course_contents cc ON cc.course_id=c.id
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id DESC
")->fetchAll();

$page_title = match($action) { 'add'=>'افزودن دوره', 'edit'=>'ویرایش دوره', default=>'مدیریت دوره‌ها' };
$active_nav = 'courses';
$topbar_actions = ($action==='list')
    ? '<a href="?action=add" class="topbar-btn primary"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>دوره جدید</a>'
    : '<a href="courses.php" class="topbar-btn ghost">انصراف</a>';

$extra_head = '<style>
.ref-row{display:flex;align-items:center;gap:8px;padding:10px 12px;background:rgba(255,255,255,.04);border:1px solid var(--stroke);border-radius:10px;margin-bottom:8px;}
.ref-row-title{flex:1;font-size:.88rem;color:#d4dbe6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ref-row-url{font-size:.75rem;color:var(--muted);direction:ltr;text-align:left;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ref-badge{padding:3px 8px;border-radius:6px;font-size:.72rem;font-weight:600;background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.2);flex-shrink:0;}
.ref-badge.link{background:rgba(59,130,246,.12);color:#93c5fd;border-color:rgba(59,130,246,.2);}
.upload-area{border:2px dashed rgba(245,196,81,.3);border-radius:12px;padding:20px;text-align:center;background:rgba(245,196,81,.03);transition:all .2s;cursor:pointer;margin-bottom:4px;}
.upload-area:hover,.upload-area.drag{border-color:rgba(245,196,81,.6);background:rgba(245,196,81,.07);}
.upload-area svg{width:28px;height:28px;stroke:var(--gold);fill:none;stroke-width:1.5;margin-bottom:8px;opacity:.7;}
.upload-area p{font-size:.85rem;color:var(--muted);}
.upload-area strong{color:var(--gold);}
.or-divider{display:flex;align-items:center;gap:10px;margin:12px 0;color:var(--muted);font-size:.8rem;}
.or-divider::before,.or-divider::after{content:"";flex:1;height:1px;background:var(--stroke);}
#fileNameDisplay{font-size:.8rem;color:var(--green);margin-top:6px;display:none;}
</style>';

include __DIR__ . '/partials/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?>">
    <svg viewBox="0 0 24 24"><?= $msgType==='success' ? '<polyline points="20 6 9 17 4 12"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' ?></svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="admin-breadcrumb">
    <a href="courses.php">دوره‌ها</a><span>›</span>
    <span><?= $action==='add' ? 'افزودن دوره جدید' : 'ویرایش: '.htmlspecialchars($course['title']) ?></span>
</div>

<form method="POST">
<?= csrf_field() ?>
<div class="form-card">
    <h2>اطلاعات دوره</h2>
    <div class="form-grid">
        <div class="form-field span2">
            <label>عنوان دوره <span class="req">*</span></label>
            <input type="text" name="title" value="<?= htmlspecialchars($course['title'] ?? '') ?>" placeholder="مثال: مکانیک کوانتومی ۱" required>
        </div>
        <div class="form-field">
            <label>استاد <span class="req">*</span></label>
            <select name="professor_id" required>
                <option value="">-- انتخاب استاد --</option>
                <?php foreach ($professors as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($course['professor_id'] ?? 0)==$p['id']?'selected':'' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>وضعیت</label>
            <select name="is_active">
                <option value="1" <?= ($course['is_active'] ?? 1)==1?'selected':'' ?>>فعال</option>
                <option value="0" <?= ($course['is_active'] ?? 1)==0?'selected':'' ?>>غیرفعال</option>
            </select>
        </div>
        <div class="form-field">
            <label>تعداد جلسات</label>
            <input type="number" name="sessions" value="<?= $course['sessions'] ?? 0 ?>" min="0">
        </div>
        <div class="form-field">
            <label>مدت دوره</label>
            <input type="text" name="duration" value="<?= htmlspecialchars($course['duration'] ?? '') ?>" placeholder="مثال: ۳۶ ساعت">
        </div>
        <div class="form-field">
            <label>ترتیب نمایش</label>
            <input type="number" name="sort_order" value="<?= $course['sort_order'] ?? 0 ?>" min="0">
            <span class="form-hint">عدد کمتر = نمایش اول</span>
        </div>
        <div class="form-field span2">
            <label>توضیحات دوره</label>
            <textarea name="description" rows="5" placeholder="توضیح کامل دوره..."><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
        </div>
        <div class="form-field span2">
            <label>سرفصل‌ها (هر سرفصل یک خط)</label>
            <textarea name="topics" rows="6" placeholder="مبانی فیزیک کوانتومی&#10;معادله شرودینگر&#10;اصل عدم قطعیت"><?= htmlspecialchars($course_topics) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="topbar-btn primary">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $action==='add' ? 'افزودن دوره' : 'ذخیره تغییرات' ?>
        </button>
        <a href="courses.php" class="topbar-btn ghost">انصراف</a>
    </div>
</div>
</form>

<?php if ($action === 'edit' && $course): ?>

<!-- ===== مراجع دوره ===== -->
<div class="form-card" style="margin-top:20px;">
    <h2>مراجع دوره (<?= count($course_refs) ?> مورد)</h2>

    <?php if (!empty($course_refs)): ?>
    <div style="margin-bottom:16px;">
        <?php foreach ($course_refs as $i => $ref): ?>
        <?php
            $is_upload = str_starts_with($ref['file_url'], '/physics_academy/uploads/references/');
            $ext = strtolower(pathinfo($ref['file_url'], PATHINFO_EXTENSION));
        ?>
        <div class="ref-row">
            <span style="font-size:.75rem;color:rgba(255,255,255,.3);width:22px;flex-shrink:0;"><?= $i+1 ?></span>
            <span class="ref-badge <?= $is_upload ? '' : 'link' ?>"><?= $is_upload ? strtoupper($ext) : 'لینک' ?></span>
            <span class="ref-row-title"><?= htmlspecialchars($ref['title']) ?></span>
            <a href="<?= htmlspecialchars($ref['file_url']) ?>" target="_blank" class="btn-icon edit" title="باز کردن" style="flex-shrink:0;">
                <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
            <a href="reference_delete.php?id=<?= $ref['id'] ?>&course_id=<?= $id ?>&csrf=<?= csrf_token() ?>"
               class="btn-icon del" title="حذف"
               onclick="return confirm('این مرجع حذف شود؟')">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- فرم افزودن مرجع -->
    <form method="POST" action="reference_add.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="course_id" value="<?= $id ?>">
        <div class="form-grid">
            <div class="form-field span2">
                <label>عنوان مرجع <span class="req">*</span></label>
                <input type="text" name="ref_title" placeholder="مثال: Griffiths - Introduction to Quantum Mechanics" required>
            </div>
            <div class="form-field span2">
                <label>آپلود فایل</label>
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('refFileInput').click()">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p>کلیک کنید یا فایل را اینجا بکشید</p>
                    <p style="font-size:.75rem;margin-top:4px;">PDF، DOC، EPUB، DJVU، ZIP — حداکثر <strong>۱۰۰ مگابایت</strong></p>
                </div>
                <div id="fileNameDisplay"></div>
                <input type="file" id="refFileInput" name="ref_file"
                       accept=".pdf,.doc,.docx,.epub,.djvu,.zip,.rar,.7z,.ppt,.pptx,.xls,.xlsx"
                       style="display:none" onchange="showFileName(this)">
            </div>
            <div class="form-field span2">
                <div class="or-divider">یا</div>
                <label>لینک مستقیم (URL)</label>
                <input type="url" name="ref_url" dir="ltr" placeholder="https://example.com/book.pdf">
                <span class="form-hint">اگر فایل آپلود شده باشد، لینک نادیده گرفته می‌شود.</span>
            </div>
            <div class="form-field">
                <label>ترتیب</label>
                <input type="number" name="ref_sort_order" value="<?= count($course_refs) * 10 ?>" min="0">
            </div>
        </div>
        <div class="form-actions" style="margin-top:12px;">
            <button type="submit" class="topbar-btn primary" style="font-size:.85rem;padding:8px 16px;">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                افزودن مرجع
            </button>
        </div>
    </form>
</div>

<!-- ===== CONTENTS ===== -->
<div class="form-card" style="margin-top:20px;">
    <h2>محتوای دوره (<?= count($course_contents) ?> آیتم)</h2>

    <?php if (!empty($course_contents)): ?>
    <div style="margin-bottom:16px;">
        <?php foreach ($course_contents as $ci): ?>
        <div class="content-row">
            <span class="drag-handle"><svg viewBox="0 0 24 24"><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg></span>
            <span class="content-type-badge <?= $ci['type'] ?>"><?= $ci['type']==='video'?'ویدیو':'PDF' ?></span>
            <span class="content-row-title"><?= htmlspecialchars($ci['title']) ?></span>
            <span style="font-size:.78rem;color:var(--muted);"><?= $ci['type']==='video' ? htmlspecialchars($ci['duration']) : htmlspecialchars($ci['file_size']) ?></span>
            <a href="content_delete.php?id=<?= $ci['id'] ?>&course_id=<?= $id ?>&csrf=<?= csrf_token() ?>"
               class="btn-icon del" title="حذف"
               onclick="return confirm('این آیتم حذف شود؟')">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="content_add.php">
        <?= csrf_field() ?>
        <input type="hidden" name="course_id" value="<?= $id ?>">
        <div class="form-grid">
            <div class="form-field">
                <label>نوع محتوا</label>
                <select name="type" id="contentType" onchange="toggleContentFields()">
                    <option value="video">ویدیو</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            <div class="form-field">
                <label>ترتیب</label>
                <input type="number" name="sort_order" value="<?= count($course_contents) * 10 ?>" min="0">
            </div>
            <div class="form-field span2">
                <label>عنوان <span class="req">*</span></label>
                <input type="text" name="title" placeholder="مثال: جلسه ۱ - مقدمه" required>
            </div>
            <div class="form-field span2">
                <label>لینک (URL) <span class="req">*</span></label>
                <input type="url" name="url" dir="ltr" placeholder="https://youtube.com/watch?v=... یا لینک مستقیم MP4 یا PDF" required>
                <span class="form-hint" id="urlHint">یوتیوب، ویمئو یا لینک مستقیم MP4 پشتیبانی می‌شود.</span>
            </div>
            <div class="form-field" id="fieldDuration">
                <label>مدت ویدیو</label>
                <input type="text" name="duration" placeholder="مثال: ۹۰ دقیقه">
            </div>
            <div class="form-field" id="fieldSize" style="display:none;">
                <label>حجم فایل PDF</label>
                <input type="text" name="file_size" placeholder="مثال: ۲.۴ مگابایت">
            </div>
        </div>
        <div class="form-actions" style="margin-top:12px;">
            <button type="submit" class="topbar-btn primary" style="font-size:.85rem;padding:8px 16px;">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                افزودن محتوا
            </button>
        </div>
    </form>
</div>

<script>
function toggleContentFields(){
    const t=document.getElementById('contentType').value;
    document.getElementById('fieldDuration').style.display=t==='video'?'':'none';
    document.getElementById('fieldSize').style.display=t==='pdf'?'':'none';
    document.getElementById('urlHint').textContent=t==='video'
        ?'یوتیوب، ویمئو یا لینک مستقیم MP4 پشتیبانی می‌شود.'
        :'لینک مستقیم فایل PDF را وارد کنید.';
}
function showFileName(input) {
    const disp = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        disp.style.display = 'block';
        disp.textContent   = '✓ ' + input.files[0].name + ' (' + (input.files[0].size/1024/1024).toFixed(1) + ' MB)';
    }
}
// Drag & Drop
const ua = document.getElementById('uploadArea');
const fi = document.getElementById('refFileInput');
if (ua && fi) {
    ua.addEventListener('dragover', e => { e.preventDefault(); ua.classList.add('drag'); });
    ua.addEventListener('dragleave',()=> ua.classList.remove('drag'));
    ua.addEventListener('drop', e => {
        e.preventDefault(); ua.classList.remove('drag');
        if (e.dataTransfer.files.length) {
            fi.files = e.dataTransfer.files;
            showFileName(fi);
        }
    });
}
</script>
<?php endif; ?>

<?php else: ?>
<!-- ===== LIST ===== -->
<div class="table-card">
    <div class="table-card-header">
        <h2>فهرست دوره‌ها (<?= count($courses) ?>)</h2>
    </div>
    <?php if (empty($courses)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>دوره‌ای ثبت نشده. <a href="?action=add" style="color:var(--gold)">اولین دوره را اضافه کنید</a></p>
    </div>
    <?php else: ?>
    <table>
        <thead><tr><th>عنوان</th><th>استاد</th><th>جلسات</th><th>محتوا</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
            <td><?= htmlspecialchars($c['prof_name']) ?></td>
            <td><?= $c['sessions'] ?></td>
            <td><?= $c['content_count'] ?> فایل</td>
            <td>
                <a href="?action=toggle&id=<?= $c['id'] ?>&csrf=<?= csrf_token() ?>" title="تغییر وضعیت">
                    <span class="badge-active <?= $c['is_active']?'on':'off' ?>" style="cursor:pointer;">
                        <?= $c['is_active']?'فعال':'غیرفعال' ?>
                    </span>
                </a>
            </td>
            <td>
                <div class="td-actions">
                    <a href="?action=edit&id=<?= $c['id'] ?>" class="btn-icon edit" title="ویرایش و محتوا">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <a href="?action=delete&id=<?= $c['id'] ?>&csrf=<?= csrf_token() ?>" class="btn-icon del" title="حذف"
                       onclick="return confirm('دوره «<?= addslashes($c['title']) ?>» و تمام محتوایش حذف شوند؟')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </a>
                    <a href="/physics_academy/course.php?slug=<?= urlencode($c['slug']) ?>" target="_blank" class="btn-icon edit" title="مشاهده در سایت">
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
