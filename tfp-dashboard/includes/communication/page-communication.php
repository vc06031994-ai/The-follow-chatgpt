<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_communication_content()
{
    $user_id  = get_current_user_id();
    $is_staff = tfp_dashboard_user_is_staff($user_id);

    $open_count     = tfp_dashboard_count_tickets_by_status($user_id, 'open');
    $pending_count  = tfp_dashboard_count_tickets_by_status($user_id, 'pending');
    $resolved_count = tfp_dashboard_count_tickets_by_status($user_id, 'resolved');

    $tickets    = tfp_dashboard_get_visible_tickets($user_id);
    $categories = tfp_dashboard_ticket_categories();

    tfp_dashboard_render_page_header(
        __('Communication', 'tfp-dashboard'),
        __('Communication', 'tfp-dashboard'),
        $is_staff
            ? __('Platform support inbox — access issues, technical help, and admin messaging.', 'tfp-dashboard')
            : __('Stay connected with The Follow Project team and keep track of your conversations.', 'tfp-dashboard')
    );
    ?>
    <div class="tfp-dash-stats">
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Open', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value"><?php echo esc_html($open_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('Active communications awaiting response', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Pending Reply', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value tfp-dash-stat__value--amber"><?php echo esc_html($pending_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('Communications waiting for your reply', 'tfp-dashboard'); ?></span>
        </div>
        <div class="tfp-dash-panel tfp-dash-stat">
            <span class="tfp-dash-stat__label"><?php esc_html_e('Resolved', 'tfp-dashboard'); ?></span>
            <span class="tfp-dash-stat__value"><?php echo esc_html($resolved_count); ?></span>
            <span class="tfp-dash-stat__sub"><?php esc_html_e('All completed communications', 'tfp-dashboard'); ?></span>
        </div>
    </div>

    <div class="tfp-dash-chatlayout">
        <aside class="tfp-dash-ticketlist">
            <div class="tfp-dash-ticketlist__toolbar">
                <div class="tfp-dash-ticketlist__search">
                    <span class="tfp-dash-ticketlist__search-icon"><?php echo tfp_dashboard_icon('search'); ?></span>
                    <input
                        type="search"
                        placeholder="<?php echo esc_attr($is_staff ? __('Search Name or Cohort', 'tfp-dashboard') : __('Search communications', 'tfp-dashboard')); ?>"
                        data-tfp-ticket-search
                    >
                </div>

                <div class="tfp-dash-ticketlist__tabs" data-tfp-ticket-tabs>
                    <button type="button" class="is-active" data-filter="all"><?php esc_html_e('All', 'tfp-dashboard'); ?></button>
                    <button type="button" data-filter="open"><?php esc_html_e('Open', 'tfp-dashboard'); ?></button>
                    <button type="button" data-filter="access"><?php esc_html_e('Access', 'tfp-dashboard'); ?></button>
                    <button type="button" data-filter="technical"><?php esc_html_e('Technical', 'tfp-dashboard'); ?></button>
                    <?php if ($is_staff) : ?>
                        <button type="button" data-filter="billing"><?php esc_html_e('Billing', 'tfp-dashboard'); ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tfp-dash-ticketlist__items">
                <?php if (empty($tickets)) : ?>
                    <div class="tfp-dash-ticketlist__empty">
                        <p><?php esc_html_e('No communications yet.', 'tfp-dashboard'); ?></p>
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-new-ticket>
                            <?php echo tfp_dashboard_icon('plus'); ?>
                            <?php esc_html_e('Start a Conversation', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <?php foreach ($tickets as $ticket) :
                    $category = get_post_meta($ticket->ID, '_tfp_category', true);
                    $status   = get_post_meta($ticket->ID, '_tfp_status', true);
                    $owner_id = (int) get_post_meta($ticket->ID, '_tfp_student_id', true);
                    $owner    = get_userdata($owner_id);
                    $last     = tfp_chat_get_last_message($ticket->ID);

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
                    <button
                        type="button"
                        class="tfp-dash-ticketlist__item"
                        data-tfp-ticket-item
                        data-ticket-id="<?php echo esc_attr($ticket->ID); ?>"
                        data-category="<?php echo esc_attr($category); ?>"
                        data-status="<?php echo esc_attr($status); ?>"
                        data-search="<?php echo esc_attr($search_text); ?>"
                    >
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
                                <i class="tfp-dash-ticketlist__dot tfp-dash-ticketlist__dot--<?php echo esc_attr($status); ?>"></i>
                                <?php echo esc_html($categories[$category] ?? ucfirst($category)); ?>
                            </span>

                            <span class="tfp-dash-ticketlist__preview">
                                <?php echo $last ? esc_html(wp_trim_words(wp_strip_all_tags($last->message), 8)) : esc_html($ticket->post_title); ?>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($tickets)) : ?>
                <div class="tfp-dash-ticketlist__footer">
                    <button type="button" class="tfp-dash-btn tfp-dash-btn--outline tfp-dash-ticketlist__new" data-tfp-new-ticket>
                        <?php echo tfp_dashboard_icon('plus'); ?>
                        <?php esc_html_e('New Conversation', 'tfp-dashboard'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </aside>

        <section class="tfp-dash-chatpanel" data-tfp-chatpanel>
            <div class="tfp-dash-chatpanel__empty" data-tfp-chat-empty>
                <div>
                    <p><?php esc_html_e('Select a communication to view the conversation.', 'tfp-dashboard'); ?></p>
                    <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-new-ticket>
                        <?php echo tfp_dashboard_icon('plus'); ?>
                        <?php esc_html_e('Start a Conversation', 'tfp-dashboard'); ?>
                    </button>
                </div>
            </div>

            <div class="tfp-dash-chatpanel__thread" data-tfp-chat-thread hidden>
                <div class="tfp-dash-chatpanel__header">
                    <div class="tfp-dash-chatpanel__person">
                        <span class="tfp-dash-chatpanel__avatar" data-tfp-chat-avatar></span>
                        <div>
                            <h2 class="tfp-dash-chatpanel__title" data-tfp-chat-title></h2>
                            <p class="tfp-dash-chatpanel__subtitle" data-tfp-chat-subtitle></p>
                        </div>
                    </div>

                    <div class="tfp-dash-chatpanel__actions">
                        <span class="tfp-dash-badge" data-tfp-chat-status></span>
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--outline" data-tfp-mark-resolved>
                            <?php esc_html_e('Mark Resolved', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                </div>

                <div class="tfp-dash-chatpanel__messages" data-tfp-chat-messages></div>

                <form class="tfp-dash-chatpanel__reply" data-tfp-chat-reply>
                    <input type="text" placeholder="<?php esc_attr_e('Type a reply...', 'tfp-dashboard'); ?>" data-tfp-chat-input required autocomplete="off">
                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">
                        <?php esc_html_e('Reply', 'tfp-dashboard'); ?>
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="tfp-dash-modal" data-tfp-new-ticket-modal hidden>
        <div class="tfp-dash-modal__backdrop" data-tfp-modal-close></div>
        <form class="tfp-dash-modal__box" data-tfp-new-ticket-form>
            <h2><?php esc_html_e('Start a Conversation', 'tfp-dashboard'); ?></h2>

            <label>
                <?php esc_html_e('Subject', 'tfp-dashboard'); ?>
                <input type="text" name="subject" required>
            </label>

            <label>
                <?php esc_html_e('Category', 'tfp-dashboard'); ?>
                <select name="category" required>
                    <?php foreach ($categories as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <?php esc_html_e('Message', 'tfp-dashboard'); ?>
                <textarea name="message" rows="4" required></textarea>
            </label>

            <div class="tfp-dash-modal__buttons">
                <button type="button" class="tfp-dash-btn tfp-dash-btn--outline" data-tfp-modal-close><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Send Message', 'tfp-dashboard'); ?></button>
            </div>
        </form>
    </div>
    <?php
}
