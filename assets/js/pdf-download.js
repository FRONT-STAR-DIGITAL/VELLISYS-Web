/**
 * Instant PDF of the exact branded desk sheet (same design as on-view preview).
 *
 * Always opens document_sheet.php?autodownload=1 — one path every click/refresh.
 * Capture mutates ONLY the html2canvas clone. Desktop A4 sheets are forced to a
 * single page (scale-to-fit + drop a trailing blank page) so short docs never
 * download as page-1 content + page-2 empty.
 */
(function () {
  var html2pdfLoading = null;
  var busy = false;
  var A4_PX = 1122.52; // 297mm at 96dpi

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
    return document.querySelector('.invoice-sheet') || document.querySelector('.sheet-stage');
  }

  function prepareCloneForCapture(clonedDoc, liveRoot, opts) {
    opts = opts || {};
    var cloned = clonedDoc.querySelector('.invoice-sheet') || clonedDoc.querySelector('.sheet-stage');
    if (!cloned) return null;

    var stage = clonedDoc.querySelector('.sheet-stage');
    if (stage) {
      stage.style.height = 'auto';
      stage.style.minHeight = '0';
      stage.style.padding = '0';
      stage.style.margin = '0';
      stage.style.overflow = 'visible';
    }

    var sheets = clonedDoc.querySelectorAll('.invoice-sheet');
    for (var i = 0; i < sheets.length; i++) {
      var n = sheets[i];
      n.style.transform = 'none';
      n.style.zoom = '1';
      n.style.marginLeft = '0';
      n.style.boxShadow = 'none';
      n.style.height = 'auto';
      n.style.minHeight = '0';
      n.style.pageBreakAfter = 'avoid';
      n.style.breakAfter = 'avoid';
    }

    var stretch = clonedDoc.querySelectorAll(
      '.sheet-frame .page-frame, .sheet-inset .page-inset, .booklet-page, .chit-page'
    );
    for (var s = 0; s < stretch.length; s++) {
      stretch[s].style.minHeight = '0';
      stretch[s].style.height = 'auto';
    }

    var auths = clonedDoc.querySelectorAll('.doc-authenticity');
    for (var a = 0; a < auths.length; a++) {
      auths[a].style.marginTop = '14px';
    }

    // Read computed paints from the LIVE sheet; write onto the clone only.
    var liveNodes = liveRoot
      ? [liveRoot].concat(Array.prototype.slice.call(liveRoot.querySelectorAll('*')))
      : [];
    var cloneNodes = [cloned].concat(Array.prototype.slice.call(cloned.querySelectorAll('*')));
    var len = Math.min(liveNodes.length, cloneNodes.length);
    for (var j = 0; j < len; j++) {
      var liveEl = liveNodes[j];
      var el = cloneNodes[j];
      if (!liveEl || !el || el.nodeType !== 1) continue;
      if (el.classList && (el.classList.contains('bill-corner') || el.classList.contains('d-watermark'))) {
        continue;
      }
      var cs = window.getComputedStyle(liveEl);
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

    // Desktop often measures a hair over A4 and html2pdf invents a blank page 2.
    // Scale the clone uniformly into one A4 when we are forcing a single page.
    if (opts.forceSingle && !opts.thermal) {
      void cloned.offsetHeight;
      var cloneH = Math.max(cloned.scrollHeight || 0, cloned.offsetHeight || 0, opts.liveHeight || 0);
      if (cloneH > A4_PX + 1) {
        var sc = A4_PX / cloneH;
        cloned.style.transformOrigin = 'top left';
        cloned.style.transform = 'scale(' + sc + ')';
        var parent = cloned.parentElement;
        if (parent) {
          parent.style.width = (cloned.offsetWidth || opts.liveWidth || 794) + 'px';
          parent.style.height = A4_PX + 'px';
          parent.style.overflow = 'hidden';
        }
      }
    }

    return cloned;
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

  function trimTrailingBlankPages(pdf, maxKeep) {
    maxKeep = Math.max(1, maxKeep || 1);
    try {
      var total = pdf.internal.getNumberOfPages();
      while (total > maxKeep) {
        pdf.deletePage(total);
        total--;
      }
    } catch (e) {}
    return pdf;
  }

  function saveExactSheet(filename) {
    var sheet = exactSheetRoot();
    if (!sheet) return Promise.reject(new Error('sheet'));
    return waitAssets().then(function () {
      return loadHtml2Pdf().then(function (html2pdf) {
        var thermal = !!(document.body && document.body.classList.contains('print-thermal'));
        var multipage = !!(sheet.classList && sheet.classList.contains('is-multipage'));
        var w = Math.max(sheet.scrollWidth || 0, sheet.offsetWidth || 0, thermal ? 302 : 794);
        var h = Math.max(sheet.scrollHeight || 0, sheet.offsetHeight || 0, 1);
        // Short desk sheets (desktop + mobile): one A4. Only true multipage / very tall keep >1.
        var forceSingle = !thermal && !multipage && h <= A4_PX * 1.45;
        var captureH = forceSingle ? Math.min(h, Math.ceil(A4_PX)) : h;

        var worker = html2pdf().set({
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
            height: captureH,
            windowWidth: w,
            windowHeight: captureH,
            scrollX: 0,
            scrollY: 0,
            onclone: function (clonedDoc) {
              prepareCloneForCapture(clonedDoc, sheet, {
                forceSingle: forceSingle,
                thermal: thermal,
                liveHeight: h,
                liveWidth: w,
              });
            },
          },
          jsPDF: {
            unit: 'mm',
            format: thermal
              ? [80, Math.max(120, Math.round(h * 0.2646))]
              : 'a4',
            orientation: 'portrait',
          },
          pagebreak: { mode: forceSingle || thermal ? ['avoid-all'] : ['css', 'legacy'] },
        }).from(sheet);

        if (!forceSingle) {
          return worker.save();
        }

        // Build PDF, drop any trailing blank page html2pdf adds on desktop, then save.
        return worker.toPdf().get('pdf').then(function (pdf) {
          trimTrailingBlankPages(pdf, 1);
          return pdf;
        }).then(function (pdf) {
          pdf.save(filename || 'document.pdf');
        });
      });
    });
  }

  function sheetUrlFromLink(a) {
    var sheet = a.getAttribute('data-sheet-url');
    if (sheet) return sheet;
    var id = a.getAttribute('data-doc-id');
    if (id) return 'document_sheet.php?id=' + encodeURIComponent(id) + '&autodownload=1';
    return a.getAttribute('href') || a.href;
  }

  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[data-pdf-download]') : null;
    if (!a) return;

    var onExact = document.body
      && (document.body.getAttribute('data-pdf-exact') === '1'
        || document.body.getAttribute('data-autodownload') === '1');
    if (onExact) {
      e.preventDefault();
      e.stopPropagation();
      if (busy) return;
      busy = true;
      var name = a.getAttribute('data-pdf-name') || document.body.getAttribute('data-pdf-name') || 'document.pdf';
      saveExactSheet(name).finally(function () { busy = false; });
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    window.location.href = sheetUrlFromLink(a);
  }, true);

  if (document.body && document.body.getAttribute('data-autodownload') === '1') {
    var autoName = document.body.getAttribute('data-pdf-name') || 'document.pdf';
    function runAuto() {
      if (busy) return;
      busy = true;
      saveExactSheet(autoName).then(function () {
        setTimeout(function () {
          if (window.history.length > 1) window.history.back();
        }, 400);
      }).catch(function () {
        document.title = 'Download failed';
      }).finally(function () {
        busy = false;
      });
    }
    if (document.readyState === 'complete') setTimeout(runAuto, 80);
    else window.addEventListener('load', function () { setTimeout(runAuto, 80); });
  }
})();
