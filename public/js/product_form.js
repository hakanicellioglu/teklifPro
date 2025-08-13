(function(){
  function isKumanda(text){
    if(!text) return false;
    return text.toLocaleLowerCase('tr').includes('kumanda');
  }
  document.querySelectorAll('form').forEach(function(form){
    var category = form.querySelector('.category-select');
    var group = form.querySelector('.channel-count-group');
    var field = form.querySelector('.channel-count-field');
    if(!category || !group || !field) return;
    function sync(){
      var opt = category.options[category.selectedIndex];
      var label = opt ? opt.textContent : '';
      var show = isKumanda(label);
      group.classList.toggle('d-none', !show);
      field.disabled = !show;
      if(!show){
        field.value = '';
      }
    }
    category.addEventListener('change', sync);
    sync();
  });
})();
