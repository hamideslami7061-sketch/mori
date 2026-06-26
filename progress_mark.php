<?php
// progress_mark.php — ثبت درصد پیشرفت یک محتوا (نسبی به زمان تماشای ویدیو)
//
// منطق:
//   • برای ویدیوها: درصد بر اساس currentTime/duration محاسبه و ارسال می‌شود.
//     مثلاً تماشای ۵۰٪ ویدیو → progress=50، پایان ویدیو → progress=100.
//   • برای PDFها: هنگام باز کردن progress=100 ارسال می‌شود.
//   • پیشرفت فقط افزایش می‌یابد (GREATEST): اگر کاربر به عقب برگردد،
//     درصد ذخیره‌شده کم نمی‌شود.
//   • درصد کل دوره = SUM(progress) / تعداد کل محتوا.
//
// این endpoint مستقل از روش ورود کاربر است؛ فقط به user_id نیاز دارد.
require_once __DIR__ . '/config.php';
startUserSession();

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// CSRF برای درخواست AJAX — توکن در هدر HTTP-X-CSRF-TOKEN ارسال می‌شود
startUserSession();
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'csrf_invalid']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$content_id = (int)($input['content_id'] ?? 0);
$course_id  = (int)($input['course_id']  ?? 0);
// درصد ۰ تا ۱۰۰ — کلاینت ارسال می‌کند. پیش‌فرض ۱۰۰ (سازگار با PDF).
$progress   = (int)($input['progress'] ?? 100);
$progress   = max(0, min(100, $progress)); // محدود کردن به بازه مجاز

if (!$content_id || !$course_id) {
    echo json_encode(['ok' => false, 'error' => 'missing_params']);
    exit;
}

$db = getDB();

// اطمینان از اینکه content واقعاً به این course تعلق دارد
$check = $db->prepare("SELECT id FROM course_contents WHERE id=? AND course_id=?");
$check->execute([$content_id, $course_id]);
if (!$check->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

// ثبت یا به‌روزرسانی درصد پیشرفت.
// از GREATEST استفاده می‌کنیم تا درصد فقط افزایش یابد — اگر کاربر
// ویدیو را به عقب ببرد، درصد ذخیره‌شده کم نمی‌شود.
$db->prepare("
    INSERT INTO user_content_progress (user_id, content_id, course_id, progress)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE progress = GREATEST(progress, VALUES(progress))
")->execute([$user['id'], $content_id, $course_id, $progress]);

// ===== محاسبه درصد جدید کل دوره =====
// درصد کل = SUM(progress هر محتوا) / تعداد کل محتوا
// محتواهایی که هنوز دیده نشده‌اند (ردیف ندارند) به‌عنوان ۰ حساب می‌شوند.
$totalStmt = $db->prepare("SELECT COUNT(*) FROM course_contents WHERE course_id=?");
$totalStmt->execute([$course_id]);
$total = (int)$totalStmt->fetchColumn();

$sumStmt = $db->prepare("SELECT COALESCE(SUM(progress), 0) FROM user_content_progress WHERE user_id=? AND course_id=?");
$sumStmt->execute([$user['id'], $course_id]);
$progress_sum = (int)$sumStmt->fetchColumn();

$percent = $total > 0 ? (int)round($progress_sum / $total) : 0;

echo json_encode([
    'ok'      => true,
    'percent' => $percent,
    'content_progress' => $progress,
    'sum'     => $progress_sum,
    'total'   => $total
]);
exit;
