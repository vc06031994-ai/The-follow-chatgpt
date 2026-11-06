(function(){
'use strict';
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.tfp-cohort-week-filter select').forEach(function(select){
        select.addEventListener('change',function(){
            var form=select.closest('form');
            if(form) form.submit();
        });
    });
});
})();