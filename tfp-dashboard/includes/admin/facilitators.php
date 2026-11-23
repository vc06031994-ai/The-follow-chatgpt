<?php
/**
 * Admin Facilitators overview and directory.
 * Uses existing WordPress facilitator accounts, user meta and activity audit records.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_facilitators_url() {
    return home_url('/admin-facilitators/');
}

/**
 * Persist facilitator compensation and manually recorded payout entries.
 * Recording a payout here is an audit entry only; it does not transfer funds.
 */
function tfp_admin_save_facilitator_payment() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to manage facilitator payments.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $facilitator_id = isset($_POST['facilitator_id']) ? absint($_POST['facilitator_id']) : 0;
    $facilitator = $facilitator_id ? get_userdata($facilitator_id) : false;
    if (!$facilitator || !array_intersect(['facilitator', 'group_leader'], (array) $facilitator->roles)) {
        wp_die(esc_html__('Invalid facilitator account.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    check_admin_referer('tfp_facilitator_payment_' . $facilitator_id);
    $mode = isset($_POST['payment_mode']) ? sanitize_key(wp_unslash($_POST['payment_mode'])) : '';

    if ($mode === 'save_compensation') {
        $type = isset($_POST['compensation_type']) ? sanitize_key(wp_unslash($_POST['compensation_type'])) : 'per_student';
        $rate = isset($_POST['pay_rate']) ? wp_unslash($_POST['pay_rate']) : '';
        $bonus = isset($_POST['completion_bonus']) ? wp_unslash($_POST['completion_bonus']) : '';

        if (!in_array($type, ['per_student', 'flat_rate'], true) ||
            !is_numeric($rate) || (float) $rate < 0 ||
            ($bonus !== '' && (!is_numeric($bonus) || (float) $bonus < 0))) {
            wp_die(esc_html__('Please enter valid compensation values.', 'tfp-dashboard'), '', ['response' => 400]);
        }

        update_user_meta($facilitator_id, 'tfp_compensation_type', $type);
        update_user_meta($facilitator_id, 'tfp_pay_rate', number_format((float) $rate, 2, '.', ''));
        update_user_meta($facilitator_id, 'tfp_completion_bonus', $bonus === '' ? '' : number_format((float) $bonus, 2, '.', ''));
        $notice = 'compensation_saved';
    } elseif ($mode === 'record_payout') {
        $amount = isset($_POST['payout_amount']) ? wp_unslash($_POST['payout_amount']) : '';
        $cohort = isset($_POST['payout_cohort']) ? sanitize_text_field(wp_unslash($_POST['payout_cohort'])) : '';
        $date = isset($_POST['payout_date']) ? sanitize_text_field(wp_unslash($_POST['payout_date'])) : '';

        $date_object = DateTime::createFromFormat('Y-m-d', $date);
        if (!is_numeric($amount) || (float) $amount <= 0 || $cohort === '' ||
            !$date_object || $date_object->format('Y-m-d') !== $date) {
            wp_die(esc_html__('Please enter a valid payout amount, cohort and date.', 'tfp-dashboard'), '', ['response' => 400]);
        }

        $rows = get_user_meta($facilitator_id, 'tfp_payout_log', true);
        if (!is_array($rows)) { $rows = []; }
        $rows[] = [
            'cohort' => $cohort,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'date' => $date,
            'time' => current_time('g:i A T'),
            'recorded_by' => get_current_user_id(),
            'recorded_at' => current_time('mysql'),
        ];
        usort($rows, static function ($a, $b) {
            return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? ''));
        });
        update_user_meta($facilitator_id, 'tfp_payout_log', $rows);
        update_user_meta($facilitator_id, 'tfp_total_paid_to_date', number_format(array_sum(array_map(static function ($row) {
            return isset($row['amount']) && is_numeric($row['amount']) ? (float) $row['amount'] : 0;
        }, $rows)), 2, '.', ''));
        $latest = $rows[0];
        update_user_meta($facilitator_id, 'tfp_last_payment_amount', $latest['amount']);
        update_user_meta($facilitator_id, 'tfp_last_payment_date', $latest['date']);
        $notice = 'payout_recorded';
    } else {
        wp_die(esc_html__('Invalid payment action.', 'tfp-dashboard'), '', ['response' => 400]);
    }

    $url = add_query_arg([
        'facilitator_id' => $facilitator_id,
        'profile_tab' => 'payment',
        'payment_notice' => $notice,
    ], tfp_admin_facilitators_url());
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_tfp_save_facilitator_payment', 'tfp_admin_save_facilitator_payment');


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
    $invitation_count = 0; // Invitation records are not yet stored by this project.
    $inactive_alerts = [];
    $flag_alerts = [];
    $now = current_time('timestamp');

    // Resolve cohort assignments once, instead of querying cohorts separately for every facilitator.
    $cohort_assignments = [];
    $facilitator_cohort_ids = [];
    $cohort_ids = get_posts([
        'post_type' => 'tfp_cohort',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);
    foreach ($cohort_ids as $cohort_id) {
        $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id')
            ? absint(tfp_cohort_get_facilitator_user_id($cohort_id))
            : absint(get_post_meta($cohort_id, '_facilitator_user_id', true));

        // Preserve legacy cohort assignments when the shared resolver is unavailable.
        if (!$facilitator_id && !function_exists('tfp_cohort_get_facilitator_user_id')) {
            $legacy_name = trim((string) get_post_meta($cohort_id, '_facilitator', true));
            if ($legacy_name !== '') {
                foreach ($users as $candidate) {
                    if (strcasecmp($legacy_name, (string) $candidate->display_name) === 0) {
                        $facilitator_id = (int) $candidate->ID;
                        break;
                    }
                }
            }
        }

        if ($facilitator_id) {
            if (!isset($cohort_assignments[$facilitator_id])) {
                $cohort_assignments[$facilitator_id] = 0;
                $facilitator_cohort_ids[$facilitator_id] = [];
            }
            $cohort_assignments[$facilitator_id]++;
            $facilitator_cohort_ids[$facilitator_id][] = (int) $cohort_id;
        }
    }

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

        // Check LearnDash course completion when the installed LearnDash APIs are available.
        $training_complete = false;
        if (function_exists('learndash_user_get_enrolled_courses') && function_exists('learndash_is_course_complete')) {
            $enrolled_courses = learndash_user_get_enrolled_courses($person->ID);
            if (is_array($enrolled_courses) && !empty($enrolled_courses)) {
                $training_complete = true;
                foreach ($enrolled_courses as $enrolled_course_id) {
                    if (!learndash_is_course_complete($person->ID, absint($enrolled_course_id))) {
                        $training_complete = false;
                        break;
                    }
                }
            }
        }

        // Count unique enrolled students across this facilitator's cohorts.
        // Cohort choices are validated against paid status when the billing helper exists.
        $student_ids = [];
        foreach (($facilitator_cohort_ids[$person->ID] ?? []) as $assigned_cohort_id) {
            $cohort_student_ids = get_users([
                'meta_key' => 'tfp_cohort_choice',
                'meta_value' => $assigned_cohort_id,
                'fields' => 'ID',
                'number' => -1,
            ]);
            foreach ($cohort_student_ids as $cohort_student_id) {
                $cohort_student_id = absint($cohort_student_id);
                if ($cohort_student_id && (!function_exists('tfp_billing_user_has_paid') || tfp_billing_user_has_paid($cohort_student_id))) {
                    $student_ids[$cohort_student_id] = true;
                }
            }
        }

        $records[] = [
            'id' => $person->ID,
            'name' => $person->display_name ?: $person->user_login,
            'email' => $person->user_email,
            'avatar' => get_avatar_url($person->ID, ['size' => 96]),
            'registered_label' => $person->user_registered ? wp_date('M j', strtotime($person->user_registered)) : '',
            'training_complete' => $training_complete,
            'category' => $category ?: __('Unassigned', 'tfp-dashboard'),
            'cohorts' => (int) ($cohort_assignments[$person->ID] ?? 0),
            'students' => count($student_ids),
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

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
    if (!in_array($tab, ['overview', 'active', 'onboarding', 'invitations'], true)) { $tab = 'overview'; }

    // Build both directories for the Facilitators tab so they appear together.
    $active_rows = array_values(array_filter($records, static function ($row) {
        return $row['status'] === 'active';
    }));
    $pending_rows = array_values(array_filter($records, static function ($row) {
        return $row['status'] === 'active' && $row['completion'] < 100;
    }));

    $active_demo = false;
    $pending_demo = false;

    // Build onboarding cards from real WordPress facilitator records.
    $onboarding_rows = [];
    foreach ($records as $record) {
        if ($record['status'] !== 'active') { continue; }
        $steps = [
            'account_created' => true,
            'profile_complete' => $record['completion'] >= 100,
            'training_complete' => !empty($record['training_complete']),
            'cohort_assigned' => $record['cohorts'] > 0,
        ];
        $completed_steps = count(array_filter($steps));
        if ($completed_steps >= 4) { continue; }
        $needs_review = !$record['last_login'] || $record['last_login'] < ($now - (5 * DAY_IN_SECONDS));
        $record['steps'] = $steps;
        $record['completed_steps'] = $completed_steps;
        $record['progress'] = (int) round(($completed_steps / 4) * 100);
        $record['onboarding_status'] = $needs_review ? 'review' : 'progress';
        $record['onboarding_status_label'] = $needs_review ? __('Needs Review', 'tfp-dashboard') : __('In Progress', 'tfp-dashboard');
        $onboarding_rows[] = $record;
    }

    // Onboarding cards are populated only from real WordPress facilitator records.
    $onboarding_demo = false;

    $filtered = $records;
    if ($tab === 'active') {
        $filtered = $active_rows;
    } elseif ($tab === 'onboarding') {
        $filtered = $pending_rows;
    }

    return [
        'records' => $records,
        'categories' => $categories,
        'active_week' => $active_week,
        'inactive_week' => $inactive_week,
        'pending_onboarding' => $pending_onboarding,
        'invitation_count' => $invitation_count,
        'alerts' => array_slice($alerts, 0, 6),
        'recent_activity' => $recent_activity,
        'total' => count($records),
        'tab' => $tab,
        'filtered' => $filtered,
        'is_demo_data' => $is_demo_data ?? false,
        'active_rows' => $active_rows,
        'pending_rows' => $pending_rows,
        'onboarding_rows' => $onboarding_rows,
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
    $detail_demo = isset($_GET['demo']) && sanitize_key(wp_unslash($_GET['demo'])) === '1';
    $detail_id = isset($_GET['facilitator_id']) ? absint($_GET['facilitator_id']) : 0;
    $detail_person = $detail_demo
        ? (object) ['ID' => 0, 'display_name' => 'Shannon Stiggins', 'user_email' => 'ShannonEmails@gmail.com', 'user_registered' => '2026-04-12 09:00:00', 'roles' => ['facilitator']]
        : ($detail_id ? get_user_by('id', $detail_id) : false);
    if (!$detail_demo && $detail_id && (!$detail_person || !array_intersect(['facilitator', 'group_leader'], (array) $detail_person->roles))) {
        wp_die(esc_html__('Facilitator account not found.', 'tfp-dashboard'), esc_html__('Not found', 'tfp-dashboard'), ['response' => 404]);
    }
    $detail_tab = isset($_GET['profile_tab']) ? sanitize_key(wp_unslash($_GET['profile_tab'])) : 'onboarding';
    if (!in_array($detail_tab, ['onboarding', 'payment', 'activity'], true)) { $detail_tab = 'onboarding'; }
    $detail_category = $detail_demo ? 'Discipleship' : trim((string) get_user_meta($detail_id, 'tfp_system', true));
    $detail_roles = (array) get_user_meta($detail_id, 'tfp_assigned_roles', true);
    if ($detail_demo) { $detail_roles = ['Senior Facilitator', 'Facilitator']; }
    if (!$detail_roles) { $detail_roles = (array) $detail_person->roles; }
    $detail_categories = (array) get_user_meta($detail_id, 'tfp_assigned_categories', true);
    if ($detail_demo) { $detail_categories = ['Discipleship', 'Leadership']; }
    if (!$detail_categories && $detail_category !== '') { $detail_categories = [$detail_category]; }
    $detail_notes = $detail_demo ? 'Strong communicator. Recommended by Pastor James. Cleared for Discipleship + Leadership tracks.' : (string) get_user_meta($detail_id, 'tfp_admin_notes', true);
    $detail_cohorts = [];
    if ($detail_id) {
        $assigned_cohort_ids = get_posts([
            'post_type' => 'tfp_cohort',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        foreach ($assigned_cohort_ids as $assigned_cohort_id) {
            $assigned_facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id')
                ? absint(tfp_cohort_get_facilitator_user_id($assigned_cohort_id))
                : absint(get_post_meta($assigned_cohort_id, '_facilitator_user_id', true));
            if ($assigned_facilitator_id === $detail_id) {
                $assigned_cohort = get_post($assigned_cohort_id);
                if ($assigned_cohort) { $detail_cohorts[] = $assigned_cohort; }
            }
        }
    }
    $detail_profile_complete = $detail_demo ? 50 : (function_exists('tfp_get_profile_completion') ? (int) tfp_get_profile_completion($detail_person) : 0);
    $detail_training_complete = false;
    if (function_exists('learndash_user_get_enrolled_courses') && function_exists('learndash_is_course_complete')) {
        $detail_courses = learndash_user_get_enrolled_courses($detail_id);
        if (is_array($detail_courses) && !empty($detail_courses)) {
            $detail_training_complete = true;
            foreach ($detail_courses as $detail_course_id) {
                if (!learndash_is_course_complete($detail_id, absint($detail_course_id))) {
                    $detail_training_complete = false;
                    break;
                }
            }
        }
    }
    $detail_paperwork_completed_count = $detail_demo
        ? 1
        : count(array_filter([
            !empty(get_user_meta($detail_id, 'tfp_signed_contract', true)),
            !empty(get_user_meta($detail_id, 'tfp_w9_form', true)),
            !empty(get_user_meta($detail_id, 'tfp_background_check', true)),
            $detail_profile_complete >= 100,
        ]));
    $detail_paperwork_complete = $detail_paperwork_completed_count === 4;
    $detail_steps = $detail_demo
        ? [true, true, false, false]
        : [true, $detail_paperwork_complete, $detail_training_complete, !empty($detail_cohorts)];
    $detail_completed = count(array_filter($detail_steps));
    $user = wp_get_current_user();
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        $item['active'] = ($item['slug'] ?? '') === 'facilitators';
        if (($item['slug'] ?? '') === 'facilitators') { $item['url'] = tfp_admin_facilitators_url(); }
    }
    unset($item);

    $tabs = [
        'overview' => __('Overview', 'tfp-dashboard'),
        'active' => __('Facilitators', 'tfp-dashboard'),
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
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><a href="<?php echo esc_url(tfp_admin_facilitators_url()); ?>"><?php esc_html_e('Facilitators', 'tfp-dashboard'); ?></a><?php if ($detail_person) : ?><b>/</b><strong><?php esc_html_e('Invitations', 'tfp-dashboard'); ?></strong><?php else : ?><?php if ($data['tab'] !== 'overview') : ?><b>/</b><strong><?php echo esc_html($tabs[$data['tab']]); ?></strong><?php endif; ?><?php endif; ?></div>
        <?php if ($detail_person) : ?>
          <header class="tfp-admin-facilitator-detail-header">
            <div class="tfp-admin-facilitator-detail-identity"><img src="<?php echo esc_url(get_avatar_url($detail_id, ['size' => 160])); ?>" alt=""><div><h1><?php echo esc_html($detail_person->display_name); ?></h1><div class="tfp-admin-facilitator-contact"><span>✉ <?php echo esc_html($detail_person->user_email); ?></span><?php $detail_phone = get_user_meta($detail_id, 'billing_phone', true); if ($detail_phone) : ?><span>☎ <?php echo esc_html($detail_phone); ?></span><?php endif; ?></div></div></div>
            <?php if ($detail_demo) : ?><span class="tfp-admin-invitation-status is-pending"><?php esc_html_e('Demo Preview', 'tfp-dashboard'); ?></span><?php endif; ?>
            <a class="tfp-admin-add-user-btn tfp-admin-facilitator-back" href="<?php echo esc_url(add_query_arg('tab', 'invitations', tfp_admin_facilitators_url())); ?>">↶ <?php esc_html_e('Back to Facilitators', 'tfp-dashboard'); ?></a>
          </header>
        <?php else : ?>
          <header class="tfp-admin-page-header tfp-admin-facilitators-header"><div><h1><?php esc_html_e('Facilitators', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Manage facilitators, track onboarding, and facilitator activity across every cohort.', 'tfp-dashboard'); ?></p></div><a class="tfp-admin-add-user-btn tfp-admin-facilitators-add" href="<?php echo esc_url(admin_url('user-new.php')); ?>"><span aria-hidden="true">＋</span><?php echo $data['tab'] === 'invitations' ? esc_html__('Send Invitation', 'tfp-dashboard') : esc_html__('Add Facilitators', 'tfp-dashboard'); ?></a></header>
        <?php endif; ?>
        <main class="tfp-admin-content tfp-admin-facilitators-content">
          <?php if ($detail_person) : ?>
            <nav class="tfp-admin-facilitator-detail-tabs" aria-label="<?php esc_attr_e('Facilitator details', 'tfp-dashboard'); ?>">
              <a class="<?php echo $detail_tab === 'onboarding' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg($detail_demo ? ['demo' => 1, 'profile_tab' => 'onboarding'] : ['facilitator_id' => $detail_id, 'profile_tab' => 'onboarding'], tfp_admin_facilitators_url())); ?>"><?php esc_html_e('Profile & Onboarding', 'tfp-dashboard'); ?></a>
              <a class="<?php echo $detail_tab === 'payment' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg($detail_demo ? ['demo' => 1, 'profile_tab' => 'payment'] : ['facilitator_id' => $detail_id, 'profile_tab' => 'payment'], tfp_admin_facilitators_url())); ?>"><?php esc_html_e('Payment Information', 'tfp-dashboard'); ?></a>
              <a class="<?php echo $detail_tab === 'activity' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg($detail_demo ? ['demo' => 1, 'profile_tab' => 'activity'] : ['facilitator_id' => $detail_id, 'profile_tab' => 'activity'], tfp_admin_facilitators_url())); ?>"><?php esc_html_e('Cohort Activity', 'tfp-dashboard'); ?></a>
            </nav>
          <?php else : ?>
            <nav class="tfp-admin-facilitators-tabs" aria-label="<?php esc_attr_e('Facilitator sections', 'tfp-dashboard'); ?>">
              <?php foreach ($tabs as $key => $label) : ?><a class="<?php echo $data['tab'] === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', $key, tfp_admin_facilitators_url())); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
            </nav>
          <?php endif; ?>
          <?php if ($detail_person) : ?>
            <?php if ($detail_tab === 'onboarding') : ?>
              <section class="tfp-admin-panel tfp-admin-detail-onboarding">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Onboarding Invitations', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Current Active Module', 'tfp-dashboard'); ?><?php $detail_module = $detail_demo ? 'Holy Spirit: Water Baptism' : get_user_meta($detail_id, 'tfp_current_training_module', true); ?><?php if ($detail_module) : ?> - <?php echo esc_html($detail_module); ?><?php endif; ?></p></div><span class="tfp-admin-onboarding-status <?php echo $detail_completed >= 4 ? 'is-complete' : 'is-progress'; ?>"><?php echo $detail_completed >= 4 ? esc_html__('Completed', 'tfp-dashboard') : esc_html__('In Progress', 'tfp-dashboard'); ?></span></header>
                <div class="tfp-admin-onboarding-steps"><?php $detail_labels = [__('Account Created', 'tfp-dashboard'), __('Profile & Paperwork', 'tfp-dashboard'), __('Training Module', 'tfp-dashboard'), __('Cohort Assigned', 'tfp-dashboard')]; foreach ($detail_labels as $step_index => $step_label) : ?><div class="tfp-admin-onboarding-step <?php echo !empty($detail_steps[$step_index]) ? 'is-complete' : (!empty($detail_steps[1]) && $step_index === 1 ? 'is-current' : ''); ?>"><?php echo esc_html($step_label); ?></div><?php endforeach; ?></div>
                <div class="tfp-admin-onboarding-progress"><span style="width:<?php echo esc_attr(($detail_completed / 4) * 100); ?>%"></span></div><p class="tfp-admin-onboarding-footnote"><?php echo esc_html(sprintf(__('%1$d of 4 steps complete', 'tfp-dashboard'), $detail_completed)); ?></p>
              </section>
              <section class="tfp-admin-panel tfp-admin-detail-card"><header><h2><?php esc_html_e('Step 1 — Account Creation', 'tfp-dashboard'); ?></h2><span class="tfp-admin-invitation-status is-active"><?php esc_html_e('Completed', 'tfp-dashboard'); ?></span></header><p><?php echo esc_html(sprintf(__('Account created by Admin • %s • Login credentials sent via email', 'tfp-dashboard'), wp_date('M j, Y', strtotime($detail_person->user_registered)))); ?></p></section>
              <section class="tfp-admin-panel tfp-admin-detail-card"><header><div><h2><?php esc_html_e('Step 2 — Profile & Paperwork', 'tfp-dashboard'); ?></h2><p><?php echo esc_html(sprintf(__('%d of 4 Complete', 'tfp-dashboard'), $detail_paperwork_completed_count)); ?></p></div><span class="tfp-admin-invitation-status <?php echo $detail_steps[1] ? 'is-active' : 'is-pending'; ?>"><?php echo $detail_steps[1] ? esc_html__('Completed', 'tfp-dashboard') : esc_html__('In Progress', 'tfp-dashboard'); ?></span></header>
                <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-detail-table"><thead><tr><th><?php esc_html_e('Compliance / Paperwork', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                  <?php $detail_docs = [['Signed Contract','tfp_signed_contract'],['W-9 Form','tfp_w9_form'],['Background Check','tfp_background_check'],['Profile Info','profile']]; foreach ($detail_docs as $doc_index => $doc) : $doc_value = $detail_demo ? ($doc_index === 0 ? 'demo-complete' : '') : ($doc[1] === 'profile' ? $detail_profile_complete >= 100 : get_user_meta($detail_id, $doc[1], true)); $doc_done = !empty($doc_value); ?>
                    <tr><td><?php echo esc_html($doc[0]); ?></td><td><span class="tfp-admin-invitation-status <?php echo $doc_done ? 'is-active' : 'is-pending'; ?>"><?php echo $doc_done ? esc_html__('Completed', 'tfp-dashboard') : esc_html__('Not Started', 'tfp-dashboard'); ?></span></td><td><?php if ($doc_done && is_string($doc_value) && filter_var($doc_value, FILTER_VALIDATE_URL)) : ?><a class="tfp-admin-invitation-view" href="<?php echo esc_url($doc_value); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'tfp-dashboard'); ?></a><?php elseif ($detail_demo && $doc_done) : ?><button class="tfp-admin-invitation-view" type="button" disabled aria-label="<?php esc_attr_e('Demo document preview only', 'tfp-dashboard'); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></button><?php else : ?><span class="tfp-admin-detail-muted">—</span><?php endif; ?></td></tr>
                  <?php endforeach; ?>
                </tbody></table></div>
              </section>
              <section class="tfp-admin-panel tfp-admin-detail-card"><header><div><h2><?php esc_html_e('Step 3 — Training Modules', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Locked - Complete Step 2 First', 'tfp-dashboard'); ?></p></div><span class="tfp-admin-invitation-status"><?php echo $detail_training_complete ? esc_html__('Completed', 'tfp-dashboard') : esc_html__('Not Started', 'tfp-dashboard'); ?></span></header><?php if ($detail_demo) : ?><ul class="tfp-admin-detail-module-list"><?php foreach (['Discipleship Foundations','Facilitator Standards','LMS Platform Orientation','Facilitation Assisting'] as $demo_module) : ?><li><?php echo esc_html($demo_module); ?><span>0%</span></li><?php endforeach; ?></ul><?php elseif (function_exists('learndash_user_get_enrolled_courses')) : $detail_course_ids = learndash_user_get_enrolled_courses($detail_id); if ($detail_course_ids) : ?><ul class="tfp-admin-detail-module-list"><?php foreach ($detail_course_ids as $detail_course_id) : ?><li><?php echo esc_html(get_the_title($detail_course_id)); ?><span><?php echo function_exists('learndash_is_course_complete') && learndash_is_course_complete($detail_id, $detail_course_id) ? '100%' : '0%'; ?></span></li><?php endforeach; ?></ul><?php else : ?><p class="tfp-admin-detail-muted"><?php esc_html_e('No training modules assigned.', 'tfp-dashboard'); ?></p><?php endif; else : ?><p class="tfp-admin-detail-muted"><?php esc_html_e('LearnDash training data is not available.', 'tfp-dashboard'); ?></p><?php endif; ?></section>
              <section class="tfp-admin-panel tfp-admin-detail-card"><header><div><h2><?php esc_html_e('Step 4 — First Cohort Assignment', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Assign this facilitator to a cohort from the Cohorts tab.', 'tfp-dashboard'); ?></p></div><span class="tfp-admin-invitation-status <?php echo $detail_cohorts ? 'is-active' : ''; ?>"><?php echo $detail_cohorts ? esc_html__('Assigned', 'tfp-dashboard') : esc_html__('Not Started', 'tfp-dashboard'); ?></span></header><?php if ($detail_cohorts) : ?><ul class="tfp-admin-detail-module-list"><?php foreach ($detail_cohorts as $cohort) : ?><li><?php echo esc_html(get_the_title($cohort)); ?></li><?php endforeach; ?></ul><?php endif; ?></section>
              <div class="tfp-admin-detail-bottom-grid">
                <section class="tfp-admin-panel tfp-admin-detail-card"><h2><?php esc_html_e('Roles', 'tfp-dashboard'); ?></h2><label><?php esc_html_e('Assigned Roles (select all that apply)', 'tfp-dashboard'); ?></label><div class="tfp-admin-detail-chips"><?php foreach ($detail_roles as $role) : ?><span><?php echo esc_html(ucwords(str_replace(['_', '-'], ' ', sanitize_text_field((string) $role)))); ?></span><?php endforeach; ?></div><label><?php esc_html_e('Assigned Categories (can teach in multiple)', 'tfp-dashboard'); ?></label><div class="tfp-admin-detail-chips is-category"><?php foreach ($detail_categories as $category) : ?><span><?php echo esc_html(sanitize_text_field((string) $category)); ?></span><?php endforeach; ?></div><label><?php esc_html_e('Email Address', 'tfp-dashboard'); ?></label><p class="tfp-admin-detail-field"><?php echo esc_html($detail_person->user_email); ?></p></section>
                <section class="tfp-admin-panel tfp-admin-detail-card"><h2><?php esc_html_e('Admin Notes', 'tfp-dashboard'); ?></h2><label><?php esc_html_e('Internal Notes', 'tfp-dashboard'); ?></label><div class="tfp-admin-detail-notes"><?php echo $detail_notes !== '' ? esc_html($detail_notes) : esc_html__('No internal notes have been added.', 'tfp-dashboard'); ?></div><label><?php esc_html_e('Onboarding Admin', 'tfp-dashboard'); ?></label><p class="tfp-admin-detail-field"><?php $detail_admin = $detail_demo ? 'Admin — Sarah T.' : get_user_meta($detail_id, 'tfp_onboarding_admin', true); echo esc_html($detail_admin ?: '—'); ?></p></section>
              </div>
            <?php elseif ($detail_tab === 'payment') : ?>
              <?php
                // Load saved payment metadata; demo values are isolated to the preview.
                $pay_type = get_user_meta($detail_id, 'tfp_compensation_type', true);
                $pay_rate = get_user_meta($detail_id, 'tfp_pay_rate', true);
                $pay_bonus = get_user_meta($detail_id, 'tfp_completion_bonus', true);
                $pay_total = get_user_meta($detail_id, 'tfp_total_paid_to_date', true);
                $pay_last_amount = get_user_meta($detail_id, 'tfp_last_payment_amount', true);
                $pay_last_date = get_user_meta($detail_id, 'tfp_last_payment_date', true);
                $pay_provider = get_user_meta($detail_id, 'tfp_payout_provider_status', true);
                $payout_rows = get_user_meta($detail_id, 'tfp_payout_log', true);
                if (!is_array($payout_rows)) { $payout_rows = []; }
                // Derive payment totals and latest payment from the saved payout history.
                $pay_total = 0;
                foreach ($payout_rows as $saved_payout) {
                    if (is_array($saved_payout) && isset($saved_payout['amount']) && is_numeric($saved_payout['amount'])) {
                        $pay_total += (float) $saved_payout['amount'];
                    }
                }
                usort($payout_rows, static function ($a, $b) {
                    return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? ''));
                });
                $pay_last_amount = !empty($payout_rows[0]['amount']) ? $payout_rows[0]['amount'] : '';
                $pay_last_date = !empty($payout_rows[0]['date']) ? $payout_rows[0]['date'] : '';
                $payment_notice = isset($_GET['payment_notice']) ? sanitize_key(wp_unslash($_GET['payment_notice'])) : '';
                $available_cohorts = get_posts([
                    'post_type' => 'tfp_cohort',
                    'post_status' => ['publish', 'private'],
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ]);
                if ($detail_demo) {
                    $pay_type = 'per_student';
                    $pay_rate = 500;
                    $pay_bonus = 50;
                    $pay_total = 2050;
                    $pay_last_amount = 500;
                    $pay_last_date = '2025-05-15';
                    $pay_provider = 'Stripe Connect · Verified';
                    $payout_rows = [
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2025-05-15', 'time' => '6:00 PM PST'],
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2025-04-18', 'time' => '6:00 PM PST'],
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2025-03-18', 'time' => '6:00 PM PST'],
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2025-02-18', 'time' => '6:00 PM PST'],
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2025-01-18', 'time' => '6:00 PM PST'],
                        ['cohort' => 'COH-SUMMER-26', 'amount' => 500, 'date' => '2024-12-18', 'time' => '6:00 PM PST'],
                    ];
                }
                $pay_type_label = $pay_type === 'flat_rate' ? __('Flat Rate', 'tfp-dashboard') : __('Per Student (Flat Rate)', 'tfp-dashboard');
              ?>
              <?php if ($payment_notice === 'compensation_saved' || $payment_notice === 'payout_recorded') : ?><div class="tfp-admin-payment-notice" role="status"><?php echo $payment_notice === 'compensation_saved' ? esc_html__('Compensation settings saved.', 'tfp-dashboard') : esc_html__('Payout record added successfully. This is an administrative record only; no funds were transferred.', 'tfp-dashboard'); ?></div><?php endif; ?>
              <section class="tfp-admin-payment-summary">
                <div><small><?php esc_html_e('Total Paid to Date', 'tfp-dashboard'); ?></small><strong><?php echo is_numeric($pay_total) ? esc_html(chr(36) . number_format_i18n((float) $pay_total, 2)) : '—'; ?></strong></div>
                <div class="tfp-admin-payment-last"><small><?php esc_html_e('Last Payment', 'tfp-dashboard'); ?></small><strong><?php echo is_numeric($pay_last_amount) ? esc_html(chr(36) . number_format_i18n((float) $pay_last_amount, 2)) : esc_html__('No payment recorded', 'tfp-dashboard'); ?><?php if ($pay_last_date && strtotime($pay_last_date)) : ?> — <?php echo esc_html(wp_date('M j, Y', strtotime($pay_last_date))); ?><?php endif; ?></strong><small><?php echo $pay_provider ? esc_html($pay_provider) : esc_html__('Payout provider not connected', 'tfp-dashboard'); ?></small></div>
              </section>
              <div class="tfp-admin-payment-actions">
                <div class="tfp-admin-payment-action"><strong><?php esc_html_e('Send Payment', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Issue a manual payment', 'tfp-dashboard'); ?></span></div>
                <div class="tfp-admin-payment-action"><strong><?php esc_html_e('Request Banking Update', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Send Stripe Connect invite', 'tfp-dashboard'); ?></span></div>
                <div class="tfp-admin-payment-action"><strong><?php esc_html_e('Send Paperwork', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Resend W-9, contract, or doc', 'tfp-dashboard'); ?></span></div>
                <div class="tfp-admin-payment-action"><strong><?php esc_html_e('Adjust Pay Rate', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Edit flat rate or bonus', 'tfp-dashboard'); ?></span></div>
              </div>
              <div class="tfp-admin-payment-grid">
                <section class="tfp-admin-panel tfp-admin-payment-editor">
                  <h2><?php esc_html_e('Pay Structure Editor', 'tfp-dashboard'); ?></h2>
                  <?php if ($detail_demo) : ?><p class="tfp-admin-payment-hint"><?php esc_html_e('Demo preview — editing is disabled.', 'tfp-dashboard'); ?></p><?php else : ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="tfp_save_facilitator_payment">
                    <input type="hidden" name="payment_mode" value="save_compensation">
                    <input type="hidden" name="facilitator_id" value="<?php echo esc_attr($detail_id); ?>">
                    <?php wp_nonce_field('tfp_facilitator_payment_' . $detail_id); ?>
                    <label for="tfp-pay-type"><?php esc_html_e('Compensation Type', 'tfp-dashboard'); ?></label>
                    <select id="tfp-pay-type" name="compensation_type">
                      <option value="per_student" <?php selected($pay_type ?: 'per_student', 'per_student'); ?>><?php esc_html_e('Per Student (Flat Rate)', 'tfp-dashboard'); ?></option>
                      <option value="flat_rate" <?php selected($pay_type, 'flat_rate'); ?>><?php esc_html_e('Flat Rate', 'tfp-dashboard'); ?></option>
                    </select>
                    <label for="tfp-pay-rate"><?php esc_html_e('Pay Rate', 'tfp-dashboard'); ?></label>
                    <input id="tfp-pay-rate" name="pay_rate" type="number" min="0" step="0.01" value="<?php echo is_numeric($pay_rate) ? esc_attr($pay_rate) : ''; ?>" placeholder="0.00" required>
                    <label for="tfp-pay-bonus"><?php esc_html_e('Completion Bonus (Optional)', 'tfp-dashboard'); ?></label>
                    <input id="tfp-pay-bonus" name="completion_bonus" type="number" min="0" step="0.01" value="<?php echo is_numeric($pay_bonus) ? esc_attr($pay_bonus) : ''; ?>" placeholder="0.00">
                    <button type="submit" class="tfp-admin-payment-save"><?php esc_html_e('Save Pay Structure', 'tfp-dashboard'); ?></button>
                  </form>
                  <?php endif; ?>
                </section>
                <section class="tfp-admin-panel tfp-admin-payment-payouts">
                  <h2><?php esc_html_e('Payout Log', 'tfp-dashboard'); ?></h2>
                  <?php if (!empty($payout_rows)) : ?>
                    <div class="tfp-admin-payment-payout-list">
                      <?php foreach ($payout_rows as $payout) : if (!is_array($payout)) { continue; } ?>
                        <article class="tfp-admin-payment-payout-row">
                          <div><strong><?php echo !empty($payout['cohort']) ? esc_html(sanitize_text_field($payout['cohort'])) : esc_html__('Payout', 'tfp-dashboard'); ?></strong><small><?php echo !empty($payout['date']) && strtotime($payout['date']) ? esc_html(wp_date('M j, Y', strtotime($payout['date'])) . (!empty($payout['time']) ? ' ' . sanitize_text_field($payout['time']) : '')) : '—'; ?></small></div>
                          <strong><?php echo isset($payout['amount']) && is_numeric($payout['amount']) ? esc_html(chr(36) . number_format_i18n((float) $payout['amount'], 2)) : '—'; ?></strong>
                        </article>
                      <?php endforeach; ?>
                    </div>
                  <?php else : ?><div class="tfp-admin-payment-empty"><?php esc_html_e('No payout records are available for this facilitator yet.', 'tfp-dashboard'); ?></div><?php endif; ?>
                  <?php if (!$detail_demo) : ?>
                    <form class="tfp-admin-payment-record-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                      <h3><?php esc_html_e('Record Payout', 'tfp-dashboard'); ?></h3>
                      <input type="hidden" name="action" value="tfp_save_facilitator_payment">
                      <input type="hidden" name="payment_mode" value="record_payout">
                      <input type="hidden" name="facilitator_id" value="<?php echo esc_attr($detail_id); ?>">
                      <?php wp_nonce_field('tfp_facilitator_payment_' . $detail_id); ?>
                      <label for="tfp-payout-cohort"><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></label>
                      <select id="tfp-payout-cohort" name="payout_cohort" required><option value=""><?php esc_html_e('Select cohort', 'tfp-dashboard'); ?></option>
                        <?php foreach ($available_cohorts as $available_cohort) : ?><option value="<?php echo esc_attr(get_the_title($available_cohort)); ?>"><?php echo esc_html(get_the_title($available_cohort)); ?></option><?php endforeach; ?>
                      </select>
                      <label for="tfp-payout-amount"><?php esc_html_e('Amount', 'tfp-dashboard'); ?></label>
                      <input id="tfp-payout-amount" name="payout_amount" type="number" min="0.01" step="0.01" placeholder="0.00" required>
                      <label for="tfp-payout-date"><?php esc_html_e('Payout Date', 'tfp-dashboard'); ?></label>
                      <input id="tfp-payout-date" name="payout_date" type="date" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required>
                      <button type="submit" class="tfp-admin-payment-save"><?php esc_html_e('Add Payout Record', 'tfp-dashboard'); ?></button>
                      <p class="tfp-admin-payment-hint"><?php esc_html_e('This saves an administrative payout record only; it does not transfer money.', 'tfp-dashboard'); ?></p>
                    </form>
                  <?php endif; ?>
                </section>
              </div>
            <?php else : ?>
              <section class="tfp-admin-panel tfp-admin-detail-card"><h2><?php esc_html_e('Cohort Activity', 'tfp-dashboard'); ?></h2><?php if ($detail_cohorts) : ?><ul class="tfp-admin-detail-module-list"><?php foreach ($detail_cohorts as $cohort) : ?><li><?php echo esc_html(get_the_title($cohort)); ?></li><?php endforeach; ?></ul><?php else : ?><p class="tfp-admin-detail-muted"><?php esc_html_e('No cohort activity is recorded for this facilitator.', 'tfp-dashboard'); ?></p><?php endif; ?></section>
            <?php endif; ?>
          <?php elseif ($data['tab'] === 'overview') : ?>
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
            <div class="tfp-admin-facilitators-directory-stack">
              <section class="tfp-admin-panel tfp-admin-facilitators-directory-panel">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Facilitator Invitations', 'tfp-dashboard'); ?></h2></div></header>
                <p class="tfp-admin-invitation-demo-note"><?php esc_html_e('Demo preview: sample rows for design review only. These are not real invitations.', 'tfp-dashboard'); ?></p>
                <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-facilitators-table">
                  <thead><tr>
                    <th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Email', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Sent', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Expires', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                  </tr></thead>
                  <tbody>
                    <tr>
                      <td><strong>Rachel Moore</strong></td><td>rachel.moore@example.com</td><td>Discipleship</td><td>May 18</td><td>Jun 1</td>
                      <td><span class="tfp-admin-invitation-status is-active">Active</span></td>
                      <td><button class="tfp-admin-invitation-view" type="button" disabled aria-label="Demo View action">View</button></td>
                    </tr>
                    <tr>
                      <td><strong>Kevin Lim</strong></td><td>kevin.lim@example.com</td><td>Leadership</td><td>May 20</td><td>Jun 3</td>
                      <td><span class="tfp-admin-invitation-status is-active">Active</span></td>
                      <td><button class="tfp-admin-invitation-view" type="button" disabled aria-label="Demo View action">View</button></td>
                    </tr>
                    <tr>
                      <td><strong>Sandra James</strong></td><td>s.james@example.com</td><td>Kingdom Expansion</td><td>May 22</td><td>May 30</td>
                      <td><span class="tfp-admin-invitation-status is-pending">Pending</span></td>
                      <td><button class="tfp-admin-invitation-resend" type="button" disabled>Resend</button><button class="tfp-admin-invitation-view" type="button" disabled aria-label="Demo View action">View</button></td>
                    </tr>
                    <tr>
                      <td><strong>Andre Williams</strong></td><td>a.williams@example.com</td><td>Discipleship</td><td>May 23</td><td>May 30</td>
                      <td><span class="tfp-admin-invitation-status is-pending">Pending</span></td>
                      <td><button class="tfp-admin-invitation-resend" type="button" disabled>Resend</button><button class="tfp-admin-invitation-view" type="button" disabled aria-label="Demo View action">View</button></td>
                    </tr>
                  </tbody>
                </table></div>
              </section>
            </div>

          <?php elseif ($data['tab'] === 'active') : ?>
            <div class="tfp-admin-facilitators-directory-stack">
              <section class="tfp-admin-panel tfp-admin-facilitators-directory-panel">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Active Facilitators', 'tfp-dashboard'); ?></h2></div></header>
                
                <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-facilitators-table">
                  <thead><tr><th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Students', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Hmwk Rate', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Last Active', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                  <tbody>
                    <?php if ($data['active_rows']) : foreach ($data['active_rows'] as $record) : ?>
                      <tr><td><strong><?php echo esc_html($record['name']); ?></strong><small><?php echo esc_html($record['email']); ?></small></td><td><?php echo esc_html($record['category']); ?></td><td><?php echo esc_html(isset($record['students']) ? number_format_i18n($record['students']) : 'N/A'); ?></td><td><?php echo esc_html(number_format_i18n($record['cohorts'])); ?></td><td><?php echo esc_html($record['homework_rate'] ?? 'N/A'); ?></td><td><?php echo esc_html($record['last_login_label']); ?></td><td><span class="tfp-admin-facilitator-status is-active"><?php echo esc_html(ucfirst($record['status'])); ?></span></td><td><a class="tfp-admin-overview-view" href="<?php echo esc_url(add_query_arg('facilitator_id', absint($record['id']), tfp_admin_facilitators_url())); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td></tr>
                    <?php endforeach; endif; ?>
                    <tr class="tfp-admin-facilitator-demo-row"><td><strong>Shannon Stiggins</strong><small>shannon.demo@example.com · Demo</small></td><td>Discipleship</td><td>50</td><td>2</td><td>75%</td><td>Mon, 7:00 PM PST</td><td><span class="tfp-admin-facilitator-status is-active">Active</span></td><td><a class="tfp-admin-overview-view" href="<?php echo esc_url(add_query_arg('demo', 1, tfp_admin_facilitators_url())); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td></tr>
                    <?php if (!$data['active_rows']) : ?><tr><td colspan="8" class="tfp-admin-overview-empty"><?php esc_html_e('No real active facilitators found. The demo row above is for design preview only.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                  </tbody>
                </table></div>
              </section>

              <section class="tfp-admin-panel tfp-admin-facilitators-directory-panel">
                <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Pending Facilitators', 'tfp-dashboard'); ?></h2></div></header>
                
                <div class="tfp-dash-table-wrap"><table class="tfp-dash-table tfp-admin-facilitators-table">
                  <thead><tr><th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Category', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Past Students', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Past Cohorts', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Last Active', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                  <tbody>
                    <?php if ($data['pending_rows']) : foreach ($data['pending_rows'] as $record) : ?>
                      <tr><td><strong><?php echo esc_html($record['name']); ?></strong><small><?php echo esc_html($record['email']); ?></small></td><td><?php echo esc_html($record['category']); ?></td><td><?php echo esc_html(isset($record['past_students']) ? number_format_i18n($record['past_students']) : 'N/A'); ?></td><td><?php echo esc_html($record['past_cohorts'] ?? 'N/A'); ?></td><td><?php echo esc_html($record['last_login_label']); ?></td><td><span class="tfp-admin-facilitator-status is-active"><?php echo esc_html(ucfirst($record['status'])); ?></span></td><td><a class="tfp-admin-overview-view" href="<?php echo esc_url(add_query_arg('facilitator_id', absint($record['id']), tfp_admin_facilitators_url())); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td></tr>
                    <?php endforeach; else : ?><tr><td colspan="7" class="tfp-admin-overview-empty"><?php esc_html_e('No pending facilitators found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                  </tbody>
                </table></div>
              </section>
            </div>

          <?php else : ?>
            <?php $in_progress_count = count($data['onboarding_rows']); ?>
            <section class="tfp-admin-panel tfp-admin-facilitator-onboarding-panel">
              <header class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Facilitator Onboarding', 'tfp-dashboard'); ?></h2></div><small><?php echo esc_html(sprintf(__('%d in progress', 'tfp-dashboard'), $in_progress_count)); ?></small></header>
              <div class="tfp-admin-onboarding-list">
                <?php if ($data['onboarding_rows']) : foreach ($data['onboarding_rows'] as $record) : ?>
                  <article class="tfp-admin-onboarding-card">
                    <div class="tfp-admin-onboarding-person">
                      <div class="tfp-admin-onboarding-identity"><img src="<?php echo esc_url($record['avatar']); ?>" alt=""><div><strong><?php echo esc_html($record['name']); ?></strong><small><?php echo esc_html($record['category'] . ' · ' . __('Account created', 'tfp-dashboard') . ($record['registered_label'] ? ' ' . $record['registered_label'] : '')); ?></small></div></div>
                      <span class="tfp-admin-onboarding-status <?php echo $record['onboarding_status'] === 'review' ? 'is-review' : 'is-progress'; ?>"><?php echo esc_html($record['onboarding_status_label']); ?></span>
                    </div>
                    <div class="tfp-admin-onboarding-steps">
                      <?php $step_labels = ['account_created' => __('Account Created', 'tfp-dashboard'), 'profile_complete' => __('Profile Complete', 'tfp-dashboard'), 'training_complete' => __('Training Module', 'tfp-dashboard'), 'cohort_assigned' => __('Cohort Assigned', 'tfp-dashboard')]; foreach ($step_labels as $step_key => $step_label) : ?>
                        <div class="tfp-admin-onboarding-step <?php echo !empty($record['steps'][$step_key]) ? 'is-complete' : ''; ?>"><?php echo esc_html($step_label); ?></div>
                      <?php endforeach; ?>
                    </div>
                    <div class="tfp-admin-onboarding-progress"><span style="width:<?php echo esc_attr($record['progress']); ?>%"></span></div>
                    <div class="tfp-admin-onboarding-footnote <?php echo $record['onboarding_status'] === 'review' ? 'is-review' : ''; ?>"><?php echo esc_html(sprintf(__('%1$d of 4 steps complete', 'tfp-dashboard'), $record['completed_steps'])); ?><?php if ($record['onboarding_status'] === 'review') : ?> · <?php esc_html_e('No activity in 5 days', 'tfp-dashboard'); ?><?php endif; ?></div>
                  </article>
                <?php endforeach; else : ?><p class="tfp-admin-facilitators-empty"><?php esc_html_e('No facilitator onboarding is currently in progress.', 'tfp-dashboard'); ?></p><?php endif; ?>
              </div>
            </section>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <?php
}
