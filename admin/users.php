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

// ===== CSRF برای درخواست‌های GET تغییر وضعیت (delete/toggle/block/unblock) =====
// توکن در query string باید با session تطابق داشته باشد.
$statefulActions = ['delete','toggle','block','unblock'];
if (in_array($action, $statefulActions, true)) {
    startUserSession();
    $token = $_GET['csrf'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('دسترسی غیرمجاز: توکن امنیتی نامعتبر.');
    }
}

// ===== DELETE =====
if ($action === 'delete' && $id) {
    $row = $db->prepare("SELECT name FROM users WHERE id = ?");
    $row->execute([$id]);
    $uname = $row->fetchColumn();
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    auditLog('user_delete', 'user', $id, ['name' => $uname]);
    header('Location: /physics_academy/admin/users.php?msg=deleted');
    exit;
}

// ===== TOGGLE ACTIVE =====
if ($action === 'toggle' && $id) {
    $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    auditLog('user_toggle', 'user', $id);
    header('Location: /physics_academy/admin/users.php?msg=updated');
    exit;
}

// ===== ADD USER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $name          = trim($_POST['name'] ?? '');
    $student_id    = trim($_POST['student_id'] ?? '');
    $national_code = trim($_POST['national_code'] ?? '');

    if (!$name || !$student_id || !$national_code) {
        $msg = 'همه فیلدها الزامی هستند.';
        $msgType = 'error';
    } else {
        $check = $db->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");
        $check->execute([$student_id]);
        if ($check->fetchColumn()) {
            $msg = 'این شماره دانشجویی قبلاً ثبت شده.';
            $msgType = 'error';
        } else {
            // کد ملی قبل از ذخیره رمزنگاری می‌شود (AES-256-GCM)
            $enc_code = encryptNationalCode($national_code);
            $db->prepare("INSERT INTO users (name, student_id, national_code) VALUES (?,?,?)")
               ->execute([$name, $student_id, $enc_code]);
            auditLog('user_add', 'user', (int)$db->lastInsertId(), ['name' => $name, 'student_id' => $student_id]);
            header('Location: /physics_academy/admin/users.php?msg=added');
            exit;
        }
    }
}

// ===== EDIT USER =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    $name          = trim($_POST['name'] ?? '');
    $student_id    = trim($_POST['student_id'] ?? '');
    $national_code = trim($_POST['national_code'] ?? '');

    if (!$name || !$student_id || !$national_code) {
        $msg = 'همه فیلدها الزامی هستند.';
        $msgType = 'error';
    } else {
        $check = $db->prepare("SELECT COUNT(*) FROM users WHERE student_id = ? AND id != ?");
        $check->execute([$student_id, $id]);
        if ($check->fetchColumn()) {
            $msg = 'این شماره دانشجویی قبلاً برای کاربر دیگری ثبت شده.';
            $msgType = 'error';
        } else {
            // کد ملی قبل از ذخیره رمزنگاری می‌شود
            $enc_code = encryptNationalCode($national_code);
            $db->prepare("UPDATE users SET name=?, student_id=?, national_code=? WHERE id=?")
               ->execute([$name, $student_id, $enc_code, $id]);
            auditLog('user_edit', 'user', $id, ['name' => $name, 'student_id' => $student_id]);
            header('Location: /physics_academy/admin/users.php?msg=updated');
            exit;
        }
    }
}

// ===== BLOCK / UNBLOCK COURSE ACCESS =====
// مدل blocklist: پیش‌فرض همه کاربران به همه دوره‌ها دسترسی دارند.
// ادمین می‌تواند دسترسی به دوره‌های خاص را برای کاربر خاص قطع کند.
if ($action === 'block' && $id) {
    $course_id = (int)($_GET['course_id'] ?? 0);
    if ($course_id) {
        $db->prepare("INSERT IGNORE INTO user_course_blocks (user_id,course_id) VALUES (?,?)")->execute([$id,$course_id]);
        auditLog('access_block', 'user', $id, ['course_id' => $course_id]);
    }
    header("Location: /physics_academy/admin/users.php?action=access&id={$id}&msg=blocked");
    exit;
}
if ($action === 'unblock' && $id) {
    $course_id = (int)($_GET['course_id'] ?? 0);
    if ($course_id) {
        $db->prepare("DELETE FROM user_course_blocks WHERE user_id=? AND course_id=?")->execute([$id,$course_id]);
        auditLog('access_unblock', 'user', $id, ['course_id' => $course_id]);
    }
    header("Location: /physics_academy/admin/users.php?action=access&id={$id}&msg=unblocked");
    exit;
}

