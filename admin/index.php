<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../config.php';

$db = getDB();

$stats = [
    'courses'    => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'professors' => $db->query("SELECT COUNT(*) FROM professors")->fetchColumn(),
    'users'      => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'contents'   => $db->query("SELECT COUNT(*) FROM course_contents")->fetchColumn(),
];

// آخرین دوره‌ها بر اساس content_updated_at (دوره‌هایی که اخیراً محتوا گرفته‌اند)
$recent_courses = $db->query("
    SELECT c.title, c.sessions, c.is_active, p.name AS prof_name, c.created_at, c.content_updated_at
    FROM courses c JOIN professors p ON c.professor_id = p.id
    ORDER BY c.content_updated_at DESC, c.id DESC LIMIT 5
")->fetchAll();

$page_title = 'داشبورد';
$active_nav = 'dashboard';
$topbar_actions = '<a href="/physics_academy/admin/courses.php?action=add" class="topbar-btn primary"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>دوره جدید</a>';
include __DIR__ . '/partials/layout_start.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-top">
            <div><div class="stat-val"><?= $stats['courses'] ?></div><div class="stat-label">دوره فعال</div></div>
            <div class="stat-icon gold"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-top">
            <div><div class="stat-val"><?= $stats['professors'] ?></div><div class="stat-label">استاد</div></div>
            <div class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-top">
            <div><div class="stat-val"><?= $stats['users'] ?></div><div class="stat-label">کاربر ثبت‌نام‌شده</div></div>
            <div class="stat-icon green"><svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-top">
            <div><div class="stat-val"><?= $stats['contents'] ?></div><div class="stat-label">فایل محتوا</div></div>
            <div class="stat-icon purple"><svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-card-header">
        <h2>آخرین دوره‌ها</h2>
        <a href="/physics_academy/admin/courses.php" class="topbar-btn ghost" style="font-size:.82rem;padding:6px 12px;">مشاهده همه</a>
    </div>
    <?php if (empty($recent_courses)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>دوره‌ای وجود ندارد. <a href="/physics_academy/admin/courses.php?action=add" style="color:var(--gold)">اولین دوره را اضافه کنید</a></p>
    </div>
    <?php else: ?>
    <table>
        <thead><tr><th>عنوان</th><th>استاد</th><th>جلسات</th><th>وضعیت</th></tr></thead>
        <tbody>
        <?php foreach ($recent_courses as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td><?= htmlspecialchars($c['prof_name']) ?></td>
            <td><?= $c['sessions'] ?></td>
            <td><span class="badge-active <?= $c['is_active'] ? 'on' : 'off' ?>"><?= $c['is_active'] ? 'فعال' : 'غیرفعال' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/layout_end.php'; ?>
