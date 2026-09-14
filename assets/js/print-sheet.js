(function () {
  function markPages(root) {
    var sheet = (root || document).querySelector('.invoice-sheet');
    if (!sheet || sheet.classList.contains('sheet-thermal')) return;
    sheet.style.minHeight = '0';
    var pagePx = (297 * 96) / 25.4;
    if (sheet.offsetHeight > pagePx + 8) {
      sheet.classList.add('is-multipage');
      (root || document).documentElement.classList.add('is-multipage');
    } else {
      sheet.classList.remove('is-multipage');
      (root || document).documentElement.classList.remove('is-multipage');
    }
  }
  function stripPrintUrls(doc) {
    doc.title = '';
    var nodes = doc.querySelectorAll('a[href]');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].removeAttribute('href');
    }
  }
  function waitImages(doc, fn) {
    var imgs = Array.prototype.slice.call(doc.images || []);
    var left = imgs.filter(function (img) { return !img.complete; }).length;
    if (!left) { fn(); return; }
    function done() { left -= 1; if (left <= 0) fn(); }
    imgs.forEach(function (img) {
      if (img.complete) return;
      img.addEventListener('load', done);
      img.addEventListener('error', done);
    });
  }
  function printFrame(html) {
    var frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    document.body.appendChild(frame);
    var w = frame.contentWindow;
    var d = w.document;
    d.open();
    d.write(html);
    d.close();
    stripPrintUrls(d);
    d.title = '';
    function go() {
      if (typeof w.fitDocumentSheets === 'function') {
        w.fitDocumentSheets();
      }
      markPages(d);
      w.focus();
      w.print();
      window.setTimeout(function () {
        if (frame.parentNode) frame.parentNode.removeChild(frame);
      }, 1200);
    }
    waitImages(d, function () {
      window.setTimeout(go, 280);
    });
  }
  function start() {
    document.title = '';
    stripPrintUrls(document);
    var clone = document.documentElement.cloneNode(true);
    clone.querySelectorAll('script').forEach(function (el) { el.remove(); });
    var html = '<!DOCTYPE html>' + clone.outerHTML;
    html = html.replace(/<title>[\s\S]*?<\/title>/i, '<title></title>');
    try {
      printFrame(html);
    } catch (err) {
      window.print();
    }
  }
  function boot() {
    if (typeof window.fitDocumentSheets === 'function') {
      window.fitDocumentSheets();
    }
    markPages(document);
    waitImages(document, start);
  }
  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }
})();
