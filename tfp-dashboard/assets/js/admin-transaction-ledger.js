(function(){'use strict';
document.addEventListener('DOMContentLoaded',function(){
 const all=document.querySelector('[data-ledger-select-all]');
 if(all){all.addEventListener('change',function(){document.querySelectorAll('.tfp-ledger-row-check').forEach(function(box){box.checked=all.checked;});});}
 const refundOpen=document.querySelector('[data-ledger-refund-open]');
 const refundForm=document.querySelector('[data-ledger-refund-form]');
 if(refundOpen&&refundForm){refundOpen.addEventListener('click',function(){refundForm.hidden=!refundForm.hidden;refundOpen.setAttribute('aria-expanded',String(!refundForm.hidden));if(!refundForm.hidden){const field=refundForm.querySelector('input[name="refund_amount"]');if(field)field.focus();}});}
 const print=document.querySelector('[data-ledger-print]');
 if(print){print.addEventListener('click',function(){window.print();});}
 const table=document.querySelector('.tfp-ledger-table-scroll');
 if(table){table.setAttribute('tabindex','0');table.setAttribute('aria-label','Scrollable transaction table');}
});
})();