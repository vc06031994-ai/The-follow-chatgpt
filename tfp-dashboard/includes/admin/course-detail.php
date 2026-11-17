<?php
/**
 * Admin Course Detail dashboard.
 * Renders the selected LearnDash course and its ordered week (lesson) mapping.
 */
if (!defined('ABSPATH')) {
    exit;
}

function tfp_admin_course_detail_url($course_id) {
    return home_url('/admin-course/' . absint($course_id) . '/');
}

function tfp_admin_course_detail_route_id() {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (!preg_match('~(?:^|/)admin-course/([^/]+)/?$~', $path, $matches)) {
        return 0;
    }

    $route = sanitize_title(rawurldecode($matches[1]));
    if (ctype_digit($route)) {
        return absint($route); // Preserve compatibility with previously shared numeric URLs.
    }

    $course = get_page_by_path($route, OBJECT, 'sfwd-courses');
    return ($course instanceof WP_Post) ? (int) $course->ID : 0;
}


/**
 * Set a meaningful browser title for the front-end course detail routes.
 * Avoid showing the generic WordPress page title (for example, "Page 1659").
 */
function tfp_admin_course_detail_document_title($title) {
    if (!is_admin()) {
        $course_id = tfp_admin_course_detail_route_id();
        $course = $course_id ? get_post($course_id) : null;

        if ($course instanceof WP_Post && 'sfwd-courses' === $course->post_type) {
            $tab = isset($_GET['course_tab']) ? sanitize_key(wp_unslash($_GET['course_tab'])) : 'overview';
            $tab_labels = [
                'overview' => __('Overview', 'tfp-dashboard'),
                'plan'     => __('Plan', 'tfp-dashboard'),
                'rules'    => __('Rules', 'tfp-dashboard'),
                'cohorts'  => __('Cohorts', 'tfp-dashboard'),
                'insights' => __('Insights', 'tfp-dashboard'),
                'access'   => __('Access', 'tfp-dashboard'),
                'health'   => __('Health', 'tfp-dashboard'),
            ];
            $page_label = isset($tab_labels[$tab]) ? $tab_labels[$tab] : __('Course', 'tfp-dashboard');

            return sprintf(
                /* translators: 1: course tab name, 2: course title, 3: site name. */
                __('%1$s — %2$s | %3$s', 'tfp-dashboard'),
                $page_label,
                get_the_title($course_id),
                get_bloginfo('name')
            );
        }
    }

    return $title;
}
add_filter('pre_get_document_title', 'tfp_admin_course_detail_document_title', 20);

function tfp_admin_course_detail_week_items($lesson_id) {
    $items = [];
    if (get_post_meta($lesson_id, 'tfp_week_video_url', true)) {
        $items[] = 'Video';
    }
    $lesson = get_post($lesson_id);
    if ($lesson && (trim(wp_strip_all_tags($lesson->post_content)) !== '' || trim((string) $lesson->post_excerpt) !== '')) {
        $items[] = 'Reading';
    }
    $homework = function_exists('tfp_week_get_homework_questions') ? tfp_week_get_homework_questions($lesson_id, false) : [];
    if (!empty($homework)) {
        $items[] = 'Hmwk';
    }
    $quiz = function_exists('tfp_week_get_quiz_questions') ? tfp_week_get_quiz_questions($lesson_id, false) : [];
    if (!empty($quiz)) {
        $items[] = 'Quiz';
    }
    $test = function_exists('tfp_week_get_test_questions') ? tfp_week_get_test_questions($lesson_id, false) : [];
    if (!empty($test)) {
        $items[] = 'Test';
    }
    if (get_post_meta($lesson_id, 'tfp_week_meeting_date', true) || get_post_meta($lesson_id, 'tfp_week_meeting_discord_url', true)) {
        $items[] = 'Meeting';
    }
    return $items;
}

function tfp_admin_course_detail_get_data($course_id) {
    $course = get_post(absint($course_id));
    if (!$course || $course->post_type !== 'sfwd-courses') {
        return null;
    }

    $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $cohorts = function_exists('tfp_cohorts_for_course') ? (array) tfp_cohorts_for_course($course_id) : [];
    $week_rows = [];
    $alerts = 0;
    $modules_mapped = 0;

    foreach ($weeks as $index => $week) {
        $items = tfp_admin_course_detail_week_items($week->ID);
        $modules_mapped = count($weeks);
        $is_published = $week->post_status === 'publish';
        if (!$is_published || empty($items)) {
            $alerts++;
        }
        $week_rows[] = [
            'number' => $index + 1,
            'id' => (int) $week->ID,
            'title' => get_the_title($week->ID),
            'status' => $is_published ? 'Published' : 'Attention',
            'status_class' => $is_published ? 'is-published' : 'is-attention',
            'items' => $items,
            'gate' => 'Strict',
            'edit_url' => function_exists('tfp_admin_course_editor_url') ? tfp_admin_course_editor_url($course_id, $week->ID) : get_edit_post_link($week->ID, 'raw'),
        ];
    }

    $course_type = '';
    foreach (['tfp_course_type', '_tfp_course_type', 'course_type'] as $key) {
        $course_type = trim((string) get_post_meta($course_id, $key, true));
        if ($course_type !== '') {
            break;
        }
    }

    $attendance_policy = '';
    foreach (['tfp_attendance_policy', '_tfp_attendance_policy', 'tfp_default_attendance_policy'] as $key) {
        $attendance_policy = trim((string) get_post_meta($course_id, $key, true));
        if ($attendance_policy !== '') {
            break;
        }
    }
    if ($attendance_policy === '') {
        $attendance_policy = 'Strict';
    }

    $first_week_id = !empty($weeks) ? (int) $weeks[0]->ID : 0;
    $quiz_defaults = '—';
    if ($first_week_id && function_exists('tfp_week_get_quiz_max_attempts') && function_exists('tfp_week_get_quiz_pass_percentage')) {
        $quiz_defaults = sprintf(
            __('%1$s attempts, %2$s%% pass', 'tfp-dashboard'),
            tfp_week_get_quiz_max_attempts($first_week_id),
            tfp_week_get_quiz_pass_percentage($first_week_id)
        );
    }
    $test_defaults = '—';
    if ($first_week_id && function_exists('tfp_week_get_test_max_attempts') && function_exists('tfp_week_get_test_pass_percentage')) {
        $test_defaults = sprintf(
            __('%1$s attempts, %2$s%% pass', 'tfp-dashboard'),
            tfp_week_get_test_max_attempts($first_week_id),
            tfp_week_get_test_pass_percentage($first_week_id)
        );
    }

    return [
        'course' => $course,
        'weeks' => $weeks,
        'week_rows' => $week_rows,
        'total_weeks' => count($weeks),
        'modules_mapped' => $modules_mapped,
        'active_cohorts' => count(array_filter($cohorts, static function ($cohort) {
            return $cohort->post_status === 'publish';
        })),
        'alerts' => $alerts,
        'course_type' => $course_type !== '' ? $course_type : '—',
        'attendance_policy' => $attendance_policy,
        'quiz_defaults' => $quiz_defaults,
        'test_defaults' => $test_defaults,
        'created' => get_the_date('M j, g:i A', $course_id),
        'updated' => get_post_modified_time('M j, g:i A', false, $course_id, true),
        'status' => ucfirst($course->post_status),
    ];
}


