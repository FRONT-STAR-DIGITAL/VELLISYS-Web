(function () {
  function money(boot, v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : v;
  }
  function setsFrom(boot, block) {
    var color = boot.color || '#82B440';
    var out = [
      { label: 'Income', data: block.income || [], borderColor: color, tension: 0.25, fill: false },
      { label: 'Spend', data: block.expense || [], borderColor: '#b42318', tension: 0.25, fill: false }
    ];
    if (boot.showProfit) {
      out.push({ label: 'Profit', data: block.profit || [], borderColor: '#1f3a12', tension: 0.25, fill: false });
      out.push({ label: 'Net', data: block.net || [], borderColor: '#1E4EFF', tension: 0.25, fill: false });
    }
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
