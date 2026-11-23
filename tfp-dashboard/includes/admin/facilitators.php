<?php
/**
 * Admin Facilitators overview and directory.
 * Uses existing WordPress facilitator accounts, user meta and activity audit records.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_facilitators_url() {
    return home_url('/admin-facilitators/');
}

add_filter('pre_handle_404', function ($preempt, $query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-facilitators') {
        if ($query) { $query->is_404 = false; $query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 13, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-facilitators') { return; }
    status_header(200);
    nocache_headers();
    $template = TFP_DASH_PATH . 'templates/template-admin-facilitators.php';
    if (is_readable($template)) { include $template; exit; }
}, 1);

/** Fetch facilitator records from existing WordPress accounts. */
function tfp_admin_facilitators_get_data() {
    $users = get_users([
        'role__in' => ['facilitator', 'group_leader'],
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => -1,
    ]);

    $records = [];
    $categories = [];
    $active_week = 0;
    $inactive_week = 0;
    $pending_onboarding = 0;
    $inactive_alerts = [];
    $flag_alerts = [];
    $now = current_time('timestamp');

    foreach ($users as $person) {
        $deactivated = (bool) get_user_meta($person->ID, '_tfp_deactivated', true);
        $last_login = (string) get_user_meta($person->ID, '_tfp_last_login', true);
        $last_login_ts = $last_login ? strtotime($last_login) : 0;
        $category = trim((string) get_user_meta($person->ID, 'tfp_system', true));
        if (function_exists('tfp_get_profile_completion')) {
            $completion = (int) tfp_get_profile_completion($person);
        } else {
            // Fallback to the established profile fields if the authentication helper is unavailable.
            $required = [
                trim((string) get_user_meta($person->ID, 'first_name', true)),
                trim((string) get_user_meta($person->ID, 'last_name', true)),
                trim((string) $person->user_email),
                trim((string) get_user_meta($person->ID, 'billing_phone', true)),
                absint(get_user_meta($person->ID, 'tfp_program_choice', true)),
            ];
            $completed = count(array_filter($required, static function ($value) { return !empty($value); }));
            $completion = (int) round(($completed / count($required)) * 100);
        }
        $flags = get_user_meta($person->ID, 'tfp_flags', true);
        if ($flags === '' || $flags === false) { $flags = get_user_meta($person->ID, '_tfp_flags', true); }
        $has_flags = is_array($flags) ? !empty($flags) : (absint($flags) > 0 || (is_string($flags) && trim($flags) !== ''));

        if ($category !== '') {
            if (!isset($categories[$category])) { $categories[$category] = 0; }
            $categories[$category]++;
        }
        if (!$deactivated && $last_login_ts && $last_login_ts >= ($now - WEEK_IN_SECONDS)) { $active_week++; }
        if (!$deactivated && $completion < 100) { $pending_onboarding++; }
        if (!$deactivated && (!$last_login_ts || $last_login_ts < ($now - (7 * DAY_IN_SECONDS)))) {
            $inactive_week++;
            $inactive_alerts[] = [
                'title' => __('Inactive Facilitator', 'tfp-dashboard'),
                'name' => $person->display_name,
                'detail' => $last_login_ts
                    ? sprintf(__('No login for %s days', 'tfp-dashboard'), (int) floor(($now - $last_login_ts) / DAY_IN_SECONDS))
                    : __('No recorded login yet', 'tfp-dashboard'),
                'time' => $last_login_ts ? $last_login_ts : 0,
            ];
        }
        if ($has_flags) {
            $flag_alerts[] = [
                'title' => __('Facilitator Flag', 'tfp-dashboard'),
                'name' => $person->display_name,
                'detail' => is_array($flags) ? implode(', ', array_filter(array_map(static function ($flag) { return is_scalar($flag) ? sanitize_text_field((string) $flag) : ''; }, $flags))) : (is_string($flags) ? $flags : __('A facilitator account has an active flag.', 'tfp-dashboard')),
                'time' => $last_login_ts,
            ];
        }

        $cohorts = get_posts([
            'post_type' => 'tfp_cohort',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'relation' => 'OR',
                ['key' => '_facilitator_user_id', 'value' => $person->ID, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => '_facilitator', 'value' => $person->display_name, 'compare' => '='],
            ]],
        ]);

        $records[] = [
            'id' => $person->ID,
            'name' => $person->display_name ?: $person->user_login,
            'email' => $person->user_email,
            'category' => $category ?: __('Unassigned', 'tfp-dashboard'),
            'cohorts' => count($cohorts),
            'completion' => max(0, min(100, $completion)),
            'last_login' => $last_login_ts,
            'last_login_label' => $last_login_ts ? wp_date('M j, g:i A', $last_login_ts) : __('Never', 'tfp-dashboard'),
            'status' => $deactivated ? 'inactive' : 'active',
            'edit_url' => get_edit_user_link($person->ID),
        ];
    }

    uasort($categories, static function ($a, $b) { return $b <=> $a; });

    // Load only genuine course-related audit events for facilitator accounts.
    $recent_activity = [];
    global $wpdb;
    if ($users && function_exists('tfp_user_activity_table')) {
        $table = tfp_user_activity_table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists === $table) {
            $ids = array_map('absint', wp_list_pluck($users, 'ID'));
            $types = ['course', 'homework', 'quiz', 'test', 'cohort', 'flag'];
            $sql = 'SELECT a.user_id, a.event_type, a.event_label, a.context, a.created_at, u.display_name
                FROM ' . $table . ' a INNER JOIN ' . $wpdb->users . ' u ON u.ID = a.user_id
                WHERE a.user_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')
                AND a.event_type IN (' . implode(',', array_fill(0, count($types), '%s')) . ')
                ORDER BY a.created_at DESC LIMIT 8';
            $recent_activity = $wpdb->get_results($wpdb->prepare($sql, array_merge($ids, $types)));
        }
    }

    $alerts = array_merge($inactive_alerts, $flag_alerts);
    usort($alerts, static function ($a, $b) { return ($b['time'] ?? 0) <=> ($a['time'] ?? 0); });

    // Demo records are display-only: they are never inserted into WordPress users, cohorts or activity tables.
    // Show them when explicitly requested (?demo=1), or as a preview when the site has no facilitator accounts.
    $demo_mode = (isset($_GET['demo']) && sanitize_text_field(wp_unslash($_GET['demo'])) === '1') || empty($users);
    if ($demo_mode) {
        $demo_now = current_time('timestamp');
        $records = [
            ['id' => 0, 'name' => 'David Osei', 'email' => 'david.osei@example.com', 'category' => 'Cultural Intelligence', 'cohorts' => 2, 'completion' => 100, 'last_login' => $demo_now - DAY_IN_SECONDS, 'last_login_label' => __('Yesterday, 4:20 PM', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
            ['id' => 0, 'name' => 'Marcus Chen', 'email' => 'marcus.chen@example.com', 'category' => 'Discipleship', 'cohorts' => 3, 'completion' => 80, 'last_login' => $demo_now - (2 * DAY_IN_SECONDS), 'last_login_label' => __('2 days ago', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
            ['id' => 0, 'name' => 'Priya Nair', 'email' => 'priya.nair@example.com', 'category' => 'Leadership', 'cohorts' => 1, 'completion' => 60, 'last_login' => $demo_now - (4 * DAY_IN_SECONDS), 'last_login_label' => __('4 days ago', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
            ['id' => 0, 'name' => 'Sara Pooha', 'email' => 'sara.pooha@example.com', 'category' => 'Kingdom Business', 'cohorts' => 2, 'completion' => 100, 'last_login' => $demo_now - (9 * DAY_IN_SECONDS), 'last_login_label' => __('9 days ago', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
            ['id' => 0, 'name' => 'Tanisha Brooks', 'email' => 'tanisha.brooks@example.com', 'category' => 'Kingdom Expansion', 'cohorts' => 1, 'completion' => 40, 'last_login' => $demo_now - (12 * DAY_IN_SECONDS), 'last_login_label' => __('12 days ago', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
            ['id' => 0, 'name' => 'James Walker', 'email' => 'james.walker@example.com', 'category' => 'Discipleship', 'cohorts' => 1, 'completion' => 100, 'last_login' => $demo_now - (3 * HOUR_IN_SECONDS), 'last_login_label' => __('Today, 9:15 AM', 'tfp-dashboard'), 'status' => 'active', 'edit_url' => '#'],
        ];
        $categories = [
            'Discipleship' => 2,
            'Leadership' => 1,
            'Kingdom Business' => 1,
            'Kingdom Expansion' => 1,
            'Cultural Intelligence' => 1,
        ];
        $active_week = 4;
        $inactive_week = 2;
        $pending_onboarding = 2;
        $inactive_alerts = [
            ['title' => __('Inactive Facilitator', 'tfp-dashboard'), 'name' => 'David Osei', 'detail' => __('No login for 9 days · Cultural Intelligence', 'tfp-dashboard'), 'time' => $demo_now - (9 * DAY_IN_SECONDS)],
        ];
        $flag_alerts = [
            ['title' => __('Missed Post Deadline', 'tfp-dashboard'), 'name' => 'Marcus Chen', 'detail' => __('Missed Week 5 post window · DISC-FALL-PM', 'tfp-dashboard'), 'time' => $demo_now - DAY_IN_SECONDS],
            ['title' => __('Invitation Expiring', 'tfp-dashboard'), 'name' => '2 invitations', 'detail' => __('Expire within 48 hours · no response yet', 'tfp-dashboard'), 'time' => $demo_now],
            ['title' => __('Onboarding Complete', 'tfp-dashboard'), 'name' => 'Priya Nair', 'detail' => __('Completed all onboarding steps · ready to lead', 'tfp-dashboard'), 'time' => $demo_now - (2 * HOUR_IN_SECONDS)],
            ['title' => __('Onboarding Complete', 'tfp-dashboard'), 'name' => 'Sara Pooha', 'detail' => __('Completed all onboarding steps · ready to lead', 'tfp-dashboard'), 'time' => $demo_now - (3 * HOUR_IN_SECONDS)],
        ];
        $recent_activity = [
            (object) ['display_name' => 'Marcus Chen', 'event_label' => __('Reviewed 12 homework submissions', 'tfp-dashboard'), 'context' => '12WK-FOUND-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (2 * HOUR_IN_SECONDS))],
            (object) ['display_name' => 'Marcus Chen', 'event_label' => __('Reviewed 12 homework submissions', 'tfp-dashboard'), 'context' => '12WK-FOUND-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (5 * HOUR_IN_SECONDS))],
            (object) ['display_name' => 'Assessment set added to Week 6', 'event_label' => __('Posted Week 14 teaching', 'tfp-dashboard'), 'context' => 'LEAD-SPRING-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - DAY_IN_SECONDS)],
            (object) ['display_name' => 'Priya Nair', 'event_label' => __('Completed onboarding and was assigned', 'tfp-dashboard'), 'context' => 'BIZ-COHORT-01', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (2 * DAY_IN_SECONDS))],
            (object) ['display_name' => 'Tanisha Brooks', 'event_label' => __('Flagged 2 disciples for follow-up in', 'tfp-dashboard'), 'context' => 'KE-COHORT-02', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (3 * DAY_IN_SECONDS))],
            (object) ['display_name' => 'Marcus Chen', 'event_label' => __('Reviewed 12 homework submissions', 'tfp-dashboard'), 'context' => '12WK-FOUND-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (4 * DAY_IN_SECONDS))],
            (object) ['display_name' => 'Marcus Chen', 'event_label' => __('Reviewed 12 homework submissions', 'tfp-dashboard'), 'context' => '12WK-FOUND-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (5 * DAY_IN_SECONDS))],
            (object) ['display_name' => 'Marcus Chen', 'event_label' => __('Reviewed 12 homework submissions', 'tfp-dashboard'), 'context' => '12WK-FOUND-AM', 'created_at' => gmdate('Y-m-d H:i:s', $demo_now - (6 * DAY_IN_SECONDS))],
        ];
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
    if (!in_array($tab, ['overview', 'active', 'onboarding', 'invitations'], true)) { $tab = 'overview'; }

    $filtered = $records;
    if ($tab === 'active') {
        $filtered = array_values(array_filter($records, static function ($row) { return $row['status'] === 'active'; }));
    } elseif ($tab === 'onboarding') {
        $filtered = array_values(array_filter($records, static function ($row) { return $row['status'] === 'active' && $row['completion'] < 100; }));
    }

    return [
        'records' => $records,
        'categories' => $categories,
        'active_week' => $active_week,
        'inactive_week' => $inactive_week,
        'pending_onboarding' => $pending_onboarding,
        'alerts' => array_slice($alerts, 0, 6),
        'recent_activity' => $recent_activity,
        'total' => count($records),
        'invitation_count' => $demo_mode ? 1 : 0,
        'is_demo' => $demo_mode,
        'tab' => $tab,
        'filtered' => $filtered,
    ];
}

/** Render the Facilitators page in the shared Admin application shell. */
function tfp_admin_facilitators_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    if (function_exists('tfp_admin_dashboard_enqueue_assets')) { tfp_admin_dashboard_enqueue_assets(); }
    $data = tfp_admin_facilitators_get_data();
    $user = wp_get_current_user();
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        $item['active'] = ($item['slug'] ?? '') === 'facilitators';
        if (($item['slug'] ?? '') === 'facilitators') { $item['url'] = tfp_admin_facilitators_url(); }
    }
    unset($item);

    $tabs = [
        'overview' => __('Overview', 'tfp-dashboard'),
        'active' => __('Active Facilitators', 'tfp-dashboard'),
        'onboarding' => __('Onboarding', 'tfp-dashboard'),
        'invitations' => __('Invitations', 'tfp-dashboard'),
    ];
    ?>
    <div class="tfp-admin-app tfp-admin-facilitators-app">
      <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt=""></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
          <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : ''; ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($user->ID, ['size' => 96])); ?>" alt=""><span><strong><?php echo esc_html($user->display_name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
      </aside>
      <div class="tfp-admin-main">
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><strong><?php esc_html_e('Facilitators', 'tfp-dashboard'); ?></strong><?php if ($data['tab'] !== 'overview') : ?><b>/</b><strong><?php echo esc_html($tabs[$data['tab']]); ?></strong><?php endif; ?></div>
        <header class="tfp-admin-page-header tfp-admin-facilitators-header"><div><h1><?php esc_html_e('Facilitators', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Manage facilitators, track onboarding, and facilitator activity across every cohort.', 'tfp-dashboard'); ?></p></div><a class="tfp-admin-add-user-btn tfp-admin-facilitators-add" href="<?php echo esc_url(admin_url('user-new.php')); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Facilitators', 'tfp-dashboard'); ?></a></header>
        <main class="tfp-admin-content tfp-admin-facilitators-content">
          <nav class="tfp-admin-facilitators-tabs" aria-label="<?php esc_attr_e('Facilitator sections', 'tfp-dashboard'); ?>">
            <?php foreach ($tabs as $key => $label) : ?><a class="<?php echo $data['tab'] === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', $key, tfp_admin_facilitators_url())); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
          </nav>
          <?php if (!empty($data['is_demo'])) : ?>
            <div class="tfp-admin-facilitators-demo-notice"><strong><?php esc_html_e('Demo data preview', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Sample facilitator records are shown for design preview only. No demo records have been saved to your website.', 'tfp-dashboard'); ?></span></div>
          <?php endif; ?>

          <?php if ($data['tab'] === 'overview') : ?>
            <section class="tfp-admin-facilitators-stats" aria-label="<?php esc_attr_e('Facilitator statistics', 'tfp-dashboard'); ?>">
              <article class="tfp-admin-stat-card"><small><?php esc_html_e('Total Facilitators', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($data['total'])); ?></strong></div><p><?php esc_html_e('Across all Categories', 'tfp-dashboard'); ?></p></article>
              <article class="tfp-admin-stat-card"><small><?php esc_html_e('Active This Week', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($data['active_week'])); ?></strong></div><p><?php echo esc_html(sprintf(__('%d inactive 7+ days', 'tfp-dashboard'), $data['inactive_week'])); ?></p></article>
              <article class="tfp-admin-stat-card"><small><?php esc_html_e('Pending Onboarding', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($data['pending_onboarding'])); ?></strong></div><p><?php esc_html_e('Steps incomplete', 'tfp-dashboard'); ?></p></article>
              <article class="tfp-admin-stat-card"><small><?php esc_html_e('Open Invitations', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($data['invitation_count'])); ?></strong></div><p><?php esc_html_e('Invitation tracking not connected', 'tfp-dashboard'); ?></p></article>
            </section>

            <div class="tfp-admin-facilitators-overview-grid">
              <section class="tfp-admin-panel tfp-admin-facilitators-category-panel">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Category Coverage', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Number of Facilitators inside of each Category', 'tfp-dashboard'); ?></p></div></header>
                <div class="tfp-admin-facilitators-category-list">
                  <?php if ($data['categories']) : $max_category = max($data['categories']); foreach ($data['categories'] as $category => $count) : ?>
                    <div class="tfp-admin-facilitators-category"><div><span><?php echo esc_html($category); ?></span><strong><?php echo esc_html(sprintf(_n('%s facilitator', '%s facilitators', $count, 'tfp-dashboard'), number_format_i18n($count))); ?></strong></div><div class="tfp-admin-facilitators-bar"><span style="width:<?php echo esc_attr((100 * $count) / max(1, $max_category)); ?>%"></span></div></div>
                  <?php endforeach; else : ?><p class="tfp-admin-facilitators-empty"><?php esc_html_e('No facilitator categories have been assigned yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
                </div>
              </section>

              <section class="tfp-admin-panel tfp-admin-facilitators-alert-panel">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Flags & Alerts', 'tfp-dashboard'); ?></h2></div></header>
                <div class="tfp-admin-facilitators-alert-list">
                  <?php if ($data['alerts']) : foreach ($data['alerts'] as $index => $alert) : ?>
                    <article class="tfp-admin-facilitators-alert<?php echo $index === 0 ? ' is-highlighted' : ''; ?>"><strong><?php echo esc_html($alert['title']); ?></strong><span><?php echo esc_html($alert['name'] . ' · ' . $alert['detail']); ?></span></article>
                  <?php endforeach; else : ?><p class="tfp-admin-facilitators-empty"><?php esc_html_e('No facilitator flags or alerts are currently recorded.', 'tfp-dashboard'); ?></p><?php endif; ?>
                </div>
              </section>
            </div>

            <section class="tfp-admin-panel tfp-admin-facilitators-recent-panel">
              <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Recent Course Activity', 'tfp-dashboard'); ?></h2></div></header>
              <div class="tfp-admin-facilitators-recent-list">
                <?php if ($data['recent_activity']) : foreach ($data['recent_activity'] as $event) : ?>
                  <article class="tfp-admin-facilitators-recent-row"><div><strong><?php echo esc_html($event->display_name); ?></strong><span><?php echo esc_html(trim((string) $event->event_label . ((string) $event->context !== '' ? ' · ' . (string) $event->context : ''))); ?></span></div><time><?php echo esc_html(wp_date('M j, g:i A T', strtotime($event->created_at . ' UTC'))); ?></time></article>
                <?php endforeach; else : ?><p class="tfp-admin-facilitators-empty"><?php esc_html_e('No course activity is recorded for facilitators yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
              </div>
            </section>

          <?php elseif ($data['tab'] === 'invitations') : ?>
            <section class="tfp-admin-panel tfp-admin-facilitators-directory-panel">
              <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-facilitators-table">
                <thead><tr><th><?php esc_html_e('Invitee', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Invited By', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Expires', 'tfp-dashboard'); ?></th></tr></thead>
                <tbody>
                  <?php if (!empty($data['is_demo'])) : ?>
                    <tr><td><strong>Alex Morgan</strong><small>alex.morgan@example.com</small></td><td>Discipleship</td><td>Shannon Stiggins</td><td><span class="tfp-admin-facilitators-invite-status">Pending</span></td><td><?php echo esc_html(wp_date('M j, Y', current_time('timestamp') + (2 * DAY_IN_SECONDS))); ?></td></tr>
                  <?php else : ?>
                    <tr><td colspan="5" class="tfp-admin-overview-empty"><?php esc_html_e('No invitation records are available. Invitation tracking is not connected yet.', 'tfp-dashboard'); ?></td></tr>
                  <?php endif; ?>
                </tbody>
              </table></div>
            </section>

          <?php else : ?>
            <section class="tfp-admin-panel tfp-admin-facilitators-directory-panel">
              <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-facilitators-table">
                <thead><tr><th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Assigned Cohorts', 'tfp-dashboard'); ?></th><?php if ($data['tab'] === 'onboarding') : ?><th><?php esc_html_e('Onboarding', 'tfp-dashboard'); ?></th><?php endif; ?><th><?php esc_html_e('Last Active', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                <tbody>
                  <?php if ($data['filtered']) : foreach ($data['filtered'] as $record) : ?>
                    <tr><td><strong><?php echo esc_html($record['name']); ?></strong><small><?php echo esc_html($record['email']); ?></small></td><td><?php echo esc_html($record['category']); ?></td><td><?php echo esc_html(number_format_i18n($record['cohorts'])); ?></td><?php if ($data['tab'] === 'onboarding') : ?><td><div class="tfp-admin-facilitators-progress"><span style="width:<?php echo esc_attr($record['completion']); ?>%"></span></div><small><?php echo esc_html($record['completion']); ?>%</small></td><?php endif; ?><td><?php echo esc_html($record['last_login_label']); ?></td><td><a class="tfp-admin-overview-view" href="<?php echo esc_url($record['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td></tr>
                  <?php endforeach; else : ?><tr><td colspan="<?php echo $data['tab'] === 'onboarding' ? '6' : '5'; ?>" class="tfp-admin-overview-empty"><?php esc_html_e('No facilitator records found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                </tbody>
              </table></div>
            </section>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <?php
}
