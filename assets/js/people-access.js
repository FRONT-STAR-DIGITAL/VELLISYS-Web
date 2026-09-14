(function () {
  var map = window.vellisysFeatureDefaults || { books: [], sales: [] };
  function apply(root, access) {
    var allowed = map[access] || map.books || [];
    root.querySelectorAll('input[type="checkbox"][name="features[]"]').forEach(function (box) {
      box.checked = allowed.indexOf(box.value) !== -1;
    });
  }
  document.querySelectorAll('[data-access-features="new"]').forEach(function (root) {
    var sel = root.querySelector('[data-access-select]');
    if (!sel) return;
    sel.addEventListener('change', function () {
      apply(root, sel.value === 'sales' ? 'sales' : 'books');
    });
  });
})();
