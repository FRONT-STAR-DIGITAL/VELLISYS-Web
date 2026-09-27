/**
 * Instant PDF of the exact branded desk sheet (same design as on-view preview).
 *
 * Prefer capturing the live .invoice-sheet on the page (after unfitting any
 * screen scale). Fall back to document_sheet.php?autodownload=1 when the sheet
 * is not on the current page. Capture mutates ONLY the html2canvas clone.
 */
(function () {
  var html2pdfLoading = null;
  var busy = false;
  var A4_W = 794; // 210mm at 96dpi
  var A4_H = 1122.52; // 297mm at 96dpi

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
    return document.querySelector('.invoice-sheet');
  }

  function unfitLiveSheet(sheet) {
    var stage = sheet.closest('.sheet-stage');
    var prev = {
      transform: sheet.style.transform,
      zoom: sheet.style.zoom,
      marginLeft: sheet.style.marginLeft,
      margin: sheet.style.margin,
      width: sheet.style.width,
      maxWidth: sheet.style.maxWidth,
      minHeight: sheet.style.minHeight,
      height: sheet.style.height,
      overflow: sheet.style.overflow,
      stageHeight: stage ? stage.style.height : '',
      stageOverflow: stage ? stage.style.overflow : '',
    };
    sheet.style.transform = 'none';
    sheet.style.zoom = '1';
    sheet.style.margin = '0';
    sheet.style.marginLeft = '0';
    sheet.style.width = '210mm';
    sheet.style.maxWidth = '210mm';
    sheet.style.minHeight = '0';
    sheet.style.height = 'auto';
    sheet.style.overflow = 'visible';
    if (stage) {
      stage.style.height = 'auto';
      stage.style.overflow = 'visible';
    }
    return prev;
  }

  function restoreLiveSheet(sheet, prev) {
    if (!prev) return;
    sheet.style.transform = prev.transform || '';
    sheet.style.zoom = prev.zoom || '';
    sheet.style.marginLeft = prev.marginLeft || '';
    sheet.style.margin = prev.margin || '';
    sheet.style.width = prev.width || '';
    sheet.style.maxWidth = prev.maxWidth || '';
    sheet.style.minHeight = prev.minHeight || '';
    sheet.style.height = prev.height || '';
    sheet.style.overflow = prev.overflow || '';
    var stage = sheet.closest('.sheet-stage');
    if (stage) {
      stage.style.height = prev.stageHeight || '';
      stage.style.overflow = prev.stageOverflow || '';
    }
    if (typeof window.fitDocumentSheets === 'function') {
      window.fitDocumentSheets();
    }
  }

  function paintCloneTree(live, clone) {
    if (!live || !clone || live.nodeType !== 1 || clone.nodeType !== 1) return;
    if (live.tagName !== clone.tagName) return;

    clone.style.setProperty('-webkit-print-color-adjust', 'exact', 'important');
    clone.style.setProperty('print-color-adjust', 'exact', 'important');

    if (!(clone.classList && (clone.classList.contains('bill-corner') || clone.classList.contains('d-watermark')))) {
      var cs = window.getComputedStyle(live);
      if (cs && (!cs.clipPath || cs.clipPath === 'none')) {
        if (cs.backgroundColor && cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent') {
          clone.style.backgroundColor = cs.backgroundColor;
        }
        if (cs.color) clone.style.color = cs.color;
        if (cs.borderTopColor) clone.style.borderTopColor = cs.borderTopColor;
        if (cs.borderRightColor) clone.style.borderRightColor = cs.borderRightColor;
        if (cs.borderBottomColor) clone.style.borderBottomColor = cs.borderBottomColor;
        if (cs.borderLeftColor) clone.style.borderLeftColor = cs.borderLeftColor;
      }
    }

    var liveChild = live.firstElementChild;
    var cloneChild = clone.firstElementChild;
    while (liveChild && cloneChild) {
      paintCloneTree(liveChild, cloneChild);
      liveChild = liveChild.nextElementSibling;
      cloneChild = cloneChild.nextElementSibling;
    }
  }

  function prepareCloneForCapture(clonedDoc, liveRoot, opts) {
    opts = opts || {};
    var cloned = clonedDoc.querySelector('.invoice-sheet');
    if (!cloned) return null;

    var html = clonedDoc.documentElement;
    var body = clonedDoc.body;
    if (html) {
      html.style.width = A4_W + 'px';
      html.style.minWidth = A4_W + 'px';
      html.style.overflow = 'visible';
      html.style.background = '#ffffff';
    }
    if (body) {
      body.style.width = A4_W + 'px';
      body.style.minWidth = A4_W + 'px';
      body.style.margin = '0';
      body.style.padding = '0';
      body.style.overflow = 'visible';
      body.style.background = '#ffffff';
    }

    var stage = clonedDoc.querySelector('.sheet-stage');
    if (stage) {
      stage.style.height = 'auto';
      stage.style.minHeight = '0';
      stage.style.padding = '0';
      stage.style.margin = '0';
      stage.style.overflow = 'visible';
      stage.style.width = A4_W + 'px';
    }

    var wrap = clonedDoc.querySelector('.sheet-wrap');
    if (wrap) {
      wrap.style.padding = '0';
      wrap.style.margin = '0';
      wrap.style.overflow = 'visible';
      wrap.style.width = A4_W + 'px';
      wrap.style.background = '#ffffff';
    }

    var sheets = clonedDoc.querySelectorAll('.invoice-sheet');
    for (var i = 0; i < sheets.length; i++) {
      var n = sheets[i];
      n.style.setProperty('transform', 'none', 'important');
      n.style.setProperty('zoom', '1', 'important');
      n.style.setProperty('margin', '0', 'important');
      n.style.setProperty('margin-left', '0', 'important');
      n.style.setProperty('box-shadow', 'none', 'important');
      n.style.setProperty('height', 'auto', 'important');
      n.style.setProperty('min-height', '0', 'important');
      n.style.setProperty('width', '210mm', 'important');
      n.style.setProperty('max-width', '210mm', 'important');
      n.style.setProperty('overflow', 'visible', 'important');
      n.style.setProperty('page-break-after', 'avoid', 'important');
      n.style.setProperty('break-after', 'avoid', 'important');
    }

    var stretch = clonedDoc.querySelectorAll(
      '.sheet-frame .page-frame, .sheet-inset .page-inset, .booklet-page, .chit-page'
    );
    for (var s = 0; s < stretch.length; s++) {
      stretch[s].style.minHeight = '0';
      stretch[s].style.height = 'auto';
    }

    // Keep seal / party grids fully visible in the capture.
    var metas = clonedDoc.querySelectorAll('.seal-meta');
    for (var m = 0; m < metas.length; m++) {
      var meta = metas[m];
      var cols = meta.children ? meta.children.length : 0;
      if (cols > 0) {
        meta.style.display = 'grid';
        meta.style.gridTemplateColumns = 'repeat(' + cols + ', minmax(0, 1fr))';
        meta.style.width = '100%';
      }
    }
    var parties = clonedDoc.querySelectorAll('.d-party-block.d-party-cols');
    for (var p = 0; p < parties.length; p++) {
      parties[p].style.display = 'grid';
      parties[p].style.gridTemplateColumns = 'minmax(0, 1fr) minmax(0, 1fr)';
      parties[p].style.width = '100%';
    }

    var auths = clonedDoc.querySelectorAll('.doc-authenticity');
    for (var a = 0; a < auths.length; a++) {
      auths[a].style.marginTop = '14px';
    }

    if (liveRoot) {
      paintCloneTree(liveRoot, cloned);
    }

    // Scale tall sheets into one A4 page; never clip left/right content.
    if (opts.forceSingle && !opts.thermal) {
      void cloned.offsetHeight;
      var cloneH = Math.max(cloned.scrollHeight || 0, cloned.offsetHeight || 0, opts.liveHeight || 0);
      var cloneW = Math.max(cloned.scrollWidth || 0, cloned.offsetWidth || 0, A4_W);
      if (cloneH > A4_H + 1) {
        var sc = A4_H / cloneH;
        cloned.style.transformOrigin = 'top left';
        cloned.style.transform = 'scale(' + sc + ')';
        var parent = cloned.parentElement;
        if (parent) {
          parent.style.width = cloneW + 'px';
          parent.style.height = A4_H + 'px';
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

  function nextFrame() {
    return new Promise(function (resolve) {
      requestAnimationFrame(function () {
        requestAnimationFrame(resolve);
      });
    });
  }

  function saveExactSheet(filename) {
    var sheet = exactSheetRoot();
    if (!sheet) return Promise.reject(new Error('sheet'));
    var prevFit = null;
    return waitAssets().then(function () {
      prevFit = unfitLiveSheet(sheet);
      return nextFrame();
    }).then(function () {
      return loadHtml2Pdf().then(function (html2pdf) {
        var thermal = !!(document.body && document.body.classList.contains('print-thermal'))
          || !!(sheet.classList && sheet.classList.contains('sheet-thermal'));
        var multipage = !!(sheet.classList && sheet.classList.contains('is-multipage'));
        var w = thermal
          ? Math.max(sheet.scrollWidth || 0, sheet.offsetWidth || 0, 302)
          : Math.max(sheet.scrollWidth || 0, sheet.offsetWidth || 0, A4_W);
        var h = Math.max(sheet.scrollHeight || 0, sheet.offsetHeight || 0, 1);
        // Short desk sheets: one A4. Only true multipage / very tall keep >1.
        var forceSingle = !thermal && !multipage && h <= A4_H * 1.45;
        var captureH = forceSingle ? Math.min(Math.max(h, 1), Math.ceil(A4_H)) : h;

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
            windowHeight: Math.max(captureH, A4_H),
            x: 0,
            y: 0,
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

        var done;
        if (!forceSingle) {
          done = worker.save();
        } else {
          done = worker.toPdf().get('pdf').then(function (pdf) {
            trimTrailingBlankPages(pdf, 1);
            return pdf;
          }).then(function (pdf) {
            pdf.save(filename || 'document.pdf');
          });
        }
        return done.finally(function () {
          restoreLiveSheet(sheet, prevFit);
        });
      });
    }).catch(function (err) {
      restoreLiveSheet(sheet, prevFit);
      throw err;
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

    e.preventDefault();
    e.stopPropagation();
    if (busy) return;

    // Exact match to the on-screen sheet whenever it is already rendered.
    if (exactSheetRoot()) {
      busy = true;
      var name = a.getAttribute('data-pdf-name')
        || (document.body && document.body.getAttribute('data-pdf-name'))
        || 'document.pdf';
      saveExactSheet(name).finally(function () { busy = false; });
      return;
    }

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
