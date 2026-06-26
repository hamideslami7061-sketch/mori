<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /physics_academy/admin/courses.php');
    exit;
}
csrf_verify();

$db        = getDB();
$course_id = (int)($_POST['course_id'] ?? 0);
$title     = trim($_POST['ref_title'] ?? '');
$sort_order= (int)($_POST['ref_sort_order'] ?? 0);
$file_url  = '';

if (!$course_id || !$title) {
    header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_error");
    exit;
}

// آپلود فایل یا لینک مستقیم
if (!empty($_FILES['ref_file']['name'])) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/physics_academy/uploads/references/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $orig_name = basename($_FILES['ref_file']['name']);
    $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
    $dest      = $upload_dir . $safe_name;

    $allowed = ['pdf','doc','docx','epub','djvu','zip','rar','7z','ppt','pptx','xls','xlsx'];
    if (!in_array($ext, $allowed)) {
        header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_badtype");
        exit;
    }
    if ($_FILES['ref_file']['size'] > 100 * 1024 * 1024) { // 100 MB
        header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_toolarge");
        exit;
    }
    if (move_uploaded_file($_FILES['ref_file']['tmp_name'], $dest)) {
        $file_url = '/physics_academy/uploads/references/' . $safe_name;
    } else {
        header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_upload_failed");
        exit;
    }
} elseif (!empty($_POST['ref_url'])) {
    $file_url = trim($_POST['ref_url']);
} else {
    header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_error");
    exit;
}

$db->prepare("INSERT INTO course_references (course_id, title, file_url, sort_order) VALUES (?,?,?,?)")
   ->execute([$course_id, $title, $file_url, $sort_order]);

// افزودن مرجع هم زمان محتوای دوره را به‌روز می‌کند
$db->prepare("UPDATE courses SET content_updated_at = NOW() WHERE id = ?")->execute([$course_id]);

auditLog('reference_add', 'reference', (int)$db->lastInsertId(), ['course_id' => $course_id, 'title' => $title]);

header("Location: /physics_academy/admin/courses.php?action=edit&id={$course_id}&msg=ref_added");
exit;
