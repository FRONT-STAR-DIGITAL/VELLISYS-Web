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

  var topBtn = document.querySelector('[data-lp-top]');
  var chrome = document.querySelector('[data-lp-chrome]');
  var lastY = window.scrollY || 0;
  var chromeAway = false;
  var ticking = false;

  function onScrollFrame() {
    ticking = false;
    var y = window.scrollY || 0;
    if (topBtn) {
      topBtn.hidden = y < 420;
    }
    if (chrome) {
      var goingDown = y > lastY + 6;
      var goingUp = y < lastY - 6;
      if (y < 64) {
        chromeAway = false;
      } else if (goingDown) {
        chromeAway = true;
      } else if (goingUp) {
        chromeAway = false;
      }
      chrome.classList.toggle('is-away', chromeAway);
    }
    lastY = y;
  }

  function requestScroll() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(onScrollFrame);
    }
  }

  onScrollFrame();
  window.addEventListener('scroll', requestScroll, { passive: true });

  if (topBtn) {
    topBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  var wa = document.querySelector('[data-lp-wa]');
  if (wa) {
    var panel = wa.querySelector('#lp-wa-panel');
    var toggle = wa.querySelector('[data-lp-wa-toggle]');
    var home = wa.querySelector('[data-lp-wa-home]');
    var compose = wa.querySelector('[data-lp-wa-compose]');
    var label = wa.querySelector('[data-lp-agent-label]');
    var text = wa.querySelector('[data-lp-wa-text]');
    var agentPhone = '';

    function setPanel(open) {
      if (!panel || !toggle) return;
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      wa.classList.toggle('is-open', open);
    }

    function showHome() {
      if (home) home.hidden = false;
      if (compose) compose.hidden = true;
      if (text) text.value = '';
    }

    function openWhatsApp(message) {
      var url = 'https://wa.me/' + encodeURIComponent(agentPhone) + '?text=' + encodeURIComponent(message);
      var link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener';
      document.body.appendChild(link);
      link.click();
      link.remove();
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        setPanel(panel.hidden);
        if (!panel.hidden) showHome();
      });
    }
    wa.querySelectorAll('[data-lp-wa-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { setPanel(false); showHome(); });
    });
    wa.querySelectorAll('[data-lp-agent]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        agentPhone = btn.getAttribute('data-phone') || '';
        if (label) label.textContent = btn.getAttribute('data-name') || 'Agent';
        if (home) home.hidden = true;
        if (compose) compose.hidden = false;
        if (text) {
          text.value = '';
          text.focus();
        }
      });
    });
    var back = wa.querySelector('[data-lp-wa-back]');
    if (back) back.addEventListener('click', showHome);
    if (compose) {
      compose.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = text && text.value ? text.value.trim() : '';
        if (!message || !agentPhone) {
          if (text) text.focus();
          return;
        }
        openWhatsApp(message);
        setPanel(false);
        showHome();
      });
    }
  }
})();
