<?php
// partials/navbar.php
require_once __DIR__ . '/../config.php';
startUserSession();
$current_user = getLoggedInUser();
$active_page  = $active_page ?? 'home';
$site_name    = SITE_NAME;
?>
<div class="bg-particles"></div>

<nav class="navbar">
    <div class="nav-right">
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="منو">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="nav-center">
        <a href="/physics_academy/index.php" class="home-btn" title="صفحه اصلی">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </a>
    </div>
    <div class="nav-left">
        <button class="search-icon-btn" id="navSearchBtn" aria-label="جستجو">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        </button>

        <?php if ($current_user): ?>
        <!-- کاربر لاگین است: آیکون پروفایل -->
        <a href="/physics_academy/profile.php" class="user-icon-btn" title="پروفایل من">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
        </a>
        <?php else: ?>
        <!-- کاربر لاگین نیست: دکمه ورود -->
        <a href="/physics_academy/auth.php" class="auth-btn">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
            <span class="auth-text">ورود</span>
        </a>
        <?php endif; ?>
    </div>
    <div class="nav-search-dropdown" id="navSearchDropdown">
        <input type="text" placeholder="جستجو..." id="navSearchInput">
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><?= htmlspecialchars($site_name) ?></h3>
        <button class="close-sidebar" id="closeSidebar" aria-label="بستن منو">&times;</button>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="/physics_academy/courses.php" <?= $active_page==='courses'?'class="active-link"':'' ?>>
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                دوره‌ها
            </a>
        </li>
        <li>
            <a href="/physics_academy/professors.php" <?= $active_page==='professors'?'class="active-link"':'' ?>>
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                اساتید
            </a>
        </li>
        <?php if ($current_user): ?>
        <li>
            <a href="/physics_academy/profile.php" <?= $active_page==='profile'?'class="active-link"':'' ?>>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0"/></svg>
                پروفایل من
            </a>
        </li>
        <li>
            <a href="/physics_academy/logout.php">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                خروج
            </a>
        </li>
        <?php else: ?>
        <li>
            <a href="/physics_academy/auth.php">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                ورود
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-footer"><?= htmlspecialchars($site_name) ?></div>
</aside>