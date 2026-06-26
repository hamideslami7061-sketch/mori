<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /physics_academy/admin/courses.php');
    exit;
}
csrf_verify();

$db         = getDB();
$course_id  = (int)($_POST['course_id'] ?? 0);
$type       = in_array($_POST['type']??'',['video','pdf']) ? $_POST['type'] : 'video';
$title      = trim($_POST['title'] ?? '');
$url        = trim($_POST['url'] ?? '');
$duration   = trim($_POST['duration'] ?? '');
$file_size  = trim($_POST['file_size'] ?? '');
$sort_order = (int)($_POST['sort_order'] ?? 0);

if (!$course_id || !$title) {
    header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=error");
    exit;
}

$db->prepare("INSERT INTO course_contents (course_id,type,title,url,duration,file_size,sort_order)
              VALUES (?,?,?,?,?,?,?)")
   ->execute([$course_id,$type,$title,$url,$duration,$file_size,$sort_order]);

// ⭐ محتوای جدید → به‌روزرسانی زمان محتوای دوره
// این باعث می‌شود دوره در صفحه اصلی در بخش «دوره‌های اخیر» دوباره ظاهر شود
// تا کاربران متوجه افزودن محتوای جدید شوند.
$db->prepare("UPDATE courses SET content_updated_at = NOW() WHERE id = ?")->execute([$course_id]);

auditLog('content_add', 'content', (int)$db->lastInsertId(), ['course_id' => $course_id, 'title' => $title, 'type' => $type]);

header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=content_added");
exit;
