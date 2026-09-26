/**
 * Instant PDF: the PDF control is a normal link to document_download.php
 * (cached file or server print of the unfitted branded sheet).
 *
 * Never capture the on-page preview — sheet-fit.js scales it for the viewport
 * and that would corrupt colours, layout, and multi-page structure.
 * Exact capture runs only on document_sheet / share autodownload pages
 * (data-pdf-exact), which render full-size unfitted sheets.
 */
(function () {
  var html2pdfLoading = null;
  var warmed = {};

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
    // Prefer the stage (all pages / booklet sections) over a single scaled article.
    return document.querySelector('.sheet-stage') || document.querySelector('.invoice-sheet');
  }

  function saveExactSheet(filename) {
    var sheet = exactSheetRoot();
    if (!sheet) return Promise.reject(new Error('sheet'));
    return loadHtml2Pdf().then(function (html2pdf) {
      var thermal = !!(document.body && document.body.classList.contains('print-thermal'));
      var nodes = document.querySelectorAll('.invoice-sheet');
      for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.transform = 'none';
        nodes[i].style.zoom = '1';
        nodes[i].style.marginLeft = '0';
        nodes[i].style.boxShadow = 'none';
      }
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
          windowWidth: sheet.scrollWidth || 794,
        },
        jsPDF: {
          unit: 'mm',
          format: thermal ? [80, Math.max(120, Math.round(h * 0.2646))] : 'a4',
          orientation: 'portrait',
        },
        pagebreak: { mode: ['css', 'legacy'] },
      }).from(sheet).save();
    });
  }

  /** Warm server PDF cache in the background (no UI). */
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
    // Exact autodownload pages may capture locally; everywhere else navigate instantly.
    var exact = document.body && document.body.getAttribute('data-pdf-exact') === '1';
    if (!exact) {
      return;
    }
    e.preventDefault();
    var name = a.getAttribute('data-pdf-name') || a.getAttribute('download') || 'document.pdf';
    saveExactSheet(name).catch(function () {
      window.location.href = a.href;
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
    if (document.readyState === 'complete') setTimeout(runAuto, 40);
    else window.addEventListener('load', function () { setTimeout(runAuto, 40); });
  }

  // On a document detail page, warm the server cache so the next list download is a file hit.
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
