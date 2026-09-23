// =====================================================================
// Computer Nations — shared front-end behaviour
// Toasts, confirm modals, dropdown menus, sidebar toggle, button states.
// No frameworks — plain DOM APIs only.
// =====================================================================

/* ---------------------------------------------------------------------
   Toasts
   --------------------------------------------------------------------- */
function ensureToastStack() {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  return stack;
}

function showToast(type, message) {
  const stack = ensureToastStack();
  const toast = document.createElement('div');
  toast.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
  toast.innerHTML =
    '<span>' + message.replace(/</g, '&lt;') + '</span>' +
    '<button type="button" class="toast-close" aria-label="Dismiss">&times;</button>';
  toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
  stack.appendChild(toast);
  setTimeout(() => {
    toast.style.transition = 'opacity 0.3s ease';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 4500);
}

/* ---------------------------------------------------------------------
   Confirm modal (replaces native confirm() for a more professional feel)
   Usage: add class="js-confirm" plus data-confirm-title / data-confirm-message
   to any <form> whose submission should be confirmed first.
   --------------------------------------------------------------------- */
function ensureConfirmModal() {
  let overlay = document.getElementById('confirmModal');
  if (overlay) return overlay;

  overlay = document.createElement('div');
  overlay.id = 'confirmModal';
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal-box">
      <div class="modal-icon">${iconSvg('warning')}</div>
      <h3 id="confirmModalTitle">Are you sure?</h3>
      <p id="confirmModalMessage">This action cannot be undone.</p>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="confirmModalCancel">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeConfirmModal(); });
  overlay.querySelector('#confirmModalCancel').addEventListener('click', closeConfirmModal);
  return overlay;
}

function iconSvg(name) {
  const paths = {
    warning: '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v4"/><path d="M12 17.5v.01"/>'
  };
  return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (paths[name] || '') + '</svg>';
}

function closeConfirmModal() {
  const overlay = document.getElementById('confirmModal');
  if (overlay) overlay.classList.remove('open');
}

function bindConfirmForms() {
  document.querySelectorAll('form.js-confirm').forEach((form) => {
    if (form.dataset.confirmBound) return;
    form.dataset.confirmBound = '1';
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1') return; // already confirmed, let it through
      e.preventDefault();
      const overlay = ensureConfirmModal();
      overlay.querySelector('#confirmModalTitle').textContent = form.dataset.confirmTitle || 'Are you sure?';
      overlay.querySelector('#confirmModalMessage').textContent = form.dataset.confirmMessage || 'This action cannot be undone.';
      overlay.classList.add('open');

      const okBtn = overlay.querySelector('#confirmModalOk');
      const onOk = function () {
        form.dataset.confirmed = '1';
        overlay.classList.remove('open');
        okBtn.removeEventListener('click', onOk);
        setButtonLoading(form.querySelector('button[type=submit]'));
        form.submit();
      };
      okBtn.addEventListener('click', onOk);
    });
  });
}

/* ---------------------------------------------------------------------
   Button loading state (prevents double-submit on real navigations)
   --------------------------------------------------------------------- */
function setButtonLoading(btn) {
  if (!btn) return;
  if (!btn.querySelector('.btn-label')) {
    btn.innerHTML = '<span class="spinner"></span><span class="btn-label">' + btn.innerHTML + '</span>';
  }
  btn.classList.add('loading');
  btn.disabled = true;
}

function bindLoadingForms() {
  document.querySelectorAll('form:not(.js-confirm)').forEach((form) => {
    if (form.dataset.loadingBound) return;
    form.dataset.loadingBound = '1';
    form.addEventListener('submit', function () {
      if (form.checkValidity && !form.checkValidity()) return;
      setButtonLoading(form.querySelector('button[type=submit]'));
    });
  });
}

/* ---------------------------------------------------------------------
   Dropdown menus (notifications bell, user menu)
   --------------------------------------------------------------------- */
function bindDropdowns() {
  document.querySelectorAll('.dropdown').forEach((dd) => {
    const trigger = dd.querySelector('.dropdown-trigger');
    if (!trigger || trigger.dataset.bound) return;
    trigger.dataset.bound = '1';
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      const wasOpen = dd.classList.contains('open');
      document.querySelectorAll('.dropdown.open').forEach((o) => o.classList.remove('open'));
      if (!wasOpen) dd.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown.open').forEach((o) => o.classList.remove('open'));
  });
}

/* ---------------------------------------------------------------------
   Flash message (rendered by PHP as a hidden data node) -> toast
   --------------------------------------------------------------------- */
function showFlashAsToast() {
  const node = document.getElementById('flashData');
  if (node) {
    showToast(node.dataset.type, node.dataset.message);
    node.remove();
  }
}

/* ---------------------------------------------------------------------
   Init
   --------------------------------------------------------------------- */
/* ---------------------------------------------------------------------
   Show/hide password toggle — auto-applied to every password field
   on the page, so no per-page markup is needed.
   --------------------------------------------------------------------- */
function enablePasswordToggles() {
  const EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_OFF = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.6 21.6 0 0 1 5.06-5.94M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a21.6 21.6 0 0 1-2.65 3.79"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.dataset.toggleEnabled) return;
    input.dataset.toggleEnabled = '1';

    const wrap = document.createElement('div');
    wrap.className = 'password-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle-btn';
    btn.setAttribute('aria-label', 'Show password');
    btn.innerHTML = EYE;
    wrap.appendChild(btn);

    btn.addEventListener('click', function () {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.innerHTML = showing ? EYE : EYE_OFF;
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  });
}

/* ---------------------------------------------------------------------
   Dark mode toggle — theme itself is applied by the inline head script
   (to avoid a flash of the wrong theme); this just wires up the button
   and keeps its icon in sync.
   --------------------------------------------------------------------- */
function enableThemeToggle() {
  const btn = document.getElementById('themeToggleBtn');
  const iconSpan = document.getElementById('themeToggleIcon');
  if (!btn || !iconSpan) return;

  const SUN = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v3"/><path d="M12 19v3"/><path d="M4.2 4.2l2.1 2.1"/><path d="M17.7 17.7l2.1 2.1"/><path d="M2 12h3"/><path d="M19 12h3"/><path d="M4.2 19.8l2.1-2.1"/><path d="M17.7 6.3l2.1-2.1"/></svg>';
  const MOON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/></svg>';

  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }
  function syncIcon() {
    iconSpan.innerHTML = currentTheme() === 'dark' ? SUN : MOON;
    btn.setAttribute('aria-label', currentTheme() === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
  }

  syncIcon();
  btn.addEventListener('click', function () {
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('cn-theme', next); } catch (e) {}
    syncIcon();
  });
}

document.addEventListener('DOMContentLoaded', function () {
  // Mobile sidebar toggle
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('open');
      }
    });
  }

  showFlashAsToast();
  bindConfirmForms();
  bindLoadingForms();
  bindDropdowns();
  enablePasswordToggles();
  enableThemeToggle();

  // Legacy static .alert banners (used on the login page, before the shell exists)
  document.querySelectorAll('.alert').forEach(function (alertBox) {
    setTimeout(function () {
      alertBox.style.transition = 'opacity 0.4s ease';
      alertBox.style.opacity = '0';
      setTimeout(function () { alertBox.remove(); }, 400);
    }, 5000);
  });
});
