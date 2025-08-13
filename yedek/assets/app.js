document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('themeToggle');
  const stored = localStorage.getItem('theme');
  if (stored === 'dark') document.body.classList.add('dark');
  toggle?.addEventListener('click', () => {
    document.body.classList.toggle('dark');
    localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
  });

  const confirmModalEl = document.getElementById('confirmModal');
  const confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
  let confirmAction = null;
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      const msg = el.getAttribute('data-confirm');
      if (confirmModalEl) {
        confirmModalEl.querySelector('.modal-body').textContent = msg || '';
        confirmModal.show();
        confirmAction = () => {
          const href = el.getAttribute('href');
          if (href) {
            window.location = href;
          } else {
            el.closest('form')?.submit();
          }
        };
      } else if (msg && !window.confirm(msg)) {
        return;
      } else {
        el.closest('form')?.submit();
      }
    });
  });
  document.getElementById('confirmYes')?.addEventListener('click', () => {
    confirmModal?.hide();
    confirmAction && confirmAction();
  });

  window.showToast = function (msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    container.appendChild(toast);
    new bootstrap.Toast(toast).show();
  };

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('button[type=submit]').forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Kaydediliyor';
      });
    }, { once: true });
  });
});
