(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpChatSettings === 'undefined') return;

        var items          = Array.prototype.slice.call(document.querySelectorAll('[data-tfp-ticket-item]'));
        var searchInput    = document.querySelector('[data-tfp-ticket-search]');
        var tabsWrap       = document.querySelector('[data-tfp-ticket-tabs]');
        var emptyPanel     = document.querySelector('[data-tfp-chat-empty]');
        var threadPanel    = document.querySelector('[data-tfp-chat-thread]');
        var titleEl        = document.querySelector('[data-tfp-chat-title]');
        var subtitleEl     = document.querySelector('[data-tfp-chat-subtitle]');
        var avatarEl       = document.querySelector('[data-tfp-chat-avatar]');
        var statusEl       = document.querySelector('[data-tfp-chat-status]');
        var messagesEl     = document.querySelector('[data-tfp-chat-messages]');
        var replyForm      = document.querySelector('[data-tfp-chat-reply]');
        var replyInput     = document.querySelector('[data-tfp-chat-input]');
        var resolveBtn     = document.querySelector('[data-tfp-mark-resolved]');
        var newTicketBtns  = Array.prototype.slice.call(document.querySelectorAll('[data-tfp-new-ticket]'));
        var newTicketModal = document.querySelector('[data-tfp-new-ticket-modal]');
        var newTicketForm  = document.querySelector('[data-tfp-new-ticket-form]');
        var modalCloseBtns = document.querySelectorAll('[data-tfp-modal-close]');

        var state = {
            activeTicketId: null,
            lastMessageId: 0,
            pollTimer: null,
            activeFilter: 'all',
            searchTerm: '',
        };

        function ajax(action, data) {
            var body = new URLSearchParams(Object.assign({
                action: action,
                tfp_chat_nonce: tfpChatSettings.nonce,
            }, data));

            return fetch(tfpChatSettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (res) {
                return res.json();
            });
        }

        function renderMessage(msg) {
            var wrap = document.createElement('div');
            wrap.className = 'tfp-dash-msg ' + (msg.is_mine ? 'tfp-dash-msg--mine' : 'tfp-dash-msg--theirs');
            wrap.dataset.id = msg.id;

            var avatar = document.createElement('span');
            avatar.className = 'tfp-dash-msg__avatar';

            if (msg.avatar_url) {
                var image = document.createElement('img');
                image.src = msg.avatar_url;
                image.alt = '';
                image.loading = 'lazy';
                avatar.appendChild(image);
            }

            var content = document.createElement('div');
            content.className = 'tfp-dash-msg__content';

            var bubble = document.createElement('div');
            bubble.className = 'tfp-dash-msg__bubble';
            bubble.textContent = msg.message;

            var time = document.createElement('div');
            time.className = 'tfp-dash-msg__time';
            time.textContent = (msg.is_mine ? '' : msg.sender_name + ' · ') + msg.created_at;

            content.appendChild(bubble);
            content.appendChild(time);
            wrap.appendChild(avatar);
            wrap.appendChild(content);

            return wrap;
        }

        function scrollMessagesToBottom() {
            if (messagesEl) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        }

        function setStatusBadge(status) {
            if (!statusEl) return;

            var labels = {
                open: 'Open',
                pending: 'Pending Reply',
                resolved: 'Resolved'
            };

            statusEl.textContent = labels[status] || status;
            statusEl.dataset.status = status || '';

            if (resolveBtn) {
                resolveBtn.disabled = status === 'resolved';
                resolveBtn.textContent = status === 'resolved' ? 'Resolved' : 'Mark Resolved';
            }
        }

        function stopPolling() {
            if (state.pollTimer) {
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
        }

        function applyFilters() {
            items.forEach(function (item) {
                var haystack = item.getAttribute('data-search') || '';
                var status = item.getAttribute('data-status') || '';
                var category = item.getAttribute('data-category') || '';

                var matchesSearch = !state.searchTerm || haystack.indexOf(state.searchTerm) !== -1;
                var matchesFilter = state.activeFilter === 'all' ||
                    status === state.activeFilter ||
                    category === state.activeFilter;

                item.hidden = !(matchesSearch && matchesFilter);
            });
        }

        function updateTicketStatusInList(ticketId, status) {
            var item = items.filter(function (el) {
                return el.getAttribute('data-ticket-id') === String(ticketId);
            })[0];

            if (!item) return;

            item.setAttribute('data-status', status);

            var dot = item.querySelector('.tfp-dash-ticketlist__dot');
            if (dot) {
                dot.className = 'tfp-dash-ticketlist__dot tfp-dash-ticketlist__dot--' + status;
            }

            applyFilters();
        }

        function poll() {
            if (!state.activeTicketId) return;

            ajax('tfp_chat_get_messages', {
                ticket_id: state.activeTicketId,
                after_id: state.lastMessageId,
            }).then(function (res) {
                if (!res.success) return;

                var hasMessages = false;

                (res.messages || []).forEach(function (msg) {
                    if (messagesEl.querySelector('[data-id="' + msg.id + '"]')) return;
                    messagesEl.appendChild(renderMessage(msg));
                    state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                    hasMessages = true;
                });

                if (hasMessages) {
                    scrollMessagesToBottom();
                }

                setStatusBadge(res.status);
                updateTicketStatusInList(state.activeTicketId, res.status);
            }).catch(function () {
                // Keep the current conversation usable if a temporary poll fails.
            });
        }

        function openTicket(item) {
            if (!item) return;

            var ticketId = item.getAttribute('data-ticket-id');
            var name = item.querySelector('.tfp-dash-ticketlist__name');
            var category = item.querySelector('.tfp-dash-ticketlist__category');
            var avatar = item.querySelector('.tfp-dash-ticketlist__avatar img');

            stopPolling();

            state.activeTicketId = ticketId;
            state.lastMessageId = 0;

            items.forEach(function (el) {
                el.classList.toggle('is-active', el === item);
            });

            if (emptyPanel) emptyPanel.hidden = true;
            if (threadPanel) threadPanel.hidden = false;
            if (titleEl) titleEl.textContent = name ? name.textContent.trim() : '';
            if (subtitleEl) subtitleEl.textContent = category ? category.textContent.trim() : '';

            if (avatarEl) {
                avatarEl.innerHTML = '';
                if (avatar) {
                    var avatarClone = avatar.cloneNode(true);
                    avatarClone.removeAttribute('loading');
                    avatarEl.appendChild(avatarClone);
                }
            }

            messagesEl.innerHTML = '';

            ajax('tfp_chat_get_messages', {
                ticket_id: ticketId,
                after_id: 0
            }).then(function (res) {
                if (!res.success) return;

                (res.messages || []).forEach(function (msg) {
                    messagesEl.appendChild(renderMessage(msg));
                    state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                });

                scrollMessagesToBottom();
                setStatusBadge(res.status);
                updateTicketStatusInList(ticketId, res.status);

                state.pollTimer = setInterval(poll, tfpChatSettings.pollInterval || 4000);
            }).catch(function () {
                if (emptyPanel) emptyPanel.hidden = false;
                if (threadPanel) threadPanel.hidden = true;
            });
        }

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                openTicket(item);
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.searchTerm = searchInput.value.trim().toLowerCase();
                applyFilters();
            });
        }

        if (tabsWrap) {
            tabsWrap.addEventListener('click', function (event) {
                var btn = event.target.closest('button[data-filter]');
                if (!btn) return;

                tabsWrap.querySelectorAll('button[data-filter]').forEach(function (button) {
                    button.classList.remove('is-active');
                });

                btn.classList.add('is-active');
                state.activeFilter = btn.getAttribute('data-filter') || 'all';
                applyFilters();
            });
        }

        if (replyForm) {
            replyForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var message = replyInput.value.trim();

                if (!message || !state.activeTicketId || replyInput.disabled) return;

                replyInput.disabled = true;

                ajax('tfp_chat_send_message', {
                    ticket_id: state.activeTicketId,
                    message: message,
                }).then(function (res) {
                    if (!res.success) {
                        window.alert(res.message || 'Could not send your reply.');
                        return;
                    }

                    replyInput.value = '';
                    messagesEl.appendChild(renderMessage(res.message));
                    state.lastMessageId = Math.max(state.lastMessageId, res.message.id);
                    scrollMessagesToBottom();

                    setStatusBadge(res.status);
                    updateTicketStatusInList(state.activeTicketId, res.status);
                }).catch(function () {
                    window.alert('Could not send your reply. Please try again.');
                }).finally(function () {
                    replyInput.disabled = false;
                    replyInput.focus();
                });
            });
        }

        if (resolveBtn) {
            resolveBtn.addEventListener('click', function () {
                if (!state.activeTicketId || resolveBtn.disabled) return;

                resolveBtn.disabled = true;

                ajax('tfp_chat_mark_resolved', {
                    ticket_id: state.activeTicketId
                }).then(function (res) {
                    if (!res.success) {
                        window.alert(res.message || 'Could not update this communication.');
                        resolveBtn.disabled = false;
                        return;
                    }

                    setStatusBadge('resolved');
                    updateTicketStatusInList(state.activeTicketId, 'resolved');
                }).catch(function () {
                    resolveBtn.disabled = false;
                    window.alert('Could not update this communication. Please try again.');
                });
            });
        }

        newTicketBtns.forEach(function (button) {
            button.addEventListener('click', function () {
                if (newTicketModal) newTicketModal.hidden = false;
            });
        });

        modalCloseBtns.forEach(function (button) {
            button.addEventListener('click', function () {
                if (newTicketModal) newTicketModal.hidden = true;
            });
        });

        if (newTicketForm) {
            newTicketForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var submitButton = newTicketForm.querySelector('[type="submit"]');
                var formData = new FormData(newTicketForm);

                if (submitButton) submitButton.disabled = true;

                ajax('tfp_chat_create_ticket', {
                    subject: formData.get('subject'),
                    category: formData.get('category'),
                    message: formData.get('message'),
                }).then(function (res) {
                    if (!res.success) {
                        window.alert(res.message || 'Could not create the conversation.');
                        return;
                    }

                    window.location.reload();
                }).catch(function () {
                    window.alert('Could not create the conversation. Please try again.');
                }).finally(function () {
                    if (submitButton) submitButton.disabled = false;
                });
            });
        }

        // Match the dashboard inbox experience: when conversations exist,
        // open the newest one immediately instead of leaving the right panel empty.
        if (items.length) {
            openTicket(items[0]);
        }
    });
})();
