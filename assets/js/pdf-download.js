(function () {
  var html2pdfLoading = null;

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

  function triggerBlobDownload(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'document.pdf';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
  }

  function parseFilename(contentDisposition, fallback) {
    if (!contentDisposition) return fallback;
    var star = contentDisposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
    if (star && star[1]) {
      try { return decodeURIComponent(star[1].trim().replace(/["']/g, '')); } catch (e) {}
    }
    var plain = contentDisposition.match(/filename\s*=\s*"([^"]+)"/i)
      || contentDisposition.match(/filename\s*=\s*([^;]+)/i);
    if (plain && plain[1]) {
      return plain[1].trim().replace(/^["']|["']$/g, '');
    }
    return fallback;
  }

  function loadHtml2Pdf() {
    if (typeof window.html2pdf === 'function') {
      return Promise.resolve(window.html2pdf);
    }
    if (html2pdfLoading) return html2pdfLoading;
    var host = document.querySelector('script[data-html2pdf]');
    var src = (host && host.getAttribute('data-html2pdf')) || '';
    if (!src) {
      return Promise.reject(new Error('PDF capture library missing.'));
    }
    html2pdfLoading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () {
        if (typeof window.html2pdf === 'function') resolve(window.html2pdf);
        else reject(new Error('PDF capture failed to load.'));
      };
      s.onerror = function () {
        html2pdfLoading = null;
        reject(new Error('Could not load PDF capture.'));
      };
      document.head.appendChild(s);
    });
    return html2pdfLoading;
  }

  function waitImages(doc) {
    var imgs = Array.prototype.slice.call((doc && doc.images) || []);
    var pending = imgs.filter(function (img) { return !img.complete; });
    if (!pending.length) return Promise.resolve();
    return new Promise(function (resolve) {
      var left = pending.length;
      var done = function () {
        left -= 1;
        if (left <= 0) resolve();
      };
      setTimeout(resolve, 4000);
      pending.forEach(function (img) {
        img.addEventListener('load', done);
        img.addEventListener('error', done);
      });
    });
  }

  /** Prefer server PDF built from the real HTML sheet (Chrome). */
  async function downloadViaServer(id, fallbackName) {
    var res = await fetch('document_download.php?id=' + encodeURIComponent(String(id)), {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/pdf, application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    var ct = (res.headers.get('Content-Type') || '').toLowerCase();
    if (res.ok && ct.indexOf('application/pdf') !== -1) {
      var blob = await res.blob();
      var name = parseFilename(res.headers.get('Content-Disposition'), fallbackName);
      triggerBlobDownload(blob, name);
      return { ok: true, filename: name };
    }
    if (ct.indexOf('application/json') !== -1) {
      var data = await res.json().catch(function () { return null; });
      return {
        ok: false,
        sheetUrl: data && data.sheetUrl ? data.sheetUrl : ('document_sheet.php?id=' + id),
        filename: (data && data.filename) || fallbackName,
      };
    }
    return {
      ok: false,
      sheetUrl: 'document_sheet.php?id=' + id,
      filename: fallbackName,
    };
  }

  /**
   * Hostinger-safe fallback: render the system's real sheet HTML and capture it.
   * Uses the same designs as Print — not a separate React layout.
   */
  async function downloadViaSheet(sheetUrl, filename) {
    var html2pdf = await loadHtml2Pdf();
    var iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.setAttribute('title', 'PDF');
    iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:794px;height:1123px;border:0;opacity:0;pointer-events:none';
    document.body.appendChild(iframe);

    try {
      await new Promise(function (resolve, reject) {
        var settled = false;
        var timer = setTimeout(function () {
          if (!settled) {
            settled = true;
            reject(new Error('Sheet took too long to load.'));
          }
        }, 20000);
        iframe.onload = function () {
          if (settled) return;
          settled = true;
          clearTimeout(timer);
          resolve();
        };
        iframe.onerror = function () {
          if (settled) return;
          settled = true;
          clearTimeout(timer);
          reject(new Error('Could not open the document sheet.'));
        };
        iframe.src = sheetUrl;
      });

      var idoc = iframe.contentDocument || iframe.contentWindow.document;
      await waitImages(idoc);
      // Let layout / sheet-fit settle
      await new Promise(function (r) { setTimeout(r, 120); });

      var sheet = idoc.querySelector('.invoice-sheet') || idoc.querySelector('.sheet-stage') || idoc.body;
      if (!sheet) throw new Error('Document sheet not found.');

      // Undo any screen fit transforms so capture is print-sized
      var nodes = idoc.querySelectorAll('.invoice-sheet');
      for (var i = 0; i < nodes.length; i++) {
        nodes[i].style.transform = 'none';
        nodes[i].style.zoom = '1';
        nodes[i].style.marginLeft = '0';
      }

      var isThermal = !!(idoc.body && idoc.body.classList.contains('print-thermal'));
      var opt = {
        margin: 0,
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
          scale: 2,
          useCORS: true,
          allowTaint: true,
          backgroundColor: '#ffffff',
          logging: false,
          windowWidth: isThermal ? 302 : 794,
        },
        jsPDF: {
          unit: 'mm',
          format: isThermal ? [80, Math.max(120, Math.round((sheet.scrollHeight || 1123) * 0.2646))] : 'a4',
          orientation: 'portrait',
        },
        pagebreak: { mode: ['css', 'legacy'] },
      };

      await html2pdf().set(opt).from(sheet).save();
      return filename;
    } finally {
      if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
    }
  }

  async function onDownloadPdf(btn) {
    var id = parseInt(btn.getAttribute('data-doc-id') || '0', 10);
    if (!id) {
      toast('Document not found.', 'err');
      return;
    }
    var fallbackName = btn.getAttribute('data-pdf-name')
      || ((btn.getAttribute('data-doc-number') || 'document').replace(/[^\w.-]+/g, '-') + '.pdf');

    setBusy(btn, true);
    try {
      var server = await downloadViaServer(id, fallbackName);
      if (server.ok) {
        toast('Downloaded ' + server.filename, 'ok');
        return;
      }
      var name = await downloadViaSheet(server.sheetUrl || ('document_sheet.php?id=' + id), server.filename || fallbackName);
      toast('Downloaded ' + name, 'ok');
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
