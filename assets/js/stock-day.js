(function () {
  var boot = window.vellisysDayCharts || {};
  function money(v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : v;
  }
  function setsFrom(block) {
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
  function line(id, labels, sets) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: sets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { ticks: { callback: money } } }
      }
    });
  }
  if (boot.days) line('chart-stock-days', boot.days.labels, setsFrom(boot.days));
  if (boot.months) line('chart-stock-months', boot.months.labels, setsFrom(boot.months));
  document.querySelectorAll('[data-live-filters]').forEach(function (form) {
    form.querySelectorAll('[data-live-date]').forEach(function (input) {
      input.addEventListener('change', function () {
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    });
  });
})();
