<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Demo tickets matching Figma media_1790601569672.png
 */
function tfp_communication_get_demo_tickets()
{
    return [
        'demo-1' => [
            'id' => 'demo-1',
            'title' => "Can't access Week 4 content",
            'display_name' => 'Sarah Briner',
            'category' => 'technical',
            'category_label' => 'Technical',
            'status' => 'open',
            'time' => '1 hour ago',
            'meta' => 'Technical • Opened Jun 10, 2026 • Assigned to Support',
            'dot_class' => 'technical',
            'preview' => 'Can you help me choose from t...',
            'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&auto=format&fit=crop&q=80',
            'messages' => [
                [
                    'id' => 101,
                    'sender_name' => 'Sarah Briner',
                    'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => false,
                    'message' => 'Hi, I need assistance choosing which spring technical module to take first.',
                    'created_at' => '11:15 AM',
                ],
                [
                    'id' => 102,
                    'sender_name' => 'Support',
                    'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => true,
                    'message' => 'Hi Sarah! Foundations of Discipleship is the recommended starting point.',
                    'created_at' => '11:20 AM',
                ],
            ],
        ],
        'demo-2' => [
            'id' => 'demo-2',
            'title' => "Can't access Week 4 content",
            'display_name' => 'Luis Pittman',
            'category' => 'access',
            'category_label' => 'Facilitator Op',
            'status' => 'open',
            'time' => '1 hour ago',
            'meta' => 'Access Issue • Opened Jun 10, 2026 • Assigned to Support',
            'dot_class' => 'access',
            'tag_label' => 'Access Issue',
            'preview' => 'Can you help me choose from t...',
            'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
            'messages' => [
                [
                    'id' => 201,
                    'sender_name' => 'Luis Pittman',
                    'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => false,
                    'message' => 'Hi, I wonder when if there is going to be anything new for spring?',
                    'created_at' => '12:24 AM',
                ],
                [
                    'id' => 202,
                    'sender_name' => 'Support',
                    'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => true,
                    'message' => 'Hi Luis, can you please be more specific?',
                    'created_at' => '12:26 AM',
                ],
                [
                    'id' => 203,
                    'sender_name' => 'Luis Pittman',
                    'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => false,
                    'message' => 'Sure, I want to know when the new in the spring modules for the section on sin coming',
                    'created_at' => '12:29 AM',
                ],
                [
                    'id' => 204,
                    'sender_name' => 'Support',
                    'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => true,
                    'message' => 'Luis, the content will stay the same but some exersies will be inside the sections',
                    'created_at' => '12:30 AM',
                ],
            ],
        ],
        'demo-3' => [
            'id' => 'demo-3',
            'title' => "Can't access Week 4 content",
            'display_name' => 'Marus Baker',
            'category' => 'access',
            'category_label' => 'Access Issue',
            'status' => 'open',
            'time' => '1 hour ago',
            'meta' => 'Access Issue • Opened Jun 10, 2026 • Assigned to Support',
            'dot_class' => 'access',
            'tag_label' => 'Access Issue',
            'preview' => 'Can you help me choose from t...',
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80',
            'messages' => [
                [
                    'id' => 301,
                    'sender_name' => 'Marus Baker',
                    'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => false,
                    'message' => 'Hello team, I am unable to view the Week 4 video lectures on my account.',
                    'created_at' => '9:40 AM',
                ],
                [
                    'id' => 302,
                    'sender_name' => 'Support',
                    'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                    'is_mine' => true,
                    'message' => 'We have verified your cohort schedule and refreshed your access tokens. Please try reloading now.',
                    'created_at' => '9:52 AM',
                ],
            ],
        ],
    ];
}

