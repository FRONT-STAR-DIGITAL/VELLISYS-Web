(function () {
  function fitSheets() {
    if (window.matchMedia && window.matchMedia('print').matches) {
      return;
    }
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      var sheet = stage.querySelector('.invoice-sheet');
      if (!sheet) return;
      sheet.style.transform = '';
      stage.style.height = '';
      stage.style.overflow = 'hidden';
      var avail = stage.clientWidth;
      var w = sheet.offsetWidth;
      if (!w || avail <= 0) return;
      var scale = Math.min(1, avail / w);
      if (scale >= 0.995) return;
      sheet.style.transformOrigin = 'top left';
      sheet.style.transform = 'scale(' + scale + ')';
      stage.style.height = Math.ceil(sheet.offsetHeight * scale) + 'px';
    });
  }
  window.fitDocumentSheets = fitSheets;
  window.addEventListener('resize', fitSheets);
  window.addEventListener('orientationchange', fitSheets);
  if (document.readyState === 'complete') {
    fitSheets();
  } else {
    window.addEventListener('load', fitSheets);
  }
  setTimeout(fitSheets, 60);
  setTimeout(fitSheets, 400);
})();
