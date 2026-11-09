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
    // A4 is 297mm tall; Trade Document is A5 (210mm).
    var pageMm = sheet.classList.contains('sheet-trade') ? 210 : 297;
    var pagePx = (pageMm * 96) / 25.4;
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
  /**
   * Chrome drops `@page { size: 80mm auto }` (invalid). Without a real height the
   * job falls back to A4/Letter and many 80mm roll printers refuse or feed blank.
   * Measure the laid-out slip and declare an explicit 80mm × Nmm page.
   */
  function setThermalPageSize(doc) {
    var sheet = (doc || document).querySelector('.invoice-sheet.sheet-thermal');
    if (!sheet) return;
    var rect = sheet.getBoundingClientRect();
    var px = Math.max(rect.height || 0, sheet.scrollHeight || 0, sheet.offsetHeight || 0);
    // CSS px → mm, plus a small buffer so the cutter does not clip the last line.
    var mm = Math.ceil((px * 25.4) / 96) + 4;
    if (!isFinite(mm) || mm < 40) mm = 120;
    if (mm > 4000) mm = 4000;
    var style = (doc || document).getElementById('thermal-page-size');
    if (!style) {
      style = (doc || document).createElement('style');
      style.id = 'thermal-page-size';
      (doc || document).head.appendChild(style);
    }
    style.textContent =
      '@page { size: 80mm ' + mm + 'mm; margin: 0; }' +
      '@page thermal-roll { size: 80mm ' + mm + 'mm; margin: 0; }';
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
    setThermalPageSize(document);
    window.focus();
    window.print();
  }
  window.addEventListener('beforeprint', function () {
    clearFit();
    scrubLocation();
    stripPrintUrls(document);
    setThermalPageSize(document);
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
