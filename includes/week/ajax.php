<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_tfp_week_mark_step_complete', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $step      = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
    $user_id   = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_ld_user_can_access_week($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('You do not have access to this week.', 'tfp-dashboard')], 403);
    }

    if (!in_array($step, tfp_ld_week_steps(), true)) {
        wp_send_json(['success' => false, 'message' => __('Invalid step.', 'tfp-dashboard')], 400);
    }

    // Only the video step can be self-marked-complete purely by watching;
    // everything else requires its own future submission flow (reading
    // checklist, homework form, quiz, test). This handler currently only
    // supports 'video' — trying to mark any other step here is rejected
    // rather than silently allowed, so gating can't be bypassed from the
    // browser console.
    if ($step !== 'video') {
        wp_send_json(['success' => false, 'message' => __('This step cannot be completed this way yet.', 'tfp-dashboard')], 400);
    }

    // Must actually be unlocked (defensive — video is always unlocked,
    // but keep the check for consistency/future steps).
    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, $step)) {
        wp_send_json(['success' => false, 'message' => __('This step is locked.', 'tfp-dashboard')], 403);
    }

    tfp_ld_mark_step_complete($user_id, $lesson_id, $step);

    wp_send_json([
        'success'  => true,
        'progress' => tfp_ld_get_week_progress($user_id, $lesson_id),
    ]);
});

add_action('wp_ajax_tfp_week_submit_reflection', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $text = isset($_POST['text']) ? wp_kses_post(wp_unslash($_POST['text'])) : '';
    $user_id = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_ld_user_can_access_week($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('You do not have access to this week.', 'tfp-dashboard')], 403);
    }

    $meta_key = 'tfp_week_meeting_notes_' . $lesson_id;
    update_user_meta($user_id, $meta_key, $text);
    update_user_meta($user_id, $meta_key . '_at', current_time('mysql'));

    wp_send_json(['success' => true, 'message' => __('Reflection saved.', 'tfp-dashboard'), 'text' => $text]);
});

add_action('wp_ajax_tfp_week_add_facilitator_note', function () {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json(['success' => false, 'message' => __('Insufficient permissions.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $text = isset($_POST['text']) ? wp_kses_post(wp_unslash($_POST['text'])) : '';
    $user_id = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    $meta_key = 'tfp_week_meeting_facilitator_notes_list';
    $existing = get_post_meta($lesson_id, $meta_key, true);
    $list = [];
    if (!empty($existing)) {
        $decoded = json_decode($existing, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $list = $decoded;
        }
    }

    $entry = [
        'author_id' => $user_id,
        'text'      => $text,
        'created_at'=> current_time('mysql'),
    ];

    $list[] = $entry;
    update_post_meta($lesson_id, $meta_key, wp_json_encode($list));

    // Also keep legacy single-field (HTML) in sync for backwards compatibility.
    $legacy = get_post_meta($lesson_id, 'tfp_week_meeting_facilitator_notes', true);
    $legacy .= '<p class="tfp-week__meeting-facilitator-note">' . wp_kses_post($text) . '</p>';
    update_post_meta($lesson_id, 'tfp_week_meeting_facilitator_notes', $legacy);

    // Render the single new note HTML to return to the client.
    $avatar = get_avatar($user_id, 48);
    $author_name = get_the_author_meta('display_name', $user_id) ?: __('Facilitator', 'tfp-dashboard');
    $time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry['created_at']));

    ob_start();
    ?>
    <div class="tfp-week__meeting-facilitator-entry">
        <div class="tfp-week__meeting-facilitator-avatar"><?php echo $avatar; ?></div>
        <div class="tfp-week__meeting-facilitator-body">
            <div class="tfp-week__meeting-facilitator-meta"><strong><?php echo esc_html($author_name); ?></strong> <span class="tfp-week__meeting-facilitator-time"><?php echo esc_html($time); ?></span></div>
            <div class="tfp-week__meeting-facilitator-text"><?php echo wp_kses_post(wpautop($entry['text'])); ?></div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json(['success' => true, 'message' => __('Note added.', 'tfp-dashboard'), 'html' => $html]);
});

add_action('wp_ajax_tfp_week_mark_reading_complete', function () {
    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => __('You must be logged in.', 'tfp-dashboard')], 403);
    }

    if (!isset($_POST['tfp_week_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_week_nonce'])), 'tfp_week_nonce')) {
        wp_send_json(['success' => false, 'message' => __('Security check failed.', 'tfp-dashboard')], 403);
    }

    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
    $reading_id = isset($_POST['reading_id']) ? absint($_POST['reading_id']) : 0;
    $user_id   = get_current_user_id();

    if (!$lesson_id || !tfp_ld_get_week($lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('Week not found.', 'tfp-dashboard')], 404);
    }

    if (!tfp_ld_user_can_access_week($user_id, $lesson_id)) {
        wp_send_json(['success' => false, 'message' => __('You do not have access to this week.', 'tfp-dashboard')], 403);
    }

    if (!$reading_id) {
        wp_send_json(['success' => false, 'message' => __('Reading not found.', 'tfp-dashboard')], 404);
    }

    $reading = get_post($reading_id);
    if (
        !$reading ||
        $reading->post_type !== 'tfp_reading' ||
        $reading->post_status !== 'publish' ||
        (int) get_post_meta($reading_id, '_tfp_lesson_id', true) !== $lesson_id
    ) {
        wp_send_json(['success' => false, 'message' => __('This reading does not belong to the selected week.', 'tfp-dashboard')], 403);
    }

    $meta_key = 'tfp_reading_progress_' . $lesson_id;
    $completed_readings = get_user_meta($user_id, $meta_key, true);
    $completed_readings = is_array($completed_readings) ? $completed_readings : [];
    
    if (!in_array($reading_id, $completed_readings)) {
        $completed_readings[] = $reading_id;
        update_user_meta($user_id, $meta_key, $completed_readings);
    }

    $readings = get_posts([
        'post_type'      => 'tfp_reading',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => '_tfp_lesson_id',
                'value' => $lesson_id,
            ]
        ]
    ]);

    $valid_completed = array_intersect(wp_list_pluck($readings, 'ID'), $completed_readings);
    $completed_count = count($valid_completed);

    // Never trust a client-provided "last reading" flag. The step unlocks only
    // after every published reading assigned to this lesson is complete.
    if (!empty($readings) && $completed_count === count($readings)) {
        tfp_ld_mark_step_complete($user_id, $lesson_id, 'reading');
    }

    wp_send_json([
        'success'  => true,
        'data'     => [
            'completed_count' => $completed_count,
            'total'           => count($readings),
        ]
    ]);
});

