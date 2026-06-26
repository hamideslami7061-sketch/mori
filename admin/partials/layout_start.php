<?php
// admin/partials/layout.php
// فراخوانی: include با $page_title و $active_nav تعریف شده
$page_title = $page_title ?? 'پنل ادمین';
$active_nav = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page_title) ?> | پنل ادمین</title>
<link rel="stylesheet" href="/physics_academy/admin/assets/admin.css">
<?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <h2><?= htmlspecialchars(SITE_NAME) ?></h2>
        <p>پنل مدیریت</p>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section">داشبورد</span>
        <a href="/physics_academy/admin/" class="<?= $active_nav==='dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            داشبورد
        </a>

        <span class="nav-section">مدیریت محتوا</span>
        <a href="/physics_academy/admin/professors.php" class="<?= $active_nav==='professors' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            اساتید
        </a>
        <a href="/physics_academy/admin/courses.php" class="<?= $active_nav==='courses' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            دوره‌ها
        </a>
        <a href="/physics_academy/admin/users.php" class="<?= $active_nav==='users' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
            کاربران
        </a>

        <span class="nav-section">سیستم</span>
        <a href="/physics_academy/" target="_blank">
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            مشاهده سایت
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/physics_academy/admin/logout.php">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            خروج
        </a>
    </div>
</aside>

<main class="admin-main">
    <div class="admin-topbar">
        <h1><?= htmlspecialchars($page_title) ?></h1>
        <div class="topbar-actions">
            <?php if (!empty($topbar_actions)) echo $topbar_actions; ?>
        </div>
    </div>
    <div class="admin-content">
