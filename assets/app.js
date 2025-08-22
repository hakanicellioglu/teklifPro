document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('themeToggle');
  const stored = localStorage.getItem('theme');
  if (stored === 'dark') document.body.classList.add('dark');
  toggle?.addEventListener('click', () => {
    document.body.classList.toggle('dark');
    localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
  });

  document.querySelectorAll('[data-bs-toggle="tooltip"], .edit-guillotine[title]').forEach(el => {
    new bootstrap.Tooltip(el);
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
      const buttons = form.querySelectorAll('button[type=submit]');
      buttons.forEach(btn => {
        if (!btn.dataset.origHtml) {
          btn.dataset.origHtml = btn.innerHTML;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Kaydediliyor';
      });

      // Re-enable and restore buttons if the page does not navigate
      setTimeout(() => {
        buttons.forEach(btn => {
          if (btn.dataset.origHtml) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.origHtml;
            delete btn.dataset.origHtml;
          }
        });
      }, 5000);
    });
  });

  document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = btn.getAttribute('data-url');
      if (!url) return;
      if (navigator.share) {
        try {
          await navigator.share({ url });
        } catch (e) {
          /* ignore */
        }
      } else {
        try {
          await navigator.clipboard.writeText(url);
          window.showToast && window.showToast('Bağlantı panoya kopyalandı', 'info');
        } catch (e) {
          window.showToast && window.showToast('Bağlantı kopyalanamadı', 'danger');
        }
      }
    });
  });
});
