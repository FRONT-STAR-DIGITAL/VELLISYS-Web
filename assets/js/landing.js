(function () {
  function playCharts() {
    document.querySelectorAll('[data-lp-bars], [data-lp-pies]').forEach(function (el) {
      el.classList.remove('is-play');
      void el.offsetWidth;
      el.classList.add('is-play');
    });
  }
  playCharts();
  window.addEventListener('pageshow', playCharts);

  var nodes = document.querySelectorAll('[data-reveal]');
  if (nodes.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('is-in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.14 });
    nodes.forEach(function (n) { io.observe(n); });
  } else {
    nodes.forEach(function (n) { n.classList.add('is-in'); });
  }
})();
