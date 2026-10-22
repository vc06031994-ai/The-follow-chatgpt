(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){
  var forms=document.querySelectorAll('.tfp-qt-filters');
  forms.forEach(function(form){
    var selects=form.querySelectorAll('select');
    if(window.jQuery){
      window.jQuery(form).off('change.tfpQT').on('change.tfpQT','select',function(){
        form.submit();
      });
    }else{
      selects.forEach(function(select){select.addEventListener('change',function(){form.submit();});});
    }
    var search=form.querySelector('input[type="search"]');
    var timer;
    if(search){
      search.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();form.submit();}});
      search.addEventListener('search',function(){form.submit();});
      search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){form.submit();},500);});
    }
  });
  document.querySelectorAll('.tfp-qt-week-progress__track').forEach(function(track){
    var total=track.querySelectorAll('span').length;
    if(total) track.style.setProperty('--qt-weeks',total);
  });
});
})();