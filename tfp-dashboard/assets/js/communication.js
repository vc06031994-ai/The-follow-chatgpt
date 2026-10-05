(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var items = Array.prototype.slice.call(document.querySelectorAll('[data-tfp-ticket-item]'));
        var searchInput = document.querySelector('[data-tfp-ticket-search]');
        var tabsWrap = document.querySelector('[data-tfp-ticket-tabs]');
        var emptyPanel = document.querySelector('[data-tfp-chat-empty]');
        var threadPanel = document.querySelector('[data-tfp-chat-thread]');
        var titleEl = document.querySelector('[data-tfp-chat-title]');
        var subtitleEl = document.querySelector('[data-tfp-chat-subtitle]');
        var tagTextEl = document.querySelector('[data-tfp-chat-tag]');
        var tagDotEl = document.querySelector('[data-tfp-chat-dot]');
        var statusEl = document.querySelector('[data-tfp-chat-status]');
        var messagesEl = document.querySelector('[data-tfp-chat-messages]');
        var replyForm = document.querySelector('[data-tfp-chat-reply]');
        var replyInput = document.querySelector('[data-tfp-chat-input]');
        var resolveBtn = document.querySelector('[data-tfp-mark-resolved]');
        var newTicketBtns = Array.prototype.slice.call(document.querySelectorAll('[data-tfp-new-ticket]'));
        var newTicketModal = document.querySelector('[data-tfp-new-ticket-modal]');
        var newTicketForm = document.querySelector('[data-tfp-new-ticket-form]');
        var modalCloseBtns = document.querySelectorAll('[data-tfp-modal-close]');

        var demoDataEl = document.getElementById('tfpDemoTicketsJSON');
        var demoTickets = {};
        if (demoDataEl) {
            try {
                demoTickets = JSON.parse(demoDataEl.textContent);
            } catch (e) {
                console.error('Error parsing demo tickets JSON', e);
            }
        }

        var state = {
            activeTicketId: null,
            lastMessageId: 0,
            pollTimer: null,
            activeFilter: 'all',
            searchTerm: '',
        };

        function ajax(action, data) {
            if (typeof tfpChatSettings === 'undefined') {
                return Promise.reject(new Error('Settings missing'));
            }

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

        /**
         * Render chat message bubble matching Figma media_1790601569672.png
         * - Student message (theirs): Avatar on Left, Teal Bubble, Left Timestamp
         * - Support message (mine): Avatar on Right, Light Gray Bubble, Right Timestamp
         */
        function renderMessage(msg) {
            var wrap = document.createElement('div');
            wrap.className = 'tfp-dash-msg ' + (msg.is_mine ? 'tfp-dash-msg--mine' : 'tfp-dash-msg--theirs');
            if (msg.id) wrap.dataset.id = msg.id;

            var avatar = document.createElement('span');
            avatar.className = 'tfp-dash-msg__avatar';

            if (msg.avatar_url) {
                var image = document.createElement('img');
                image.src = msg.avatar_url;
                image.alt = msg.sender_name || '';
                image.loading = 'lazy';
                avatar.appendChild(image);
            }

            var body = document.createElement('div');
            body.className = 'tfp-dash-msg__body';

            var bubble = document.createElement('div');
            bubble.className = 'tfp-dash-msg__bubble';
            bubble.textContent = msg.message;

            var time = document.createElement('div');
            time.className = 'tfp-dash-msg__time';
            time.textContent = msg.created_at || '';

            body.appendChild(bubble);
            body.appendChild(time);

            if (msg.is_mine) {
                wrap.appendChild(body);
                wrap.appendChild(avatar);
            } else {
                wrap.appendChild(avatar);
                wrap.appendChild(body);
            }

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

            statusEl.textContent = labels[status] || (status.charAt(0).toUpperCase() + status.slice(1));
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

                // communication.css uses !important on ticket items, so an inline
                // display value cannot hide them reliably. Toggle a dedicated class instead.
                item.classList.toggle('tfp-ticket-item--filtered', !(matchesSearch && matchesFilter));
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
                var category = item.getAttribute('data-category') || '';
            dot.className = 'tfp-dash-ticketlist__dot tfp-dash-ticketlist__dot--' + category;
            }

            applyFilters();
        }

        function poll() {
            if (!state.activeTicketId || String(state.activeTicketId).indexOf('demo-') === 0) return;

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
                // Keep current conversation usable on temporary network poll fail
            });
        }

        function openTicket(item) {
            if (!item) return;

            var ticketId = item.getAttribute('data-ticket-id');
            var name = item.querySelector('.tfp-dash-ticketlist__name');

            stopPolling();

            state.activeTicketId = ticketId;
            state.lastMessageId = 0;

            items.forEach(function (el) {
                el.classList.toggle('is-active', el === item);
            });

            if (emptyPanel) emptyPanel.style.display = 'none';
            if (threadPanel) threadPanel.style.display = '';

            // Handle Demo Ticket (Figma Mode)
            if (String(ticketId).indexOf('demo-') === 0 && demoTickets[ticketId]) {
                var dData = demoTickets[ticketId];

                if (titleEl) titleEl.textContent = dData.title || (name ? name.textContent.trim() : '');
                if (subtitleEl) subtitleEl.textContent = dData.meta || '';
                if (tagTextEl) tagTextEl.textContent = dData.tag_label || dData.category_label || '';

                if (tagDotEl) {
                    tagDotEl.className = 'tfp-dash-chatpanel__tag-dot tfp-dash-chatpanel__tag-dot--' + (dData.category || 'access');
                }

                setStatusBadge(dData.status || 'open');

                if (messagesEl) {
                    messagesEl.innerHTML = '';
                    (dData.messages || []).forEach(function (msg) {
                        messagesEl.appendChild(renderMessage(msg));
                    });
                    scrollMessagesToBottom();
                }
                return;
            }

            // Real WordPress Ticket Mode
            if (titleEl) titleEl.textContent = name ? name.textContent.trim() : '';

            var catEl = item.querySelector('.tfp-dash-ticketlist__category');
            if (subtitleEl) subtitleEl.textContent = catEl ? catEl.textContent.trim() : '';
            if (tagTextEl) tagTextEl.textContent = catEl ? catEl.textContent.trim() : '';
            if (tagDotEl) {
                var category = item.getAttribute('data-category') || 'access';
                tagDotEl.className = 'tfp-dash-chatpanel__tag-dot tfp-dash-chatpanel__tag-dot--' + category;
            }

            if (messagesEl) messagesEl.innerHTML = '';

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

                state.pollTimer = setInterval(poll, (typeof tfpChatSettings !== 'undefined' && tfpChatSettings.pollInterval) || 4000);
            }).catch(function () {
                // Keep thread open
            });
        }

        // Attach ticket click listeners
        items.forEach(function (item) {
            item.addEventListener('click', function () {
                openTicket(item);
            });
        });

        // Search input
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.searchTerm = searchInput.value.trim().toLowerCase();
                applyFilters();
            });
        }

        // Filter tabs
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

        // Reply form submit
        if (replyForm) {
            replyForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var message = replyInput.value.trim();
                if (!message || !state.activeTicketId || replyInput.disabled) return;

                // If currently on demo ticket, append message directly
                if (String(state.activeTicketId).indexOf('demo-') === 0) {
                    var now = new Date();
                    var hours = now.getHours();
                    var minutes = now.getMinutes();
                    var ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    minutes = minutes < 10 ? '0' + minutes : minutes;
                    var timeStr = hours + ':' + minutes + ' ' + ampm;

                    var newMsg = {
                        id: Date.now(),
                        is_mine: true,
                        sender_name: 'Support',
                        avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                        message: message,
                        created_at: timeStr,
                    };

                    replyInput.value = '';
                    messagesEl.appendChild(renderMessage(newMsg));
                    scrollMessagesToBottom();
                    replyInput.focus();
                    return;
                }

                // Real WordPress AJAX reply
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

        // Mark Resolved button
        if (resolveBtn) {
            resolveBtn.addEventListener('click', function () {
                if (!state.activeTicketId || resolveBtn.disabled) return;

                if (String(state.activeTicketId).indexOf('demo-') === 0) {
                    setStatusBadge('resolved');
                    updateTicketStatusInList(state.activeTicketId, 'resolved');
                    return;
                }

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

        // WooCommerce SelectWoo category picker with the Figma category dots.
        function initCategorySelect() {
            if (!newTicketForm || !window.jQuery || !jQuery.fn.selectWoo) return;

            var categorySelect = newTicketForm.querySelector('select[name="category"]');
            if (!categorySelect || jQuery(categorySelect).hasClass('select2-hidden-accessible')) return;

            function dotClass(value) {
                return String(value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
            }

            function renderCategory(state) {
                if (!state.id) return state.text;

                var value = dotClass(state.id);
                var wrap = jQuery('<span class="tfp-select-category-option"></span>');
                var dot = jQuery('<span class="tfp-select-category-dot"></span>');
                dot.addClass('tfp-select-category-dot--' + value);

                wrap.append(dot);
                wrap.append(document.createTextNode(state.text || ''));
                return wrap;
            }

            function renderSelected(state) {
                // SelectWoo expects a text/HTML value here; returning a jQuery object
                // causes the native SelectWoo renderer to display "[object Object]".
                // The selected value intentionally has no category dot.
                return state.text || '';
            }

            jQuery(categorySelect).selectWoo({
                width: '100%',
                minimumResultsForSearch: Infinity,
                templateResult: renderCategory,
                templateSelection: renderSelected,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
        }

        // New Ticket Modal controls
        newTicketBtns.forEach(function (button) {
            button.addEventListener('click', function () {
                if (newTicketModal) {
                    newTicketModal.hidden = false;
                    initCategorySelect();
                }
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

        // Initial selection: open the active or first ticket
        var initialItem = items.filter(function (it) {
            return it.classList.contains('is-active');
        })[0] || items[0];

        if (initialItem) {
            openTicket(initialItem);
        }
    });
})();
