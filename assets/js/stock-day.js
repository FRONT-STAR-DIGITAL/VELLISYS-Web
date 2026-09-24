(function () {
  function money(boot, v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : String(v);
  }
  function setsFrom(boot, block) {
    var color = boot.color || '#82B440';
    return [
      {
        label: 'Income',
        data: block.income || [],
        borderColor: color,
        backgroundColor: color + '33',
        tension: 0.35,
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      },
      {
        label: 'Expenditure',
        data: block.expense || [],
        borderColor: '#3b82f6',
        backgroundColor: 'transparent',
        tension: 0.35,
        fill: false,
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
        plugins: { legend: { position: 'bottom' } },
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
      if (window.Chart) {
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
