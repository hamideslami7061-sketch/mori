<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

$db        = getDB();
$id        = (int)($_GET['id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);

// CSRF برای GET state-changing action
startUserSession();
$token = $_GET['csrf'] ?? '';
if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die('دسترسی غیرمجاز: توکن امنیتی نامعتبر.');
}

if ($id && $course_id) {
    $db->prepare("DELETE FROM course_contents WHERE id = ? AND course_id = ?")->execute([$id, $course_id]);
    // حذف محتوا هم زمان محتوای دوره را به‌روز می‌کند
    $db->prepare("UPDATE courses SET content_updated_at = NOW() WHERE id = ?")->execute([$course_id]);
    auditLog('content_delete', 'content', $id, ['course_id' => $course_id]);
}

header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=content_deleted");
exit;
