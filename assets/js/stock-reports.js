(function () {
  function money(boot, v) {
    return window.vellisysChartMoney ? window.vellisysChartMoney(boot.currency)(v) : String(v);
  }
  function qtyFmt(v) {
    var n = Number(v);
    if (!isFinite(n)) return '0';
    if (Math.abs(n - Math.round(n)) < 0.001) return String(Math.round(n));
    return (Math.round(n * 100) / 100).toLocaleString('en-US');
  }
  var charts = {};
  function destroy(id) {
    if (charts[id]) {
      try { charts[id].destroy(); } catch (e) {}
      charts[id] = null;
    }
  }
  function sizeCanvas(el) {
    var frame = el && el.closest('.chart-frame');
    if (frame) {
      el.style.width = '100%';
      el.style.height = '100%';
    }
  }
  function brand(boot) {
    return boot.color || '#1E4EFF';
  }
  function line(boot, id, labels, datasets, moneyAxis) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    labels = labels && labels.length ? labels : ['-'];
    datasets = (datasets || []).map(function (s) {
      var data = s.data && s.data.length ? s.data : [0];
      if (labels.length === 1 && labels[0] === '-' && !s.data.length) data = [0];
      return Object.assign({}, s, { data: data });
    });
    destroy(id);
    sizeCanvas(el);
    charts[id] = new Chart(el, {
      type: 'line',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
          tooltip: typeof window.vellisysChartTooltip === 'function'
            ? window.vellisysChartTooltip(function (v) {
                return moneyAxis ? money(boot, v) : qtyFmt(v);
              })
            : undefined
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (v) { return moneyAxis ? money(boot, v) : qtyFmt(v); },
              font: { size: 10 }
            },
            grid: { color: 'rgba(8,20,58,.06)' }
          },
          x: {
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } }
          }
        }
      }
    });
  }
  function bar(boot, id, labels, values, opts) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    opts = opts || {};
    labels = labels && labels.length ? labels : ['-'];
    values = values && values.length ? values : [0];
    destroy(id);
    sizeCanvas(el);
    var horizontal = !!opts.horizontal;
    charts[id] = new Chart(el, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: opts.label || 'Value',
          data: values,
          backgroundColor: (brand(boot) || '#1E4EFF') + (opts.soft ? '99' : 'cc'),
          borderColor: brand(boot),
          borderWidth: 0,
          borderRadius: 6,
          maxBarThickness: horizontal ? 22 : 36
        }]
      },
      options: {
        indexAxis: horizontal ? 'y' : 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: typeof window.vellisysChartTooltip === 'function'
            ? window.vellisysChartTooltip(function (v) {
                return opts.money ? money(boot, v) : qtyFmt(v);
              })
            : undefined
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: horizontal ? { color: 'rgba(8,20,58,.06)' } : { display: false },
            ticks: {
              callback: function (v) {
                if (!horizontal) return v;
                return opts.money ? money(boot, v) : qtyFmt(v);
              },
              font: { size: 10 }
            }
          },
          y: {
            beginAtZero: true,
            grid: horizontal ? { display: false } : { color: 'rgba(8,20,58,.06)' },
            ticks: {
              callback: function (v) {
                if (horizontal) return v;
                return opts.money ? money(boot, v) : qtyFmt(v);
              },
              font: { size: 10 }
            }
          }
        }
      }
    });
  }
  function doughnut(boot, id, labels, values) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return;
    labels = labels && labels.length ? labels : ['None'];
    values = values && values.length ? values : [0];
    var sum = values.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
    if (sum <= 0) {
      labels = ['No sales'];
      values = [1];
    }
    destroy(id);
    sizeCanvas(el);
    var colors = [brand(boot), '#08143A', '#8EB0FF', '#94a3b8'];
    charts[id] = new Chart(el, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: colors.slice(0, labels.length),
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
          tooltip: typeof window.vellisysChartTooltip === 'function'
            ? window.vellisysChartTooltip(function (v) {
                return sum <= 0 ? '—' : money(boot, v);
              })
            : undefined
        }
      },
      plugins: typeof window.vellisysPiePercentPlugins === 'function'
        ? window.vellisysPiePercentPlugins()
        : []
    });
  }
  function bootCharts() {
    var boot = window.vellisysStockReports || {};
    var days = boot.days || {};
    var months = boot.months || {};
    var topRev = boot.top_revenue || {};
    var topQty = boot.top_qty || {};
    var weekday = boot.weekday || {};
    var mix = boot.mix || {};
    var color = brand(boot);

    line(boot, 'chart-stock-daily', days.labels || [], [
      {
        label: 'Sales',
        data: days.income || [],
        borderColor: color,
        backgroundColor: color + '33',
        tension: 0.35,
        fill: true,
        borderWidth: 2,
        pointRadius: 2
      },
      {
        label: 'Spend',
        data: days.expense || [],
        borderColor: '#64748b',
        backgroundColor: 'transparent',
        tension: 0.35,
        fill: false,
        borderWidth: 2,
        pointRadius: 2
      }
    ].concat(boot.show_profit ? [{
      label: 'Profit',
      data: days.profit || [],
      borderColor: '#08143A',
      backgroundColor: 'transparent',
      tension: 0.35,
      fill: false,
      borderWidth: 2,
      pointRadius: 2,
      borderDash: [4, 3]
    }] : []), true);

    line(boot, 'chart-stock-units', days.labels || [], [
      {
        label: 'Units',
        data: days.units || [],
        borderColor: color,
        backgroundColor: color + '22',
        tension: 0.3,
        fill: true,
        borderWidth: 2,
        pointRadius: 2
      },
      {
        label: 'Tickets',
        data: days.tickets || [],
        borderColor: '#08143A',
        backgroundColor: 'transparent',
        tension: 0.3,
        fill: false,
        borderWidth: 2,
        pointRadius: 2
      }
    ], false);

    bar(boot, 'chart-stock-top-rev', topRev.labels || [], topRev.values || [], {
      horizontal: true,
      money: true,
      label: 'Sales',
      soft: true
    });
    bar(boot, 'chart-stock-top-qty', topQty.labels || [], topQty.values || [], {
      horizontal: true,
      money: false,
      label: 'Qty',
      soft: true
    });
    bar(boot, 'chart-stock-weekday', weekday.labels || [], weekday.income || [], {
      horizontal: false,
      money: true,
      label: 'Sales'
    });
    doughnut(boot, 'chart-stock-mix', mix.labels || [], mix.values || []);
    line(boot, 'chart-stock-months-rep', months.labels || [], [
      {
        label: 'Sales',
        data: months.income || [],
        borderColor: color,
        backgroundColor: color + '33',
        tension: 0.35,
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      },
      {
        label: 'Spend',
        data: months.expense || [],
        borderColor: '#64748b',
        backgroundColor: 'transparent',
        tension: 0.35,
        fill: false,
        borderWidth: 2,
        pointRadius: 3
      }
    ], true);
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
  window.addEventListener('resize', function () {
    Object.keys(charts).forEach(function (id) {
      if (charts[id]) {
        try { charts[id].resize(); } catch (e) {}
      }
    });
  });
})();
