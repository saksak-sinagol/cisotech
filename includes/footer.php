    </div>
  </main>
</div>

<div id="formSubmitStatus" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

<script>

  (function(){
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var accountMenu = document.querySelector('.account-menu');
    var accountDropdown = document.getElementById('accountDropdown');
    var accountToggle = document.getElementById('accountDropdownToggle');
    var mobileNavigation = window.matchMedia('(max-width: 900px)');

    function syncSidebarState() {
      if (!sidebar || !sidebarToggle) return;
      var isOpen = mobileNavigation.matches && sidebar.classList.contains('open');
      sidebar.toggleAttribute('inert', mobileNavigation.matches && !isOpen);
      sidebarToggle.setAttribute('aria-expanded', String(isOpen));
      sidebarToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
      if (!mobileNavigation.matches) sidebar.classList.remove('open');
    }

    function closeAccountDropdown(returnFocus) {
      if (!accountMenu || !accountDropdown || !accountToggle) return;
      accountDropdown.classList.remove('open');
      accountToggle.setAttribute('aria-expanded', 'false');
      if (returnFocus) accountToggle.focus();
    }

    if (sidebar && sidebarToggle) {
      syncSidebarState();
      sidebarToggle.addEventListener('click', function(){
        if (!mobileNavigation.matches) return;
        sidebar.classList.toggle('open');
        syncSidebarState();
        if (sidebar.classList.contains('open')) {
          var firstLink = sidebar.querySelector('a[href]');
          if (firstLink) firstLink.focus();
        }
      });
      mobileNavigation.addEventListener('change', syncSidebarState);
    }

    if (accountMenu && accountDropdown && accountToggle) {
      accountToggle.addEventListener('click', function(){
        var isOpen = accountDropdown.classList.toggle('open');
        accountToggle.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
          var firstLink = accountDropdown.querySelector('a[href], button:not([disabled])');
          if (firstLink) firstLink.focus();
        }
      });

      document.addEventListener('click', function(event){
        if (!accountMenu.contains(event.target)) closeAccountDropdown(false);
      });
    }

    document.addEventListener('keydown', function(event){
      if (event.key !== 'Escape') return;
      var activeElement = document.activeElement;
      if (accountDropdown && accountDropdown.classList.contains('open')) {
        closeAccountDropdown(accountMenu && accountMenu.contains(activeElement));
      }
      if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        syncSidebarState();
        if (sidebarToggle) sidebarToggle.focus();
      }
    });
  })();

  function showToast(message, duration) {
    duration = duration || 3600;
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(function(){ toast.classList.add('show'); });
    setTimeout(function(){
      toast.classList.remove('show');
      setTimeout(function(){ toast.remove(); }, 300);
    }, duration);
  }

  document.addEventListener('submit', function(e){
    var form = e.target;
    var btn = form.querySelector('button.btn-primary[type="submit"], button.btn-primary:not([type])');
    if (!btn || btn.disabled) return;
    var loadingText = btn.getAttribute('data-loading-text') || 'Saving...';
    btn.dataset.originalHtml = btn.innerHTML;
    var submitStatus = document.getElementById('formSubmitStatus');
    if (submitStatus) submitStatus.textContent = loadingText;
    form.setAttribute('aria-busy', 'true');
    btn.disabled = true;
    btn.classList.add('is-loading');
    btn.setAttribute('aria-label', loadingText);
    var spinner = document.createElement('span');
    spinner.className = 'btn-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    var label = document.createElement('span');
    label.setAttribute('aria-hidden', 'true');
    label.textContent = loadingText;
    btn.replaceChildren(spinner, label);
  });
</script>
</body>
</html>
