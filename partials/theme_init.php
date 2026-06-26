<script>
// اعمال فوری تم ذخیره‌شده — قبل از رندر محتوا تا flash نداشته باشیم
(function(){
  try {
    if (localStorage.getItem('pa_theme') === 'light') {
      document.body.classList.add('light-mode');
    }
  } catch(e) {}
})();
</script>