/**
 * Build course insights from live cohort membership and saved LearnDash progress.
 * Status is based on completed week progress versus elapsed cohort weeks.
 */
function tfp_admin_course_insights_data($course_id, $cohort_filter = 0, $period = '90') {
    $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $cohorts = function_exists('tfp_cohorts_for_course') ? (array) tfp_cohorts_for_course($course_id) : [];
    $period_days = in_array($period, ['30', '90', '365'], true) ? (int) $period : 0;
    $cutoff = $period_days ? strtotime('-' . $period_days . ' days', current_time('timestamp')) : 0;
    $cohorts = array_values(array_filter($cohorts, static function($cohort) use ($cohort_filter, $cutoff) {
        if ($cohort_filter && (int) $cohort->ID !== (int) $cohort_filter) return false;
        if ($cutoff) {
            $start = get_post_meta($cohort->ID, '_start_date', true);
            $timestamp = $start ? strtotime($start) : 0;
            if (!$timestamp || $timestamp < $cutoff) return false;
        }
        return true;
    }));

    $students_by_cohort = [];
    $student_ids = [];
    $facilitators = [];
    foreach ($cohorts as $cohort) {
        $cid = (int) $cohort->ID;
        $ids = function_exists('tfp_facilitator_cohort_students')
            ? (array) tfp_facilitator_cohort_students($cid)
            : array_map('absint', (array) get_users(['meta_key' => 'tfp_cohort_choice', 'meta_value' => $cid, 'fields' => 'ID']));
        $students_by_cohort[$cid] = array_values(array_unique(array_map('absint', $ids)));
        $student_ids = array_merge($student_ids, $students_by_cohort[$cid]);
        $fid = function_exists('tfp_cohort_get_facilitator_user_id') ? absint(tfp_cohort_get_facilitator_user_id($cid)) : 0;
        $user = $fid ? get_userdata($fid) : false;
        $legacy = (string) get_post_meta($cid, '_facilitator', true);
        $facilitator_name = $user ? $user->display_name : ($legacy ?: __('Unassigned', 'tfp-dashboard'));
        $key = $fid ?: 'legacy-' . sanitize_title($facilitator_name);
        if (!isset($facilitators[$key])) $facilitators[$key] = ['name' => $facilitator_name, 'students' => [], 'pending' => 0, 'cohorts' => 0];
        $facilitators[$key]['students'] = array_merge($facilitators[$key]['students'], $students_by_cohort[$cid]);
        $facilitators[$key]['cohorts']++;
    }
    $student_ids = array_values(array_unique(array_filter(array_map('absint', $student_ids))));

    $status_counts = ['on_track' => 0, 'behind' => 0, 'stalled' => 0];
    $step_totals = ['video' => 0, 'reading' => 0, 'homework' => 0, 'quiz' => 0, 'test' => 0];
    $step_done = $step_totals;
    $week_status = [];
    $quiz_scores = [];
    $test_scores = [];
    $passed = 0;
    $graded = 0;
    $first_try_passed = 0;
    $first_try_total = 0;
    $missed = [];
    $student_completed = [];

    foreach ($weeks as $index => $week) {
        $week_status[$index] = ['on_track' => 0, 'behind' => 0, 'stalled' => 0, 'pending_gate' => 0];
    }

    foreach ($student_ids as $student_id) {
        $completed_weeks = 0;
        $started_steps = 0;
        foreach ($weeks as $index => $week) {
            $progress = function_exists('tfp_ld_get_week_progress') ? (array) tfp_ld_get_week_progress($student_id, $week->ID) : [];
            $done = 0;
            foreach (array_keys($step_totals) as $step) {
                $step_totals[$step]++;
                if (!empty($progress[$step])) { $step_done[$step]++; $done++; $started_steps++; }
            }
            if ($done === count($step_totals)) $completed_weeks++;
            if ('publish' !== $week->post_status) {
                $week_status[$index]['pending_gate']++;
            } elseif ($done === count($step_totals)) {
                $week_status[$index]['on_track']++;
            } elseif ($done > 0) {
                $week_status[$index]['behind']++;
            } else {
                $week_status[$index]['stalled']++;
            }

            if (function_exists('tfp_week_get_quiz_result')) {
                $result = tfp_week_get_quiz_result($student_id, $week->ID);
                if (is_array($result) && isset($result['score'])) {
                    $quiz_scores[] = (float) $result['score']; $graded++;
                    if (!empty($result['passed'])) $passed++;
                    $attempts = function_exists('tfp_week_get_quiz_attempts') ? (int) tfp_week_get_quiz_attempts($student_id, $week->ID) : 0;
                    if ($attempts > 0) { $first_try_total++; if (!empty($result['passed']) && $attempts === 1) $first_try_passed++; }
                    foreach ((array) ($result['results'] ?? []) as $question) {
                        if (empty($question['is_correct']) && !empty($question['prompt'])) {
                            $label = wp_strip_all_tags((string) $question['prompt']);
                            $missed[$label] = ($missed[$label] ?? 0) + 1;
                        }
                    }
                }
            }
            if (function_exists('tfp_week_get_test_result')) {
                $result = tfp_week_get_test_result($student_id, $week->ID);
                if (is_array($result) && isset($result['score'])) {
                    $test_scores[] = (float) $result['score'];
                    if (!empty($result['passed'])) $passed++;
                    $graded++;
                }
            }
        }
        $expected = count($weeks);
        $cohort_id = (int) get_user_meta($student_id, 'tfp_cohort_choice', true);
        $cohort_start = $cohort_id ? get_post_meta($cohort_id, '_start_date', true) : '';
        if ($cohort_start && $expected) {
            $elapsed = max(1, (int) floor((current_time('timestamp') - strtotime($cohort_start)) / WEEK_IN_SECONDS) + 1);
            $expected = min(count($weeks), $elapsed);
        }
        if ($started_steps === 0) $status_counts['stalled']++;
        elseif ($completed_weeks >= $expected) $status_counts['on_track']++;
        else $status_counts['behind']++;
        $student_completed[$student_id] = $completed_weeks;
    }

    foreach ($facilitators as &$facilitator) {
        $facilitator['students'] = array_values(array_unique(array_map('absint', $facilitator['students'])));
        foreach ($facilitator['students'] as $sid) {
            if (($student_completed[$sid] ?? 0) < count($weeks)) $facilitator['pending']++;
        }
    }
    unset($facilitator);

    $section_count = min(13, max(1, count($weeks)));
    $chunk_size = $section_count ? (int) ceil(max(1, count($weeks)) / $section_count) : 1;
    $sections = [];
    for ($i = 0; $i < $section_count; $i++) {
        $slice = array_slice($weeks, $i * $chunk_size, $chunk_size);
        if (!$slice) continue;
        $counts = ['on_track' => 0, 'behind' => 0, 'stalled' => 0, 'pending_gate' => 0];
        foreach ($slice as $week) {
            $index = array_search($week, $weeks, true);
            foreach ($counts as $key => $unused) $counts[$key] += $week_status[$index][$key] ?? 0;
        }
        $total = array_sum($counts);
        $sections[] = ['label' => sprintf(__('Section %d', 'tfp-dashboard'), $i + 1), 'counts' => $counts, 'total' => $total];
    }

    $assessment_scores = array_merge($quiz_scores, $test_scores);
    $missed_questions = [];
    arsort($missed);
    foreach (array_slice($missed, 0, 3, true) as $question => $count) $missed_questions[] = ['question' => $question, 'count' => $count];

    $activation = [];
    foreach ($step_totals as $step => $total) $activation[$step] = $total ? (int) round(($step_done[$step] / $total) * 100) : null;

    return [
        'cohorts' => $cohorts,
        'students' => $student_ids,
        'total_enrolled' => count($student_ids),
        'statuses' => $status_counts,
        'sections' => $sections,
        'activation' => $activation,
        'avg_pass_rate' => $graded ? (int) round(($passed / $graded) * 100) : null,
        'avg_score' => $assessment_scores ? (int) round(array_sum($assessment_scores) / count($assessment_scores)) : null,
        'first_try_pass' => $first_try_total ? (int) round(($first_try_passed / $first_try_total) * 100) : null,
        'missed_questions' => $missed_questions,
        'facilitators' => array_values($facilitators),
        'graded_count' => $graded,
    ];
}

