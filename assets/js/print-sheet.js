(function () {
  var savedHref = '';
  var printFrame = null;

  function isIos() {
    var ua = navigator.userAgent || '';
    return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }
  function isAndroid() {
    return /Android/i.test(navigator.userAgent || '');
  }
  function isNarrow() {
    var w = (window.visualViewport && window.visualViewport.width)
      || document.documentElement.clientWidth
      || window.innerWidth
      || 0;
    return w > 0 && w < 900;
  }
  function isMobilePrint() {
    return isNarrow() || isIos() || isAndroid();
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
  function stripPrintUrls(doc) {
    doc.title = '\u00a0';
    var nodes = doc.querySelectorAll('a[href]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].removeAttribute('href');
    }
  }
  function scrubLocation() {
    if (savedHref) return;
    savedHref = location.href;
    try {
      // Drop path/query so mobile browser headers/footers do not print the document URL.
      history.replaceState(null, '', '/');
    } catch (e) {}
    document.title = '\u00a0';
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
  function cleanupPrintFrame() {
    if (printFrame && printFrame.parentNode) {
      printFrame.parentNode.removeChild(printFrame);
    }
    printFrame = null;
  }
  /** Mobile: print from about:blank so the footer has no document_view.php link. */
  function openMobilePrint() {
    clearFit();
    stripPrintUrls(document);
    markPages(document);
    var wrap = document.querySelector('.sheet-wrap');
    if (!wrap) {
      scrubLocation();
      window.focus();
      window.print();
      return;
    }
    cleanupPrintFrame();
    var iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.setAttribute('title', 'Print');
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none';
    document.body.appendChild(iframe);
    printFrame = iframe;
    var idoc = iframe.contentDocument || iframe.contentWindow.document;
    var headBits = [];
    var links = document.querySelectorAll('link[rel="stylesheet"], style');
    for (var i = 0; i < links.length; i++) {
      headBits.push(links[i].outerHTML);
    }
    idoc.open();
    idoc.write(
      '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
      + '<meta name="viewport" content="width=device-width, initial-scale=1">'
      + '<meta name="format-detection" content="telephone=no,email=no,address=no,date=no">'
      + '<title>\u00a0</title>'
      + headBits.join('')
      + '<style>@page{margin:0}.print-bar{display:none!important}a{color:inherit!important;text-decoration:none!important}a[href]::after,a[href]::before{content:none!important}</style>'
      + '</head><body class="' + (document.body.className || 'print-body') + '">'
      + wrap.outerHTML
      + '</body></html>'
    );
    idoc.close();
    stripPrintUrls(idoc);
    waitImages(idoc, function () {
      window.setTimeout(function () {
        try {
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
        } catch (e) {
          scrubLocation();
          window.print();
        }
        window.setTimeout(cleanupPrintFrame, 1500);
      }, isIos() ? 280 : 80);
    });
  }
  function openPrint() {
    if (isMobilePrint()) {
      openMobilePrint();
      return;
    }
    scrubLocation();
    document.title = '\u00a0';
    stripPrintUrls(document);
    markPages(document);
    window.focus();
    window.print();
  }
  window.addEventListener('beforeprint', function () {
    clearFit();
    stripPrintUrls(document);
    if (!isMobilePrint()) {
      scrubLocation();
    }
  });
  window.addEventListener('afterprint', function () {
    restoreLocation();
    cleanupPrintFrame();
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
