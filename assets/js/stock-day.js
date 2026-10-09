(function () {
  function money(boot, v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : String(v);
  }
  function softFill(hex, alpha) {
    hex = String(hex || '#1E4EFF').replace('#', '');
    if (hex.length === 3) {
      hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    if (hex.length !== 6) return 'rgba(30,78,255,' + alpha + ')';
    var r = parseInt(hex.slice(0, 2), 16);
    var g = parseInt(hex.slice(2, 4), 16);
    var b = parseInt(hex.slice(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }
  function setsFrom(boot, block) {
    var color = boot.color || '#1E4EFF';
    if (String(color).charAt(0) !== '#') color = '#' + color;
    if (!/^#[0-9A-Fa-f]{6}$/.test(color)) color = '#1E4EFF';
    return [
      {
        label: 'Income',
        data: block.income || [],
        borderColor: color,
        backgroundColor: softFill(color, 0.22),
        tension: 0.35,
        fill: 'origin',
        borderWidth: 2,
        pointRadius: 3
      },
      {
        label: 'Expenditure',
        data: block.expense || [],
        borderColor: '#64748b',
        backgroundColor: softFill('#64748b', 0.12),
        tension: 0.35,
        fill: 'origin',
        borderWidth: 2,
        pointRadius: 3
      }
    ];
  }
  var charts = {};
  function line(boot, id, labels, sets) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    labels = labels || [];
    if (!labels.length) {
      labels = ['-'];
      sets = (sets || []).map(function (s) {
        return Object.assign({}, s, { data: [0] });
      });
    }
    if (charts[id]) {
      try { charts[id].destroy(); } catch (e) {}
    }
    var frame = el.closest('.chart-frame');
    if (frame) {
      el.style.width = '100%';
      el.style.height = '100%';
    }
    charts[id] = new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: sets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: typeof window.vellisysChartTooltip === 'function'
            ? window.vellisysChartTooltip(function (v) { return money(boot, v); })
            : undefined
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: function (v) { return money(boot, v); } },
            grid: { color: 'rgba(8,20,58,.06)' }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }
  function bootCharts() {
    var boot = window.vellisysDayCharts || {};
    if (boot.days) line(boot, 'chart-stock-days', boot.days.labels, setsFrom(boot, boot.days));
    if (boot.months) line(boot, 'chart-stock-months', boot.months.labels, setsFrom(boot, boot.months));
  }
  function whenReady(fn) {
    var tries = 0;
    function go() {
      if (window.Chart && typeof window.vellisysChartTooltip === 'function') {
        fn();
        return;
      }
      if (++tries > 80) return;
      setTimeout(go, 50);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', go);
    } else {
      go();
    }
  }
  whenReady(bootCharts);
  document.querySelectorAll('[data-live-filters]').forEach(function (form) {
    form.querySelectorAll('[data-live-date]').forEach(function (input) {
      input.addEventListener('change', function () {
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    });
  });
})();
