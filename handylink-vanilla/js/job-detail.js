document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('acceptJobBtn');
  var formView = document.getElementById('acceptFormView');
  var successView = document.getElementById('acceptSuccessView');
  if (!btn) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Unlocking…';
    setTimeout(function () {
      formView.classList.add('hidden');
      successView.classList.remove('hidden');
    }, 700);
  });
});
