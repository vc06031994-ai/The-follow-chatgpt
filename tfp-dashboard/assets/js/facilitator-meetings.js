(function(){
'use strict';
function config(){var n=document.querySelector('[data-tfp-meetings-config]');if(!n)return {};try{return JSON.parse(n.textContent||'{}')}catch(e){return {}}}
function esc(v){var e=document.createElement('div');e.textContent=v==null?'':String(v);return e.innerHTML}
function openModal(n){if(!n)return;n.classList.add('is-open');n.setAttribute('aria-hidden','false');document.body.classList.add('tfp-meetings-modal-open')}
function closeModal(){document.querySelectorAll('.tfp-meetings-modal.is-open').forEach(function(n){n.classList.remove('is-open');n.setAttribute('aria-hidden','true')});document.body.classList.remove('tfp-meetings-modal-open')}
function card(label,value){return '<div class="tfp-meetings-detail-card"><span>'+esc(label)+'</span><strong>'+esc(value||'—')+'</strong></div>'}
function showMeeting(data){var m=document.querySelector('[data-tfp-meeting-modal]');if(!m)return;m.querySelector('[data-tfp-modal-title]').textContent='Meeting Details';m.querySelector('[data-tfp-modal-body]').innerHTML='<div class="tfp-meetings-detail-grid">'+card('Meeting Date',data.date_label)+card('Meeting Time',data.time_label)+card('Cohort',data.cohort_name)+card('Week / Module','Week '+data.week_number+': '+data.module)+card('Homework Avg.',data.homework_label)+card('Test Avg.',data.test_label)+card('Attendance',data.attendance_label)+card('Facilitator',data.facilitator)+'</div>'+(data.description?'<div class="tfp-meetings-detail-card"><span>Meeting Description</span><strong>'+esc(data.description)+'</strong></div>':'')+(data.notes?'<div class="tfp-meetings-detail-card"><span>Facilitator Notes</span><strong>'+esc(data.notes)+'</strong></div>':'');m.querySelector('[data-tfp-modal-actions]').innerHTML=data.meeting_url?'<a href="'+esc(data.meeting_url)+'" target="_blank" rel="noopener">Join Meeting</a>':'';openModal(m)}
function updateRequest(id,status){var c=config(),d=new URLSearchParams();d.append('action','tfp_facilitator_meeting_update_request');d.append('nonce',c.nonce||'');d.append('request_id',id);d.append('status',status);fetch(c.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:d.toString()}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error(r.data&&r.data.message?r.data.message:'Unable to update request.');window.location.reload()}).catch(function(e){window.alert(e.message||'Unable to update request.')})}
function showRequest(data){var m=document.querySelector('[data-tfp-meeting-modal]');if(!m)return;m.querySelector('[data-tfp-modal-title]').textContent=data.type_label||'Meeting Request';m.querySelector('[data-tfp-modal-body]').innerHTML='<div class="tfp-meetings-detail-grid">'+card('Requested By',data.requester)+card('Cohort',data.cohort)+card('Affected Date',data.date)+card('Status',data.status_label)+'</div><div class="tfp-meetings-detail-card"><span>Reason</span><strong>'+esc(data.reason)+'</strong></div>';var a=m.querySelector('[data-tfp-modal-actions]');a.innerHTML='<button type="button" data-action="acknowledged" class="is-secondary">Acknowledge</button><button type="button" data-action="approved">Approve</button><button type="button" data-action="denied" class="is-danger">Deny</button>';a.querySelectorAll('[data-action]').forEach(function(b){b.addEventListener('click',function(){updateRequest(data.id,b.getAttribute('data-action'))})});openModal(m)}
function openSkipRequest(data){
var m=document.querySelector('[data-tfp-create-modal]');
if(!m)return;
var type=document.querySelector('[data-tfp-request-type]');
var cohort=document.querySelector('[data-tfp-request-cohort]');
var date=document.querySelector('[data-tfp-request-date]');
var reason=document.querySelector('[data-tfp-request-form] textarea[name=reason]');
if(type)type.value='cohort_skip';
if(cohort)cohort.value=String(data.cohort_id||'');
if(date)date.value=data.request_date||'';
if(reason)reason.value='';
refreshStudents();
openModal(m);
}

function refreshStudents(){var c=config(),co=document.querySelector('[data-tfp-request-cohort]'),s=document.querySelector('[data-tfp-request-student]'),w=document.querySelector('[data-tfp-student-wrap]'),t=document.querySelector('[data-tfp-request-type]');if(!co||!s||!w)return;var absence=t&&t.value==='student_absence';w.hidden=!absence;s.innerHTML='<option value="">Select Student</option>';var list=c.studentsByCohort&&c.studentsByCohort[co.value]?c.studentsByCohort[co.value]:[];list.forEach(function(item){var o=document.createElement('option');o.value=item.id;o.textContent=item.name;s.appendChild(o)})}

