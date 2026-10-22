(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.tfp-fd-filters').forEach(function(form){
        if(window.jQuery){
            window.jQuery(form).off('change.tfpDocs').on('change.tfpDocs','select',function(){
                form.submit();
            });
        }else{
            form.querySelectorAll('select').forEach(function(select){
                select.addEventListener('change',function(){form.submit();});
            });
        }
        var search=form.querySelector('input[name="tfp_fd_search"]');
        var timer=null;
        if(search){
            search.addEventListener('keydown',function(e){
                if(e.key==='Enter'){e.preventDefault();form.submit();}
            });
            search.addEventListener('search',function(){form.submit();});
            search.addEventListener('input',function(){
                clearTimeout(timer);
                timer=setTimeout(function(){form.submit();},500);
            });
        }
    });
});
})();