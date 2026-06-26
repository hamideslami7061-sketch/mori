<?php
// resume_save.php — ذخیره/بازخوانی وضعیت «ادامه تماشا» برای هر کاربر/دوره
//
// این endpoint دو عمل انجام می‌دهد:
//   POST  → ذخیره last_content_id و last_position_seconds برای (user, course)
//   GET   → بازگرداندن آخرین وضعیت ذخیره‌شده برای (user, course)
//
// ذخیره در جدول user_course_state (PK: user_id + course_id) با upsert.
require_once __DIR__ . '/config.php';
startUserSession();

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

$course_id = (int)($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));
if (!$course_id) {
    // برای POST با JSON body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $course_id = (int)($input['course_id'] ?? 0);
}
if (!$course_id) {
    echo json_encode(['ok' => false, 'error' => 'missing_course']);
    exit;
}

$db = getDB();

// ===== GET: بازگرداندن وضعیت ذخیره‌شده =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT ucs.last_content_id, ucs.last_position_seconds, cc.title AS content_title, cc.type AS content_type
        FROM user_course_state ucs
        LEFT JOIN course_contents cc ON cc.id = ucs.last_content_id
        WHERE ucs.user_id = ? AND ucs.course_id = ?
    ");
    $stmt->execute([$user['id'], $course_id]);
    $state = $stmt->fetch();
    echo json_encode([
        'ok'      => true,
        'has_state'      => $state && $state['last_content_id'],
        'last_content_id'=> $state ? (int)$state['last_content_id'] : null,
        'last_position'  => $state ? (float)$state['last_position_seconds'] : 0,
        'content_title'  => $state['content_title'] ?? null,
        'content_type'   => $state['content_type'] ?? null,
    ]);
    exit;
}

// ===== POST: ذخیره وضعیت =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// CSRF برای AJAX
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'csrf_invalid']);
    exit;
}

$input        = json_decode(file_get_contents('php://input'), true) ?? [];
$content_id   = (int)($input['content_id'] ?? 0);
$position     = (float)($input['position_seconds'] ?? 0);

if (!$content_id) {
    echo json_encode(['ok' => false, 'error' => 'missing_content']);
    exit;
}

// اطمینان از تعلق content به course
$check = $db->prepare("SELECT id FROM course_contents WHERE id=? AND course_id=?");
$check->execute([$content_id, $course_id]);
if (!$check->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

// Upsert
$db->prepare("
    INSERT INTO user_course_state (user_id, course_id, last_content_id, last_position_seconds, updated_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        last_content_id = VALUES(last_content_id),
        last_position_seconds = VALUES(last_position_seconds),
        updated_at = NOW()
")->execute([$user['id'], $course_id, $content_id, $position]);

echo json_encode(['ok' => true]);
exit;