function initMeetingsFilters(){
var form=document.querySelector('.tfp-meetings-filter-form');
if(!form)return;
var pageInput=form.querySelector('input[name="tfp_meeting_page"]');
var searchInput=form.querySelector('input[name="tfp_meeting_search"]');
var timer=null;
function submitFilters(){
if(pageInput)pageInput.value='1';
form.submit();
}
if(window.jQuery){
window.jQuery(form).find('select').each(function(){
var $select=window.jQuery(this);
$select.off('.tfpMeetings');
$select.on('change.tfpMeetings select2:select.tfpMeetings select2:unselect.tfpMeetings',function(){
submitFilters();
});
});
}else{
form.querySelectorAll('select').forEach(function(select){select.addEventListener('change',submitFilters)});
}
if(searchInput){
var scheduleSearch=function(){
window.clearTimeout(timer);
timer=window.setTimeout(submitFilters,350);
};
searchInput.addEventListener('input',scheduleSearch);
searchInput.addEventListener('keyup',scheduleSearch);
searchInput.addEventListener('change',submitFilters);
searchInput.addEventListener('search',submitFilters);
searchInput.addEventListener('blur',function(){
if(searchInput.value.trim()!=='')submitFilters();
});
searchInput.addEventListener('keydown',function(e){
if(e.key==='Enter'){
e.preventDefault();
submitFilters();
}
});
}
}

function initSessionAttendance(){
var root=document.querySelector('[data-tfp-session-attendance]');
if(!root)return;
var search=root.querySelector('[data-tfp-attendance-search]');
var rows=Array.from(root.querySelectorAll('[data-tfp-attendance-row]'));
var empty=root.querySelector('[data-tfp-attendance-empty]');
var save=root.querySelector('[data-tfp-attendance-save]');
var feedback=root.querySelector('[data-tfp-attendance-feedback]');
var cfg=config();
if(search){
search.addEventListener('input',function(){
var q=String(search.value||'').trim().toLowerCase(),visible=0;
rows.forEach(function(row){
var match=!q||String(row.getAttribute('data-student-name')||'').indexOf(q)!==-1;
row.hidden=!match;
if(match)visible++;
});
if(empty)empty.hidden=visible!==0;
});
}
root.addEventListener('click',function(event){
var button=event.target.closest('[data-attendance-status]');
if(!button||!root.contains(button))return;
var row=button.closest('[data-tfp-attendance-row]');
if(!row)return;
var status=button.getAttribute('data-attendance-status');
row.setAttribute('data-status',status);
row.querySelectorAll('[data-attendance-status]').forEach(function(item){
var active=item===button;
item.classList.toggle('is-active',active);
item.classList.toggle('is-present',active&&status==='P');
item.classList.toggle('is-absent',active&&status==='UA');
item.setAttribute('aria-pressed',active?'true':'false');
});
});
if(save){
save.addEventListener('click',function(){
var records=[],missing=[];
rows.forEach(function(row){
var status=String(row.getAttribute('data-status')||'');
if(!['P','EA','UA'].includes(status)){
missing.push(row.querySelector('.tfp-meeting-attendance-student')?.textContent.trim()||'');
return;
}
records.push({
student_id:parseInt(row.getAttribute('data-student-id'),10)||0,
status:status,
note:row.querySelector('[data-attendance-note]')?.value||''
});
});
if(missing.length){
if(feedback)feedback.textContent='Please mark Present or Absent for every disciple before saving.';
return;
}
if(!records.length){
if(feedback)feedback.textContent='There are no attendance records to save.';
return;
}
save.disabled=true;
save.setAttribute('aria-busy','true');
if(feedback)feedback.textContent='Saving attendance…';
var data=new FormData();
data.append('action',cfg.attendanceAction||'tfp_facilitator_meeting_save_attendance');
data.append('nonce',cfg.attendanceNonce||'');
data.append('cohort_id',String(cfg.cohortId||''));
data.append('lesson_id',String(cfg.lessonId||''));
data.append('records',JSON.stringify(records));
fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',body:data})
.then(function(response){return response.json();})
.then(function(response){
if(!response||!response.success)throw new Error(response&&response.data&&response.data.message?response.data.message:'Unable to save attendance.');
if(feedback)feedback.textContent=response.data.message||'Attendance saved successfully.';
})
.catch(function(error){
if(feedback)feedback.textContent=error.message||'Unable to save attendance.';
})
.finally(function(){
save.disabled=false;
save.removeAttribute('aria-busy');
});
});
}
}

