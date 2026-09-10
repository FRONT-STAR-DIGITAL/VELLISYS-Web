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

  document.querySelectorAll('[data-lp-stack], .lp-stack').forEach(function (stack) {
    function sync() {
      stack.setAttribute('aria-expanded', stack.classList.contains('is-open') ? 'true' : 'false');
    }
    stack.addEventListener('click', function () {
      stack.classList.toggle('is-open');
      sync();
    });
    stack.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        stack.classList.toggle('is-open');
        sync();
      }
    });
    var fine = window.matchMedia('(hover: hover) and (pointer: fine)');
    if (fine.matches) {
      stack.addEventListener('mouseenter', function () {
        stack.classList.add('is-open');
        sync();
      });
      stack.addEventListener('mouseleave', function () {
        stack.classList.remove('is-open');
        sync();
      });
    }
    sync();
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
  var ticking = false;

  function onScrollFrame() {
    ticking = false;
    var y = window.scrollY || 0;
    if (topBtn) {
      topBtn.hidden = y < 420;
    }
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

  var CCY_COOKIE = 'vellisys_ccy';

  function cookieCcy() {
    var match = document.cookie.match(/(?:^|; )vellisys_ccy=([A-Z]{3})/);
    return match ? match[1] : '';
  }

  function setCcyCookie(code) {
    document.cookie = CCY_COOKIE + '=' + encodeURIComponent(code) + ';path=/;max-age=34560000;SameSite=Lax';
  }

  function parseJson(value, fallback) {
    try {
      return JSON.parse(value || '');
    } catch (e) {
      return fallback;
    }
  }

  function formatFromUgx(ugx, ccy, rates, currencies) {
    var rate = Number((rates && rates[ccy]) || 1);
    if (!rate) rate = 1;
    var decimals = currencies && currencies[ccy] && typeof currencies[ccy].decimals === 'number'
      ? currencies[ccy].decimals
      : 0;
    var amount = ugx / rate;
    amount = decimals === 0 ? Math.round(amount) : Math.round(amount * 100) / 100;
    var parts = amount.toFixed(decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return ccy + ' ' + parts.join('.');
  }

  function applyCurrency(code) {
    document.querySelectorAll('[data-pricing]').forEach(function (root) {
      var rates = parseJson(root.getAttribute('data-rates'), {});
      var currencies = parseJson(root.getAttribute('data-currencies'), {});
      root.setAttribute('data-ccy', code);
      root.querySelectorAll('[data-ugx]').forEach(function (el) {
        var ugx = Number(el.getAttribute('data-ugx') || 0);
        var text = formatFromUgx(ugx, code, rates, currencies);
        if (el.getAttribute('data-pay-btn') !== null || el.hasAttribute('data-pay-btn')) {
          el.textContent = 'Pay ' + text;
        } else {
          el.textContent = text;
        }
      });
      root.querySelectorAll('[data-pricing-ccy-label]').forEach(function (el) {
        el.textContent = code;
      });
    });
    document.querySelectorAll('[data-lp-ccy]').forEach(function (sel) {
      if (sel.value !== code) sel.value = code;
    });
  }

  document.querySelectorAll('[data-lp-ccy]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var code = (sel.value || 'UGX').toUpperCase();
      setCcyCookie(code);
      applyCurrency(code);
    });
  });

  var fromCookie = cookieCcy();
  if (fromCookie) {
    applyCurrency(fromCookie);
  }

  function kampalaDeadline() {
    var parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Africa/Kampala',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date());
    var get = function (type) {
      var found = parts.find(function (p) { return p.type === type; });
      return found ? found.value : '01';
    };
    var start = Date.parse(get('year') + '-' + get('month') + '-' + get('day') + 'T00:00:00+03:00');
    var root = document.querySelector('[data-pricing]');
    var days = Number(root && root.getAttribute('data-discount-days') ? root.getAttribute('data-discount-days') : 3);
    var hours = Number(root && root.getAttribute('data-discount-hours') ? root.getAttribute('data-discount-hours') : 12);
    if (!isFinite(days) || days < 0) days = 3;
    if (!isFinite(hours) || hours < 0) hours = 12;
    return start + (days * 24 * 60 * 60 * 1000) + (hours * 60 * 60 * 1000);
  }

  function tickDiscount() {
    var clocks = document.querySelectorAll('[data-discount-clock]');
    if (!clocks.length) return;
    var remain = Math.max(0, kampalaDeadline() - Date.now());
    var total = Math.floor(remain / 1000);
    var d = Math.floor(total / 86400);
    var h = Math.floor((total % 86400) / 3600);
    var m = Math.floor((total % 3600) / 60);
    var s = total % 60;
    var pad = function (n) { return String(n).padStart(2, '0'); };
    clocks.forEach(function (clock) {
      var db = clock.querySelector('[data-discount-d]');
      var hb = clock.querySelector('[data-discount-h]');
      var mb = clock.querySelector('[data-discount-m]');
      var sb = clock.querySelector('[data-discount-s]');
      if (db) db.textContent = pad(d);
      if (hb) hb.textContent = pad(h);
      if (mb) mb.textContent = pad(m);
      if (sb) sb.textContent = pad(s);
    });
  }
  tickDiscount();
  setInterval(tickDiscount, 1000);

  function paceMarquees() {
    document.querySelectorAll('[data-marquee-track]').forEach(function (track) {
      var set = track.querySelector('[data-marquee-set]');
      if (!set) return;
      var width = set.offsetWidth;
      if (!width) return;
      var seconds = Math.max(18, Math.round(width / 42));
      track.style.animationDuration = seconds + 's';
    });
  }
  paceMarquees();
  window.addEventListener('load', paceMarquees);
  window.addEventListener('resize', paceMarquees);

  var payFrame = document.querySelector('[data-pay-frame]');
  if (payFrame) {
    var hold = document.querySelector('[data-pay-hold]');
    var fallback = document.querySelector('[data-pay-fallback]');
    var ready = false;
    function markReady() {
      ready = true;
      payFrame.classList.add('is-ready');
      if (hold) hold.hidden = true;
    }
    payFrame.addEventListener('load', markReady);
    window.setTimeout(function () {
      if (!ready && fallback) fallback.hidden = false;
      if (hold && !ready) hold.hidden = true;
    }, 9000);
  }

  var checkout = document.querySelector('[data-checkout-form]');
  if (checkout) {
    var timer = 0;
    var publicInput = checkout.querySelector('[data-order-public]');
    function saveDraft() {
      var name = (checkout.querySelector('[name="contact_name"]') || {}).value || '';
      var company = (checkout.querySelector('[name="company_name"]') || {}).value || '';
      var email = (checkout.querySelector('[name="contact_email"]') || {}).value || '';
      if (!name.trim() && !company.trim() && !email.trim()) return;
      var data = new FormData(checkout);
      data.set('action', 'draft');
      fetch(checkout.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (res) { return res.json(); }).then(function (json) {
        if (json && json.public_id && publicInput) publicInput.value = json.public_id;
      }).catch(function () {});
    }
    checkout.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(saveDraft, 1200);
    });
    window.addEventListener('pagehide', saveDraft);
  }
})();
