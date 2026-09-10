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
  if (topBtn) {
    var onScroll = function () {
      topBtn.hidden = window.scrollY < 420;
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    topBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  var wa = document.querySelector('[data-lp-wa]');
  var dock = document.querySelector('[data-lp-wa-dock]');
  if (wa) {
    var panel = wa.querySelector('#lp-wa-panel');
    var toggle = wa.querySelector('[data-lp-wa-toggle]');
    var home = wa.querySelector('[data-lp-wa-home]');
    var compose = wa.querySelector('[data-lp-wa-compose]');
    var label = wa.querySelector('[data-lp-agent-label]');
    var text = wa.querySelector('[data-lp-wa-text]');
    var frame = dock ? dock.querySelector('[data-lp-wa-frame]') : null;
    var title = dock ? dock.querySelector('[data-lp-wa-dock-title]') : null;
    var agentName = '';
    var agentPhone = '';
    var chatUrl = '';

    function waUrl(phone, message) {
      return 'https://web.whatsapp.com/send?phone=' + encodeURIComponent(phone) + '&text=' + encodeURIComponent(message);
    }

    function setPanel(open) {
      if (!panel || !toggle) return;
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      wa.classList.toggle('is-open', open);
    }

    function showHome() {
      if (home) home.hidden = false;
      if (compose) compose.hidden = true;
    }

    function closeDock() {
      if (!dock) return;
      dock.hidden = true;
      document.body.classList.remove('lp-wa-locked');
      if (frame) frame.src = 'about:blank';
      chatUrl = '';
    }

    function openDock() {
      if (!dock || !agentPhone) return;
      chatUrl = waUrl(agentPhone, (text && text.value) ? text.value.trim() : '');
      if (title) title.textContent = agentName + ' on WhatsApp';
      if (frame) frame.src = chatUrl;
      dock.hidden = false;
      document.body.classList.add('lp-wa-locked');
      setPanel(false);
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
        agentName = btn.getAttribute('data-name') || 'Agent';
        agentPhone = btn.getAttribute('data-phone') || '';
        if (label) label.textContent = agentName;
        if (home) home.hidden = true;
        if (compose) compose.hidden = false;
        if (text) text.focus();
      });
    });
    var back = wa.querySelector('[data-lp-wa-back]');
    if (back) back.addEventListener('click', showHome);
    if (compose) {
      compose.addEventListener('submit', function (e) {
        e.preventDefault();
        openDock();
      });
    }
    if (dock) {
      var dockClose = dock.querySelector('[data-lp-wa-dock-close]');
      if (dockClose) dockClose.addEventListener('click', closeDock);
      var winBtn = dock.querySelector('[data-lp-wa-window]');
      if (winBtn) {
        winBtn.addEventListener('click', function () {
          if (!chatUrl) chatUrl = waUrl(agentPhone, (text && text.value) ? text.value.trim() : '');
          window.open(chatUrl, 'vellisys-wa', 'width=440,height=720,noopener');
        });
      }
    }
  }
})();