function tfp_dashboard_render_communication_content()
{
    $user_id = get_current_user_id();
    $is_staff = tfp_dashboard_user_is_staff($user_id);

    $open_count = tfp_dashboard_count_tickets_by_status($user_id, 'open');
    $pending_count = tfp_dashboard_count_tickets_by_status($user_id, 'pending');
    $resolved_count = tfp_dashboard_count_tickets_by_status($user_id, 'resolved');

    $tickets = tfp_dashboard_get_visible_tickets($user_id);
    $categories = tfp_dashboard_ticket_categories();

    // Default counts matching Figma screenshot if empty
    $display_open = ($open_count > 0 || !empty($tickets)) ? $open_count : 2;
    $display_pending = ($pending_count > 0 || !empty($tickets)) ? $pending_count : 1;
    $display_resolved = ($resolved_count > 0 || !empty($tickets)) ? $resolved_count : 2;

    $demo_tickets = tfp_communication_get_demo_tickets();
    $has_real_tickets = !empty($tickets);

    // Initial selected ticket data (defaulting to demo-2 Luis Pittman to match Figma)
    $active_ticket_data = $demo_tickets['demo-2'];

    tfp_dashboard_render_page_header(
        __('Communication', 'tfp-dashboard'),
        __('Communication', 'tfp-dashboard'),
        __('Submit and track support tickets — access issues, billing questions, and technical help.', 'tfp-dashboard')
    );
    ?>
    <!-- Top 3 Stats Cards (Figma media_1790601569672.png) -->
    <div class="tfp-dash-stats">
        <div class="tfp-dash-panel tfp-dash-stat tfp-dash-stat--open">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Open', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value"><?php echo esc_html($display_open); ?></span>
            <span
                class="tfp-dash-stat__sub"><?php esc_html_e('Active tickets awaiting response', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat tfp-dash-stat--pending">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Pending Reply', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value tfp-dash-stat__value--amber"><?php echo esc_html($display_pending); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('Waiting on your response', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat tfp-dash-stat--resolved">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Resolved', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value tfp-dash-stat__value--dark"><?php echo esc_html($display_resolved); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('All completed tickets', 'tfp-dashboard'); ?></span>
        </div>
    </div>

    <!-- Discord Notification Banner (Figma media_1790601569672.png) -->
    <div class="tfp-comm-discord-banner">
        <div class="tfp-comm-discord-banner__left">
            <div class="tfp-comm-discord-banner__icon" aria-hidden="true">
                <svg width="42" height="42" viewBox="0 0 42 42" fill="none">
                    <rect width="42" height="42" rx="21" fill="#F97316" />
                    <path d="M21 11L31 28H11L21 11Z" stroke="#111827" stroke-width="2.2" stroke-linejoin="round"
                        fill="none" />
                    <line x1="21" y1="18" x2="21" y2="22.5" stroke="#111827" stroke-width="2.2" stroke-linecap="round" />
                    <circle cx="21" cy="25.5" r="1.2" fill="#111827" />
                </svg>
            </div>
            <div class="tfp-comm-discord-banner__text">
                <h4 class="tfp-comm-discord-banner__title">
                    <?php esc_html_e('Need to reach your facilitator or cohort?', 'tfp-dashboard'); ?></h4>
                <p class="tfp-comm-discord-banner__sub">
                    <?php esc_html_e('Day-to-day communication, prayer requests, and check-ins happen in Discord — not here.', 'tfp-dashboard'); ?>
                </p>
            </div>
        </div>
        <a href="https://discord.gg/MunVQXc3a" target="_blank" rel="noopener noreferrer" class="tfp-comm-discord-btn tfp-dash-btn tfp-dash-btn--primary">
            <?php esc_html_e('Open Discord', 'tfp-dashboard'); ?>
        </a>
    </div>

    <!-- Main Chat & Tickets Unified Box (Figma media_1790601569672.png) -->
    <div class="tfp-dash-chatlayout">
        <!-- Left Column: Tickets List -->
        <aside class="tfp-dash-ticketlist">
            <div class="tfp-dash-ticketlist__toolbar">
                <div class="tfp-dash-ticketlist__search">
                    <span class="tfp-dash-ticketlist__search-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
<path d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.21799 15.9079C1 15.4801 1 14.9203 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1.40973 2.0918 1.21799C2.51962 1 3.08009 1 4.2002 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.2837 1.40973 16.5905 1.71547 16.7822 2.0918C17 2.5192 17 3.07899 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
                    </span>
                    <input type="search" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>"
                        data-tfp-ticket-search>
                </div>

                <div class="tfp-dash-ticketlist__tabs" data-tfp-ticket-tabs>
                    <button type="button" class="is-active"
                        data-filter="all"><?php esc_html_e('All', 'tfp-dashboard'); ?></button>
                    <button type="button" data-filter="open"><?php esc_html_e('Open', 'tfp-dashboard'); ?></button>
                    <button type="button" data-filter="access"><?php esc_html_e('Access', 'tfp-dashboard'); ?></button>
                    <button type="button"
                        data-filter="technical"><?php esc_html_e('Technical', 'tfp-dashboard'); ?></button>
                </div>
            </div>

            <div class="tfp-dash-ticketlist__items" id="tfpTicketListItems">
                <?php if ($has_real_tickets): ?>
                    <?php foreach ($tickets as $ticket):
                        $category = get_post_meta($ticket->ID, '_tfp_category', true);
                        $status = get_post_meta($ticket->ID, '_tfp_status', true);
                        $owner_id = (int) get_post_meta($ticket->ID, '_tfp_student_id', true);
                        $owner = get_userdata($owner_id);
                        $last = tfp_chat_get_last_message($ticket->ID);

                        $display_name = $is_staff
                            ? ($owner ? $owner->display_name : $ticket->post_title)
                            : $ticket->post_title;

                        $search_text = strtolower(
                            $ticket->post_title . ' ' .
                            ($owner ? $owner->display_name : '') . ' ' .
                            ($categories[$category] ?? '') . ' ' .
                            ($last ? wp_strip_all_tags($last->message) : '')
                        );

                        $time = $last
                            ? sprintf(__('%s ago', 'tfp-dashboard'), human_time_diff(strtotime($last->created_at), current_time('timestamp')))
                            : sprintf(__('%s ago', 'tfp-dashboard'), human_time_diff(get_post_timestamp($ticket), current_time('timestamp')));
                        ?>
                        <button type="button" class="tfp-dash-ticketlist__item" data-tfp-ticket-item
                            data-ticket-id="<?php echo esc_attr($ticket->ID); ?>" data-category="<?php echo esc_attr($category); ?>"
                            data-status="<?php echo esc_attr($status); ?>" data-search="<?php echo esc_attr($search_text); ?>">
                            <span class="tfp-dash-ticketlist__avatar">
                                <?php
                                if ($is_staff && $owner) {
                                    echo get_avatar($owner_id, 42);
                                } else {
                                    echo get_avatar($user_id, 42);
                                }
                                ?>
                            </span>

                            <span class="tfp-dash-ticketlist__meta">
                                <span class="tfp-dash-ticketlist__topline">
                                    <span class="tfp-dash-ticketlist__name"><?php echo esc_html($display_name); ?></span>
                                    <span class="tfp-dash-ticketlist__time"><?php echo esc_html($time); ?></span>
                                </span>

                                <span class="tfp-dash-ticketlist__category">
                                    <i
                                        class="tfp-dash-ticketlist__dot tfp-dash-ticketlist__dot--<?php echo esc_attr($category); ?>"></i>
                                    <?php echo esc_html($categories[$category] ?? ucfirst($category)); ?>
                                </span>

                                <span class="tfp-dash-ticketlist__preview">
                                    <?php echo $last ? esc_html(wp_trim_words(wp_strip_all_tags($last->message), 8)) : esc_html($ticket->post_title); ?>
                                </span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Figma Demo Tickets -->
                    <?php foreach ($demo_tickets as $dId => $dItem):
                        $isActive = ($dId === 'demo-2') ? ' is-active' : '';
                        $search_text = strtolower($dItem['display_name'] . ' ' . $dItem['category_label'] . ' ' . $dItem['preview']);
                        ?>
                        <button type="button" class="tfp-dash-ticketlist__item<?php echo esc_attr($isActive); ?>"
                            data-tfp-ticket-item data-ticket-id="<?php echo esc_attr($dId); ?>"
                            data-category="<?php echo esc_attr($dItem['category']); ?>"
                            data-status="<?php echo esc_attr($dItem['status']); ?>"
                            data-search="<?php echo esc_attr($search_text); ?>">
                            <span class="tfp-dash-ticketlist__avatar">
                                <img src="<?php echo esc_url($dItem['avatar_url']); ?>"
                                    alt="<?php echo esc_attr($dItem['display_name']); ?>">
                            </span>

                            <span class="tfp-dash-ticketlist__meta">
                                <span class="tfp-dash-ticketlist__topline">
                                    <span class="tfp-dash-ticketlist__name"><?php echo esc_html($dItem['display_name']); ?></span>
                                    <span class="tfp-dash-ticketlist__time"><?php echo esc_html($dItem['time']); ?></span>
                                </span>

                                <span class="tfp-dash-ticketlist__category">
                                    <i
                                        class="tfp-dash-ticketlist__dot tfp-dash-ticketlist__dot--<?php echo esc_attr($dItem['dot_class']); ?>"></i>
                                    <?php echo esc_html($dItem['category_label']); ?>
                                </span>

                                <span class="tfp-dash-ticketlist__preview">
                                    <?php echo esc_html($dItem['preview']); ?>
                                </span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tfp-dash-ticketlist__footer">
                <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-dash-ticketlist__new"
                    data-tfp-new-ticket>
                    <?php echo tfp_dashboard_icon('plus'); ?>
                    <?php esc_html_e('New Conversation', 'tfp-dashboard'); ?>
                </button>
            </div>
        </aside>

        <!-- Right Column: Conversation Panel -->
        <section class="tfp-dash-chatpanel" data-tfp-chatpanel>
            <div class="tfp-dash-chatpanel__empty" data-tfp-chat-empty style="display: none;">
                <div>
                    <p><?php esc_html_e('Select a communication to view the conversation.', 'tfp-dashboard'); ?></p>
                    <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-new-ticket>
                        <?php echo tfp_dashboard_icon('plus'); ?>
                        <?php esc_html_e('Start a Conversation', 'tfp-dashboard'); ?>
                    </button>
                </div>
            </div>

            <div class="tfp-dash-chatpanel__thread" data-tfp-chat-thread>
                <!-- Chat Panel Header (Figma media_1790601569672.png) -->
                <div class="tfp-dash-chatpanel__header">
                    <div class="tfp-dash-chatpanel__head-info">
                        <h2 class="tfp-dash-chatpanel__title" data-tfp-chat-title>
                            <?php echo esc_html($active_ticket_data['title']); ?>
                        </h2>
                        <div class="tfp-dash-chatpanel__meta" data-tfp-chat-subtitle>
                            <?php echo esc_html($active_ticket_data['meta']); ?>
                        </div>
                        <div class="tfp-dash-chatpanel__tag">
                            <span
                                class="tfp-dash-chatpanel__tag-dot tfp-dash-chatpanel__tag-dot--<?php echo esc_attr($active_ticket_data['dot_class']); ?>"
                                data-tfp-chat-dot></span>
                            <span class="tfp-dash-chatpanel__tag-text" data-tfp-chat-tag>
                                <?php echo esc_html($active_ticket_data['tag_label'] ?? $active_ticket_data['category_label']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="tfp-dash-chatpanel__actions">
                        <span class="tfp-dash-badge" data-tfp-chat-status>
                            <?php echo esc_html(ucfirst($active_ticket_data['status'])); ?>
                        </span>
                        <button type="button" class="tfp-dash-btn-resolved" data-tfp-mark-resolved>
                            <?php esc_html_e('Mark Resolved', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                </div>

                <!-- Chat Messages Body -->
                <div class="tfp-dash-chatpanel__messages" data-tfp-chat-messages id="tfpChatMessages">
                    <?php foreach ($active_ticket_data['messages'] as $msg):
                        $msgCls = $msg['is_mine'] ? 'tfp-dash-msg--mine' : 'tfp-dash-msg--theirs';
                        ?>
                        <div class="tfp-dash-msg <?php echo esc_attr($msgCls); ?>"
                            data-id="<?php echo esc_attr($msg['id']); ?>">
                            <?php if (!$msg['is_mine']): ?>
                                <span class="tfp-dash-msg__avatar">
                                    <img src="<?php echo esc_url($msg['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($msg['sender_name']); ?>">
                                </span>
                            <?php endif; ?>

                            <div class="tfp-dash-msg__body">
                                <div class="tfp-dash-msg__bubble"><?php echo esc_html($msg['message']); ?></div>
                                <div class="tfp-dash-msg__time"><?php echo esc_html($msg['created_at']); ?></div>
                            </div>

                            <?php if ($msg['is_mine']): ?>
                                <span class="tfp-dash-msg__avatar">
                                    <img src="<?php echo esc_url($msg['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($msg['sender_name']); ?>">
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <form class="tfp-dash-chatpanel__reply" data-tfp-chat-reply id="tfpChatReplyForm">
                    <input type="text" placeholder="<?php esc_attr_e('Type a reply...', 'tfp-dashboard'); ?>"
                        data-tfp-chat-input required autocomplete="off">
                    <button type="submit" class="tfp-dash-btn-reply tfp-dash-btn--primary tfp-dash-btn">
                        <?php esc_html_e('Reply', 'tfp-dashboard'); ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <!-- Embedded Demo Tickets JSON for live switching -->
    <script id="tfpDemoTicketsJSON" type="application/json">
            <?php echo wp_json_encode($demo_tickets); ?>
        </script>

    <!-- Modal for Starting a New Conversation -->
    <div class="tfp-dash-modal" data-tfp-new-ticket-modal hidden>
        <div class="tfp-dash-modal__backdrop" data-tfp-modal-close></div>
        <form class="tfp-dash-modal__box tfp-dash-form__fields" data-tfp-new-ticket-form>
            <h3><?php esc_html_e('Start a Conversation', 'tfp-dashboard'); ?></h3>

            <label>
                <?php esc_html_e('Subject', 'tfp-dashboard'); ?>
                <input type="text" name="subject" required>
            </label>

            <label>
                <?php esc_html_e('Category', 'tfp-dashboard'); ?>
                <select name="category" required>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <?php esc_html_e('Message', 'tfp-dashboard'); ?>
                <textarea name="message" rows="4" required></textarea>
            </label>

            <div class="tfp-dash-modal__buttons">
                <button type="button" class="tfp-cancel-btn"
                    data-tfp-modal-close>
                    <?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                <button type="submit"
                    class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Send Message', 'tfp-dashboard'); ?></button>
            </div>
        </form>
    </div>
    <?php
}
