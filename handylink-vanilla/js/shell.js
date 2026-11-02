document.addEventListener('DOMContentLoaded', function () {
  renderIcons();

  var menuBtn = document.getElementById('mobileMenuBtn');
  var mobileNav = document.getElementById('mobileNav');
  if (menuBtn && mobileNav) {
    menuBtn.addEventListener('click', function () {
      var nowHidden = mobileNav.classList.toggle('hidden');
      menuBtn.innerHTML = nowHidden ? '<i data-icon="menu" data-size="19"></i>' : '<i data-icon="x" data-size="19"></i>';
      renderIcons(menuBtn);
    });
  }

  var profileBtn = document.getElementById('profileMenuBtn');
  var profilePanel = document.getElementById('profilePanel');
  if (profileBtn && profilePanel) {
    profileBtn.addEventListener('click', function () {
      profilePanel.classList.toggle('hidden');
    });
  }

  var notifBtn = document.getElementById('notificationBtn');
  var notifPanel = document.getElementById('notificationPanel');
  if (notifBtn && notifPanel) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      notifPanel.classList.toggle('hidden');
    });
    document.addEventListener('click', function (e) {
      if (!notifPanel.contains(e.target) && e.target !== notifBtn) {
        notifPanel.classList.add('hidden');
      }
    });
  }
});
