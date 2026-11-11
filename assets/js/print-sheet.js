(function () {
  var savedHref = '';
  var thermalPdfBusy = false;
  var strippedHrefs = [];

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
      var a = nodes[i];
      // Print chrome uses buttons now; still skip anything in the bar.
      if (a.closest && a.closest('[data-print-bar]')) continue;
      var href = a.getAttribute('href');
      if (href == null) continue;
      strippedHrefs.push({ el: a, href: href });
      a.removeAttribute('href');
    }
  }
  function restorePrintUrls() {
    for (var i = 0; i < strippedHrefs.length; i++) {
      var item = strippedHrefs[i];
      if (item.el && item.href) {
        item.el.setAttribute('href', item.href);
      }
    }
    strippedHrefs = [];
  }
  function scrubLocation() {
    if (savedHref) return;
    savedHref = location.href;
    try {
      // Drop query/hash so printers do not print a long URL - but stay on this
      // path. Replacing with "/" made many phones show a blank page until Back.
      var path = location.pathname || '';
      if (path === '') path = savedHref;
      history.replaceState(null, '', path);
    } catch (e) {}
    keepPdfTitle(document);
  }
  function restoreLocation() {
    if (savedHref) {
      try {
        history.replaceState(null, '', savedHref);
      } catch (e) {}
      savedHref = '';
    }
    restorePrintUrls();
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
  function thermalWidthMm(doc) {
    var root = doc || document;
    var body = root.body;
    var sheet = root.querySelector('.invoice-sheet.sheet-thermal');
    var raw = (body && body.getAttribute('data-thermal-mm'))
      || (sheet && sheet.getAttribute('data-thermal-mm'))
      || '';
    var n = parseInt(raw, 10);
    if (n === 80) return 80;
    if (n === 58) return 58;
    try {
      var cssMm = getComputedStyle(root.documentElement).getPropertyValue('--thermal-mm');
      n = parseInt(String(cssMm || '').trim(), 10);
      if (n === 80) return 80;
    } catch (e) {}
    return 58;
  }
  function useThermalPdfPrint() {
    var tag = document.querySelector('script[data-thermal-pdf-print]');
    return !!(tag && thermalWidthMm(document) === 58);
  }
  /**
   * Chrome drops `@page { size: 80mm auto }` (invalid). Without a real height the
   * job falls back to A4/Letter and many roll printers refuse or shrink the slip.
   * Measure the laid-out slip and declare an explicit Wmm × Nmm page (58 or 80).
   */
  function setThermalPageSize(doc) {
    var root = doc || document;
    var sheet = root.querySelector('.invoice-sheet.sheet-thermal');
    if (!sheet) return;
    var widthMm = thermalWidthMm(root);
    var pageName = widthMm === 80 ? 'thermal-roll-80' : 'thermal-roll';
    // Collapse any A4 min-height before measuring so the roll is content-tall only.
    sheet.style.display = 'block';
    sheet.style.height = 'auto';
    sheet.style.minHeight = '0';
    sheet.style.maxHeight = 'none';
    var rect = sheet.getBoundingClientRect();
    var px = Math.max(rect.height || 0, sheet.scrollHeight || 0, sheet.offsetHeight || 0);
    // CSS px → mm, plus a small buffer so the cutter does not clip the last line.
    var mm = Math.ceil((px * 25.4) / 96) + 4;
    if (!isFinite(mm) || mm < 40) mm = 80;
    if (mm > 4000) mm = 4000;
    var style = root.getElementById('thermal-page-size');
    if (!style) {
      style = root.createElement('style');
      style.id = 'thermal-page-size';
      root.head.appendChild(style);
    }
    style.textContent =
      '@page { size: ' + widthMm + 'mm ' + mm + 'mm; margin: 0; }' +
      '@page ' + pageName + ' { size: ' + widthMm + 'mm ' + mm + 'mm; margin: 0; }' +
      'html, body { width: ' + widthMm + 'mm !important; max-width: ' + widthMm + 'mm !important; margin: 0 !important; height: auto !important; }' +
      '.print-thermal, .print-thermal .sheet-wrap, .print-thermal .sheet-stage,' +
      '.invoice-sheet.sheet-thermal { page: ' + pageName + '; display: block !important; width: ' + widthMm + 'mm !important; max-width: ' + widthMm + 'mm !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }' +
      '.invoice-sheet.sheet-thermal .doc-authenticity, .invoice-sheet.sheet-thermal .thermal-thanks { margin-top: 6px !important; }';
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
  function loadHtml2Pdf() {
    if (typeof window.html2pdf === 'function') {
      return Promise.resolve(window.html2pdf);
    }
    var host = document.querySelector('script[data-html2pdf]');
    var src = (host && host.getAttribute('data-html2pdf')) || '';
    if (!src) return Promise.reject(new Error('missing'));
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () {
        if (typeof window.html2pdf === 'function') resolve(window.html2pdf);
        else reject(new Error('load'));
      };
      s.onerror = function () { reject(new Error('load')); };
      document.head.appendChild(s);
    });
  }
  function nextFrame() {
    return new Promise(function (resolve) {
      requestAnimationFrame(function () {
        requestAnimationFrame(resolve);
      });
    });
  }
  /**
   * Pocket Bluetooth printers (MPT II etc.) often ignore HTML @page size and
   * shrink a 58mm slip onto A4. Print a real 58×Nmm PDF instead — 80mm keeps
   * the normal HTML window.print() path.
   */
  function printThermalPdf() {
    if (thermalPdfBusy) return;
    var sheet = document.querySelector('.invoice-sheet.sheet-thermal');
    if (!sheet) {
      openHtmlPrint();
      return;
    }
    thermalPdfBusy = true;
    var btn = document.querySelector('[data-print-pdf]');
    if (btn) {
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
    }
    clearFit();
    scrubLocation();
    stripPrintUrls(document);
    var widthMm = 58;
    // Lock layout width and collapse A4 min-height before capture.
    sheet.style.display = 'block';
    sheet.style.width = widthMm + 'mm';
    sheet.style.maxWidth = widthMm + 'mm';
    sheet.style.height = 'auto';
    sheet.style.minHeight = '0';
    sheet.style.maxHeight = 'none';
    sheet.style.margin = '0';
    sheet.style.transform = 'none';
    sheet.style.zoom = '1';

    loadHtml2Pdf().then(function (html2pdf) {
      return nextFrame().then(function () {
        var w = Math.max(sheet.scrollWidth || 0, sheet.offsetWidth || 0, Math.round((widthMm * 96) / 25.4));
        var h = Math.max(sheet.scrollHeight || 0, sheet.offsetHeight || 0, 1);
        var heightMm = Math.ceil((h * 25.4) / 96) + 4;
        if (!isFinite(heightMm) || heightMm < 40) heightMm = 80;
        if (heightMm > 4000) heightMm = 4000;
        return html2pdf().set({
          margin: 0,
          filename: (document.title || 'receipt').replace(/\s+/g, '-') + '.pdf',
          image: { type: 'jpeg', quality: 0.96 },
          html2canvas: {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false,
            width: w,
            height: h,
            windowWidth: w,
            windowHeight: h,
            x: 0,
            y: 0,
            scrollX: 0,
            scrollY: 0,
          },
          jsPDF: {
            unit: 'mm',
            format: [widthMm, heightMm],
            orientation: 'portrait',
          },
          pagebreak: { mode: ['avoid-all'] },
        }).from(sheet).toPdf().get('pdf').then(function (pdf) {
          try {
            var total = pdf.internal.getNumberOfPages();
            while (total > 1) {
              pdf.deletePage(total);
              total--;
            }
          } catch (e) {}
          return pdf.output('blob');
        });
      });
    }).then(function (blob) {
      if (!blob) throw new Error('blob');
      var url = URL.createObjectURL(blob);
      var iframe = document.createElement('iframe');
      iframe.setAttribute('title', 'Print receipt');
      iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none';
      var cleaned = false;
      function cleanup() {
        if (cleaned) return;
        cleaned = true;
        try { URL.revokeObjectURL(url); } catch (e) {}
        try { iframe.remove(); } catch (e2) {}
        thermalPdfBusy = false;
        if (btn) {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
        }
        restoreLocation();
        applyScreenFit();
      }
      iframe.onload = function () {
        try {
          var win = iframe.contentWindow;
          if (!win) throw new Error('frame');
          win.focus();
          win.print();
        } catch (e) {
          // Last resort: open the PDF tab so the user can print from there.
          window.open(url, '_blank');
        }
        window.setTimeout(cleanup, 90000);
      };
      document.body.appendChild(iframe);
      iframe.src = url;
    }).catch(function () {
      thermalPdfBusy = false;
      if (btn) {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
      }
      openHtmlPrint();
    });
  }
  function openHtmlPrint() {
    clearFit();
    scrubLocation();
    stripPrintUrls(document);
    markPages(document);
    setThermalPageSize(document);
    window.focus();
    window.print();
  }
  function openPrint() {
    if (useThermalPdfPrint()) {
      printThermalPdf();
      return;
    }
    openHtmlPrint();
  }
  window.addEventListener('beforeprint', function () {
    if (useThermalPdfPrint() && thermalPdfBusy) return;
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

  function isThermalPrint() {
    return !!(document.body && document.body.classList.contains('print-thermal'))
      || !!document.querySelector('.invoice-sheet.sheet-thermal');
  }
  function goBack(btn) {
    var href = (btn && (btn.getAttribute('data-href') || btn.getAttribute('data-print-href'))) || '';
    if (href) {
      window.location.assign(href);
      return;
    }
    if (window.history.length > 1) {
      window.history.back();
    }
  }
  function boot() {
    applyScreenFit();
    window.setTimeout(applyScreenFit, 80);
    window.setTimeout(applyScreenFit, 300);
    document.addEventListener('click', function (e) {
      var back = e.target && e.target.closest ? e.target.closest('[data-print-back]') : null;
      if (back) {
        e.preventDefault();
        goBack(back);
        return;
      }
      var t = e.target && e.target.closest ? e.target.closest('[data-print-pdf]') : null;
      if (!t) return;
      e.preventDefault();
      openPrint();
    });
    // Always show the on-screen preview first (all devices). Auto window.print()
    // opened a blank dialog / blank page before the slip was ready.
    document.documentElement.classList.add('print-preview');
  }
  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
