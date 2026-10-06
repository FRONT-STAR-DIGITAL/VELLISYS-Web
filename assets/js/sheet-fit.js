(function () {
  function viewportWidth() {
    var vv = window.visualViewport && window.visualViewport.width;
    var doc = document.documentElement && document.documentElement.clientWidth;
    var win = window.innerWidth;
    var w = vv || doc || win || 0;
    // Prefer the tightest reliable viewport so an overflowing A4 child cannot inflate width.
    if (vv && doc) w = Math.min(vv, doc);
    if (win && w) w = Math.min(w, win);
    return Math.max(0, w);
  }

  function availableWidth(stage, isThermal) {
    var view = viewportWidth();
    var wrap = stage.closest('.sheet-wrap') || stage.parentElement;
    var content = stage.closest('.content');
    var narrow = view > 0 && view <= 900;

    // Thermal roll already fills phones well — keep a gentle padded measure.
    if (isThermal) {
      var tw = stage.clientWidth || view;
      if (wrap && wrap.clientWidth > 8) {
        tw = wrap.clientWidth;
        var tcs = window.getComputedStyle(wrap);
        tw -= parseFloat(tcs.paddingLeft || '0') + parseFloat(tcs.paddingRight || '0');
      }
      if (view > 8) tw = Math.min(tw, view);
      return Math.max(0, tw);
    }

    // A4 on phones: fill the screen. Keep side empty space under a quarter of
    // the viewport (target ~8% total gutter) so type stays readable without pinch-zoom.
    if (narrow) {
      var gutter = Math.max(4, Math.round(view * 0.08));
      var avail = view - gutter;
      if (wrap && wrap.clientWidth > 8) {
        var ncs = window.getComputedStyle(wrap);
        var inner = wrap.clientWidth
          - parseFloat(ncs.paddingLeft || '0')
          - parseFloat(ncs.paddingRight || '0');
        if (inner > 8 && inner < view + 1) {
          avail = Math.min(avail, inner);
        }
      }
      return Math.max(0, avail);
    }

    var w = stage.clientWidth;
    if (wrap && wrap.clientWidth > 8) {
      w = wrap.clientWidth;
      var cs = window.getComputedStyle(wrap);
      w -= parseFloat(cs.paddingLeft || '0') + parseFloat(cs.paddingRight || '0');
    }
    if (content && content.clientWidth > 8 && content.clientWidth < w + 32) {
      w = Math.min(w, content.clientWidth);
    }
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
      var isThermal = sheet.classList.contains('sheet-thermal');
      sheet.style.zoom = '';
      sheet.style.transform = 'none';
      sheet.style.marginLeft = '0';
      stage.style.height = '';
      stage.style.overflow = 'hidden';
      var avail = availableWidth(stage, isThermal);
      var w = Math.max(sheet.offsetWidth, sheet.scrollWidth);
      var h = Math.max(sheet.offsetHeight, sheet.scrollHeight);
      if (!w || avail <= 0) return;
      var scale = Math.min(1, avail / w);
      sheet.style.transformOrigin = 'top left';
      if (scale >= 0.999) {
        // Still center thermal / narrow sheets when the stage is wider.
        var leftoverFull = avail - w;
        if (leftoverFull > 0.5) {
          sheet.style.marginLeft = leftoverFull / 2 + 'px';
        }
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
