(function () {
  var loading = null;

  function toast(msg, kind) {
    var el = document.createElement('p');
    el.className = 'flash ' + (kind === 'err' ? 'flash-err' : 'flash-ok');
    el.setAttribute('role', 'status');
    el.textContent = msg;
    el.style.position = 'fixed';
    el.style.left = '50%';
    el.style.bottom = '24px';
    el.style.transform = 'translateX(-50%)';
    el.style.zIndex = '200';
    el.style.maxWidth = 'min(420px, calc(100vw - 24px))';
    el.style.margin = '0';
    el.style.boxShadow = '0 10px 30px rgba(8,20,58,.18)';
    document.body.appendChild(el);
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 3200);
  }

  function setBusy(btn, on) {
    if (!btn) return;
    btn.disabled = !!on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    var label = btn.querySelector('[data-pdf-label]');
    if (label) {
      if (on) {
        if (!label.getAttribute('data-idle')) {
          label.setAttribute('data-idle', label.textContent || 'PDF');
        }
        label.textContent = 'Preparing…';
      } else {
        label.textContent = label.getAttribute('data-idle') || 'PDF';
      }
    }
  }

  function loadPdfLib() {
    if (window.VellisysPdf && typeof window.VellisysPdf.downloadDocumentPdf === 'function') {
      return Promise.resolve(window.VellisysPdf);
    }
    if (loading) return loading;
    var host = document.querySelector('script[data-pdf-bundle]');
    var src = (host && host.getAttribute('data-pdf-bundle'))
      || document.body.getAttribute('data-pdf-bundle')
      || '';
    if (!src) {
      return Promise.reject(new Error('PDF bundle URL missing.'));
    }
    loading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () {
        if (window.VellisysPdf && window.VellisysPdf.downloadDocumentPdf) {
          resolve(window.VellisysPdf);
        } else {
          reject(new Error('PDF module failed to load.'));
        }
      };
      s.onerror = function () {
        loading = null;
        reject(new Error('Could not load the PDF engine.'));
      };
      document.head.appendChild(s);
    });
    return loading;
  }

  async function fetchFullDoc(id) {
    var res = await fetch('document_api.php?id=' + encodeURIComponent(String(id)), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    var data = await res.json().catch(function () { return null; });
    if (!res.ok || !data || !data.ok || !data.doc) {
      throw new Error((data && data.error) || 'Could not load the full document.');
    }
    return data;
  }

  async function onDownloadPdf(btn) {
    var id = parseInt(btn.getAttribute('data-doc-id') || '0', 10);
    if (!id) {
      toast('Document not found.', 'err');
      return;
    }
    setBusy(btn, true);
    try {
      var payload = await fetchFullDoc(id);
      var doc = payload.doc;
      if (!doc.canExport) {
        toast('Void documents cannot be downloaded.', 'err');
        return;
      }
      var lib = await loadPdfLib();
      await lib.downloadDocumentPdf(payload.docType || doc.kind || 'invoice', doc);
      toast('Downloaded VELLISYS-' + (doc.number || 'document') + '.pdf', 'ok');
    } catch (e) {
      toast((e && e.message) || 'PDF failed', 'err');
    } finally {
      setBusy(btn, false);
    }
  }

  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('[data-pdf-download]') : null;
    if (!t) return;
    e.preventDefault();
    onDownloadPdf(t);
  });
})();
