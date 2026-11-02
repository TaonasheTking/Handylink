document.addEventListener('DOMContentLoaded', function () {
  var chips = Array.prototype.slice.call(document.querySelectorAll('.category-chip'));
  var cards = Array.prototype.slice.call(document.querySelectorAll('.job-card-wrap'));
  var searchInput = document.getElementById('jobSearchInput');
  var clearBtn = document.getElementById('jobSearchClear');
  var emptyState = document.getElementById('jobsEmptyState');
  var grid = document.getElementById('jobsGrid');
  var countHeading = document.getElementById('jobsCountHeading');
  var countDetail = document.getElementById('jobsCountDetail');
  var activeCategory = 'All work';

  function applyFilters() {
    var term = (searchInput.value || '').trim().toLowerCase();
    var visible = 0;
    cards.forEach(function (card) {
      var matchesCategory = activeCategory === 'All work' || card.getAttribute('data-category') === activeCategory;
      var matchesSearch = term === '' || card.getAttribute('data-search').indexOf(term) !== -1;
      var show = matchesCategory && matchesSearch;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    countHeading.textContent = visible + (visible === 1 ? ' open job' : ' open jobs');
    countDetail.textContent = (activeCategory === 'All work' && term === '')
      ? 'Sorted by what is happening closest to you.'
      : 'Filtered results — reset to see everything again.';
    grid.style.display = visible === 0 ? 'none' : '';
    emptyState.classList.toggle('hidden', visible !== 0);
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      chips.forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      activeCategory = chip.getAttribute('data-category-filter');
      applyFilters();
    });
  });

  searchInput.addEventListener('input', applyFilters);
  clearBtn.addEventListener('click', function () { searchInput.value = ''; applyFilters(); });

  var resetBtn = document.getElementById('jobsResetBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      searchInput.value = '';
      activeCategory = 'All work';
      chips.forEach(function (c, i) { c.classList.toggle('active', i === 0); });
      applyFilters();
    });
  }
});
