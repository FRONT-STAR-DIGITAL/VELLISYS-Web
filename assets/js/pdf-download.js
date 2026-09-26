/**
 * Instant PDF of the exact branded desk sheet.
 *
 * 1) Fetch document_download.php as a blob (real PDF when Chrome/cache available).
 * 2) Otherwise open the unfitted sheet autodownload page and capture with html2pdf.
 *
 * Never use the HTML download= attribute on redirecting URLs (saves HTML as a fake PDF).
 * Never capture the fitted on-page preview (sheet-fit scales it and drops layout).
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
    return document.querySelector('.sheet-stage') || document.querySelector('.invoice-sheet');
  }

  /** Resolve CSS variables / color-mix into concrete colours html2canvas can paint. */
  function flattenPaintStyles(root) {
    if (!root) return;
    var nodes = [root].concat(Array.prototype.slice.call(root.querySelectorAll('*')));
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (!el || el.nodeType !== 1) continue;
      var cs = window.getComputedStyle(el);
      if (!cs) continue;
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
      if (cs.outlineColor) el.style.outlineColor = cs.outlineColor;
      if (cs.boxShadow && cs.boxShadow !== 'none') el.style.boxShadow = cs.boxShadow;
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        el.style.backgroundImage = cs.backgroundImage;
        el.style.backgroundSize = cs.backgroundSize;
        el.style.backgroundPosition = cs.backgroundPosition;
        el.style.backgroundRepeat = cs.backgroundRepeat;
      }
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
      var nodes = document.querySelectorAll('.invoice-sheet');
      for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.transform = 'none';
        nodes[i].style.zoom = '1';
        nodes[i].style.marginLeft = '0';
        nodes[i].style.boxShadow = 'none';
      }
      flattenPaintStyles(sheet);
      return loadHtml2Pdf().then(function (html2pdf) {
        var thermal = !!(document.body && document.body.classList.contains('print-thermal'));
        var h = Math.max(sheet.scrollHeight || 0, sheet.offsetHeight || 0, 1123);
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
            windowWidth: Math.max(sheet.scrollWidth || 0, 794),
            onclone: function (clonedDoc) {
              var cloned = clonedDoc.querySelector('.sheet-stage') || clonedDoc.querySelector('.invoice-sheet');
              if (!cloned) return;
              var list = [cloned].concat(Array.prototype.slice.call(cloned.querySelectorAll('*')));
              for (var j = 0; j < list.length; j++) {
                var el = list[j];
                if (!el || el.nodeType !== 1) continue;
                el.style.setProperty('-webkit-print-color-adjust', 'exact', 'important');
                el.style.setProperty('print-color-adjust', 'exact', 'important');
              }
            },
          },
          jsPDF: {
            unit: 'mm',
            format: thermal ? [80, Math.max(120, Math.round(h * 0.2646))] : 'a4',
            orientation: 'portrait',
          },
          pagebreak: { mode: ['css', 'legacy'] },
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
      return res.blob();
    }).then(function (blob) {
      if (!blob || blob.size < 800 || (blob.type && blob.type.indexOf('pdf') === -1 && blob.type !== 'application/octet-stream')) {
        // Some servers omit type; sniff magic in next step via arrayBuffer if needed.
      }
      return blob.arrayBuffer().then(function (buf) {
        var head = new Uint8Array(buf.slice(0, 4));
        var isPdf = head[0] === 0x25 && head[1] === 0x50 && head[2] === 0x44 && head[3] === 0x46; // %PDF
        if (!isPdf) throw new Error('not-pdf');
        triggerBlobDownload(new Blob([buf], { type: 'application/pdf' }), filename);
      });
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

    // Already on the unfitted sheet page — capture here.
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

  // Autodownload: capture the unfitted sheet as soon as assets are ready.
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