// ===== MESSAGES =====
if (!$msg && isset($_GET['msg'])) {
    $msgs = ['added'=>'کاربر اضافه شد.','updated'=>'به‌روزرسانی شد.','deleted'=>'کاربر حذف شد.','blocked'=>'دسترسی دوره قطع شد.','unblocked'=>'دسترسی دوره بازگردانی شد.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

// ===== LOAD FOR EDIT =====
$edit_user = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM users WHERE id = ?");
    $s->execute([$id]);
    $edit_user = $s->fetch();
    if (!$edit_user) { header('Location: /physics_academy/admin/users.php'); exit; }
    // کد ملی رمزگشایی می‌شود تا در فرم ویرایش نمایش داده شود
    $edit_user['national_code'] = decryptNationalCode($edit_user['national_code'] ?? '');
}

// ===== LOAD ACCESS PAGE =====
// لیست دوره‌های مسدودشده برای این کاربر (blocklist)
$user          = null;
$blocked_ids   = [];
$all_courses   = [];
if ($action === 'access' && $id) {
    $s = $db->prepare("SELECT * FROM users WHERE id = ?");
    $s->execute([$id]);
    $user = $s->fetch();
    if (!$user) { header('Location: /physics_academy/admin/users.php'); exit; }
    $g = $db->prepare("SELECT course_id FROM user_course_blocks WHERE user_id = ?");
    $g->execute([$id]);
    $blocked_ids = array_column($g->fetchAll(), 'course_id');
    $all_courses = $db->query("SELECT c.id, c.title, p.name AS prof_name FROM courses c JOIN professors p ON c.professor_id=p.id WHERE c.is_active=1 ORDER BY c.title")->fetchAll();
}

// ===== LIST ===== (تعداد دوره‌های مسدودشده برای هر کاربر)
$users = $db->query("
    SELECT u.*, COUNT(ucb.course_id) AS blocked_count
    FROM users u LEFT JOIN user_course_blocks ucb ON ucb.user_id=u.id
    GROUP BY u.id ORDER BY u.id DESC
")->fetchAll();

$page_title = match($action) { 'add'=>'افزودن کاربر', 'edit'=>'ویرایش کاربر', 'access'=>'مدیریت دسترسی', default=>'مدیریت کاربران' };
$active_nav = 'users';
$topbar_actions = ($action==='list')
    ? '<a href="?action=add" class="topbar-btn primary"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>کاربر جدید</a>'
    : '<a href="users.php" class="topbar-btn ghost">بازگشت</a>';
include __DIR__ . '/partials/layout_start.php';
?>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?>">
    <svg viewBox="0 0 24 24"><?= $msgType==='success' ? '<polyline points="20 6 9 17 4 12"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' ?></svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- فرم افزودن / ویرایش کاربر -->
<div class="admin-breadcrumb">
    <a href="users.php">کاربران</a><span>›</span>
    <span><?= $action==='add' ? 'افزودن کاربر' : 'ویرایش: '.htmlspecialchars($edit_user['name']) ?></span>
</div>
<form method="POST">
<?= csrf_field() ?>
<div class="form-card">
    <h2>اطلاعات کاربر</h2>
    <p class="form-hint" style="margin-bottom:16px;">این اطلاعات را خودتان وارد می‌کنید. کاربر فقط با شماره دانشجویی و کد ملی وارد می‌شود (بدون ثبت‌نام).</p>
    <div class="form-grid">
        <div class="form-field span2">
            <label>نام و نام خانوادگی <span class="req">*</span></label>
            <input type="text" name="name" placeholder="مثلاً: علی فرزینی" value="<?= htmlspecialchars($edit_user['name'] ?? '') ?>" required>
        </div>
        <div class="form-field">
            <label>شماره دانشجویی <span class="req">*</span></label>
            <input type="text" name="student_id" placeholder="مثلاً: 1" dir="ltr" value="<?= htmlspecialchars($edit_user['student_id'] ?? '') ?>" required>
            <span class="form-hint">این عدد برای ورود کاربر استفاده می‌شود.</span>
        </div>
        <div class="form-field">
            <label>کد ملی <span class="req">*</span></label>
            <input type="text" name="national_code" placeholder="مثلاً: 25" dir="ltr" value="<?= htmlspecialchars($edit_user['national_code'] ?? '') ?>" required>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="topbar-btn primary">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= $action==='add' ? 'افزودن کاربر' : 'ذخیره تغییرات' ?>
        </button>
        <a href="users.php" class="topbar-btn ghost">انصراف</a>
    </div>
</div>
</form>

<?php elseif ($action === 'access' && $user): ?>
<!-- مدیریت دسترسی (blocklist) -->
<div class="admin-breadcrumb"><a href="users.php">کاربران</a><span>›</span><span>دسترسی: <?= htmlspecialchars($user['name']) ?></span></div>
<div class="form-card">
    <h2>مدیریت دسترسی دوره‌ها — <?= htmlspecialchars($user['name']) ?> (شماره دانشجویی: <?= htmlspecialchars($user['student_id']) ?>)</h2>
    <p class="form-hint" style="margin-bottom:16px;padding:10px 14px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:9px;color:#4ade80;">
        <strong>پیش‌فرض:</strong> این کاربر به همه دوره‌ها دسترسی دارد. برای قطع دسترسی به دوره خاص، روی «قطع دسترسی» کلیک کنید. هر زمان بتوانید دوباره بازگردانی کنید.
    </p>
    <?php if (empty($all_courses)): ?>
    <p style="color:var(--muted);">دوره‌ای وجود ندارد.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>دوره</th><th>استاد</th><th>وضعیت دسترسی</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($all_courses as $c): ?>
        <?php $blocked = in_array($c['id'], $blocked_ids); ?>
        <tr>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td><?= htmlspecialchars($c['prof_name']) ?></td>
            <td>
                <?php if ($blocked): ?>
                <span class="badge-active off">قطع شده</span>
                <?php else: ?>
                <span class="badge-active on">دارد</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($blocked): ?>
                <a href="?action=unblock&id=<?= $id ?>&course_id=<?= $c['id'] ?>&csrf=<?= csrf_token() ?>" class="topbar-btn primary" style="font-size:.78rem;padding:5px 10px;">بازگردانی دسترسی</a>
                <?php else: ?>
                <a href="?action=block&id=<?= $id ?>&course_id=<?= $c['id'] ?>&csrf=<?= csrf_token() ?>" class="topbar-btn ghost" style="font-size:.78rem;padding:5px 10px;color:var(--red);border-color:rgba(239,68,68,.3);">قطع دسترسی</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- لیست کاربران -->
<div class="table-card">
    <div class="table-card-header"><h2>فهرست کاربران (<?= count($users) ?>)</h2></div>
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
        <p>کاربری ثبت نشده. <a href="?action=add" style="color:var(--gold)">اولین کاربر را اضافه کنید</a></p>
    </div>
    <?php else: ?>
    <table>
        <thead><tr><th>نام</th><th>شماره دانشجویی</th><th>کد ملی</th><th>دوره‌های مسدود</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
            <td style="direction:ltr;text-align:right;color:#d4dbe6;font-size:.85rem;"><?= htmlspecialchars($u['student_id']) ?></td>
            <td style="direction:ltr;text-align:right;color:var(--muted);font-size:.85rem;"><?= htmlspecialchars(decryptNationalCode($u['national_code'] ?? '')) ?></td>
            <td><?= (int)$u['blocked_count'] ?> دوره</td>
            <td>
                <a href="?action=toggle&id=<?= $u['id'] ?>&csrf=<?= csrf_token() ?>">
                    <span class="badge-active <?= $u['is_active']?'on':'off' ?>" style="cursor:pointer;">
                        <?= $u['is_active']?'فعال':'غیرفعال' ?>
                    </span>
                </a>
            </td>
            <td>
                <div class="td-actions">
                    <a href="?action=edit&id=<?= $u['id'] ?>" class="btn-icon edit" title="ویرایش">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <a href="?action=access&id=<?= $u['id'] ?>" class="btn-icon edit" title="مدیریت دسترسی">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </a>
                    <a href="?action=delete&id=<?= $u['id'] ?>&csrf=<?= csrf_token() ?>" class="btn-icon del" title="حذف"
                       onclick="return confirm('کاربر «<?= addslashes($u['name']) ?>» حذف شود؟')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
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