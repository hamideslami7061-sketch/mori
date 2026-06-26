<?php
// ajax_toggle_save.php — ذخیره / حذف دوره به‌صورت AJAX (بدون رفرش صفحه)
// درخواست: POST JSON { course_id: int, action: 'save'|'unsave' }
// پاسخ: { ok: bool, saved: bool, error?: string }
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

// CSRF برای AJAX
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'csrf_invalid']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$course_id = (int)($input['course_id'] ?? 0);
$action    = $input['action'] ?? '';

if (!$course_id || !in_array($action, ['save', 'unsave'], true)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_params']);
    exit;
}

$db = getDB();

// اطمینان از وجود دوره و فعال بودن
$chk = $db->prepare("SELECT id, is_active FROM courses WHERE id = ? LIMIT 1");
$chk->execute([$course_id]);
$c = $chk->fetch();
if (!$c || !$c['is_active']) {
    echo json_encode(['ok' => false, 'error' => 'course_not_found']);
    exit;
}

if ($action === 'save') {
    $db->prepare("INSERT IGNORE INTO user_saved_courses (user_id, course_id) VALUES (?, ?)")
       ->execute([$user['id'], $course_id]);
    echo json_encode(['ok' => true, 'saved' => true]);
    exit;
} else { // unsave
    $db->prepare("DELETE FROM user_saved_courses WHERE user_id = ? AND course_id = ?")
       ->execute([$user['id'], $course_id]);
    echo json_encode(['ok' => true, 'saved' => false]);
    exit;
}
