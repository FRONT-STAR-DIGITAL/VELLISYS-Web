(function () {
  function markPages() {
    var sheet = document.querySelector('.invoice-sheet');
    if (!sheet || sheet.classList.contains('sheet-thermal')) return;
    sheet.style.minHeight = '0';
    var pagePx = (297 * 96) / 25.4;
    if (sheet.offsetHeight > pagePx + 8) {
      sheet.classList.add('is-multipage');
      document.documentElement.classList.add('is-multipage');
    } else {
      sheet.classList.remove('is-multipage');
      document.documentElement.classList.remove('is-multipage');
    }
  }
  function go() {
    if (typeof window.fitDocumentSheets === 'function') {
      window.fitDocumentSheets();
    }
    markPages();
    window.setTimeout(function () {
      if (typeof window.fitDocumentSheets === 'function') {
        window.fitDocumentSheets();
      }
      markPages();
      window.print();
    }, 280);
  }
  function waitImages(fn) {
    var imgs = Array.prototype.slice.call(document.images || []);
    var left = imgs.filter(function (img) { return !img.complete; }).length;
    if (!left) { fn(); return; }
    function done() { left -= 1; if (left <= 0) fn(); }
    imgs.forEach(function (img) {
      if (img.complete) return;
      img.addEventListener('load', done);
      img.addEventListener('error', done);
    });
  }
  function start() { waitImages(go); }
  if (document.readyState === 'complete') {
    start();
  } else {
    window.addEventListener('load', start);
  }
})();
