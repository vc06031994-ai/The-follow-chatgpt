<?php
/**
 * Admin Week Content Editor — custom editor for the selected course week.
 */
if (!defined('ABSPATH')) {
    exit;
}

function tfp_admin_course_editor_url($course_id, $week_id) {
    return add_query_arg([
        'week_editor' => '1',
        'week_id' => absint($week_id),
    ], tfp_admin_course_detail_url($course_id));
}

function tfp_admin_course_editor_render($course_id, $data) {
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $week_id = isset($_GET['week_id']) ? absint($_GET['week_id']) : 0;
    $week = null;
    foreach ($weeks as $candidate) {
        if ((int) $candidate->ID === $week_id) {
            $week = $candidate;
            break;
        }
    }
    if (!$week && !empty($weeks)) {
        $week = $weeks[0];
        $week_id = (int) $week->ID;
    }
    if (!$week) {
        wp_die(esc_html__('No weeks are mapped to this course yet.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $notice = '';
    $selected_reading_id = isset($_GET['reading_id']) ? absint($_GET['reading_id']) : 0;
    if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_course_editor_action'])) {
        check_admin_referer('tfp_course_editor_' . $course_id . '_' . $week_id);
        $action = sanitize_key(wp_unslash($_POST['tfp_course_editor_action']));
        $reading_id = isset($_POST['reading_id']) ? absint($_POST['reading_id']) : 0;
        $reading = $reading_id ? get_post($reading_id) : null;
        if ($reading && $reading->post_type !== 'tfp_reading') {
            $reading = null;
        }

        if ('save' === $action && $reading && current_user_can('edit_post', $reading_id)) {
            $title = isset($_POST['reading_title']) ? sanitize_text_field(wp_unslash($_POST['reading_title'])) : '';
            $body = isset($_POST['reading_content']) ? wp_kses_post(wp_unslash($_POST['reading_content'])) : '';
            $saved = wp_update_post([
                'ID' => $reading_id,
                'post_title' => $title,
                'post_content' => $body,
            ], true);
            if (!is_wp_error($saved)) {
                $selected_reading_id = $reading_id;
                $notice = __('Reading saved successfully.', 'tfp-dashboard');
            } else {
                $notice = __('The reading could not be saved. Please try again.', 'tfp-dashboard');
            }
        } elseif ('duplicate' === $action && $reading && current_user_can('edit_post', $reading_id)) {
            $copy_id = wp_insert_post([
                'post_type' => 'tfp_reading',
                'post_status' => 'draft',
                'post_title' => $reading->post_title . ' (Copy)',
                'post_content' => $reading->post_content,
                'menu_order' => (int) $reading->menu_order + 1,
            ], true);
            if (!is_wp_error($copy_id)) {
                update_post_meta($copy_id, '_tfp_lesson_id', $week_id);
                $selected_reading_id = (int) $copy_id;
                $notice = __('Reading duplicated as a draft.', 'tfp-dashboard');
            }
        } elseif ('move' === $action && $reading && current_user_can('edit_post', $reading_id)) {
            $target_week = isset($_POST['target_week_id']) ? absint($_POST['target_week_id']) : 0;
            $valid_target = false;
            foreach ($weeks as $candidate) {
                if ((int) $candidate->ID === $target_week) {
                    $valid_target = true;
                    break;
                }
            }
            if ($valid_target) {
                update_post_meta($reading_id, '_tfp_lesson_id', $target_week);
                $selected_reading_id = 0;
                $notice = __('Reading moved to the selected week.', 'tfp-dashboard');
            }
        }
    }

    $readings = get_posts([
        'post_type' => 'tfp_reading',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'meta_key' => '_tfp_lesson_id',
        'meta_value' => $week_id,
        'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
    ]);
    if (!$selected_reading_id && !empty($readings)) {
        $selected_reading_id = (int) $readings[0]->ID;
    }
    $selected_reading = null;
    foreach ($readings as $item) {
        if ((int) $item->ID === $selected_reading_id) {
            $selected_reading = $item;
            break;
        }
    }
    $course = $data['course'];
    $course_title = (int) $course_id === 1039 ? __('12 Week Foundation', 'tfp-dashboard') : ((int) $course_id === 1659 ? __('58 Week Discipleship', 'tfp-dashboard') : get_the_title($course_id));
    $week_number = 1;
    foreach ($weeks as $index => $candidate) {
        if ((int) $candidate->ID === $week_id) {
            $week_number = $index + 1;
            break;
        }
    }
    $plan_url = add_query_arg('course_tab', 'plan', tfp_admin_course_detail_url($course_id));
    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        unset($item['active']);
        if (($item['slug'] ?? '') === 'courses') {
            $item['url'] = function_exists('tfp_admin_courses_page_url') ? tfp_admin_courses_page_url() : home_url('/admin-courses/');
            $item['active'] = true;
        }
    }
    unset($item);
    ?>
    <div class="tfp-admin-app tfp-admin-course-detail-app tfp-admin-course-editor-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg></button></div>
            <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></a></div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url($avatar); ?>" alt=""><span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
        </aside>
        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar"><button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button><span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span></div>
            <div class="tfp-admin-breadcrumb">
                <a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b>
                <a href="<?php echo esc_url(function_exists('tfp_admin_courses_page_url') ? tfp_admin_courses_page_url() : home_url('/admin-courses/')); ?>"><?php esc_html_e('Course', 'tfp-dashboard'); ?></a><b>/</b>
                <a href="<?php echo esc_url($plan_url); ?>"><?php echo esc_html($course_title); ?></a><b>/</b>
                <a href="<?php echo esc_url($plan_url); ?>"><?php esc_html_e('Plan', 'tfp-dashboard'); ?></a><b>/</b>
                <strong class="tfp-admin-breadcrumb-current"><?php echo esc_html(sprintf(__('Week %d', 'tfp-dashboard'), $week_number)); ?></strong><b>/</b><strong class="tfp-admin-breadcrumb-current"><?php esc_html_e('Content Editor (reading)', 'tfp-dashboard'); ?></strong>
            </div>
            <header class="tfp-admin-page-header tfp-admin-course-editor-header">
                <div><h1><?php esc_html_e('Course Editor', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Modify the master curriculum template. Select a week below to begin.', 'tfp-dashboard'); ?></p></div>
                <div class="tfp-admin-course-editor-header-actions">
                    <?php if ($selected_reading) : ?><button type="button" class="tfp-editor-top-save" data-tfp-editor-save-trigger>▣ <?php esc_html_e('Save', 'tfp-dashboard'); ?></button><?php endif; ?>
                    <a class="tfp-editor-discard" href="<?php echo esc_url($plan_url); ?>"><?php esc_html_e('Discard Draft', 'tfp-dashboard'); ?></a>
                </div>
            </header>
            <main class="tfp-admin-content tfp-admin-course-editor-content">
                <?php if ($notice) : ?><div class="tfp-editor-notice" role="status"><?php echo esc_html($notice); ?></div><?php endif; ?>
                <form method="get" class="tfp-editor-week-switch">
                    <input type="hidden" name="week_editor" value="1">
                    <label class="screen-reader-text" for="tfp-editor-week-select"><?php esc_html_e('Switch week', 'tfp-dashboard'); ?></label>
                    <select id="tfp-editor-week-select" name="week_id" onchange="this.form.submit()">
                        <?php foreach ($weeks as $index => $item) : ?><option value="<?php echo esc_attr($item->ID); ?>" <?php selected($week_id, $item->ID); ?>><?php echo esc_html(sprintf(__('Week %d', 'tfp-dashboard'), $index + 1)); ?></option><?php endforeach; ?>
                    </select>
                    <noscript><button type="submit"><?php esc_html_e('Go', 'tfp-dashboard'); ?></button></noscript>
                </form>
                <nav class="tfp-editor-tabs" aria-label="<?php esc_attr_e('Content type', 'tfp-dashboard'); ?>"><a class="is-active" href="#tfp-reading-editor"><?php esc_html_e('Reading', 'tfp-dashboard'); ?></a><span aria-disabled="true"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></span><span aria-disabled="true"><?php esc_html_e('Test Bank', 'tfp-dashboard'); ?></span></nav>
                <section class="tfp-editor-workspace" id="tfp-reading-editor">
                    <div class="tfp-editor-workspace-heading"><div><h2><?php esc_html_e('Reading Editor', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Edit the scripture list for the week', 'tfp-dashboard'); ?></p></div><span class="tfp-editor-count"><?php echo esc_html(sprintf(__('%d items', 'tfp-dashboard'), count($readings))); ?></span></div>
                    <div class="tfp-editor-columns">
                        <section class="tfp-editor-list-panel"><h3><?php esc_html_e('Scrollable reading list', 'tfp-dashboard'); ?></h3><div class="tfp-editor-reading-list">
                            <?php foreach ($readings as $item) : ?><a class="tfp-editor-reading-item<?php echo (int) $item->ID === $selected_reading_id ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['week_editor' => '1', 'week_id' => $week_id, 'reading_id' => $item->ID], tfp_admin_course_detail_url($course_id))); ?>"><strong><?php echo esc_html($item->post_title); ?></strong><small><?php echo 'publish' === $item->post_status ? esc_html__('Template published and ready for assignment', 'tfp-dashboard') : esc_html(ucfirst($item->post_status)); ?></small></a><?php endforeach; ?>
                            <?php if (empty($readings)) : ?><p class="tfp-editor-empty"><?php esc_html_e('No reading passages are assigned to this week yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div></section>
                        <section class="tfp-editor-edit-panel"><h3><?php esc_html_e('Edit Reading', 'tfp-dashboard'); ?></h3>
                            <?php if ($selected_reading) : ?>
                            <form method="post" class="tfp-editor-reading-form"><?php wp_nonce_field('tfp_course_editor_' . $course_id . '_' . $week_id); ?><input type="hidden" name="tfp_course_editor_action" value="save"><input type="hidden" name="reading_id" value="<?php echo esc_attr($selected_reading->ID); ?>">
                                <label for="tfp-reading-title"><?php esc_html_e('Reference', 'tfp-dashboard'); ?></label><input id="tfp-reading-title" type="text" name="reading_title" value="<?php echo esc_attr($selected_reading->post_title); ?>">
                                <label for="tfp-reading-content"><?php esc_html_e('Scripture Text', 'tfp-dashboard'); ?></label><textarea id="tfp-reading-content" name="reading_content" rows="6"><?php echo esc_textarea($selected_reading->post_content); ?></textarea>
                                <div class="tfp-editor-actions"><button type="submit" class="tfp-editor-save">▣ <?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                                    <button type="submit" name="tfp_course_editor_action" value="duplicate" class="tfp-editor-secondary"><?php esc_html_e('Duplicate', 'tfp-dashboard'); ?></button>
                                    <button type="button" class="tfp-editor-secondary" data-tfp-editor-move-toggle><?php esc_html_e('↕ Move', 'tfp-dashboard'); ?></button>
                                </div>
                                <div class="tfp-editor-move-row" hidden><label for="tfp-editor-target-week"><?php esc_html_e('Move to week', 'tfp-dashboard'); ?></label><select id="tfp-editor-target-week" name="target_week_id"><?php foreach ($weeks as $index => $item) : if ((int) $item->ID === $week_id) continue; ?><option value="<?php echo esc_attr($item->ID); ?>"><?php echo esc_html(sprintf(__('Week %d', 'tfp-dashboard'), $index + 1)); ?></option><?php endforeach; ?></select><button type="submit" name="tfp_course_editor_action" value="move" class="tfp-editor-save"><?php esc_html_e('Confirm Move', 'tfp-dashboard'); ?></button></div>
                            </form>
                            <?php else : ?><p class="tfp-editor-empty"><?php esc_html_e('Select a reading passage from the list to edit it.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </section>
                    </div>
                    <div class="tfp-editor-footnote"><?php esc_html_e('This editor is for reading passages only.', 'tfp-dashboard'); ?></div>
                </section>
            </main>
        </div>
    </div>
    <?php
}
