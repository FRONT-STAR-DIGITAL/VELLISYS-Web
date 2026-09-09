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

  document.querySelectorAll('.lp-stack').forEach(function (stack) {
    stack.addEventListener('mouseenter', function () { stack.classList.add('is-open'); });
    stack.addEventListener('mouseleave', function () { stack.classList.remove('is-open'); });
    stack.addEventListener('blur', function () { stack.classList.remove('is-open'); }, true);
  });

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
