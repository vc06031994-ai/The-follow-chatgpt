<?php
/**
 * Course Access & Permissions tab.
 * Uses WordPress users and course post meta; no demo users or placeholder data.
 */
if (!defined('ABSPATH')) {
    exit;
}

$access_meta_key = '_tfp_course_access_settings';
$access_settings = get_post_meta($course_id, $access_meta_key, true);
$access_settings = is_array($access_settings) ? $access_settings : [];
$editor_ids = isset($access_settings['editor_ids']) && is_array($access_settings['editor_ids'])
    ? array_values(array_unique(array_filter(array_map('absint', $access_settings['editor_ids']))))
    : [];
$owner_id = absint(get_post_field('post_author', $course_id));
$permission_defaults = [
    'lock_course' => 0,
    'manual_unlock_weeks' => 1,
    'view_global_insights' => 0,
    'override_quiz_retakes' => 1,
    'view_other_cohorts' => 1,
];
foreach ($permission_defaults as $key => $default) {
    $access_settings[$key] = isset($access_settings[$key]) ? (int) (bool) $access_settings[$key] : $default;
}

if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['tfp_course_access_action'])) {
    if (!isset($_POST['tfp_course_access_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_course_access_nonce'])), 'tfp_course_access_' . $course_id)) {
        wp_die(esc_html__('Security check failed.', 'tfp-dashboard'), '', ['response' => 403]);
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to change course access.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $action = sanitize_key(wp_unslash($_POST['tfp_course_access_action']));
    if ('add_editor' === $action) {
        $email = isset($_POST['editor_email']) ? sanitize_email(wp_unslash($_POST['editor_email'])) : '';
        $editor = $email ? get_user_by('email', $email) : false;
        if ($editor && (int) $editor->ID !== $owner_id && !in_array((int) $editor->ID, $editor_ids, true)) {
            $editor_ids[] = (int) $editor->ID;
            $access_settings['editor_ids'] = $editor_ids;
            update_post_meta($course_id, $access_meta_key, $access_settings);
        }
    } elseif ('remove_editor' === $action) {
        $remove_id = isset($_POST['editor_id']) ? absint($_POST['editor_id']) : 0;
        $editor_ids = array_values(array_diff($editor_ids, [$remove_id]));
        $access_settings['editor_ids'] = $editor_ids;
        update_post_meta($course_id, $access_meta_key, $access_settings);
    } elseif ('save_permissions' === $action) {
        foreach ($permission_defaults as $key => $default) {
            $access_settings[$key] = isset($_POST[$key]) ? 1 : 0;
        }
        update_post_meta($course_id, $access_meta_key, $access_settings);
    }
    wp_safe_redirect(add_query_arg('course_tab', 'access', tfp_admin_course_detail_url($course_id)));
    exit;
}

