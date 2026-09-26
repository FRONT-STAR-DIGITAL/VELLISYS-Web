/**
 * Instant PDF: prefer the on-page sheet (exact desk design) with no "Preparing…" UI.
 * Otherwise the PDF link navigates to document_download.php (cached file or server PDF).
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

  function saveSheet(sheet, filename) {
    return loadHtml2Pdf().then(function (html2pdf) {
      var thermal = !!(document.body && document.body.classList.contains('print-thermal'));
      var nodes = document.querySelectorAll('.invoice-sheet');
      for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.transform = 'none';
        nodes[i].style.zoom = '1';
        nodes[i].style.marginLeft = '0';
      }
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
        },
        jsPDF: {
          unit: 'mm',
          format: thermal ? [80, Math.max(120, Math.round((sheet.scrollHeight || 1123) * 0.2646))] : 'a4',
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
    var sheet = document.querySelector('.invoice-sheet');
    if (!sheet) {
      // Let the browser hit document_download.php immediately (no Preparing UI).
      return;
    }
    e.preventDefault();
    var name = a.getAttribute('data-pdf-name') || a.getAttribute('download') || 'document.pdf';
    saveSheet(sheet, name).catch(function () {
      // If capture fails, fall through to the server/autodownload URL.
      window.location.href = a.href;
    });
  }, true);

  // On a document detail page, warm the server cache so the next list download is a file hit.
  if (document.querySelector('.invoice-sheet')) {
    var link = document.querySelector('a[data-pdf-download][data-doc-id]');
    var id = link ? parseInt(link.getAttribute('data-doc-id') || '0', 10) : 0;
    if (id) {
      if (window.requestIdleCallback) {
        window.requestIdleCallback(function () { warmServer(id); }, { timeout: 2500 });
      } else {
        setTimeout(function () { warmServer(id); }, 800);
      }
    }
    // Prefetch html2pdf so the first click on this page is immediate.
    if (window.requestIdleCallback) {
      window.requestIdleCallback(function () { loadHtml2Pdf().catch(function () {}); }, { timeout: 3000 });
    } else {
      setTimeout(function () { loadHtml2Pdf().catch(function () {}); }, 1000);
    }
  }
})();
