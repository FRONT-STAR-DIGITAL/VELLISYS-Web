(function () {
  var stage = document.querySelector('[data-lp-stage]');
  if (!stage) return;
  var paths = stage.querySelectorAll('.lp-flow');
  paths.forEach(function (path, i) {
    var len = 0;
    try { len = path.getTotalLength(); } catch (e) { return; }
    var dot = document.createElement('span');
    dot.className = 'lp-arrow-dot';
    stage.appendChild(dot);
    var speed = 4200 + i * 900;
    var offset = i * 0.28;
    function tick(now) {
      var t = ((now / speed) + offset) % 1;
      var p = path.getPointAtLength(t * len);
      var box = path.ownerSVGElement.getBoundingClientRect();
      var stageBox = stage.getBoundingClientRect();
      var sx = box.width / 640;
      var sy = box.height / 420;
      dot.style.left = (box.left - stageBox.left + p.x * sx) + 'px';
      dot.style.top = (box.top - stageBox.top + p.y * sy) + 'px';
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
})();
