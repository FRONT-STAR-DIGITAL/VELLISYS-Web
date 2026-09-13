(function () {
  var useZoom = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('zoom', '0.5');

  function stageWidth(stage) {
    var wrap = stage.parentElement;
    var box = wrap || stage;
    var w = box.clientWidth;
    var view = (window.visualViewport && window.visualViewport.width)
      || document.documentElement.clientWidth
      || window.innerWidth;
    if (view > 8 && w > view) {
      w = view;
    }
    return w;
  }

  function fitSheets() {
    if (window.matchMedia && window.matchMedia('print').matches) {
      return;
    }
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      var sheet = stage.querySelector('.invoice-sheet');
      if (!sheet) return;
      sheet.style.zoom = '';
      sheet.style.transform = '';
      sheet.style.marginLeft = '';
      stage.style.height = '';
      stage.style.overflow = 'hidden';
      var avail = stageWidth(stage);
      var w = sheet.offsetWidth;
      var h = sheet.offsetHeight;
      if (!w || avail <= 0) return;
      var scale = Math.min(1, avail / w);
      if (scale >= 0.999) return;
      if (useZoom) {
        sheet.style.zoom = String(scale);
        sheet.style.marginLeft = '0';
        stage.style.height = '';
      } else {
        sheet.style.transformOrigin = 'top left';
        sheet.style.transform = 'scale(' + scale + ')';
        var leftover = avail - w * scale;
        sheet.style.marginLeft = leftover > 0.5 ? leftover / 2 + 'px' : '';
        stage.style.height = Math.ceil(h * scale) + 'px';
      }
    });
  }

  window.fitDocumentSheets = fitSheets;
  window.addEventListener('resize', fitSheets);
  window.addEventListener('orientationchange', fitSheets);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitSheets);
  }
  if (typeof ResizeObserver !== 'undefined') {
    var ro = new ResizeObserver(function () {
      fitSheets();
    });
    document.querySelectorAll('.sheet-wrap, .sheet-stage').forEach(function (el) {
      ro.observe(el);
    });
  }
  document.querySelectorAll('.invoice-sheet img').forEach(function (img) {
    if (!img.complete) {
      img.addEventListener('load', fitSheets);
    }
  });
  if (document.readyState === 'complete') {
    fitSheets();
  } else {
    window.addEventListener('load', fitSheets);
  }
  setTimeout(fitSheets, 60);
  setTimeout(fitSheets, 400);
})();