document.addEventListener('DOMContentLoaded',function(){
initMeetingsFilters();
initSessionAttendance();
var weekSelect=document.querySelector('[data-tfp-meeting-week]');
if(weekSelect){weekSelect.addEventListener('change',function(){if(this.form)this.form.submit();});}
var exportButton=document.querySelector('[data-tfp-export-meeting-report]');
if(exportButton){
exportButton.addEventListener('click',function(){
var table=document.querySelector('[data-tfp-meeting-report-table]');
if(!table){window.alert('No report data is available to export.');return;}
var rows=Array.from(table.querySelectorAll('tr')).map(function(row){
return Array.from(row.querySelectorAll('th,td')).map(function(cell){
var value=(cell.innerText||cell.textContent||'').replace(/\s+/g,' ').trim().replace(/"/g,'""');
return '"'+value+'"';
}).join(',');
});
var csv='\uFEFF'+rows.join('\r\n');
var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
var url=window.URL.createObjectURL(blob);
var link=document.createElement('a');
var title=document.querySelector('.tfp-meeting-cohort-header__main h1');
var week=weekSelect?weekSelect.value:'all';
var slug=(title?title.textContent:'cohort').trim().replace(/[^a-z0-9]+/gi,'-').replace(/^-|-$/g,'').toLowerCase();
link.href=url;link.download=(slug||'cohort')+'-week-'+week+'-report.csv';
document.body.appendChild(link);link.click();link.remove();window.URL.revokeObjectURL(url);
});
}
document.querySelectorAll('[data-tfp-meeting]').forEach(function(b){b.addEventListener('click',function(){try{showMeeting(JSON.parse(b.getAttribute('data-tfp-meeting')))}catch(e){}})});document.querySelectorAll('[data-tfp-skip]').forEach(function(b){b.addEventListener('click',function(){try{openSkipRequest(JSON.parse(b.getAttribute('data-tfp-skip')))}catch(e){}})});document.querySelectorAll('[data-tfp-request]').forEach(function(b){b.addEventListener('click',function(){try{showRequest(JSON.parse(b.getAttribute('data-tfp-request')))}catch(e){}})});document.querySelectorAll('[data-tfp-modal-close]').forEach(function(b){b.addEventListener('click',closeModal)});document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal()});var create=document.querySelector('[data-tfp-create-modal]'),open=document.querySelector('[data-tfp-open-request-create]'),form=document.querySelector('[data-tfp-request-form]'),type=document.querySelector('[data-tfp-request-type]'),cohort=document.querySelector('[data-tfp-request-cohort]');if(open)open.addEventListener('click',function(){openModal(create)});if(type)type.addEventListener('change',refreshStudents);if(cohort)cohort.addEventListener('change',refreshStudents);if(form)form.addEventListener('submit',function(e){e.preventDefault();var c=config(),d=new FormData(form);d.append('action','tfp_facilitator_meeting_create_request');d.append('nonce',c.nonce||'');fetch(c.ajaxUrl,{method:'POST',body:d}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error(r.data&&r.data.message?r.data.message:'Unable to create request.');window.location.reload()}).catch(function(err){window.alert(err.message||'Unable to create request.')})});});
})();

document.addEventListener('DOMContentLoaded',function(){
    var attendanceRoot=document.querySelector('[data-tfp-course-attendance]');
    if(!attendanceRoot)return;
    attendanceRoot.querySelectorAll('[data-tfp-attendance-choice]').forEach(function(button){
        button.addEventListener('click',function(){
            var group=button.closest('.tfp-attendance-choice');
            if(!group)return;
            var value=button.getAttribute('data-tfp-attendance-choice')||'';
            var hidden=group.querySelector('[data-tfp-attendance-value]');
            if(hidden)hidden.value=value;
            group.querySelectorAll('[data-tfp-attendance-choice]').forEach(function(option){
                var active=option===button;
                option.classList.toggle('is-selected',active);
                option.setAttribute('aria-pressed',active?'true':'false');
            });
        });
    });
    var search=attendanceRoot.querySelector('[data-tfp-attendance-search]');
    if(search){
        search.addEventListener('input',function(){
            var term=(search.value||'').trim().toLowerCase();
            attendanceRoot.querySelectorAll('[data-tfp-attendance-row]').forEach(function(row){
                var name=(row.getAttribute('data-student-name')||'').toLowerCase();
                row.hidden=term!==''&&name.indexOf(term)===-1;
            });
        });
    }
});
