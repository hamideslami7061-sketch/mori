/* assets/shared.js — منطق مشترک navbar، sidebar، جستجوی navbar */
/* توجه: اعمال فوری تم در partials/theme_init.php انجام می‌شود (بلافاصله بعد از <body>) */

document.addEventListener('DOMContentLoaded', function () {
  // ===== Sidebar =====
  var hamburgerBtn = document.getElementById('hamburgerBtn');
  var sidebar = document.getElementById('sidebar');
  var sidebarOverlay = document.getElementById('sidebarOverlay');
  var closeSidebarBtn = document.getElementById('closeSidebar');

  function openSidebar() {
    sidebar.classList.add('active');
    sidebarOverlay.classList.add('active');
    hamburgerBtn.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
    hamburgerBtn.classList.remove('active');
    document.body.style.overflow = '';
  }
  if (hamburgerBtn && sidebar && sidebarOverlay && closeSidebarBtn) {
    hamburgerBtn.addEventListener('click', function () {
      sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
    });
    closeSidebarBtn.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar-menu a').forEach(function (link) {
      link.addEventListener('click', closeSidebar);
    });
  }

  // ===== Navbar search dropdown =====
  var navSearchBtn = document.getElementById('navSearchBtn');
  var navSearchDropdown = document.getElementById('navSearchDropdown');
  var navSearchInput = document.getElementById('navSearchInput');
  if (navSearchBtn && navSearchDropdown && navSearchInput) {
    navSearchBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      navSearchDropdown.classList.toggle('active');
      if (navSearchDropdown.classList.contains('active')) {
        setTimeout(function () { navSearchInput.focus(); }, 100);
      }
    });
    document.addEventListener('click', function (e) {
      if (!navSearchDropdown.contains(e.target) && e.target !== navSearchBtn) {
        navSearchDropdown.classList.remove('active');
      }
    });
  }

  // ===== Theme toggle =====
  var themeBtn = document.getElementById('themeToggleBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var isLight = document.body.classList.toggle('light-mode');
      try { localStorage.setItem('pa_theme', isLight ? 'light' : 'dark'); } catch (e) {}
    });
  }

  // ===== کلاینت‌ساید فیلتر کارت‌ها (در صورت وجود) =====
  var mainSearchInput = document.getElementById('mainSearchInput');
  var cardSelector = document.body.dataset.cardSelector; // 'course-card' یا 'professor-card'
  if (mainSearchInput && cardSelector) {
    var cards = document.querySelectorAll('.' + cardSelector);
    function filterCards(q) {
      q = q.trim().toLowerCase();
      cards.forEach(function (card) {
        var titleEl = card.querySelector('h3');
        var subEl = card.querySelector('.card-professor, .card-sub');
        var title = titleEl ? titleEl.textContent.toLowerCase() : '';
        var sub = subEl ? subEl.textContent.toLowerCase() : '';
        card.style.display = (!q || title.indexOf(q) !== -1 || sub.indexOf(q) !== -1) ? '' : 'none';
      });
    }
    mainSearchInput.addEventListener('input', function (e) {
      filterCards(e.target.value);
      if (navSearchInput) navSearchInput.value = e.target.value;
    });
    if (navSearchInput) {
      navSearchInput.addEventListener('input', function (e) {
        filterCards(e.target.value);
        mainSearchInput.value = e.target.value;
      });
    }
  }

  // ===== کلیک روی کارت‌های دوره/استاد برای رفتن به صفحه =====
  document.querySelectorAll('[data-course-url]').forEach(function (card) {
    card.addEventListener('click', function (e) {
      if (e.target.closest('.professor-link')) return;
      var url = card.getAttribute('data-course-url');
      if (url) window.location.href = url;
    });
    card.addEventListener('keydown', function (e) {
      if (e.target.closest('.professor-link')) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        var url = card.getAttribute('data-course-url');
        if (url) window.location.href = url;
      }
    });
  });
  document.querySelectorAll('[data-professor-url]').forEach(function (card) {
    card.addEventListener('click', function () {
      var url = card.getAttribute('data-professor-url');
      if (url) window.location.href = url;
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        var url = card.getAttribute('data-professor-url');
        if (url) window.location.href = url;
      }
    });
  });
});
