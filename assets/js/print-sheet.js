(function () {
  function markPages() {
    var sheet = document.querySelector('.invoice-sheet');
    if (!sheet) return;
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
    markPages();
    window.setTimeout(function () {
      markPages();
      window.print();
    }, 280);
  }
  if (document.readyState === 'complete') {
    go();
  } else {
    window.addEventListener('load', go);
  }
})();
