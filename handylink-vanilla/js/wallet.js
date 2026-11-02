document.addEventListener('DOMContentLoaded', function () {
  var CURRENT_BALANCE = 16;
  var bundleButtons = Array.prototype.slice.call(document.querySelectorAll('.bundle-card'));
  var summaryBundle = document.getElementById('summaryBundle');
  var summaryPrice = document.getElementById('summaryPrice');
  var summaryNewBalance = document.getElementById('summaryNewBalance');
  var purchaseBtn = document.getElementById('purchaseBtn');
  var successState = document.getElementById('walletSuccessState');
  var successDetail = document.getElementById('walletSuccessDetail');

  var selected = { credits: 13, price: '$5.00' };

  function selectBundle(btn) {
    bundleButtons.forEach(function (b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    selected.credits = parseInt(btn.getAttribute('data-credits'), 10);
    selected.price = btn.getAttribute('data-price');
    updateSummary();
  }

  function updateSummary() {
    summaryBundle.textContent = selected.credits + ' credits';
    summaryPrice.textContent = selected.price;
    summaryNewBalance.textContent = (CURRENT_BALANCE + selected.credits) + ' credits';
  }

  bundleButtons.forEach(function (btn) {
    btn.addEventListener('click', function () { selectBundle(btn); });
  });

  purchaseBtn.addEventListener('click', function () {
    purchaseBtn.disabled = true;
    purchaseBtn.textContent = 'Waiting for EcoCash confirmation…';
    setTimeout(function () {
      successDetail.textContent = 'Added ' + selected.credits + ' credits — new balance ' + (CURRENT_BALANCE + selected.credits) + '.';
      successState.classList.add('show');
      purchaseBtn.textContent = 'Credits added ✓';
    }, 1000);
  });

  updateSummary();
});
