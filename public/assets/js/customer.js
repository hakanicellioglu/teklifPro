(function(){
  const modalEl = document.getElementById('customerModal');
  if(!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('customerForm');
  const titleEl = modalEl.querySelector('.modal-title');
  const saveBtn = document.getElementById('saveCustomerBtn');

  function resetCustomerForm(){
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el=>el.textContent='');
    form.querySelector('#customer_id').value='';
    titleEl.textContent='Müşteri Ekle';
    saveBtn.textContent='Kaydet';
  }

  function fillCustomerForm(data){
    for(const key in data){
      if(form.elements[key]){
        form.elements[key].value = data[key] ?? '';
      }
    }
  }

  document.getElementById('addCustomerBtn')?.addEventListener('click', () => {
    resetCustomerForm();
    modal.show();
  });

  document.querySelectorAll('.editCustomerBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      resetCustomerForm();
      fetch(`customers_get.php?id=${id}`)
        .then(r => r.json())
        .then(d => {
          if(d.ok){
            fillCustomerForm(d.customer);
            titleEl.textContent='Müşteri Düzenle';
            modal.show();
          }else{
            window.showToast(d.message || 'Veri alınamadı','danger');
          }
        })
        .catch(()=>window.showToast('Sunucu hatası','danger'));
    });
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    const orig = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Kaydediliyor';
    const fd = new FormData(form);
    fetch('customers_save.php',{method:'POST',body:fd})
      .then(r=>r.json())
      .then(data=>{
        if(data.ok){
          fetch(`customers_get.php?id=${data.id}`)
            .then(r=>r.json())
            .then(resp=>{
              if(resp.ok){
                updateRow(resp.customer);
                modal.hide();
                window.showToast('İşlem başarıyla kaydedildi.');
              }else{
                location.reload();
              }
            })
            .catch(()=>location.reload());
        }else if(data.errors){
          Object.entries(data.errors).forEach(([field,msg])=>{
            const el = form.elements[field];
            if(el){
              el.classList.add('is-invalid');
              const fb = el.parentElement.querySelector('.invalid-feedback');
              if(fb) fb.textContent = msg;
            }
          });
          window.showToast('Lütfen formu kontrol edin.','danger');
        }else{
          window.showToast(data.message || 'Hata oluştu','danger');
        }
      })
      .catch(()=>window.showToast('Sunucu hatası','danger'))
      .finally(()=>{
        saveBtn.disabled=false;
        saveBtn.innerHTML=orig;
      });
  });

  function escapeHtml(str){
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function updateRow(c){
    const row = document.querySelector(`tr[data-id="${c.id}"]`);
    const name = `${c.first_name || ''} ${c.last_name || ''}`.trim();
    if(row){
      row.querySelector('.col-name').textContent = name;
      row.querySelector('.col-company').textContent = c.company_name || '';
      row.querySelector('.col-email').textContent = c.email || '';
      row.querySelector('.col-phone').textContent = c.phone || '';
    }else{
      const tbody = document.querySelector('table tbody');
      if(!tbody){ location.reload(); return; }
      const tr = document.createElement('tr');
      tr.className = 'text-center';
      tr.dataset.id = c.id;
      tr.innerHTML = `
        <td class="col-name">${escapeHtml(name)}</td>
        <td class="col-company">${escapeHtml(c.company_name || '')}</td>
        <td class="col-email">${escapeHtml(c.email || '')}</td>
        <td class="col-phone">${escapeHtml(c.phone || '')}</td>
        <td>${escapeHtml(c.registration_date || '')}</td>
        <td class="text-center">
          <button type="button" class="btn btn-sm btn-outline-secondary editCustomerBtn" data-id="${c.id}" title="Düzenle"><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline">
            <input type="hidden" name="delete_id" value="${c.id}">
            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Bu müşteri silinsin mi?" title="Sil"><i class="bi bi-trash"></i></button>
          </form>
        </td>`;
      tbody.prepend(tr);
      tr.querySelector('.editCustomerBtn').addEventListener('click', () => {
        resetCustomerForm();
        fetch(`customers_get.php?id=${c.id}`)
          .then(r=>r.json())
          .then(d=>{
            if(d.ok){
              fillCustomerForm(d.customer);
              titleEl.textContent='Müşteri Düzenle';
              modal.show();
            }else{
              window.showToast(d.message || 'Veri alınamadı','danger');
            }
          })
          .catch(()=>window.showToast('Sunucu hatası','danger'));
      });
      const delBtn = tr.querySelector('[data-confirm]');
      delBtn?.addEventListener('click', e => {
        if(!window.confirm(delBtn.getAttribute('data-confirm') || 'Emin misiniz?')){
          e.preventDefault();
        }
      });
    }
  }
})();
