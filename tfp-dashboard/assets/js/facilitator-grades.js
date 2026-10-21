(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){
  var forms=document.querySelectorAll('.tfp-fg-filters');
  if(!forms.length)return;
  forms.forEach(function(form){
    form.querySelectorAll('select').forEach(function(select){
      select.addEventListener('change',function(){
        if(select.name==='tfp_fg_course'){
          var cohort=form.querySelector('[name="tfp_fg_cohort"]');
          if(cohort){cohort.value='';}
          var week=form.querySelector('[name="tfp_fg_week"]');
          if(week){week.value='0';}
        }
        form.submit();
      });
    });
    var search=form.querySelector('input[type="search"]');
    if(search){
      search.addEventListener('keydown',function(e){
        if(e.key==='Enter'){e.preventDefault();form.submit();}
      });
    }
  });
});
})();