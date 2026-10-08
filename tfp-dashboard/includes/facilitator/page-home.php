<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Facilitator dashboard data helpers.
 * The dashboard is intentionally read-only for now; action links point to
 * the future facilitator pages so those pages can be implemented independently.
 */
function tfp_facilitator_current_cohorts($user_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();
    $user = get_userdata($user_id);
    $name = $user ? $user->display_name : '';

    $args = [
        'post_type' => 'tfp_cohort',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => '_start_date',
        'order' => 'ASC',
    ];

    // Administrators/shop managers can review all cohorts. Facilitators
    // only see cohorts explicitly assigned to their WordPress user.
    $is_admin = user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce');
    if ($is_admin) {
        return get_posts($args);
    }

    $meta_query = [
        [
            'key' => '_facilitator_user_id',
            'value' => $user_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ],
    ];

    // Keep legacy cohorts working while they are migrated to user-ID
    // assignments.
    if ($name !== '') {
        $meta_query = [
            'relation' => 'OR',
            [
                'key' => '_facilitator_user_id',
                'value' => $user_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
            [
                'key' => '_facilitator',
                'value' => $name,
                'compare' => '=',
            ],
        ];
    }

    $args['meta_query'] = $meta_query;
    return get_posts($args);
}

function tfp_facilitator_cohort_students($cohort_id)
{
    $ids = get_users([
        'meta_key' => 'tfp_cohort_choice',
        'meta_value' => absint($cohort_id),
        'fields' => 'ID',
    ]);

    $students = [];
    foreach ($ids as $id) {
        if (function_exists('tfp_billing_user_has_paid') && !tfp_billing_user_has_paid($id)) {
            continue;
        }
        $students[] = (int) $id;
    }

    return array_values(array_unique($students));
}

function tfp_facilitator_metric_counts($cohorts)
{
    $course_ids = [];
    $student_ids = [];

    foreach ($cohorts as $cohort) {
        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
        if ($course_id) {
            $course_ids[] = $course_id;
        }
        $student_ids = array_merge($student_ids, tfp_facilitator_cohort_students($cohort->ID));
    }

    return [
        'courses' => count(array_unique($course_ids)),
        'students' => count(array_unique($student_ids)),
    ];
}

function tfp_facilitator_cohort_course_id($cohort_id)
{
    return (int) get_post_meta($cohort_id, '_related_course', true);
}
function tfp_facilitator_course_weeks($course_id)
{
    if ($course_id && function_exists('tfp_ld_get_weeks')) {
        $weeks = tfp_ld_get_weeks($course_id);
        return is_array($weeks) ? count($weeks) : 0;
    }
    return 0;
}
function tfp_facilitator_cohort_week($cohort_id, $course_id = 0)
{
    $start = get_post_meta($cohort_id, '_start_date', true);
    if (!$start || !strtotime($start))
        return 1;
    $week = (int) floor(max(0, current_time('timestamp') - strtotime($start)) / WEEK_IN_SECONDS) + 1;
    $total = tfp_facilitator_course_weeks($course_id);
    return $total > 0 ? min($week, $total) : $week;
}
function tfp_facilitator_filter_cohorts($cohorts)
{
    $course = isset($_GET['tfp_fac_course']) ? absint($_GET['tfp_fac_course']) : 0;
    $cohort = isset($_GET['tfp_fac_cohort']) ? absint($_GET['tfp_fac_cohort']) : 0;
    $week = isset($_GET['tfp_fac_week']) ? absint($_GET['tfp_fac_week']) : 0;
    $search = isset($_GET['tfp_fac_search']) ? sanitize_text_field(wp_unslash($_GET['tfp_fac_search'])) : '';
    if (!$course && !$cohort && !$week && !$search)
        return $cohorts;
    return array_values(array_filter($cohorts, function ($item) use ($course, $cohort, $week, $search) {
        $course_id = tfp_facilitator_cohort_course_id($item->ID);
        if ($course && $course_id !== $course)
            return false;
        if ($cohort && (int) $item->ID !== $cohort)
            return false;
        if ($week && tfp_facilitator_cohort_week($item->ID, $course_id) !== $week)
            return false;
        if ($search) {
            $needle = strtolower($search);
            $haystack = strtolower($item->post_title . ' ' . ($course_id ? get_the_title($course_id) : ''));
            $student_match = false;
            foreach (tfp_facilitator_cohort_students($item->ID) as $student_id) {
                $student = get_userdata($student_id);
                if ($student && strpos(strtolower($student->display_name . ' ' . $student->user_email), $needle) !== false) {
                    $student_match = true;
                    break;
                }
            }
            if (strpos($haystack, $needle) === false && !$student_match)
                return false;
        }
        return true;
    }));
}
function tfp_facilitator_homework_items($cohorts)
{
    $students = $courses = [];
    foreach ($cohorts as $cohort) {
        $students = array_merge($students, tfp_facilitator_cohort_students($cohort->ID));
        $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
        if ($course_id)
            $courses[] = $course_id;
    }
    $students = array_values(array_unique(array_map('absint', $students)));
    $courses = array_values(array_unique(array_map('absint', $courses)));
    if (!$students || !$courses || !post_type_exists('sfwd-assignment'))
        return [];
    $posts = get_posts(['post_type' => 'sfwd-assignment', 'post_status' => ['publish', 'pending', 'draft', 'graded', 'not_graded'], 'posts_per_page' => 200, 'author__in' => $students, 'orderby' => 'date', 'order' => 'DESC']);
    $items = [];
    foreach ($posts as $post) {
        $course_id = function_exists('learndash_get_course_id') ? (int) learndash_get_course_id($post->ID) : 0;
        if ($course_id && !in_array($course_id, $courses, true))
            continue;
        if (function_exists('learndash_is_assignment_approved') && learndash_is_assignment_approved($post->ID))
            continue;
        $items[] = $post;
    }
    return $items;
}
function tfp_facilitator_test_items($cohorts)
{
    $students = $courses = [];
    foreach ($cohorts as $cohort) {
        $students = array_merge($students, tfp_facilitator_cohort_students($cohort->ID));
        $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
        if ($course_id)
            $courses[] = $course_id;
    }
    $students = array_values(array_unique(array_map('absint', $students)));
    $courses = array_values(array_unique(array_map('absint', $courses)));
    if (!$students || !$courses || !post_type_exists('sfwd-essays'))
        return [];
    $posts = get_posts(['post_type' => 'sfwd-essays', 'post_status' => ['publish', 'pending', 'draft', 'not_graded'], 'posts_per_page' => 200, 'author__in' => $students, 'orderby' => 'date', 'order' => 'DESC']);
    $items = [];
    foreach ($posts as $post) {
        $course_id = (int) get_post_meta($post->ID, 'course_id', true);
        if (!$course_id && function_exists('learndash_get_course_id'))
            $course_id = (int) learndash_get_course_id($post->ID);
        if ($course_id && !in_array($course_id, $courses, true))
            continue;
        if ($post->post_status === 'graded')
            continue;
        $items[] = $post;
    }
    return $items;
}
function tfp_facilitator_activity_items($cohorts, $date = '')
{
    $posts = array_merge(tfp_facilitator_homework_items($cohorts), tfp_facilitator_test_items($cohorts));
    $items = [];
    foreach ($posts as $post) {
        if ($date && get_the_date('Y-m-d', $post) !== $date)
            continue;
        $items[] = ['type' => get_post_type($post) === 'sfwd-essays' ? __('Quiz / test submitted', 'tfp-dashboard') : __('Homework submitted', 'tfp-dashboard'), 'title' => get_the_title($post), 'date' => get_the_date('m/d/Y', $post), 'time' => get_the_date('g:i A', $post), 'url' => get_permalink($post->ID), 'timestamp' => get_post_time('U', true, $post)];
    }
    usort($items, function ($a, $b) {
        return $b['timestamp'] <=> $a['timestamp']; });
    return array_slice($items, 0, 8);
}

function tfp_dashboard_render_facilitator_home_content()
{
    $user = wp_get_current_user();
    $name = $user && $user->exists() ? $user->display_name : __('Facilitator', 'tfp-dashboard');
    $all_cohorts = tfp_facilitator_current_cohorts();
    $cohorts = tfp_facilitator_filter_cohorts($all_cohorts);
    $metrics = tfp_facilitator_metric_counts($cohorts);
    $homework_items = tfp_facilitator_homework_items($cohorts);
    $test_items = tfp_facilitator_test_items($cohorts);
    $homework_count = count($homework_items);
    $tests_count = count($test_items);
    $activities = tfp_facilitator_activity_items($cohorts);

    tfp_dashboard_render_facilitator_page_header($name);

    ?>
    <?php
    $course_options = [];
    $cohort_options = [];
    $max_week = 1;
    foreach ($all_cohorts as $cohort) {
        $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
        if ($course_id)
            $course_options[$course_id] = get_the_title($course_id);
        $cohort_options[$cohort->ID] = $cohort->post_title;
        $max_week = max($max_week, tfp_facilitator_cohort_week($cohort->ID, $course_id));
    }
    $selected_course = isset($_GET['tfp_fac_course']) ? absint($_GET['tfp_fac_course']) : 0;
    $selected_cohort = isset($_GET['tfp_fac_cohort']) ? absint($_GET['tfp_fac_cohort']) : 0;
    $selected_week = isset($_GET['tfp_fac_week']) ? absint($_GET['tfp_fac_week']) : 0;
    $search_term = isset($_GET['tfp_fac_search']) ? sanitize_text_field(wp_unslash($_GET['tfp_fac_search'])) : '';
    if ($selected_course)
        foreach ($all_cohorts as $cohort)
            if (tfp_facilitator_cohort_course_id($cohort->ID) !== $selected_course)
                unset($cohort_options[$cohort->ID]);
    ?>
    <form class="tfp-facilitator-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>">
        <select name="tfp_fac_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>"
            onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
            <?php foreach ($course_options as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_course, $id); ?>>
                    <?php echo esc_html($title); ?></option><?php endforeach; ?>
        </select>
        <select name="tfp_fac_cohort" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>"
            onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
            <?php foreach ($cohort_options as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_cohort, $id); ?>>
                    <?php echo esc_html($title); ?></option><?php endforeach; ?>
        </select>
        <select name="tfp_fac_week" aria-label="<?php esc_attr_e('Week', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
            <?php for ($w = 1; $w <= $max_week; $w++): ?>
                <option value="<?php echo esc_attr($w); ?>" <?php selected($selected_week, $w); ?>>
                    <?php printf(esc_html__('Week %d', 'tfp-dashboard'), $w); ?></option><?php endfor; ?>
        </select>
        <div class="tfp-dash-search tfp-facilitator-search"><span class="tfp-dash-search__icon" aria-hidden="true"><svg
                    xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path
                        d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.21799 15.9079C1 15.4801 1 14.9203 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1.40973 2.0918 1.21799C2.51962 1 3.08009 1 4.2002 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.2837 1.40973 16.5905 1.71547 16.7822 2.0918C17 2.5192 17 3.07899 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z"
                        stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg></span><input type="search" name="tfp_fac_search" value="<?php echo esc_attr($search_term); ?>"
                placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>"
                aria-label="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>"></div>
        <?php if ($selected_course || $selected_cohort || $selected_week || $search_term): ?><a
                class="tfp-facilitator-clear-filter"
                href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('Clear Filters', 'tfp-dashboard'); ?></a><?php endif; ?>
    </form>

    <div class="tfp-facilitator-metrics">
        <article class="tfp-facilitator-metric">
            <span><?php esc_html_e('Active Courses', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($metrics['courses']); ?></strong>
            <small><?php esc_html_e('Current Courses Facilitating', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--teal">
            <span><?php esc_html_e('Total Students', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($metrics['students']); ?></strong>
            <small><?php esc_html_e('Across Selected Cohorts', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--gold">
            <span><?php esc_html_e('Homework to Review', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($homework_count); ?></strong>
            <small><?php esc_html_e('Submitted Assignments', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--red">
            <span><?php esc_html_e('Tests to Review', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($tests_count); ?></strong>
            <small><?php esc_html_e('Awaiting Review', 'tfp-dashboard'); ?></small>
        </article>
    </div>

    <div class="tfp-facilitator-grid">
        <section class="tfp-dash-panel tfp-facilitator-panel">
            <div class="tfp-facilitator-panel__head">
                <div>
                    <h6><?php esc_html_e('Your Active Courses', 'tfp-dashboard'); ?></h6>
                    <p><?php esc_html_e("Classes you're currently facilitating", 'tfp-dashboard'); ?></p>
                </div>
                <a
                    href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
            </div>

            <div class="tfp-facilitator-course-list">
                <?php if ($cohorts): ?>
                    <?php foreach (array_slice($cohorts, 0, 6) as $cohort):
                        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
                        $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
                        $students = count(tfp_facilitator_cohort_students($cohort->ID));
                        $schedule = (string) get_post_meta($cohort->ID, '_schedule_text', true);
                        $weeks = tfp_facilitator_course_weeks($course_id);
                        $current_week = tfp_facilitator_cohort_week($cohort->ID, $course_id);
                        ?>
                        <div class="tfp-facilitator-course-row">
                            <div>
                                <h6><?php echo esc_html($course_title); ?> – <?php echo esc_html(get_the_title($cohort->ID)); ?>
                                </h6>
                                <p># <?php echo esc_html($students); ?>             <?php esc_html_e('Students', 'tfp-dashboard'); ?></p>
                                <p><?php esc_html_e('Current Week', 'tfp-dashboard'); ?>:
                                    <span class="current-week">[<?php printf(esc_html__('Week %d', 'tfp-dashboard'), $current_week); ?>]</span></p>
                            </div>
                            <div class="tfp-facilitator-course-meta">
                                <strong><?php echo esc_html($weeks ? 'Week ' . $current_week . '/' . $weeks : 'Week ' . $current_week); ?></strong>
                                <span><?php echo esc_html($schedule ?: '—'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tfp-facilitator-empty">
                        <?php esc_html_e('No active cohorts are assigned yet.', 'tfp-dashboard'); ?></div>
                <?php endif; ?>
            </div>
        </section>

        <div class="tfp-facilitator-right">
            <section class="tfp-dash-panel tfp-facilitator-panel tfp-facilitator-panel--quick">
                <h6><?php esc_html_e('Quick Actions', 'tfp-dashboard'); ?></h6>
                <p><?php esc_html_e('Common tasks for every class', 'tfp-dashboard'); ?></p>
                <a class="tfp-dash-btn tfp-dash-btn--primary tfp-facilitator-action"
                    href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')); ?>"><?php esc_html_e('Review Homework Submissions', 'tfp-dashboard'); ?></a>
                <a class="tfp-facilitator-action tfp-dash-btn tfp-dash-btn--primary"
                    href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-tests')); ?>"><?php esc_html_e('Review Quizzes & Tests', 'tfp-dashboard'); ?></a>
            </section>

            <section class="tfp-facilitator-panel tfp-dash-panel">
                <div class="tfp-facilitator-panel__head">
                    <div>
                        <h6><?php esc_html_e('Pending Tasks', 'tfp-dashboard'); ?></h6>
                        <p><?php esc_html_e('Tasks that need your Attention', 'tfp-dashboard'); ?></p>
                    </div>
                    <a href="#tfp-facilitator-review-list"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                </div>
                <div class="tfp-facilitator-task-list" id="tfp-facilitator-review-list">
                    <?php if ($homework_count): ?>
                        <div>
                            <span><strong><?php printf(esc_html__('%d homework submission%s to review', 'tfp-dashboard'), $homework_count, $homework_count === 1 ? '' : 's'); ?></strong><small><?php esc_html_e('LearnDash assignments awaiting approval', 'tfp-dashboard'); ?></small></span><a
                                class="tfp-grade-btn tfp-grade-btn--view"
                                href="#tfp-facilitator-homework"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                    <?php endif; ?>
                    <?php if ($tests_count): ?>
                        <div>
                            <span><strong><?php printf(esc_html__('%d quiz/test response%s to review', 'tfp-dashboard'), $tests_count, $tests_count === 1 ? '' : 's'); ?></strong><small><?php esc_html_e('Submitted responses awaiting grading', 'tfp-dashboard'); ?></small></span><a
                                class="tfp-dash-btn tfp-dash-btn--primary tfp-facilitator-task-action"
                                href="#tfp-facilitator-tests"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                    <?php endif; ?>
                    <?php if (!$homework_count && !$tests_count): ?>
                        <div class="tfp-facilitator-task-empty">
                            <strong><?php esc_html_e("You're all caught up", 'tfp-dashboard'); ?></strong><small><?php esc_html_e('No submitted homework or quiz responses require review for the selected filters.', 'tfp-dashboard'); ?></small>
                        </div><?php endif; ?>
                </div>
                <?php if ($homework_items || $test_items): ?>
                    <div class="tfp-facilitator-review-items">
                        <?php foreach (array_slice(array_merge($homework_items, $test_items), 0, 6) as $review_post): ?>
                            <div>
                                <span>
                                    <strong><?php echo esc_html(get_the_title($review_post)); ?></strong>
                                    <small><?php echo esc_html(get_the_author_meta('display_name', $review_post->post_author)); ?> ·
                                        <?php echo esc_html(get_the_date('m/d/Y g:i A', $review_post)); ?></small>
                                </span>
                                <a class="tfp-grade-btn tfp-grade-btn--view"
                                    href="<?php echo esc_url(get_permalink($review_post->ID)); ?>"><?php esc_html_e('Open', 'tfp-dashboard'); ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="tfp-dash-panel tfp-facilitator-panel tfp-facilitator-panel--recent">
            <div class="tfp-facilitator-panel__head">
                <div>
                    <h6><?php esc_html_e('Recent Activities', 'tfp-dashboard'); ?></h6>
                    <p><?php esc_html_e('Your recent facilitation activities', 'tfp-dashboard'); ?></p>
                </div>
            </div>
            <div class="tfp-facilitator-activity-list">
                <?php if ($activities):
                    foreach ($activities as $activity): ?>
                        <div>
                            <strong><?php echo esc_html($activity['type']); ?></strong><span><?php echo esc_html($activity['date']); ?></span><small><?php echo esc_html($activity['title']); ?></small><span><?php echo esc_html($activity['time']); ?></span>
                        </div><?php endforeach; else: ?>
                    <div class="tfp-facilitator-activity-empty">
                        <?php esc_html_e('No recent activity matches the selected filters.', 'tfp-dashboard'); ?></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php
}

function tfp_dashboard_render_facilitator_page_header($name)
{
    ?>
    <div class="tfp-dash-pageheader tfp-facilitator-header">
        <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
            <a href="#"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <strong><?php esc_html_e('Console', 'tfp-dashboard'); ?></strong>
        </div>
        <h1 class="tfp-dash-pageheader__title"><?php printf(esc_html__('Welcome, %s', 'tfp-dashboard'), esc_html($name)); ?>
        </h1>
        <p class="tfp-dash-pageheader__subtitle">
            <?php esc_html_e("Here’s your teaching overview and recent activities.", 'tfp-dashboard'); ?></p>
    </div>
    <?php
}
