document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('providerSearchInput');
  var cards = Array.prototype.slice.call(document.querySelectorAll('.provider-card'));
  var grid = document.getElementById('providersGrid');
  var empty = document.getElementById('providersEmptyState');
  var heading = document.getElementById('providersCountHeading');
  var resetBtn = document.getElementById('providersResetBtn');

  function applyFilter() {
    var term = (input.value || '').trim().toLowerCase();
    var visible = 0;
    cards.forEach(function (card) {
      var show = term === '' || card.getAttribute('data-search').indexOf(term) !== -1;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    heading.textContent = visible + (visible === 1 ? ' helper nearby' : ' helpers nearby');
    grid.style.display = visible === 0 ? 'none' : '';
    empty.classList.toggle('hidden', visible !== 0);
  }

  input.addEventListener('input', applyFilter);
  if (resetBtn) {
    resetBtn.addEventListener('click', function () { input.value = ''; applyFilter(); });
  }
});
