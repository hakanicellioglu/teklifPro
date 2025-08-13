(function(){
  function isKumanda(text){
    if(!text) return false;
    return text.toLocaleLowerCase('tr').includes('kumanda');
  }
  document.querySelectorAll('form').forEach(function(form){
    var category = form.querySelector('.category-select');
    var channelGroup = form.querySelector('.channel-count-group');
    var channelField = form.querySelector('.channel-count-field');
    var unitGroup = form.querySelector('.unit-value-group');
    var unitField = form.querySelector('.unit-value-field');
    if(!category) return;
    function sync(){
      var opt = category.options[category.selectedIndex];
      var label = opt ? opt.textContent : '';
      var isK = isKumanda(label);
      if(channelGroup && channelField){
        channelGroup.classList.toggle('d-none', !isK);
        channelField.disabled = !isK;
        if(!isK){
          channelField.value = '';
        }
      }
      if(unitGroup && unitField){
        unitGroup.classList.toggle('d-none', isK);
        unitField.disabled = isK;
        if(isK){
          unitField.value = '';
        }
      }
    }
    category.addEventListener('change', sync);
    sync();
  });
})();
