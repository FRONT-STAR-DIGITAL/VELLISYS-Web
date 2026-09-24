(function () {
  function money(boot, v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : v;
  }
  function setsFrom(boot, block) {
    var color = boot.color || '#82B440';
    var out = [
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
    return out;
  }
  function line(boot, id, labels, sets) {
    var el = document.getElementById(id);
    if (!el || !window.Chart || !labels) return;
    new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: sets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { ticks: { callback: function (v) { return money(boot, v); } } } }
      }
    });
  }
  function bootCharts() {
    var boot = window.vellisysDayCharts || {};
    if (boot.days) line(boot, 'chart-stock-days', boot.days.labels, setsFrom(boot, boot.days));
    if (boot.months) line(boot, 'chart-stock-months', boot.months.labels, setsFrom(boot, boot.months));
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCharts);
  } else {
    bootCharts();
  }
  document.querySelectorAll('[data-live-filters]').forEach(function (form) {
    form.querySelectorAll('[data-live-date]').forEach(function (input) {
      input.addEventListener('change', function () {
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    });
  });
})();
