/**
 * Instant PDF of the exact branded desk sheet (same design as on view).
 *
 * 1) Fetch document_download.php as a blob when the server can print it.
 * 2) Otherwise open the unfitted sheet autodownload and capture with html2pdf.
 *
 * Capture rules: use .invoice-sheet only, collapse screen min-height (297mm),
 * avoid blank second pages, keep A4 (thermal = roll width).
 */
(function () {
  var html2pdfLoading = null;
  var warmed = {};
  var busy = false;

  function loadHtml2Pdf() {
    if (typeof window.html2pdf === 'function') {
      return Promise.resolve(window.html2pdf);
    }
    if (html2pdfLoading) return html2pdfLoading;
    var host = document.querySelector('script[data-html2pdf]');
    var src = (host && host.getAttribute('data-html2pdf')) || '';
    if (!src) return Promise.reject(new Error('missing'));
    html2pdfLoading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () {
        if (typeof window.html2pdf === 'function') resolve(window.html2pdf);
        else reject(new Error('load'));
      };
      s.onerror = function () {
        html2pdfLoading = null;
        reject(new Error('load'));
      };
      document.head.appendChild(s);
    });
    return html2pdfLoading;
  }

  function exactSheetRoot() {
    // Always the sheet article — never .sheet-stage (padding/fit height → blank page 2).
    return document.querySelector('.invoice-sheet') || document.querySelector('.sheet-stage');
  }

  /** Collapse screen-only A4 min-height so capture matches print (no empty footer page). */
  function prepareSheetForCapture(sheet) {
    var nodes = document.querySelectorAll('.invoice-sheet');
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      n.style.transform = 'none';
      n.style.zoom = '1';
      n.style.marginLeft = '0';
      n.style.boxShadow = 'none';
      n.style.height = 'auto';
      n.style.minHeight = '0';
      n.style.pageBreakAfter = 'avoid';
      n.style.breakAfter = 'avoid';
    }
    var stage = document.querySelector('.sheet-stage');
    if (stage) {
      stage.style.height = 'auto';
      stage.style.minHeight = '0';
      stage.style.overflow = 'visible';
      stage.style.padding = '0';
      stage.style.margin = '0';
    }
    var stretch = document.querySelectorAll(
      '.sheet-frame .page-frame, .sheet-inset .page-inset, .booklet-page, .chit-page'
    );
    for (var s = 0; s < stretch.length; s++) {
      stretch[s].style.minHeight = '0';
      stretch[s].style.height = 'auto';
    }
    var auths = document.querySelectorAll('.doc-authenticity');
    for (var a = 0; a < auths.length; a++) {
      auths[a].style.marginTop = '14px';
    }
    if (sheet) {
      sheet.style.height = 'auto';
      sheet.style.minHeight = '0';
    }
  }

  /** Resolve CSS variables / color-mix into concrete paints (skip clip-path corners). */
  function flattenPaintStyles(root) {
    if (!root) return;
    var nodes = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (!el || el.nodeType !== 1) continue;
      // clip-path corners must keep their own background; flattening breaks them.
      if (el.classList && (el.classList.contains('bill-corner') || el.classList.contains('d-watermark'))) {
        continue;
      }
      var cs = window.getComputedStyle(el);
      if (!cs) continue;
      if (cs.clipPath && cs.clipPath !== 'none') continue;
      el.style.setProperty('-webkit-print-color-adjust', 'exact', 'important');
      el.style.setProperty('print-color-adjust', 'exact', 'important');
      if (cs.backgroundColor && cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent') {
        el.style.backgroundColor = cs.backgroundColor;
      }
      if (cs.color) el.style.color = cs.color;
      if (cs.borderTopColor) el.style.borderTopColor = cs.borderTopColor;
      if (cs.borderRightColor) el.style.borderRightColor = cs.borderRightColor;
      if (cs.borderBottomColor) el.style.borderBottomColor = cs.borderBottomColor;
      if (cs.borderLeftColor) el.style.borderLeftColor = cs.borderLeftColor;
    }
  }

  function waitAssets() {
    var imgs = Array.prototype.slice.call(document.images || []);
    var pending = imgs.filter(function (img) { return !img.complete; }).map(function (img) {
      return new Promise(function (resolve) {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
      });
    });
    var fonts = (document.fonts && document.fonts.ready) ? document.fonts.ready.catch(function () {}) : Promise.resolve();
    return Promise.all([fonts].concat(pending));
  }

  function saveExactSheet(filename) {
    var sheet = exactSheetRoot();
    if (!sheet) return Promise.reject(new Error('sheet'));
    return waitAssets().then(function () {
      prepareSheetForCapture(sheet);
      // Re-measure after collapsing min-height.
      void sheet.offsetHeight;
      flattenPaintStyles(sheet);
      return loadHtml2Pdf().then(function (html2pdf) {
        var thermal = !!(document.body && document.body.classList.contains('print-thermal'));
        var w = Math.max(sheet.scrollWidth || 0, sheet.offsetWidth || 0, thermal ? 302 : 794);
        var h = Math.max(sheet.scrollHeight || 0, sheet.offsetHeight || 0, 1);
        // A4 height at 96dpi ≈ 1123px. If we fit, force one page (no blank page 2).
        var a4px = 1123;
        var fitsOne = !thermal && h <= a4px + 8;
        return html2pdf().set({
          margin: 0,
          filename: filename || 'document.pdf',
          image: { type: 'jpeg', quality: 0.98 },
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
            scrollX: 0,
            scrollY: 0,
            onclone: function (clonedDoc) {
              var cloned = clonedDoc.querySelector('.invoice-sheet') || clonedDoc.querySelector('.sheet-stage');
              if (!cloned) return;
              cloned.style.minHeight = '0';
              cloned.style.height = 'auto';
              cloned.style.transform = 'none';
              cloned.style.boxShadow = 'none';
              var stage = clonedDoc.querySelector('.sheet-stage');
              if (stage) {
                stage.style.height = 'auto';
                stage.style.minHeight = '0';
                stage.style.padding = '0';
                stage.style.margin = '0';
              }
              var stretch = clonedDoc.querySelectorAll(
                '.sheet-frame .page-frame, .sheet-inset .page-inset, .booklet-page, .chit-page'
              );
              for (var i = 0; i < stretch.length; i++) {
                stretch[i].style.minHeight = '0';
                stretch[i].style.height = 'auto';
              }
            },
          },
          jsPDF: {
            unit: 'mm',
            format: thermal
              ? [80, Math.max(120, Math.round(h * 0.2646))]
              : 'a4',
            orientation: 'portrait',
          },
          // avoid-all prevents html2pdf from inventing a blank trailing page.
          pagebreak: { mode: fitsOne || thermal ? ['avoid-all'] : ['css', 'legacy'] },
        }).from(sheet).save();
      });
    });
  }

  function triggerBlobDownload(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'document.pdf';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
      URL.revokeObjectURL(url);
      a.remove();
    }, 1500);
  }

  function sheetFallbackUrl(a) {
    var sheet = a.getAttribute('data-sheet-url');
    if (sheet) return sheet;
    var id = a.getAttribute('data-doc-id');
    if (id) return 'document_sheet.php?id=' + encodeURIComponent(id) + '&autodownload=1';
    return a.href;
  }

  function fetchServerPdf(url, filename) {
    return fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/pdf', 'X-Requested-With': 'XMLHttpRequest' },
      redirect: 'follow',
    }).then(function (res) {
      var type = (res.headers.get('content-type') || '').toLowerCase();
      if (!res.ok || type.indexOf('pdf') === -1) {
        throw new Error('not-pdf');
      }
      return res.arrayBuffer();
    }).then(function (buf) {
      var head = new Uint8Array(buf.slice(0, 4));
      var isPdf = head[0] === 0x25 && head[1] === 0x50 && head[2] === 0x44 && head[3] === 0x46;
      if (!isPdf) throw new Error('not-pdf');
      triggerBlobDownload(new Blob([buf], { type: 'application/pdf' }), filename);
    });
  }

  function warmServer(id) {
    if (!id || warmed[id]) return;
    warmed[id] = true;
    try {
      fetch('document_download.php?id=' + encodeURIComponent(String(id)) + '&warm=1', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      }).catch(function () {});
    } catch (e) {}
  }

  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[data-pdf-download]') : null;
    if (!a) return;
    e.preventDefault();
    e.stopPropagation();
    if (busy) return;

    var name = a.getAttribute('data-pdf-name') || 'document.pdf';
    var exact = document.body && document.body.getAttribute('data-pdf-exact') === '1';

    if (exact || document.body.getAttribute('data-autodownload') === '1') {
      busy = true;
      saveExactSheet(name).finally(function () { busy = false; });
      return;
    }

    busy = true;
    fetchServerPdf(a.href, name).then(function () {
      busy = false;
    }).catch(function () {
      busy = false;
      window.location.href = sheetFallbackUrl(a);
    });
  }, true);

  if (document.body && document.body.getAttribute('data-autodownload') === '1') {
    var autoName = document.body.getAttribute('data-pdf-name') || 'document.pdf';
    function runAuto() {
      saveExactSheet(autoName).then(function () {
        setTimeout(function () {
          if (window.history.length > 1) window.history.back();
        }, 400);
      }).catch(function () {
        document.title = 'Download failed';
      });
    }
    if (document.readyState === 'complete') setTimeout(runAuto, 60);
    else window.addEventListener('load', function () { setTimeout(runAuto, 60); });
  }

  var link = document.querySelector('a[data-pdf-download][data-doc-id]');
  var id = link ? parseInt(link.getAttribute('data-doc-id') || '0', 10) : 0;
  if (id && document.querySelector('.invoice-sheet') && !(document.body && document.body.getAttribute('data-autodownload'))) {
    if (window.requestIdleCallback) {
      window.requestIdleCallback(function () { warmServer(id); }, { timeout: 2500 });
    } else {
      setTimeout(function () { warmServer(id); }, 800);
    }
  }
})();
