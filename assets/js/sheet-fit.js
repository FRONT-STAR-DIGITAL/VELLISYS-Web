(function () {
  function fitSheets() {
    if (window.matchMedia && window.matchMedia('print').matches) {
      return;
    }
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      var sheet = stage.querySelector('.invoice-sheet');
      if (!sheet) return;
      sheet.style.transform = '';
      sheet.style.marginLeft = '';
      stage.style.height = '';
      stage.style.overflow = 'hidden';
      var avail = stage.clientWidth;
      var w = sheet.offsetWidth;
      var h = sheet.offsetHeight;
      if (!w || avail <= 0) return;
      var scale = Math.min(1, avail / w);
      sheet.style.transformOrigin = 'top left';
      if (scale < 0.999) {
        sheet.style.transform = 'scale(' + scale + ')';
        var leftover = avail - w * scale;
        if (leftover > 0.5) {
          sheet.style.marginLeft = (leftover / 2) + 'px';
        }
        stage.style.height = Math.ceil(h * scale) + 'px';
      }
    });
  }
  window.fitDocumentSheets = fitSheets;
  window.addEventListener('resize', fitSheets);
  window.addEventListener('orientationchange', fitSheets);
  if (typeof ResizeObserver !== 'undefined') {
    var ro = new ResizeObserver(fitSheets);
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      ro.observe(stage);
    });
  }
  if (document.readyState === 'complete') {
    fitSheets();
  } else {
    window.addEventListener('load', fitSheets);
  }
  setTimeout(fitSheets, 60);
  setTimeout(fitSheets, 400);
})();