function tfp_admin_course_detail_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    $course_id = tfp_admin_course_detail_route_id();
    $data = tfp_admin_course_detail_get_data($course_id);
    if (!$data) {
        wp_die(esc_html__('The requested course could not be found.', 'tfp-dashboard'), esc_html__('Course not found', 'tfp-dashboard'), ['response' => 404]);
    }
    if (isset($_GET['week_editor']) && '1' === sanitize_text_field(wp_unslash($_GET['week_editor'])) && function_exists('tfp_admin_course_editor_render')) {
        tfp_admin_course_editor_render($course_id, $data);
        return;
    }

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
    $course_title = get_the_title($data['course']);
    if (!$course_title) {
        $course_title = (string) $data['course']->post_title;
    }
    // Use concise display headings for the two named foundation courses.
    $course_heading_title = $course_title;
    $course_id = (int) $data['course']->ID;
    if (1039 === $course_id) {
        $course_heading_title = __('12 Week Foundation', 'tfp-dashboard');
    } elseif (1659 === $course_id) {
        $course_heading_title = __('58 Week Discipleship', 'tfp-dashboard');
    }
    $cohorts_url = admin_url('edit.php?post_type=tfp_cohort');
    $page_number = isset($_GET['week_page']) ? max(1, absint($_GET['week_page'])) : 1;
    $per_page = 6;
    $total_rows = count($data['week_rows']);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page_number = min($page_number, $total_pages);
    $visible_rows = array_slice($data['week_rows'], ($page_number - 1) * $per_page, $per_page);
    $first_item = $total_rows ? (($page_number - 1) * $per_page) + 1 : 0;
    $last_item = min($page_number * $per_page, $total_rows);

    // The Plan tab has its own 15-row pagination, independent of the Overview tab.
    $plan_per_page = 15;
    $plan_page_number = isset($_GET['plan_page']) ? max(1, absint($_GET['plan_page'])) : 1;
    $plan_total_pages = max(1, (int) ceil($total_rows / $plan_per_page));
    $plan_page_number = min($plan_page_number, $plan_total_pages);
    $plan_visible_rows = array_slice($data['week_rows'], ($plan_page_number - 1) * $plan_per_page, $plan_per_page);
    $plan_first_item = $total_rows ? (($plan_page_number - 1) * $plan_per_page) + 1 : 0;
    $plan_last_item = min($plan_page_number * $plan_per_page, $total_rows);
    $tabs = ['Overview' => '', 'Plan' => 'plan', 'Rules' => 'rules', 'Cohorts' => 'cohorts', 'Insights' => 'insights', 'Access' => 'access', 'Health' => 'health'];
    $active_tab = isset($_GET['course_tab']) ? sanitize_key(wp_unslash($_GET['course_tab'])) : '';
    $is_plan_tab = ($active_tab === 'plan');
    $is_rules_tab = ($active_tab === 'rules');
    ?>
    <div class="tfp-admin-app tfp-admin-course-detail-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="">
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                    <span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span>
                </a>
            </div>
        </aside>

        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span>
            </div>
            <div class="tfp-admin-breadcrumb">
                <a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a>
                <b>/</b><a href="<?php echo esc_url(tfp_admin_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a>
                <b>/</b><strong class="tfp-admin-breadcrumb-current"><?php echo esc_html($course_heading_title); ?></strong>
                <?php if ($is_plan_tab || $is_rules_tab || $active_tab === 'cohorts') : ?><b>/</b><strong class="tfp-admin-breadcrumb-current"><?php echo $is_rules_tab ? esc_html__('Rules', 'tfp-dashboard') : ($is_plan_tab ? esc_html__('Plan', 'tfp-dashboard') : esc_html__('Overview — Cohorts', 'tfp-dashboard')); ?></strong><?php endif; ?>
            </div>

            <header class="tfp-admin-page-header tfp-admin-course-detail-header <?php echo $is_rules_tab ? 'tfp-admin-course-rules-header' : ''; ?>">
                <div><h1><?php echo $is_rules_tab ? esc_html__('Course rules & defaults', 'tfp-dashboard') : ($active_tab === 'cohorts' ? esc_html__('Cohorts', 'tfp-dashboard') : esc_html($course_heading_title)); ?></h1><p><?php echo $active_tab === 'cohorts' ? esc_html__('Manage active groups, track facilitator activity, and manage cross-course progress', 'tfp-dashboard') : ($is_plan_tab ? esc_html__('Course Plan & Week Mapping', 'tfp-dashboard') : ($is_rules_tab ? esc_html__('Set course-wide progression, completion, assessment, grading, and facilitation defaults.', 'tfp-dashboard') : esc_html__('Course details and week mapping', 'tfp-dashboard'))); ?></p></div>
                <div class="tfp-admin-course-header-actions">
                    <?php if ($is_rules_tab) : ?>
                        <button class="tfp-admin-course-rules-save" type="submit" form="tfp-course-rules-form" name="tfp_save_course_rules" value="1"><img class="tfp-admin-course-rules-save-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Save.png" alt="" aria-hidden="true"><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                        <a class="tfp-admin-course-rules-cancel" href="<?php echo esc_url(add_query_arg('course_tab', 'rules', tfp_admin_course_detail_url($course_id))); ?>"><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></a>
                    <?php else : ?>
                        <?php if ($is_plan_tab) : ?>
                            <a class="tfp-admin-course-edit" href="<?php echo esc_url(get_edit_post_link($course_id, 'raw')); ?>"><span aria-hidden="true">✎</span><?php esc_html_e('Edit Course', 'tfp-dashboard'); ?></a>
                        <?php endif; ?>
                        <?php if ($active_tab !== 'cohorts') : ?><a class="tfp-admin-course-view-cohorts" href="<?php echo esc_url($cohorts_url); ?>"><span aria-hidden="true">◉</span><?php esc_html_e('View Cohorts', 'tfp-dashboard'); ?></a><?php endif; ?>
                    <?php endif; ?>
                </div>
            </header>

            <nav class="tfp-admin-course-tabs" aria-label="<?php esc_attr_e('Course sections', 'tfp-dashboard'); ?>">
                <?php foreach ($tabs as $label => $tab) : ?>
                    <a class="<?php echo $active_tab === $tab ? 'is-active' : 'is-inactive'; ?>" href="<?php echo esc_url($tab === '' ? tfp_admin_course_detail_url($course_id) : add_query_arg('course_tab', $tab, tfp_admin_course_detail_url($course_id))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="tfp-admin-content tfp-admin-course-detail-content">
                <?php if ($is_rules_tab) : ?>
                    <?php require TFP_DASH_PATH . 'includes/admin/course-detail-rules.php'; ?>
                <?php elseif ($is_plan_tab) : ?>
                    <section class="tfp-admin-panel tfp-admin-course-plan" id="tfp-course-plan">
                        <div class="tfp-admin-course-plan-toolbar">
                            <h2><?php esc_html_e('Course Plan', 'tfp-dashboard'); ?></h2>
                            <div class="tfp-admin-course-plan-actions">
                                <a href="<?php echo esc_url(get_edit_post_link($course_id, 'raw')); ?>" class="tfp-admin-course-plan-action"><span aria-hidden="true">⇧</span><?php esc_html_e('Import', 'tfp-dashboard'); ?></a>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'tfp_course_plan_export', 'course_id' => $course_id, '_wpnonce' => wp_create_nonce('tfp_course_plan_export_' . $course_id)], admin_url('admin-post.php'))); ?>" class="tfp-admin-course-plan-action"><span aria-hidden="true">⇩</span><?php esc_html_e('Export', 'tfp-dashboard'); ?></a>
                                <a href="#tfp-course-plan-sync-log" class="tfp-admin-course-plan-sync"><span aria-hidden="true">···</span><?php esc_html_e('Sync Log', 'tfp-dashboard'); ?></a>
                            </div>
                        </div>
                        <div class="tfp-admin-course-plan-scroll">
                            <table>
                                <thead><tr><th><?php esc_html_e('Week', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Module Name', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gate Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Exercise ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Content Summary', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Attention', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($plan_visible_rows as $row) :
                                    $lesson_id = (int) $row['id'];
                                    $video_count = get_post_meta($lesson_id, 'tfp_week_video_url', true) ? 1 : 0;
                                    $lesson_post = get_post($lesson_id);
                                    $reading_count = ($lesson_post && (trim(wp_strip_all_tags($lesson_post->post_content)) !== '' || trim((string) $lesson_post->post_excerpt) !== '')) ? 1 : 0;
                                    $quiz_questions = function_exists('tfp_week_get_quiz_questions') ? (array) tfp_week_get_quiz_questions($lesson_id, false) : [];
                                    $test_questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($lesson_id, false) : [];
                                    $question_count = count($quiz_questions) + count($test_questions);
                                    $exercise_id = '';
                                    foreach (['tfp_week_exercise_id', '_tfp_week_exercise_id', 'exercise_id'] as $exercise_key) {
                                        $exercise_id = trim((string) get_post_meta($lesson_id, $exercise_key, true));
                                        if ($exercise_id !== '') break;
                                    }
                                    $needs_attention = ($row['status'] !== 'Published' || empty($row['items']));
                                    $summary_parts = [];
                                    if ($video_count) $summary_parts[] = sprintf(_n('%s Video', '%s Videos', $video_count, 'tfp-dashboard'), number_format_i18n($video_count));
                                    if ($reading_count) $summary_parts[] = sprintf(_n('%s Reading', '%s Readings', $reading_count, 'tfp-dashboard'), number_format_i18n($reading_count));
                                    if ($question_count) $summary_parts[] = sprintf(_n('%s Question', '%s Questions', $question_count, 'tfp-dashboard'), number_format_i18n($question_count));
                                    if (empty($summary_parts)) $summary_parts[] = __('No mapped content', 'tfp-dashboard');
                                ?>
                                    <tr>
                                        <td><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></td>
                                        <td><?php echo esc_html($row['title']); ?></td>
                                        <td><span class="tfp-admin-course-pill is-strict"><?php esc_html_e('Strict', 'tfp-dashboard'); ?></span></td>
                                        <td><?php echo esc_html($exercise_id !== '' ? $exercise_id : '—'); ?></td>
                                        <td><?php echo esc_html(implode(' | ', $summary_parts)); ?></td>
                                        <td><span class="tfp-admin-course-plan-attention <?php echo $needs_attention ? 'is-attention' : 'is-good'; ?>"><?php echo $needs_attention ? esc_html__('Attention', 'tfp-dashboard') : esc_html__('Good', 'tfp-dashboard'); ?></span></td>
                                        <td><a class="tfp-admin-course-week-view" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data['week_rows'])) : ?><tr><td colspan="7" class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="tfp-admin-course-plan-footer">
                            <span><?php echo esc_html(sprintf(__('%1$s - %2$s of %3$s items', 'tfp-dashboard'), number_format_i18n($plan_first_item), number_format_i18n($plan_last_item), number_format_i18n($total_rows))); ?></span>
                            <div class="tfp-admin-course-pagination-actions">
                                <?php if ($plan_page_number > 1) : ?>
                                    <a class="is-previous" href="<?php echo esc_url(add_query_arg(['course_tab' => 'plan', 'plan_page' => $plan_page_number - 1], tfp_admin_course_detail_url($course_id)) . '#tfp-course-plan'); ?>"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                                <?php else : ?>
                                    <span class="is-previous is-disabled"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                                <?php endif; ?>
                                <?php if ($plan_page_number < $plan_total_pages) : ?>
                                    <a class="is-next" href="<?php echo esc_url(add_query_arg(['course_tab' => 'plan', 'plan_page' => $plan_page_number + 1], tfp_admin_course_detail_url($course_id)) . '#tfp-course-plan'); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></a>
                                <?php else : ?>
                                    <span class="is-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tfp-admin-course-plan-sync-note" id="tfp-course-plan-sync-log"><?php esc_html_e('Sync log is not available yet.', 'tfp-dashboard'); ?></div>
                    </section>
                <?php elseif ($active_tab === 'cohorts') : ?>
                    <?php
                    // Load published cohort records from the existing cohort API.
                    $cohort_posts = function_exists('tfp_cohorts_for_course') ? (array) tfp_cohorts_for_course($course_id) : [];
                    $facilitator_filter = isset($_GET['facilitator']) ? absint($_GET['facilitator']) : 0;
                    $cohort_search = isset($_GET['cohort_search']) ? sanitize_text_field(wp_unslash($_GET['cohort_search'])) : '';
                    $cohort_rows = [];
                    $facilitator_ids = [];
                    // Load real published courses for the course switcher.
                    $available_courses = get_posts([
                        'post_type' => 'sfwd-courses',
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                    ]);
                    foreach ($cohort_posts as $cohort_post) {
                        if (!$cohort_post instanceof WP_Post || 'publish' !== $cohort_post->post_status) {
                            continue;
                        }
                        $cohort_id = (int) $cohort_post->ID;
                        $cohort_data = function_exists('tfp_cohort_get') ? tfp_cohort_get($cohort_id) : [];
                        $cohort_data = is_array($cohort_data) ? $cohort_data : [];
                        $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id') ? (int) tfp_cohort_get_facilitator_user_id($cohort_id) : 0;
                        $facilitator_user = $facilitator_id ? get_userdata($facilitator_id) : false;
                        $facilitator_name = $facilitator_user ? $facilitator_user->display_name : (string) get_post_meta($cohort_id, '_facilitator', true);
                        if ($facilitator_id) {
                            $facilitator_ids[$facilitator_id] = $facilitator_name;
                        }
                        // Apply facilitator and text search together.
                        $searchable_text = get_the_title($cohort_id) . ' ' . $course_heading_title . ' ' . $facilitator_name;
                        if ($facilitator_filter && $facilitator_id !== $facilitator_filter) {
                            continue;
                        }
                        if ($cohort_search !== '' && stripos($searchable_text, $cohort_search) === false) {
                            continue;
                        }
                        $cohort_rows[] = [
                            'id' => $cohort_id,
                            'name' => get_the_title($cohort_id),
                            'course' => $course_heading_title,
                            'start' => !empty($cohort_data['start_label']) ? $cohort_data['start_label'] : '—',
                            'schedule' => !empty($cohort_data['schedule']) ? $cohort_data['schedule'] : '—',
                            'facilitator' => $facilitator_name !== '' ? $facilitator_name : '—',
                            'facilitator_id' => $facilitator_id,
                        ];
                    }
                    asort($facilitator_ids, SORT_NATURAL | SORT_FLAG_CASE);
                    $cohort_export_url = add_query_arg([
                        'action' => 'tfp_admin_course_cohorts_export',
                        'course_id' => $course_id,
                        '_wpnonce' => wp_create_nonce('tfp_admin_course_cohorts_export_' . $course_id),
                    ], admin_url('admin-post.php'));
                    ?>
                    <section class="tfp-admin-course-cohorts">
                        <div class="tfp-admin-course-cohorts-filters">
                            <label class="screen-reader-text" for="tfp-cohort-course"><?php esc_html_e('Select course', 'tfp-dashboard'); ?></label>
                            <select id="tfp-cohort-course" aria-label="<?php esc_attr_e('Select course', 'tfp-dashboard'); ?>" onchange="if (this.value) { window.location.href = this.value; }">
                                <?php foreach ($available_courses as $available_course) :
                                    $available_course_id = (int) $available_course->ID;
                                    $available_course_label = $available_course->post_title;
                                    if (1039 === $available_course_id) $available_course_label = __('12 Week Foundation', 'tfp-dashboard');
                                    elseif (1659 === $available_course_id) $available_course_label = __('58 Week Discipleship', 'tfp-dashboard');
                                    $available_course_url = add_query_arg('course_tab', 'cohorts', tfp_admin_course_detail_url($available_course_id));
                                ?>
                                    <option value="<?php echo esc_url($available_course_url); ?>" <?php selected($course_id, $available_course_id); ?>><?php echo esc_html($available_course_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <form method="get" class="tfp-admin-course-cohorts-filter-form" id="tfp-course-cohorts-filter-form">
                                <input type="hidden" name="course_tab" value="cohorts">
                                <label class="screen-reader-text" for="tfp-cohort-facilitator"><?php esc_html_e('Filter by facilitator', 'tfp-dashboard'); ?></label>
                                <select id="tfp-cohort-facilitator" name="facilitator" onchange="this.form.submit()">
                                    <option value="0"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></option>
                                    <?php foreach ($facilitator_ids as $fid => $fname) : ?>
                                        <option value="<?php echo esc_attr($fid); ?>" <?php selected($facilitator_filter, $fid); ?>><?php echo esc_html($fname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="tfp-admin-course-cohorts-search">
                                    <img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png" alt="" class="tfp-admin-course-cohorts-search-icon" aria-hidden="true">
                                    <input id="tfp-cohort-search" type="search" name="cohort_search" value="<?php echo esc_attr($cohort_search); ?>" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>" autocomplete="off">
                                </label>
                                <button type="submit" class="screen-reader-text"><?php esc_html_e('Search', 'tfp-dashboard'); ?></button>
                            </form>
                            <script>
                            (function () {
                                var search = document.getElementById('tfp-cohort-search');
                                var form = document.getElementById('tfp-course-cohorts-filter-form');
                                if (!search || !form) return;
                                var timer;
                                search.addEventListener('input', function () {
                                    window.clearTimeout(timer);
                                    timer = window.setTimeout(function () {
                                        if (form.requestSubmit) form.requestSubmit();
                                        else form.submit();
                                    }, 350);
                                });
                            }());
                            </script>
                        </div>
                        <div class="tfp-admin-panel tfp-admin-course-cohorts-panel">
                            <div class="tfp-admin-course-cohorts-toolbar">
                                <h2><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></h2>
                                <div>
                                    <a class="tfp-admin-course-cohorts-export" href="<?php echo esc_url($cohort_export_url); ?>"><span aria-hidden="true">⇩</span><?php esc_html_e('Export', 'tfp-dashboard'); ?></a>
                                    <a class="tfp-admin-course-cohorts-sync" href="#tfp-course-cohorts-sync-log"><span aria-hidden="true">···</span><?php esc_html_e('Sync Log', 'tfp-dashboard'); ?></a>
                                </div>
                            </div>
                            <div class="tfp-admin-course-cohorts-table-wrap">
                                <table>
                                    <thead><tr>
                                        <th><?php esc_html_e('Cohort Name', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Course Name', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Start Date', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Weekly Meeting', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Discord', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ($cohort_rows as $row) :
                                        $discord_url = apply_filters('tfp_dashboard_discord_url', 'https://discord.gg/MunVQXc3a');
                                        $view_url = get_edit_post_link($row['id'], 'raw');
                                    ?>
                                        <tr>
                                            <td><?php echo esc_html($row['name']); ?></td>
                                            <td><?php echo esc_html($row['course']); ?></td>
                                            <td><?php echo esc_html($row['start']); ?></td>
                                            <td><?php echo esc_html($row['schedule']); ?></td>
                                            <td><?php echo esc_html($row['facilitator']); ?></td>
                                            <td><a class="tfp-admin-course-cohorts-join" href="<?php echo esc_url($discord_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Join', 'tfp-dashboard'); ?></a></td>
                                            <td><a class="tfp-admin-course-cohorts-view" href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($cohort_rows)) : ?>
                                        <tr><td colspan="7" class="tfp-admin-course-cohorts-empty"><?php esc_html_e('No active cohorts found for this course.', 'tfp-dashboard'); ?></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="tfp-admin-course-cohorts-sync-note" id="tfp-course-cohorts-sync-log"><?php esc_html_e('Sync log is not available yet.', 'tfp-dashboard'); ?></p>
                    </section>
                <?php else : ?>
                <section class="tfp-admin-course-detail-stats">
                    <article><small><?php esc_html_e('Total Weeks', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total_weeks'])); ?></strong><p><?php esc_html_e('Number of weeks in course', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Modules Mapped', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['modules_mapped'])); ?></strong><p><?php esc_html_e('Total mapped items in course weeks', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['active_cohorts'])); ?></strong><p><?php esc_html_e('Live cohorts running courses', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Course Alerts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['alerts'])); ?></strong><p><?php esc_html_e('Issues needing attention in courses', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-course-detail-panels">
                    <article class="tfp-admin-panel tfp-admin-course-summary">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Course Summary', 'tfp-dashboard'); ?></h2></div>
                        <dl>
                            <div><dt><?php esc_html_e('Status', 'tfp-dashboard'); ?></dt><dd><span class="tfp-admin-course-pill <?php echo $data['course']->post_status === 'publish' ? 'is-published' : 'is-attention'; ?>"><?php echo esc_html($data['status']); ?></span></dd></div>
                            <div><dt><?php esc_html_e('Course Type', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['course_type']); ?></dd></div>
                            <div><dt><?php esc_html_e('Created', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['created']); ?></dd></div>
                            <div><dt><?php esc_html_e('Last Updated', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['updated']); ?></dd></div>
                            <div><dt><?php esc_html_e('Default Attendance Policy', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['attendance_policy']); ?></dd></div>
                            <div><dt><?php esc_html_e('Quiz Defaults', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['quiz_defaults']); ?></dd></div>
                            <div><dt><?php esc_html_e('Test Defaults', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['test_defaults']); ?></dd></div>
                        </dl>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-course-week-health">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('User System Health', 'tfp-dashboard'); ?></h2><a href="#tfp-course-weeks"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-course-week-health-list">
                            <?php foreach (array_slice($data['week_rows'], 0, 12) as $row) : ?>
                                <div class="tfp-admin-course-week-health-row">
                                    <strong><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></strong>
                                    <span><?php echo esc_html($row['title']); ?></span>
                                    <em class="tfp-admin-course-pill <?php echo esc_attr($row['status_class']); ?>"><?php echo esc_html($row['status']); ?></em>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($data['week_rows'])) : ?><p class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </article>
                </section>

                <section class="tfp-admin-panel tfp-admin-course-weeks" id="tfp-course-weeks">
                    <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Weeks', 'tfp-dashboard'); ?></h2></div>
                    <div class="tfp-admin-course-weeks-scroll">
                        <table>
                            <thead><tr><th><?php esc_html_e('Week', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Module', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Required Items', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gate', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($visible_rows as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></td>
                                        <td><?php echo esc_html($row['title']); ?></td>
                                        <td><div class="tfp-admin-course-required-items"><?php foreach ($row['items'] as $item) : ?><span><?php echo esc_html($item); ?></span><?php endforeach; ?><?php if (empty($row['items'])) : ?><span class="is-empty">—</span><?php endif; ?></div></td>
                                        <td><span class="tfp-admin-course-pill is-strict"><?php echo esc_html($row['gate']); ?></span></td>
                                        <td><a class="tfp-admin-course-week-view" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($visible_rows)) : ?><tr><td colspan="5" class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tfp-admin-course-weeks-footer">
                        <span><?php echo esc_html(sprintf(__('%1$s - %2$s of %3$s weeks', 'tfp-dashboard'), number_format_i18n($first_item), number_format_i18n($last_item), number_format_i18n($total_rows))); ?></span>
                        <div class="tfp-admin-course-pagination-actions">
                            <?php if ($page_number > 1) : ?>
                                <a class="is-previous" href="<?php echo esc_url(add_query_arg('week_page', $page_number - 1, tfp_admin_course_detail_url($course_id)) . '#tfp-course-weeks'); ?>"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                            <?php else : ?>
                                <span class="is-previous is-disabled"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                            <?php endif; ?>
                            <?php if ($page_number < $total_pages) : ?>
                                <a class="is-next" href="<?php echo esc_url(add_query_arg('week_page', $page_number + 1, tfp_admin_course_detail_url($course_id)) . '#tfp-course-weeks'); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></a>
                            <?php else : ?>
                                <span class="is-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php
}

