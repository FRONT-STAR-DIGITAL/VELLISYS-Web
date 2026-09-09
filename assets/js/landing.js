(function () {
  var stage = document.querySelector('[data-lp-stage]');
  if (stage) {
    var svg = stage.querySelector('.lp-arrows');
    var paths = stage.querySelectorAll('.lp-flow');
    var riders = [];
    paths.forEach(function (path, i) {
      var dot = document.createElement('span');
      dot.className = 'lp-arrow-dot';
      stage.appendChild(dot);
      var tip = document.createElement('span');
      tip.className = 'lp-fly-tip';
      tip.textContent = '>';
      stage.appendChild(tip);
      riders.push({
        path: path,
        dot: dot,
        tip: tip,
        speed: 5200 + i * 1100,
        offset: i * 0.34
      });
    });

    function place(el, x, y, angle) {
      el.style.transform = 'translate(' + x + 'px, ' + y + 'px)' +
        (typeof angle === 'number' ? ' rotate(' + angle + 'deg)' : '');
    }

    function along(path, t) {
      var len = path.getTotalLength();
      var d = ((t % 1) + 1) % 1 * len;
      var p = path.getPointAtLength(d);
      var p2 = path.getPointAtLength(Math.min(len, d + 8));
      var svgEl = path.ownerSVGElement;
      var ctm = path.getScreenCTM();
      if (!svgEl || !ctm) return null;
      var pt = svgEl.createSVGPoint();
      pt.x = p.x;
      pt.y = p.y;
      var a = pt.matrixTransform(ctm);
      pt.x = p2.x;
      pt.y = p2.y;
      var b = pt.matrixTransform(ctm);
      var box = stage.getBoundingClientRect();
      return {
        x: a.x - box.left,
        y: a.y - box.top,
        angle: Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI
      };
    }

    function tick(now) {
      riders.forEach(function (r) {
        var pos = along(r.path, now / r.speed + r.offset);
        if (!pos) return;
        place(r.dot, pos.x, pos.y);
        place(r.tip, pos.x, pos.y, pos.angle);
      });
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

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
