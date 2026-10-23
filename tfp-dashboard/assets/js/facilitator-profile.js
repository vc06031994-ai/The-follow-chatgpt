(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded',function(){
        if(typeof tfpProfileSettings==='undefined') return;

        document.querySelectorAll('[data-tfp-section]').forEach(function(section){
            var editBtn=section.querySelector('[data-tfp-edit-toggle]');
            var cancelBtn=section.querySelector('[data-tfp-edit-cancel]');
            var viewEl=section.querySelector('.tfp-fac-profile-view');
            var formEl=section.querySelector('.tfp-fac-profile-edit-form');

            if(!editBtn||!viewEl||!formEl) return;

            editBtn.addEventListener('click',function(){
                viewEl.hidden=true;
                formEl.hidden=false;
                editBtn.hidden=true;
            });

            if(cancelBtn){
                cancelBtn.addEventListener('click',function(){
                    formEl.hidden=true;
                    viewEl.hidden=false;
                    editBtn.hidden=false;
                });
            }
        });

        document.querySelectorAll('[data-tfp-profile-form]').forEach(function(form){
            var statusEl=form.querySelector('[data-tfp-profile-status]');
            var section=form.closest('[data-tfp-section]');
            var viewEl=section?section.querySelector('.tfp-fac-profile-view'):null;
            var editBtn=section?section.querySelector('[data-tfp-edit-toggle]'):null;

            form.addEventListener('submit',function(e){
                e.preventDefault();

                var submitBtn=form.querySelector('button[type="submit"]');
                var body=new URLSearchParams(new FormData(form));
                body.append('action','tfp_profile_update');
                body.append('tfp_profile_nonce',tfpProfileSettings.nonce);

                if(submitBtn) submitBtn.disabled=true;
                if(statusEl) statusEl.textContent='';

                fetch(tfpProfileSettings.ajaxUrl,{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:body.toString()
                })
                .then(function(res){return res.json();})
                .then(function(res){
                    if(submitBtn) submitBtn.disabled=false;

                    if(statusEl){
                        statusEl.textContent=res.message||(res.success?'Saved.':'Something went wrong.');
                        statusEl.setAttribute('data-state',res.success?'success':'error');
                    }

                    if(!res.success) return;

                    if(res.values&&viewEl){
                        Object.keys(res.values).forEach(function(key){
                            var target=viewEl.querySelector('[data-field="'+key+'"]');
                            if(!target) return;

                            var value=res.values[key];

                            if(key==='preferred_contact'||key==='notification_settings'){
                                var selected=Array.isArray(value)?value:[];
                                target.querySelectorAll('[data-option]').forEach(function(item){
                                    var option=item.getAttribute('data-option');
                                    var checked=selected.indexOf(option)!==-1;
                                    var box=item.querySelector('.tfp-fac-profile-check__box');

                                    if(!box) return;

                                    box.classList.toggle('is-checked',checked);
                                    box.textContent=checked?'✓':'';
                                });

                                /* Keep the edit-mode controls in sync as well. */
                                var editForm=section?section.querySelector('.tfp-fac-profile-edit-form'):null;
                                if(editForm){
                                    editForm.querySelectorAll('input[name="'+(key==='preferred_contact'?'preferred_contact[]':'notification_settings[]')+'"]').forEach(function(input){
                                        input.checked=selected.indexOf(input.value)!==-1;
                                    });
                                }
                                return;
                            }

                            target.textContent=value||'—';
                        });
                    }

                    if(section&&form.getAttribute('data-section')==='personal'){
                        var headerName=document.querySelector('.tfp-fac-profile-identity__info h2');
                        if(headerName&&res.values&&res.values.full_name){
                            headerName.textContent=res.values.full_name;
                        }
                    }

                    if(section&&form.getAttribute('data-section')==='connection'){
                        var headerMeta=document.querySelector('.tfp-fac-profile-identity__meta');
                        if(headerMeta&&res.values){
                            var discordTarget=document.querySelector('[data-field="discord_username"]');
                            if(discordTarget){
                                var discordText=res.values.discord_username||'';
                                var discordMeta=headerMeta.querySelector('[data-fac-discord]');
                                if(discordText){
                                    if(!discordMeta){
                                        discordMeta=document.createElement('span');
                                        discordMeta.setAttribute('data-fac-discord','');
                                        headerMeta.insertBefore(discordMeta,headerMeta.firstChild);
                                    }
                                    discordMeta.textContent=''+discordText;
                                }else if(discordMeta){
                                    discordMeta.remove();
                                }
                            }
                        }
                    }

                    setTimeout(function(){
                        form.hidden=true;
                        viewEl.hidden=false;
                        if(editBtn) editBtn.hidden=false;
                    },500);
                })
                .catch(function(){
                    if(submitBtn) submitBtn.disabled=false;
                    if(statusEl) statusEl.textContent='Could not reach the server. Please try again.';
                });
            });
        });

        var avatarInput=document.querySelector('[data-tfp-avatar-input]');
        var avatarPreview=document.querySelector('[data-tfp-avatar-preview]');

        if(avatarInput){
            avatarInput.addEventListener('change',function(){
                if(!avatarInput.files||!avatarInput.files[0]) return;

                var body=new FormData();
                body.append('action','tfp_profile_upload_avatar');
                body.append('tfp_profile_nonce',tfpProfileSettings.nonce);
                body.append('avatar',avatarInput.files[0]);

                fetch(tfpProfileSettings.ajaxUrl,{method:'POST',body:body})
                .then(function(res){return res.json();})
                .then(function(res){
                    if(!res.success){
                        window.alert(res.message||'Could not upload photo.');
                        return;
                    }
                    if(avatarPreview&&res.avatar_url){
                        avatarPreview.innerHTML='<img src="'+res.avatar_url+'" alt="">';
                    }
                })
                .catch(function(){
                    window.alert('Could not reach the server. Please try again.');
                });
            });
        }
    });
})();