add_filter('pre_handle_404', function ($preempt, $wp_query) {
    if (tfp_admin_course_detail_route_id()) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 13, 2);

add_action('template_redirect', function () {
    if (!tfp_admin_course_detail_route_id()) {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-course-detail.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 2);


/**
 * Export the mapped course plan as CSV.
 */
function tfp_admin_course_plan_export() {
    if (!is_user_logged_in() || (!current_user_can('manage_options') && !current_user_can('administrator'))) {
        wp_die(esc_html__('You do not have permission to export this course plan.', 'tfp-dashboard'), '', ['response' => 403]);
    }
    $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    check_admin_referer('tfp_course_plan_export_' . $course_id);
    $data = tfp_admin_course_detail_get_data($course_id);
    if (!$data) {
        wp_die(esc_html__('The requested course could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="course-plan-' . $course_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Week', 'Module Name', 'Gate Status', 'Exercise ID', 'Content Summary', 'Attention']);
    foreach ($data['week_rows'] as $row) {
        $lesson_id = (int) $row['id'];
        $video_count = get_post_meta($lesson_id, 'tfp_week_video_url', true) ? 1 : 0;
        $lesson_post = get_post($lesson_id);
        $reading_count = ($lesson_post && (trim(wp_strip_all_tags($lesson_post->post_content)) !== '' || trim((string) $lesson_post->post_excerpt) !== '')) ? 1 : 0;
        $quiz_questions = function_exists('tfp_week_get_quiz_questions') ? (array) tfp_week_get_quiz_questions($lesson_id, false) : [];
        $test_questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($lesson_id, false) : [];
        $question_count = count($quiz_questions) + count($test_questions);
        $exercise_id = '';
        foreach (['tfp_week_exercise_id', '_tfp_week_exercise_id', 'exercise_id'] as $exercise_key) {
            $exercise_id = trim((string) get_post_meta($lesson_id, $exercise_key, true));
            if ($exercise_id !== '') break;
        }
        $summary = [];
        if ($video_count) $summary[] = $video_count . ' Video';
        if ($reading_count) $summary[] = $reading_count . ' Reading';
        if ($question_count) $summary[] = $question_count . ' Question';
        if (!$summary) $summary[] = 'No mapped content';
        $attention = ($row['status'] !== 'Published' || empty($row['items'])) ? 'Attention' : 'Good';
        fputcsv($output, ['Week ' . $row['number'], $row['title'], 'Strict', $exercise_id, implode(' | ', $summary), $attention]);
    }
    fclose($output);
    exit;
}
add_action('admin_post_tfp_course_plan_export', 'tfp_admin_course_plan_export');


/**
 * Export published cohorts for a LearnDash course as CSV.
 */
function tfp_admin_course_cohorts_export() {
    $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    $course = $course_id ? get_post($course_id) : null;

    // Validate the target before checking its object-level permission.
    if (!$course || 'sfwd-courses' !== $course->post_type) {
        wp_die(esc_html__('Invalid course.', 'tfp-dashboard'), esc_html__('Export failed', 'tfp-dashboard'), ['response' => 404]);
    }

    // Verify nonce and ensure the current user can edit this specific course.
    check_admin_referer('tfp_admin_course_cohorts_export_' . $course_id);
    if (!is_user_logged_in() || !current_user_can('edit_post', $course_id)) {
        wp_die(esc_html__('You do not have permission to export cohorts for this course.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    if (!function_exists('tfp_cohorts_for_course') || !function_exists('tfp_cohort_get')) {
        wp_die(esc_html__('Cohort data is currently unavailable.', 'tfp-dashboard'), esc_html__('Export failed', 'tfp-dashboard'), ['response' => 500]);
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="course-' . $course_id . '-cohorts.csv"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');
    if (false === $output) {
        wp_die(esc_html__('Could not create the CSV export.', 'tfp-dashboard'), esc_html__('Export failed', 'tfp-dashboard'), ['response' => 500]);
    }

    // Prevent spreadsheet formula injection in user-controlled text fields.
    $csv_cell = static function ($value) {
        $value = (string) $value;
        return preg_match('/^[\\s]*[=+@-]/u', $value) ? "'" . $value : $value;
    };

    fputcsv($output, ['Cohort Name', 'Course Name', 'Start Date', 'Weekly Meeting', 'Facilitator']);

    foreach ((array) tfp_cohorts_for_course($course_id) as $cohort) {
        if (!$cohort instanceof WP_Post || 'publish' !== $cohort->post_status) {
            continue;
        }

        $cohort_id = (int) $cohort->ID;
        $data = tfp_cohort_get($cohort_id);
        if (!is_array($data)) {
            continue;
        }

        $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id')
            ? (int) tfp_cohort_get_facilitator_user_id($cohort_id)
            : 0;
        $facilitator_user = $facilitator_id ? get_userdata($facilitator_id) : false;
        $facilitator = $facilitator_user
            ? $facilitator_user->display_name
            : (string) ($data['facilitator'] ?? '');

        fputcsv($output, [
            $csv_cell(get_the_title($cohort_id)),
            $csv_cell(get_the_title($course_id)),
            $csv_cell($data['start_label'] ?? ''),
            $csv_cell($data['schedule'] ?? ''),
            $csv_cell($facilitator),
        ]);
    }

    fclose($output);
    exit;
}
add_action('admin_post_tfp_admin_course_cohorts_export', 'tfp_admin_course_cohorts_export');