$owner = $owner_id ? get_userdata($owner_id) : false;
$editor_users = $editor_ids ? get_users(['include' => $editor_ids, 'orderby' => 'display_name', 'order' => 'ASC']) : [];
$permission_labels = [
    'manual_unlock_weeks' => __('Manual Unlock Weeks', 'tfp-dashboard'),
    'view_global_insights' => __('View Global Insights', 'tfp-dashboard'),
    'override_quiz_retakes' => __('Override Quiz Retakes', 'tfp-dashboard'),
    'view_other_cohorts' => __('View Other Cohorts', 'tfp-dashboard'),
];
?>
<section class="tfp-admin-access-layout">
    <div class="tfp-admin-access-top-grid">
        <section class="tfp-admin-panel tfp-admin-access-card tfp-admin-access-editors">
            <header class="tfp-admin-access-card-heading">
                <h2><?php esc_html_e('Curriculum Editors', 'tfp-dashboard'); ?></h2>
                <p><?php esc_html_e('Users who can modify course content, structure, and rules.', 'tfp-dashboard'); ?></p>
            </header>
            <div class="tfp-admin-access-user-list">
                <?php if ($owner) : ?>
                    <article class="tfp-admin-access-user">
                        <span class="tfp-admin-access-avatar is-owner"><?php echo get_avatar($owner->ID, 42, '', '', ['class' => '']); ?></span>
                        <span class="tfp-admin-access-user-details"><strong><?php echo esc_html($owner->display_name); ?></strong><small><?php esc_html_e('Owner', 'tfp-dashboard'); ?></small></span>
                    </article>
                <?php endif; ?>
                <?php foreach ($editor_users as $editor) : ?>
                    <article class="tfp-admin-access-user">
                        <span class="tfp-admin-access-avatar"><?php echo get_avatar($editor->ID, 42, '', '', ['class' => '']); ?></span>
                        <span class="tfp-admin-access-user-details"><strong><?php echo esc_html($editor->display_name); ?></strong><small><?php esc_html_e('Editor', 'tfp-dashboard'); ?></small></span>
                        <form method="post" class="tfp-admin-access-remove-form">
                            <?php wp_nonce_field('tfp_course_access_' . $course_id, 'tfp_course_access_nonce'); ?>
                            <input type="hidden" name="tfp_course_access_action" value="remove_editor">
                            <input type="hidden" name="editor_id" value="<?php echo esc_attr($editor->ID); ?>">
                            <button type="submit"><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
            <form method="post" class="tfp-admin-access-add-form">
                <?php wp_nonce_field('tfp_course_access_' . $course_id, 'tfp_course_access_nonce'); ?>
                <input type="hidden" name="tfp_course_access_action" value="add_editor">
                <label class="screen-reader-text" for="tfp-course-editor-email"><?php esc_html_e('Editor email address', 'tfp-dashboard'); ?></label>
                <input id="tfp-course-editor-email" type="email" name="editor_email" placeholder="<?php esc_attr_e('Enter an existing user email', 'tfp-dashboard'); ?>" required>
                <button type="submit"><?php esc_html_e('+ Add Editor', 'tfp-dashboard'); ?></button>
            </form>
            <p class="tfp-admin-access-help"><?php esc_html_e('Only existing WordPress users can be added. The course owner cannot be removed.', 'tfp-dashboard'); ?></p>
        </section>

        <section class="tfp-admin-panel tfp-admin-access-card tfp-admin-access-integrity">
            <header class="tfp-admin-access-card-heading">
                <h2><?php esc_html_e('Content Integrity Scan', 'tfp-dashboard'); ?></h2>
                <p><?php esc_html_e('Prevent edits while cohorts are actively running.', 'tfp-dashboard'); ?></p>
            </header>
            <form method="post" class="tfp-admin-access-lock-form">
                <?php wp_nonce_field('tfp_course_access_' . $course_id, 'tfp_course_access_nonce'); ?>
                <input type="hidden" name="tfp_course_access_action" value="save_permissions">
                <label class="tfp-admin-access-setting-row">
                    <span><strong><?php esc_html_e('Lock Course', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Course is editable', 'tfp-dashboard'); ?></small></span>
                    <input type="checkbox" name="lock_course" value="1" <?php checked($access_settings['lock_course'], 1); ?>>
                </label>
                <?php foreach ($permission_labels as $key => $label) : ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo $access_settings[$key] ? '1' : '0'; ?>">
                <?php endforeach; ?>
                <button type="submit" class="tfp-admin-access-save"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
            </form>
        </section>
    </div>

    <section class="tfp-admin-panel tfp-admin-access-card tfp-admin-access-facilitators">
        <header class="tfp-admin-access-card-heading">
            <h2><?php esc_html_e('Facilitator Permissions', 'tfp-dashboard'); ?></h2>
            <p><?php esc_html_e('Controls what facilitators can override across all cohorts of this course.', 'tfp-dashboard'); ?></p>
        </header>
        <form method="post" class="tfp-admin-access-permissions-form">
            <?php wp_nonce_field('tfp_course_access_' . $course_id, 'tfp_course_access_nonce'); ?>
            <input type="hidden" name="tfp_course_access_action" value="save_permissions">
            <input type="hidden" name="lock_course" value="<?php echo $access_settings['lock_course'] ? '1' : '0'; ?>">
            <?php foreach ($permission_labels as $key => $label) : ?>
                <label class="tfp-admin-access-permission-row">
                    <span><?php echo esc_html($label); ?></span>
                    <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($access_settings[$key], 1); ?>>
                </label>
            <?php endforeach; ?>
            <button type="submit" class="tfp-admin-access-save"><?php esc_html_e('Save Permissions', 'tfp-dashboard'); ?></button>
        </form>
    </section>
</section>
