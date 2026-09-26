(function () {
  var savedHref = '';

  function isIos() {
    var ua = navigator.userAgent || '';
    return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }
  function isNarrow() {
    var w = (window.visualViewport && window.visualViewport.width)
      || document.documentElement.clientWidth
      || window.innerWidth
      || 0;
    return w > 0 && w < 900;
  }
  function markPages(root) {
    var sheet = (root || document).querySelector('.invoice-sheet');
    if (!sheet || sheet.classList.contains('sheet-thermal')) return;
    sheet.style.minHeight = '0';
    var pagePx = (297 * 96) / 25.4;
    if (sheet.offsetHeight > pagePx + 8) {
      sheet.classList.add('is-multipage');
      (root || document).documentElement.classList.add('is-multipage');
    } else {
      sheet.classList.remove('is-multipage');
      (root || document).documentElement.classList.remove('is-multipage');
    }
  }
  function keepPdfTitle(doc) {
    var t = (doc.title || '').trim();
    // Keep document-number.pdf so Save as PDF uses the sheet number.
    if (/\.pdf$/i.test(t) || /^[A-Za-z0-9][A-Za-z0-9._-]{2,80}$/.test(t)) {
      return;
    }
    doc.title = '\u00a0';
  }
  function stripPrintUrls(doc) {
    keepPdfTitle(doc);
    var nodes = doc.querySelectorAll('a[href]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].removeAttribute('href');
    }
  }
  function scrubLocation() {
    if (savedHref) return;
    savedHref = location.href;
    try {
      // Shorten what mobile browsers put in print headers/footers.
      history.replaceState(null, '', '/');
    } catch (e) {}
    keepPdfTitle(document);
  }
  function restoreLocation() {
    if (!savedHref) return;
    try {
      history.replaceState(null, '', savedHref);
    } catch (e) {}
    savedHref = '';
  }
  function clearFit() {
    document.querySelectorAll('.invoice-sheet').forEach(function (sheet) {
      sheet.style.transform = 'none';
      sheet.style.zoom = '1';
      sheet.style.marginLeft = '0';
    });
    document.querySelectorAll('.sheet-stage').forEach(function (stage) {
      stage.style.height = 'auto';
      stage.style.overflow = 'visible';
    });
  }
  function applyScreenFit() {
    if (typeof window.fitDocumentSheets === 'function') {
      window.fitDocumentSheets();
      return;
    }
  }
  function waitImages(doc, fn) {
    var imgs = Array.prototype.slice.call(doc.images || []);
    var left = imgs.filter(function (img) { return !img.complete; }).length;
    if (!left) { fn(); return; }
    var timer = window.setTimeout(fn, 2500);
    function done() {
      left -= 1;
      if (left <= 0) {
        window.clearTimeout(timer);
        fn();
      }
    }
    imgs.forEach(function (img) {
      if (img.complete) return;
      img.addEventListener('load', done);
      img.addEventListener('error', done);
    });
  }
  function openPrint() {
    clearFit();
    scrubLocation();
    stripPrintUrls(document);
    markPages(document);
    window.focus();
    window.print();
  }
  window.addEventListener('beforeprint', function () {
    clearFit();
    scrubLocation();
    stripPrintUrls(document);
  });
  window.addEventListener('afterprint', function () {
    restoreLocation();
    applyScreenFit();
    window.setTimeout(applyScreenFit, 50);
  });

  function boot() {
    applyScreenFit();
    window.setTimeout(applyScreenFit, 80);
    window.setTimeout(applyScreenFit, 300);
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('[data-print-pdf]') : null;
      if (!t) return;
      e.preventDefault();
      openPrint();
    });
    // Narrow phones: show a fitted preview; user taps Print. Wider screens auto-print.
    if (isNarrow()) {
      document.documentElement.classList.add('print-preview');
      return;
    }
    waitImages(document, function () {
      window.setTimeout(function () {
        openPrint();
      }, isIos() ? 350 : 50);
    });
  }
  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
