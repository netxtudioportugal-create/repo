// Progressive enhancement only: every flow works without JavaScript.
(function () {
  // Onboarding: enable the start button once the notice is acknowledged.
  var ack = document.getElementById('ack');
  var ackBtn = document.getElementById('ackBtn');
  if (ack && ackBtn) {
    var sync = function () { ackBtn.disabled = !ack.checked; };
    ack.addEventListener('change', sync);
    sync();
  }

  // Timed set: display the server deadline. The server enforces it either way.
  var timer = document.getElementById('timer');
  if (timer && timer.dataset.endsAt) {
    var ends = Date.parse(timer.dataset.endsAt);
    var form = document.getElementById('finishForm');
    var tick = function () {
      var ms = Math.max(0, ends - Date.now());
      var m = Math.floor(ms / 60000), s = Math.floor((ms % 60000) / 1000);
      timer.textContent = m + ':' + String(s).padStart(2, '0');
      if (ms <= 0) {
        clearInterval(id);
        if (form) form.submit();
      }
    };
    var id = setInterval(tick, 1000);
    tick();
  }

  // Language menu: close on Escape.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = document.querySelector('details.langwrap[open]');
    if (open) { open.removeAttribute('open'); open.querySelector('summary').focus(); }
  });
})();
