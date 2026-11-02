document.addEventListener('DOMContentLoaded', function () {
  var timer = document.querySelector('[data-deadline]');
  if (!timer) return;
  var deadline = Number(timer.getAttribute('data-deadline')) * 1000;
  var output = timer.querySelector('.countdown');
  function update() {
    var seconds = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
    var hours = Math.floor(seconds / 3600);
    var minutes = Math.floor((seconds % 3600) / 60);
    var remainder = seconds % 60;
    output.textContent = [hours, minutes, remainder].map(function (n) { return String(n).padStart(2, '0'); }).join(':');
    if (seconds === 0) output.textContent = 'Expired — refresh to reopen offers';
  }
  update();
  window.setInterval(update, 1000);
});
