<?php
/**
 * partials/pagination.php
 * متغیرهای مورد نیاز:
 *   $total_items  — تعداد کل آیتم‌ها
 *   $per_page     — آیتم در هر صفحه
 *   $current_page — صفحه فعلی
 *   $base_url     — آدرس پایه (مثل /physics_academy/courses.php)
 */
if ($total_items <= $per_page) return; // نیازی به pagination نیست

$total_pages = (int)ceil($total_items / $per_page);
if ($total_pages < 2) return;

$make_url = fn($p) => $base_url . '?page=' . $p;

// بازه صفحات نمایشی (حداکثر ۵ دکمه)
$range = 2;
$start = max(1, $current_page - $range);
$end   = min($total_pages, $current_page + $range);
?>
<div class="pagination-wrap">
    <!-- دکمه قبلی -->
    <?php if ($current_page > 1): ?>
    <a href="<?= $make_url($current_page - 1) ?>" class="pg-btn">
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php else: ?>
    <span class="pg-btn disabled"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
    <?php endif; ?>

    <!-- صفحه اول اگر در بازه نیست -->
    <?php if ($start > 1): ?>
    <a href="<?= $make_url(1) ?>" class="pg-btn">۱</a>
    <?php if ($start > 2): ?><span class="pg-btn disabled" style="min-width:24px;">…</span><?php endif; ?>
    <?php endif; ?>

    <!-- صفحات بازه -->
    <?php for ($p = $start; $p <= $end; $p++): ?>
    <a href="<?= $make_url($p) ?>" class="pg-btn <?= $p === $current_page ? 'active' : '' ?>">
        <?= en_to_fa((string)$p) ?>
    </a>
    <?php endfor; ?>

    <!-- صفحه آخر اگر در بازه نیست -->
    <?php if ($end < $total_pages): ?>
    <?php if ($end < $total_pages - 1): ?><span class="pg-btn disabled" style="min-width:24px;">…</span><?php endif; ?>
    <a href="<?= $make_url($total_pages) ?>" class="pg-btn"><?= en_to_fa((string)$total_pages) ?></a>
    <?php endif; ?>

    <!-- دکمه بعدی -->
    <?php if ($current_page < $total_pages): ?>
    <a href="<?= $make_url($current_page + 1) ?>" class="pg-btn">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <?php else: ?>
    <span class="pg-btn disabled"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></span>
    <?php endif; ?>
</div>
