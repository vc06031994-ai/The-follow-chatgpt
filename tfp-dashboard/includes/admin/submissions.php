<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_management_page(
        __('Weekly Submissions', 'tfp-dashboard'),
        __('Weekly Submissions', 'tfp-dashboard'),
        'manage_options',
        'tfp-weekly-submissions',
        'tfp_dashboard_admin_weekly_submissions_page'
    );
});

function tfp_dashboard_admin_weekly_submissions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'tfp-dashboard'));
    }

    // Fetch lessons (LearnDash lessons are usually 'sfwd-lessons')
    $lessons = get_posts([
        'post_type' => 'sfwd-lessons',
        'numberposts' => -1,
        'orderby' => 'post_title',
        'order' => 'ASC',
    ]);

    $selected = isset($_GET['lesson_id']) ? absint($_GET['lesson_id']) : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Weekly Submissions', 'tfp-dashboard'); ?></h1>
        <form method="get" style="margin-bottom:18px;">
            <input type="hidden" name="page" value="tfp-weekly-submissions" />
            <label for="lesson_id" style="margin-right:8px; font-weight:600;"><?php esc_html_e('Select Lesson:', 'tfp-dashboard'); ?></label>
            <select name="lesson_id" id="lesson_id" onchange="this.form.submit()" style="min-width:320px;">
                <option value="0"><?php esc_html_e('— Choose a lesson —', 'tfp-dashboard'); ?></option>
                <?php foreach ($lessons as $l) : ?>
                    <option value="<?php echo esc_attr($l->ID); ?>" <?php selected($selected, $l->ID); ?>><?php echo esc_html($l->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected) :
            $meta_key = 'tfp_week_meeting_notes_' . $selected;
            $users = get_users([
                'meta_query' => [
                    [ 'key' => $meta_key, 'compare' => 'EXISTS' ]
                ],
                'number' => -1,
            ]);

            if (!empty($users)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Student', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Submitted At', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Reflection', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Actions', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u) :
                            $uid = $u->ID;
                            $text = get_user_meta($uid, $meta_key, true);
                            $time = get_user_meta($uid, $meta_key . '_at', true);
                            ?>
                            <tr>
                                <td style="vertical-align:top;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div><?php echo get_avatar($uid, 40); ?></div>
                                        <div>
                                            <strong><?php echo esc_html($u->display_name); ?></strong><br/>
                                            <a href="<?php echo esc_url(get_edit_user_link($uid)); ?>"><?php esc_html_e('Edit user', 'tfp-dashboard'); ?></a>
                                        </div>
                                    </div>
                                </td>
                                <td style="vertical-align:top; width:180px;">
                                    <?php echo esc_html($time ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($time)) : __('—', 'tfp-dashboard')); ?>
                                </td>
                                <td style="vertical-align:top; max-width:600px;">
                                    <div style="white-space:pre-wrap;"><?php echo wp_kses_post(wpautop($text)); ?></div>
                                </td>
                                <td style="vertical-align:top; width:140px;">
                                    <a class="button" href="<?php echo esc_url(add_query_arg(['user' => $uid, 'lesson_id' => $selected], admin_url('user-edit.php'))); ?>"><?php esc_html_e('Open user', 'tfp-dashboard'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No submissions found for this lesson.', 'tfp-dashboard'); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e('Select a lesson to view student submissions.', 'tfp-dashboard'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
