(function () {
  function availableWidth(stage) {
    var wrap = stage.closest('.sheet-wrap') || stage.parentElement;
    var content = stage.closest('.content');
    var w = stage.clientWidth;
    if (wrap && wrap.clientWidth > 8) {
      w = wrap.clientWidth;
      var cs = window.getComputedStyle(wrap);
      w -= parseFloat(cs.paddingLeft || '0') + parseFloat(cs.paddingRight || '0');
    }
    if (content && content.clientWidth > 8 && content.clientWidth < w + 32) {
      w = Math.min(w, content.clientWidth);
    }
    var view = (window.visualViewport && window.visualViewport.width)
      || document.documentElement.clientWidth
      || window.innerWidth;
    if (view > 8) {
      w = Math.min(w, view);
    }
    return Math.max(0, w);
  }

  function fitSheets() {
    if (window.matchMedia && window.matchMedia('print').matches) {
      return;
    }
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      var sheet = stage.querySelector('.invoice-sheet');
      if (!sheet) return;
      sheet.style.zoom = '';
      sheet.style.transform = 'none';
      sheet.style.marginLeft = '0';
      stage.style.height = '';
      stage.style.overflow = 'hidden';
      var avail = availableWidth(stage);
      var w = Math.max(sheet.offsetWidth, sheet.scrollWidth);
      var h = Math.max(sheet.offsetHeight, sheet.scrollHeight);
      if (!w || avail <= 0) return;
      var scale = Math.min(1, avail / w);
      sheet.style.transformOrigin = 'top left';
      if (scale >= 0.999) {
        return;
      }
      sheet.style.transform = 'scale(' + scale + ')';
      var leftover = avail - w * scale;
      if (leftover > 0.5) {
        sheet.style.marginLeft = leftover / 2 + 'px';
      }
      stage.style.height = Math.ceil(h * scale) + 'px';
    });
  }

  window.fitDocumentSheets = fitSheets;
  window.addEventListener('resize', fitSheets);
  window.addEventListener('orientationchange', fitSheets);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitSheets);
  }
  document.querySelectorAll('.invoice-sheet img').forEach(function (img) {
    if (!img.complete) {
      img.addEventListener('load', fitSheets);
    }
  });
  if (document.readyState === 'complete') {
    fitSheets();
  } else {
    document.addEventListener('DOMContentLoaded', fitSheets);
    window.addEventListener('load', fitSheets);
  }
  setTimeout(fitSheets, 50);
  setTimeout(fitSheets, 250);
  setTimeout(fitSheets, 800);
})();
