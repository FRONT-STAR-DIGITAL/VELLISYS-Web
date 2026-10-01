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

  function withTimeout(promise, ms) {
    return new Promise(function (resolve, reject) {
      var done = false;
      var timer = setTimeout(function () {
        if (done) return;
        done = true;
        reject(new Error('timeout'));
      }, ms);
      Promise.resolve(promise).then(function (value) {
        if (done) return;
        done = true;
        clearTimeout(timer);
        resolve(value);
      }, function (err) {
        if (done) return;
        done = true;
        clearTimeout(timer);
        reject(err);
      });
    });
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
    // Never block WhatsApp share forever on a hung font/image.
    return withTimeout(Promise.all([fonts].concat(pending)), 4000).catch(function () {
      return null;
    });
  }

  function isMobileCapture() {
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')
      || (Math.min(screen.width || 0, screen.height || 0) > 0 && Math.min(screen.width, screen.height) < 720);
  }

  function blobToShareFile(blob, name) {
    var fileName = name || 'document.pdf';
    if (!/\.pdf$/i.test(fileName)) fileName += '.pdf';
    try {
      return new File([blob], fileName, { type: 'application/pdf' });
    } catch (e) {
      try {
        return new Blob([blob], { type: 'application/pdf' });
      } catch (e2) {
        return blob;
      }
    }
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

  function buildExactSheetWorker(filename) {
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

        var mobile = isMobileCapture();
        var worker = html2pdf().set({
          margin: 0,
          filename: filename || 'document.pdf',
          image: { type: 'jpeg', quality: mobile ? 0.92 : 0.98 },
          html2canvas: {
            // Phones OOM / crash at scale 2 on tall branded sheets.
            scale: mobile ? 1.25 : 2,
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

        return {
          worker: worker,
          forceSingle: forceSingle,
          sheet: sheet,
          prevFit: prevFit,
          filename: filename || 'document.pdf',
        };
      });
    }).catch(function (err) {
      restoreLiveSheet(sheet, prevFit);
      throw err;
    });
  }

  function pdfBlobFromWorker(built) {
    var worker = built.worker;
    var chain = worker.toPdf().get('pdf').then(function (pdf) {
      if (built.forceSingle) {
        trimTrailingBlankPages(pdf, 1);
      }
      return pdf.output('blob');
    });
    return chain.finally(function () {
      restoreLiveSheet(built.sheet, built.prevFit);
    });
  }

  function saveExactSheet(filename) {
    return buildExactSheetWorker(filename).then(function (built) {
      return pdfBlobFromWorker(built).then(function (blob) {
        var name = built.filename;
        // Prefer an <a download> so mobile never opens a blob: tab that gets shared as a link.
        var url = URL.createObjectURL(blob);
        try {
          var a = document.createElement('a');
          a.href = url;
          a.download = name;
          a.rel = 'noopener';
          a.style.display = 'none';
          document.body.appendChild(a);
          a.click();
          a.remove();
        } finally {
          setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
        }
      });
    });
  }

  function canSharePdfFile(file) {
    try {
      return !!(navigator.share && navigator.canShare && navigator.canShare({ files: [file] }));
    } catch (e) {
      return false;
    }
  }

  function supportsFileShare() {
    try {
      if (!navigator.share || !navigator.canShare) return false;
      var probe = new File([new Uint8Array([37, 80, 68, 70])], 'probe.pdf', { type: 'application/pdf' });
      return !!navigator.canShare({ files: [probe] });
    } catch (e) {
      return false;
    }
  }

  /** Share the PDF file alone — never pass url/text (those become blob links in WhatsApp). */
  function sharePdfFile(file) {
    if (!file) {
      return Promise.reject(new Error('share-unsupported'));
    }
    // Prefer real File shares; some WebViews only accept Blob-as-file after wrap.
    var shareable = file;
    if (!(file instanceof File) && typeof File === 'function') {
      try {
        shareable = new File([file], file.name || 'document.pdf', {
          type: file.type || 'application/pdf',
        });
      } catch (e) {
        shareable = file;
      }
    }
    if (!canSharePdfFile(shareable)) {
      // Last try: some Android builds accept files without canShare probing.
      if (navigator.share) {
        return navigator.share({ files: [shareable] }).catch(function () {
          return Promise.reject(new Error('share-unsupported'));
        });
      }
      return Promise.reject(new Error('share-unsupported'));
    }
    return navigator.share({ files: [shareable] });
  }

  function fetchServerPdfFile(docId, filename, pdfUrl) {
    var name = filename || 'document.pdf';
    if (!/\.pdf$/i.test(name)) name += '.pdf';
    var href = pdfUrl || '';
    if (!href && docId) {
      href = 'document_pdf.php?id=' + encodeURIComponent(docId);
    }
    if (!href) {
      return Promise.reject(new Error('pdf-url'));
    }
    return fetch(href, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/pdf',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).then(function (res) {
      var ct = (res.headers.get('content-type') || '').toLowerCase();
      if (!res.ok) {
        throw new Error('pdf-http');
      }
      if (ct.indexOf('json') !== -1) {
        throw new Error('pdf-json');
      }
      if (ct && ct.indexOf('pdf') === -1 && ct.indexOf('octet-stream') === -1) {
        throw new Error('pdf-type');
      }
      return res.blob();
    }).then(function (blob) {
      if (!blob || !blob.size) {
        throw new Error('pdf-empty');
      }
      var file = blobToShareFile(blob, name);
      if (file && !file.name) {
        try { file.name = name; } catch (e) {}
      }
      return file;
    });
  }

  function captureSheetPdfFile(filename) {
    var name = filename || 'document.pdf';
    if (!/\.pdf$/i.test(name)) name += '.pdf';
    if (!exactSheetRoot()) {
      return Promise.reject(new Error('sheet'));
    }
    return withTimeout(
      buildExactSheetWorker(name).then(function (built) {
        return pdfBlobFromWorker(built).then(function (blob) {
          if (!blob || !blob.size) {
            throw new Error('pdf-empty');
          }
          return blobToShareFile(blob, name);
        });
      }),
      isMobileCapture() ? 20000 : 45000
    );
  }

  function buildPdfFile(filename, opts) {
    opts = opts || {};
    var name = filename || opts.filename || 'document.pdf';
    if (!/\.pdf$/i.test(name)) name += '.pdf';
    var docId = opts.docId || '';
    var pdfUrl = opts.pdfUrl || '';

    // 1) Capture the on-screen sheet (matches the preview).
    // 2) If that fails on a phone, fetch the server PDF so WhatsApp still gets a file.
    return captureSheetPdfFile(name).catch(function (err) {
      if (!docId && !pdfUrl) {
        throw err || new Error('capture');
      }
      return fetchServerPdfFile(docId, name, pdfUrl);
    });
  }

  // Warm the PDF engine so the first WhatsApp tap is less likely to fail.
  try {
    if (document.readyState === 'complete') {
      setTimeout(function () { loadHtml2Pdf().catch(function () {}); }, 600);
    } else {
      window.addEventListener('load', function () {
        setTimeout(function () { loadHtml2Pdf().catch(function () {}); }, 600);
      });
    }
  } catch (e) {}

  function downloadPdfBlob(blob, name) {
    var url = URL.createObjectURL(blob);
    try {
      var a = document.createElement('a');
      a.href = url;
      a.download = name || 'document.pdf';
      a.rel = 'noopener';
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      a.remove();
    } finally {
      setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
    }
  }

  /**
   * Share the PDF file alone (document number as the filename).
   * Never pass url/text — those become the useless blob:https://… link in WhatsApp.
   */
  function shareExactSheet(filename) {
    return buildPdfFile(filename).then(function (file) {
      if (canSharePdfFile(file)) {
        return sharePdfFile(file);
      }
      downloadPdfBlob(file, file.name);
      return null;
    });
  }

  function shareItemCopy(a) {
    return {
      strong: a.querySelector('.share-pop-copy strong'),
      small: a.querySelector('.share-pop-copy small'),
    };
  }

  function rememberShareLabels(a) {
    if (a.getAttribute('data-share-label')) return;
    var copy = shareItemCopy(a);
    if (copy.strong) a.setAttribute('data-share-label', copy.strong.textContent || 'WhatsApp');
    if (copy.small) a.setAttribute('data-share-sub', copy.small.textContent || '');
  }

  function setShareItemState(a, state, filename) {
    rememberShareLabels(a);
    var copy = shareItemCopy(a);
    a.classList.toggle('is-preparing', state === 'preparing');
    a.classList.toggle('is-ready', state === 'ready');
    a.classList.toggle('is-error', state === 'error');
    if (state === 'preparing') {
      if (copy.strong) copy.strong.textContent = 'Preparing…';
      if (copy.small) copy.small.textContent = 'Building ' + (filename || 'PDF');
      return;
    }
    if (state === 'ready') {
      if (copy.strong) copy.strong.textContent = 'Send on WhatsApp';
      if (copy.small) copy.small.textContent = 'Tap to choose a contact';
      return;
    }
    if (state === 'error') {
      if (copy.strong) copy.strong.textContent = 'Could not prepare';
      if (copy.small) copy.small.textContent = 'Tap to try again';
      return;
    }
    if (copy.strong) copy.strong.textContent = a.getAttribute('data-share-label') || 'WhatsApp';
    if (copy.small) {
      copy.small.textContent = a.getAttribute('data-share-sub')
        || (filename ? ('Send ' + filename) : 'Send the PDF');
    }
  }

  function keepSharePopOpen(a) {
    var details = a && a.closest ? a.closest('details.share-pop') : null;
    if (details) details.open = true;
    return details;
  }

  function closeSharePop(a) {
    var details = a && a.closest ? a.closest('details.share-pop') : null;
    if (details) details.open = false;
  }

  var readyShareFiles = new WeakMap();

  function showWhatsAppSendBar(file) {
    var bar = document.getElementById('wa-share-send-bar');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'wa-share-send-bar';
      bar.className = 'wa-share-send-bar';
      bar.innerHTML = '<div class="wa-share-send-bar-inner">'
        + '<div class="wa-share-send-copy"><strong>PDF ready</strong><small></small></div>'
        + '<button type="button" class="btn wa-share-send-btn">Send on WhatsApp</button>'
        + '<button type="button" class="btn ghost sm wa-share-send-close" aria-label="Close">Close</button>'
        + '</div>';
      document.body.appendChild(bar);
      bar.querySelector('.wa-share-send-close').addEventListener('click', function () {
        bar.hidden = true;
      });
    }
    var small = bar.querySelector('small');
    if (small) small.textContent = file && file.name ? file.name : 'document.pdf';
    var btn = bar.querySelector('.wa-share-send-btn');
    btn.onclick = function () {
      if (!file) return;
      btn.disabled = true;
      sharePdfFile(file).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        if (err && err.message === 'share-unsupported') {
          downloadPdfBlob(file, file.name);
          return;
        }
        // Still offer download if the sheet was dismissed for another reason.
        if (!err || err.name !== 'NotAllowedError') downloadPdfBlob(file, file.name);
      }).finally(function () {
        btn.disabled = false;
      });
    };
    bar.hidden = false;
    return bar;
  }

  function sheetUrlFromLink(a) {
    var sheet = a.getAttribute('data-sheet-url');
    if (sheet) return sheet;
    var id = a.getAttribute('data-doc-id');
    if (id) return 'document_sheet.php?id=' + encodeURIComponent(id) + '&autodownload=1';
    return a.getAttribute('href') || a.href;
  }

  document.addEventListener('click', function (e) {
    var shareA = e.target && e.target.closest ? e.target.closest('a[data-pdf-share]') : null;
    if (shareA) {
      e.preventDefault();
      e.stopPropagation();
      keepSharePopOpen(shareA);
      var shareName = shareA.getAttribute('data-pdf-name')
        || (document.body && document.body.getAttribute('data-pdf-name'))
        || 'document.pdf';
      if (!/\.pdf$/i.test(shareName)) shareName += '.pdf';

      // Second tap: PDF is ready — share in this gesture so WhatsApp / Contacts open.
      var readyFile = readyShareFiles.get(shareA);
      if (readyFile && shareA.classList.contains('is-ready')) {
        sharePdfFile(readyFile).then(function () {
          setShareItemState(shareA, 'idle', shareName);
          readyShareFiles.delete(shareA);
          closeSharePop(shareA);
        }).catch(function (err) {
          if (err && err.name === 'AbortError') return;
          if (err && err.message === 'share-unsupported') {
            downloadPdfBlob(readyFile, readyFile.name);
            setShareItemState(shareA, 'idle', shareName);
            readyShareFiles.delete(shareA);
            closeSharePop(shareA);
            return;
          }
          setShareItemState(shareA, 'ready', shareName);
        });
        return;
      }

      if (busy) return;

      if (!exactSheetRoot()) {
        var go = shareA.getAttribute('href');
        if (!go || go === '#') {
          var docId = shareA.getAttribute('data-doc-id');
          if (docId) go = 'document_view.php?id=' + encodeURIComponent(docId) + '&sharepdf=1';
        }
        if (go && go !== '#') window.location.href = go;
        return;
      }

      busy = true;
      setShareItemState(shareA, 'preparing', shareName);
      var shareOpts = {
        docId: shareA.getAttribute('data-doc-id') || '',
        pdfUrl: shareA.getAttribute('data-pdf-url') || '',
      };
      buildPdfFile(shareName, shareOpts).then(function (file) {
        readyShareFiles.set(shareA, file);
        // Try immediately; most mobiles drop user-activation after async PDF work.
        return sharePdfFile(file).then(function () {
          setShareItemState(shareA, 'idle', shareName);
          readyShareFiles.delete(shareA);
          closeSharePop(shareA);
          var bar = document.getElementById('wa-share-send-bar');
          if (bar) bar.hidden = true;
        }).catch(function (err) {
          if (err && err.name === 'AbortError') {
            setShareItemState(shareA, 'idle', shareName);
            readyShareFiles.delete(shareA);
            return;
          }
          // Need a fresh tap so the OS / WhatsApp contact picker can open.
          setShareItemState(shareA, 'ready', shareName);
          keepSharePopOpen(shareA);
          showWhatsAppSendBar(file);
        });
      }).catch(function () {
        setShareItemState(shareA, 'error', shareName);
        keepSharePopOpen(shareA);
        var bar = showWhatsAppSendBar(null);
        var copyStrong = bar.querySelector('strong');
        var sendBtn = bar.querySelector('.wa-share-send-btn');
        if (copyStrong) copyStrong.textContent = 'Could not prepare PDF';
        if (sendBtn) {
          sendBtn.disabled = false;
          sendBtn.textContent = 'Open PDF page';
          sendBtn.onclick = function () {
            var sheet = shareA.getAttribute('data-sheet-url')
              || shareA.getAttribute('href')
              || '';
            if (sheet) window.location.href = sheet;
          };
        }
      }).finally(function () { busy = false; });
      return;
    }

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

  function pdfNameFromPage() {
    var named = document.querySelector('[data-pdf-name]');
    if (named && named.getAttribute('data-pdf-name')) {
      return named.getAttribute('data-pdf-name');
    }
    if (document.body && document.body.getAttribute('data-pdf-name')) {
      return document.body.getAttribute('data-pdf-name');
    }
    return 'document.pdf';
  }

  // document_view.php?sharepdf=1 — prepare the PDF, then ask for a tap (gesture required).
  if (/(?:^|[?&])sharepdf=1(?:&|$)/.test(location.search || '')) {
    function runSharePrepare() {
      if (busy) return;
      busy = true;
      var fname = pdfNameFromPage();
      var shareA = document.querySelector('a[data-pdf-share]');
      var bar = showWhatsAppSendBar(null);
      var copyStrong = bar.querySelector('strong');
      var copySmall = bar.querySelector('small');
      var sendBtn = bar.querySelector('.wa-share-send-btn');
      if (copyStrong) copyStrong.textContent = 'Preparing PDF…';
      if (copySmall) copySmall.textContent = fname;
      if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.textContent = 'Preparing…';
      }
      var params = new URLSearchParams(location.search || '');
      var shareOpts = {
        docId: (shareA && shareA.getAttribute('data-doc-id')) || params.get('id') || '',
        pdfUrl: (shareA && shareA.getAttribute('data-pdf-url')) || '',
      };
      buildPdfFile(fname, shareOpts).then(function (file) {
        showWhatsAppSendBar(file);
        if (copyStrong) copyStrong.textContent = 'PDF ready';
        if (sendBtn) {
          sendBtn.disabled = false;
          sendBtn.textContent = 'Send on WhatsApp';
        }
        if (shareA) {
          readyShareFiles.set(shareA, file);
          setShareItemState(shareA, 'ready', file.name);
          keepSharePopOpen(shareA);
        }
      }).catch(function () {
        if (copyStrong) copyStrong.textContent = 'Could not prepare PDF';
        if (sendBtn) {
          sendBtn.disabled = false;
          sendBtn.textContent = 'Open PDF page';
          sendBtn.onclick = function () {
            var sheet = (shareA && (shareA.getAttribute('data-sheet-url') || shareA.getAttribute('href'))) || '';
            if (!sheet && shareOpts.docId) {
              sheet = 'document_sheet.php?id=' + encodeURIComponent(shareOpts.docId) + '&autodownload=1';
            }
            if (sheet) window.location.href = sheet;
          };
        }
      }).finally(function () {
        busy = false;
        try {
          var clean = location.pathname + location.search.replace(/([?&])sharepdf=1&?/, '$1').replace(/[?&]$/, '');
          history.replaceState({}, '', clean || location.pathname);
        } catch (e) {}
      });
    }
    if (document.readyState === 'complete') setTimeout(runSharePrepare, 200);
    else window.addEventListener('load', function () { setTimeout(runSharePrepare, 200); });
  }
})();
