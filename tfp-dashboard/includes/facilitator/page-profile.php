<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Facilitator Profile
 *
 * Reuses the existing profile AJAX endpoints, avatar upload flow and the
 * shared facilitator dashboard shell. No separate profile persistence layer
 * is introduced.
 */
function tfp_dashboard_render_facilitator_profile_content()
{
    $user = wp_get_current_user();
    $user_id = (int) $user->ID;

    $full_name = trim($user->first_name . ' ' . $user->last_name);
    if ($full_name === '') {
        $full_name = $user->display_name;
    }

    $phone = get_user_meta($user_id, 'billing_phone', true);
    $address = get_user_meta($user_id, 'tfp_address', true);
    $dob = get_user_meta($user_id, 'tfp_date_of_birth', true);
    $timezone = get_user_meta($user_id, 'tfp_timezone', true);
    $discord = get_user_meta($user_id, 'tfp_discord_username', true);

    $preferred_contact = (array) get_user_meta($user_id, 'tfp_preferred_contact', true);
    $notifications = (array) get_user_meta($user_id, 'tfp_notification_settings', true);

    $contact_options = [
        'email' => __('Email', 'tfp-dashboard'),
        'discord' => __('Discord', 'tfp-dashboard'),
        'phone' => __('Phone', 'tfp-dashboard'),
    ];

    $notification_options = [
        'payments' => __('Payments', 'tfp-dashboard'),
        'facilitator_alerts' => __('Facilitator Alerts', 'tfp-dashboard'),
        'class_reminders' => __('Class Reminders', 'tfp-dashboard'),
        'communication_tickets' => __('Communication Tickets', 'tfp-dashboard'),
    ];

    $documents = function_exists('tfp_dashboard_user_documents')
        ? (array) tfp_dashboard_user_documents($user_id, 'Agreement')
        : [];

    $document_rows = [];
    $document_map = [];

    foreach ($documents as $document) {
        if (!is_array($document)) {
            continue;
        }

        $title = !empty($document['title'])
            ? (string) $document['title']
            : __('Document', 'tfp-dashboard');

        $document_map[strtolower(trim($title))] = [
            'id' => $document['id'] ?? '',
            'title' => $title,
            'type' => $document['type'] ?? __('Agreement', 'tfp-dashboard'),
            'date' => $document['date'] ?? '',
            'status' => $document['status'] ?? 'not_started',
            'url' => $document['url'] ?? '',
            'signed_at' => $document['signed_at'] ?? '',
        ];
    }

    /*
     * These are facilitator-facing document slots from the approved profile
     * design. A slot is populated from the existing document record when the
     * corresponding record exists; otherwise it remains Not Started rather
     * than inventing a document URL or completion state.
     */
    $document_slots = [
        [
            'title' => __('Tax Form', 'tfp-dashboard'),
            'type' => __('W-9 (USA) or W-8BEN', 'tfp-dashboard'),
            'description' => __('W-9 (USA) or W-8BEN', 'tfp-dashboard'),
        ],
        [
            'title' => __('Contractor Agreement', 'tfp-dashboard'),
            'type' => __('Legal agreement for paid facilitators.', 'tfp-dashboard'),
            'description' => __('Legal agreement for paid facilitators.', 'tfp-dashboard'),
        ],
        [
            'title' => __('Identity Verification', 'tfp-dashboard'),
            'type' => __('Optional: Passport / ID', 'tfp-dashboard'),
            'description' => __('Optional: Passport / ID', 'tfp-dashboard'),
        ],
        [
            'title' => __('Wise Payout Setup', 'tfp-dashboard'),
            'type' => __('Email + currency for payouts', 'tfp-dashboard'),
            'description' => __('Email + currency for payouts', 'tfp-dashboard'),
        ],
    ];

    foreach ($document_slots as $slot) {
        $key = strtolower($slot['title']);

        $match = $document_map[$key] ?? null;

        if (!$match) {
            foreach ($document_map as $doc_key => $doc) {
                if (strpos($doc_key, $key) !== false || strpos($key, $doc_key) !== false) {
                    $match = $doc;
                    break;
                }
            }
        }

        $document_rows[] = array_merge($slot, [
            'id' => $match['id'] ?? '',
            'date' => $match['date'] ?? '',
            'status' => $match['status'] ?? 'not_started',
            'url' => $match['url'] ?? '',
            'signed_at' => $match['signed_at'] ?? '',
        ]);
    }

    ?>
    <div class="tfp-facilitator-profile-page">

        <header class="tfp-dash-pageheader tfp-facilitator-profile-header">
            <button type="button"
                    class="tfp-dash-pageheader__mobile-toggle"
                    data-tfp-sidebar-toggle
                    aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
                <?php echo tfp_dashboard_icon('toggle'); ?>
            </button>

            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>">
                    <?php esc_html_e('Facilitator', 'tfp-dashboard'); ?>
                </a>
                <span aria-hidden="true">/</span>
                <strong><?php esc_html_e('Profile', 'tfp-dashboard'); ?></strong>
            </div>

            <h1 class="tfp-dash-pageheader__title">
                <?php printf(esc_html__('Your Profile, %s', 'tfp-dashboard'), esc_html($user->first_name ?: $user->display_name)); ?>
            </h1>

            <p class="tfp-dash-pageheader__subtitle">
                <?php esc_html_e('Manage your personal contact information, payout details, and communication settings.', 'tfp-dashboard'); ?>
            </p>
        </header>

        <section class="tfp-dash-panel tfp-fac-profile-identity">
            <div class="tfp-fac-profile-identity__avatar-wrap">
                <span class="tfp-fac-profile-identity__avatar" data-tfp-avatar-preview>
                    <?php echo tfp_dashboard_avatar(176); ?>
                </span>

                <label class="tfp-fac-profile-identity__avatar-edit" title="<?php esc_attr_e('Update profile photo', 'tfp-dashboard'); ?>">
                    <?php echo tfp_dashboard_icon('edit'); ?>
                    <input type="file" accept="image/*" data-tfp-avatar-input hidden>
                </label>
            </div>

            <div class="tfp-fac-profile-identity__info">
                <h2><?php echo esc_html($full_name); ?></h2>

                <div class="tfp-fac-profile-identity__meta">
                    <?php if ($discord !== ''): ?>
                        <span><?php echo tfp_dashboard_icon('discord'); ?><?php echo esc_html($discord); ?></span>
                    <?php endif; ?>

                    <span><?php echo tfp_dashboard_icon('mail'); ?><?php echo esc_html($user->user_email); ?></span>

                    <?php if ($timezone !== ''): ?>
                        <span><?php echo tfp_dashboard_icon('clock'); ?><?php printf(esc_html__('Timezone: %s', 'tfp-dashboard'), esc_html($timezone)); ?></span>
                    <?php endif; ?>
                </div>

                <span class="tfp-fac-profile-role">
                    <?php esc_html_e('Facilitator', 'tfp-dashboard'); ?>
                </span>
            </div>
        </section>

        <div class="tfp-fac-profile-main-grid">

            <section class="tfp-dash-panel tfp-fac-profile-section"
                     data-tfp-section="personal">
                <div class="tfp-fac-profile-section__header">
                    <h2><?php esc_html_e('Personal Information', 'tfp-dashboard'); ?></h2>

                    <button type="button"
                            class="tfp-dash-btn tfp-dash-btn--primary tfp-fac-profile-edit"
                            data-tfp-edit-toggle>
                        <?php echo tfp_dashboard_icon('edit'); ?>
                        <?php esc_html_e('Edit', 'tfp-dashboard'); ?>
                    </button>
                </div>

                <div class="tfp-fac-profile-view tfp-profile-section__view">
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Full Name', 'tfp-dashboard'); ?></span>
                        <p data-field="full_name"><?php echo esc_html($full_name); ?></p>
                    </div>
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Email', 'tfp-dashboard'); ?></span>
                        <p data-field="email"><?php echo esc_html($user->user_email); ?></p>
                    </div>
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Phone Number', 'tfp-dashboard'); ?></span>
                        <p data-field="phone"><?php echo esc_html($phone ?: '—'); ?></p>
                    </div>
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Date of Birth', 'tfp-dashboard'); ?></span>
                        <p data-field="dob"><?php echo esc_html($dob ?: '—'); ?></p>
                    </div>
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Address', 'tfp-dashboard'); ?></span>
                        <p data-field="address"><?php echo esc_html($address ?: '—'); ?></p>
                    </div>
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Timezone', 'tfp-dashboard'); ?></span>
                        <p data-field="timezone"><?php echo esc_html($timezone ?: '—'); ?></p>
                    </div>
                </div>

                <form class="tfp-fac-profile-edit-form tfp-profile-section__edit-form"
                      data-tfp-profile-form
                      data-section="personal"
                      hidden>
                    <input type="hidden" name="section" value="personal">

                    <div class="tfp-fac-profile-form-grid">
                        <label>
                            <?php esc_html_e('First Name', 'tfp-dashboard'); ?>
                            <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
                        </label>
                        <label>
                            <?php esc_html_e('Last Name', 'tfp-dashboard'); ?>
                            <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
                        </label>
                        <label class="tfp-fac-profile-form-grid__full">
                            <?php esc_html_e('Email', 'tfp-dashboard'); ?>
                            <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
                        </label>
                        <label>
                            <?php esc_html_e('Phone Number', 'tfp-dashboard'); ?>
                            <input type="text" name="phone" value="<?php echo esc_attr($phone); ?>">
                        </label>
                        <label>
                            <?php esc_html_e('Date of Birth', 'tfp-dashboard'); ?>
                            <input type="date" name="date_of_birth" value="<?php echo esc_attr($dob); ?>">
                        </label>
                        <label class="tfp-fac-profile-form-grid__full">
                            <?php esc_html_e('Address', 'tfp-dashboard'); ?>
                            <input type="text" name="address" value="<?php echo esc_attr($address); ?>">
                        </label>
                        <label class="tfp-fac-profile-form-grid__full">
                            <?php esc_html_e('Timezone', 'tfp-dashboard'); ?>
                            <input type="text" name="timezone" value="<?php echo esc_attr($timezone); ?>">
                        </label>
                    </div>

                    <div class="tfp-fac-profile-edit-actions">
                        <button type="button" class="tfp-dash-btn tfp-fac-profile-cancel" data-tfp-edit-cancel>
                            <?php esc_html_e('Cancel', 'tfp-dashboard'); ?>
                        </button>
                        <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">
                            <?php esc_html_e('Save Changes', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                    <p class="tfp-dash-form__status" data-tfp-profile-status role="status"></p>
                </form>
            </section>

            <section class="tfp-dash-panel tfp-fac-profile-section"
                     data-tfp-section="connection">
                <div class="tfp-fac-profile-section__header">
                    <h2><?php esc_html_e('Communication Details', 'tfp-dashboard'); ?></h2>

                    <button type="button"
                            class="tfp-dash-btn tfp-dash-btn--primary tfp-fac-profile-edit"
                            data-tfp-edit-toggle>
                        <?php echo tfp_dashboard_icon('edit'); ?>
                        <?php esc_html_e('Edit', 'tfp-dashboard'); ?>
                    </button>
                </div>

                <div class="tfp-fac-profile-view tfp-profile-section__view">
                    <div class="tfp-fac-profile-row">
                        <span><?php esc_html_e('Discord Username', 'tfp-dashboard'); ?></span>
                        <p data-field="discord_username"><?php echo esc_html($discord ?: '—'); ?></p>
                    </div>

                    <div class="tfp-fac-profile-row tfp-fac-profile-row--checks">
                        <span><?php esc_html_e('Preferred Contact', 'tfp-dashboard'); ?></span>
                        <div data-field="preferred_contact" class="tfp-fac-profile-checklist">
                            <?php foreach ($contact_options as $key => $label): ?>
                                <span class="tfp-fac-profile-check tfp-fac-profile-check--display">
                                    <span class="tfp-fac-profile-check__box <?php echo in_array($key, $preferred_contact, true) ? 'is-checked' : ''; ?>" aria-hidden="true">
                                        <?php echo in_array($key, $preferred_contact, true) ? '✓' : ''; ?>
                                    </span>
                                    <span><?php echo esc_html($label); ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tfp-fac-profile-row tfp-fac-profile-row--checks">
                        <span><?php esc_html_e('Notification Settings', 'tfp-dashboard'); ?></span>
                        <div data-field="notification_settings" class="tfp-fac-profile-checklist">
                            <?php foreach ($notification_options as $key => $label): ?>
                                <span class="tfp-fac-profile-check tfp-fac-profile-check--display">
                                    <span class="tfp-fac-profile-check__box <?php echo in_array($key, $notifications, true) ? 'is-checked' : ''; ?>" aria-hidden="true">
                                        <?php echo in_array($key, $notifications, true) ? '✓' : ''; ?>
                                    </span>
                                    <span><?php echo esc_html($label); ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <form class="tfp-fac-profile-edit-form tfp-profile-section__edit-form"
                      data-tfp-profile-form
                      data-section="connection"
                      hidden>
                    <input type="hidden" name="section" value="connection">

                    <label class="tfp-fac-profile-field tfp-dash-form__fields">
                        <?php esc_html_e('Discord Username', 'tfp-dashboard'); ?>
                        <input type="text" name="discord_username" value="<?php echo esc_attr($discord); ?>" placeholder="<?php esc_attr_e('e.g. yourname_1234', 'tfp-dashboard'); ?>">
                    </label>

                    <div class="tfp-fac-profile-checkgroup">
                        <span class="tfp-fac-profile-checkgroup__label"><?php esc_html_e('Preferred Contact', 'tfp-dashboard'); ?></span>
                        <div class="tfp-fac-profile-checklist">
                            <?php foreach ($contact_options as $key => $label): ?>
                                <label class="tfp-fac-profile-check">
                                    <input type="checkbox" name="preferred_contact[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $preferred_contact, true)); ?>>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tfp-fac-profile-checkgroup">
                        <span class="tfp-fac-profile-checkgroup__label"><?php esc_html_e('Notification Settings', 'tfp-dashboard'); ?></span>
                        <div class="tfp-fac-profile-checklist">
                            <?php foreach ($notification_options as $key => $label): ?>
                                <label class="tfp-fac-profile-check">
                                    <input type="checkbox" name="notification_settings[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $notifications, true)); ?>>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tfp-fac-profile-edit-actions">
                        <button type="button" class="tfp-dash-btn tfp-fac-profile-cancel" data-tfp-edit-cancel>
                            <?php esc_html_e('Cancel', 'tfp-dashboard'); ?>
                        </button>
                        <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary">
                            <?php esc_html_e('Save Changes', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                    <p class="tfp-dash-form__status" data-tfp-profile-status role="status"></p>
                </form>
            </section>

        </div>

        <section class="tfp-dash-panel tfp-fac-profile-documents">
            <div class="tfp-fac-profile-documents__head">
                <h2><?php esc_html_e('Facilitator Documents', 'tfp-dashboard'); ?></h2>
            </div>

            <div class="tfp-dash-table-wrap">
                <table class="tfp-dash-table tfp-fac-profile-documents__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Document Type', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Description', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Date Issued', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                            <th aria-label="<?php esc_attr_e('Document action', 'tfp-dashboard'); ?>"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($document_rows as $row): ?>
                            <?php
                            $status = sanitize_key((string) $row['status']);
                            $status_label = in_array($status, ['signed', 'completed'], true)
                                ? __('Completed', 'tfp-dashboard')
                                : (in_array($status, ['pending', 'active', 'needs_review'], true)
                                    ? __('Needs Review', 'tfp-dashboard')
                                    : __('Not Started', 'tfp-dashboard'));
                            $status_class = in_array($status, ['signed', 'completed'], true)
                                ? 'is-completed'
                                : (in_array($status, ['pending', 'active', 'needs_review'], true)
                                    ? 'is-review'
                                    : 'is-not-started');
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                                <td><?php echo esc_html($row['description']); ?></td>
                                <td><?php echo $row['date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))) : '—'; ?></td>
                                <td>
                                    <span class="tfp-fac-profile-doc-status <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['url']): ?>
                                        <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-fac-profile-doc-link">
                                            <?php esc_html_e('View / Download', 'tfp-dashboard'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="tfp-fac-profile-doc-link is-disabled"><?php esc_html_e('View / Download', 'tfp-dashboard'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['url']): ?>
                                        <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-dash-btn-small tfp-fac-profile-doc-button <?php echo $status_class === 'is-review' ? 'is-review' : ''; ?>">
                                            <?php echo esc_html(in_array($status, ['pending', 'active', 'needs_review'], true) ? __('Review', 'tfp-dashboard') : __('View', 'tfp-dashboard')); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="tfp-dash-btn-small tfp-fac-profile-doc-button tfp-fac-profile-doc-button--disabled">
                                            <?php echo esc_html(in_array($status, ['pending', 'active', 'needs_review'], true) ? __('Review', 'tfp-dashboard') : __('Add', 'tfp-dashboard')); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php
}
