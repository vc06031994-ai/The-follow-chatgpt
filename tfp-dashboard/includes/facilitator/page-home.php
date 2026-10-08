<?php
if (!defined('ABSPATH')) exit;

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
        'post_type'      => 'tfp_cohort',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_start_date',
        'order'          => 'ASC',
    ];

    // Facilitators see their assigned cohorts. Admin/shop-manager users can
    // see all cohorts, which keeps the dashboard useful for testing.
    $is_admin = user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce');
    if (!$is_admin && $name !== '') {
        $args['meta_query'] = [
            [
                'key'     => '_facilitator',
                'value'   => $name,
                'compare' => '=',
            ],
        ];
    }

    $cohorts = get_posts($args);

    // If the role exists but cohort records have not been assigned yet, show
    // the published cohort list instead of an empty dashboard.
    if (!$cohorts && !$is_admin) {
        $args['meta_query'] = [];
        $cohorts = get_posts($args);
    }

    return $cohorts;
}

function tfp_facilitator_cohort_students($cohort_id)
{
    $ids = get_users([
        'meta_key'   => 'tfp_cohort_choice',
        'meta_value' => absint($cohort_id),
        'fields'     => 'ID',
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
        'courses'  => count(array_unique($course_ids)),
        'students' => count(array_unique($student_ids)),
    ];
}

function tfp_dashboard_render_facilitator_home_content()
{
    $user = wp_get_current_user();
    $name = $user && $user->exists() ? $user->display_name : __('Facilitator', 'tfp-dashboard');
    $cohorts = tfp_facilitator_current_cohorts();
    $metrics = tfp_facilitator_metric_counts($cohorts);

    tfp_dashboard_render_facilitator_page_header($name);

    $homework_count = 0;
    $tests_count = 0;

    // These counters are deliberately conservative until the dedicated review
    // pages are implemented. Existing submission data can be plugged into the
    // same cards without changing the visual layout.
    $homework_count = (int) apply_filters('tfp_facilitator_homework_review_count', $homework_count, $cohorts);
    $tests_count = (int) apply_filters('tfp_facilitator_test_review_count', $tests_count, $cohorts);
    ?>
    <div class="tfp-facilitator-filters" aria-label="<?php esc_attr_e('Dashboard filters', 'tfp-dashboard'); ?>">
        <select aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>">
            <option><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
        </select>
        <select aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>">
            <option><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
        </select>
        <select aria-label="<?php esc_attr_e('Week', 'tfp-dashboard'); ?>">
            <option><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
        </select>
        <button type="button" class="tfp-facilitator-date-filter" aria-label="<?php esc_attr_e('Filter by date', 'tfp-dashboard'); ?>">
            <span aria-hidden="true">▣</span> <?php esc_html_e('All Weeks', 'tfp-dashboard'); ?>
        </button>
    </div>

    <div class="tfp-facilitator-metrics">
        <article class="tfp-facilitator-metric">
            <span><?php esc_html_e('Active Courses', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($metrics['courses']); ?></strong>
            <small><?php esc_html_e('Current Courses Facilitating', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--teal">
            <span><?php esc_html_e('Total Students', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($metrics['students']); ?></strong>
            <small><?php esc_html_e('Across All Cohorts', 'tfp-dashboard'); ?></small>
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
        <section class="tfp-facilitator-panel">
            <div class="tfp-facilitator-panel__head">
                <div>
                    <h2><?php esc_html_e('Your Active Courses', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e("Classes you're currently facilitating", 'tfp-dashboard'); ?></p>
                </div>
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
            </div>

            <div class="tfp-facilitator-course-list">
                <?php if ($cohorts) : ?>
                    <?php foreach (array_slice($cohorts, 0, 6) as $cohort) :
                        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
                        $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
                        $students = count(tfp_facilitator_cohort_students($cohort->ID));
                        $schedule = (string) get_post_meta($cohort->ID, '_schedule_text', true);
                        $weeks = ($course_id && function_exists('tfp_ld_get_weeks')) ? count(tfp_ld_get_weeks($course_id)) : 0;
                        ?>
                        <div class="tfp-facilitator-course-row">
                            <div>
                                <h3><?php echo esc_html($course_title); ?> – <?php echo esc_html(get_the_title($cohort->ID)); ?></h3>
                                <p># <?php echo esc_html($students); ?> <?php esc_html_e('Students', 'tfp-dashboard'); ?></p>
                                <p><?php esc_html_e('Current Week', 'tfp-dashboard'); ?>: <em><?php esc_html_e('Module Topic', 'tfp-dashboard'); ?></em></p>
                            </div>
                            <div class="tfp-facilitator-course-meta">
                                <strong><?php echo esc_html($weeks ? 'Week 1/' . $weeks : 'Week 1'); ?></strong>
                                <span><?php echo esc_html($schedule ?: '—'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="tfp-facilitator-empty"><?php esc_html_e('No active cohorts are assigned yet.', 'tfp-dashboard'); ?></div>
                <?php endif; ?>
            </div>
        </section>

        <div class="tfp-facilitator-right">
            <section class="tfp-facilitator-panel tfp-facilitator-panel--quick">
                <h2><?php esc_html_e('Quick Actions', 'tfp-dashboard'); ?></h2>
                <p><?php esc_html_e('Common tasks for every class', 'tfp-dashboard'); ?></p>
                <a class="tfp-facilitator-action" href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')); ?>"><?php esc_html_e('Review Homework Submissions', 'tfp-dashboard'); ?></a>
                <a class="tfp-facilitator-action" href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-tests')); ?>"><?php esc_html_e('Review Quizzes & Tests', 'tfp-dashboard'); ?></a>
            </section>

            <section class="tfp-facilitator-panel">
                <div class="tfp-facilitator-panel__head">
                    <div>
                        <h2><?php esc_html_e('Pending Tasks', 'tfp-dashboard'); ?></h2>
                        <p><?php esc_html_e('Tasks that need your Attention', 'tfp-dashboard'); ?></p>
                    </div>
                    <a href="#"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                </div>
                <div class="tfp-facilitator-task-list">
                    <div><span><strong><?php esc_html_e('Review submitted homework', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Student submissions', 'tfp-dashboard'); ?></small></span><a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')); ?>"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                    <div><span><strong><?php esc_html_e('Review quizzes & tests', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Student results', 'tfp-dashboard'); ?></small></span><a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-tests')); ?>"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                    <div><span><strong><?php esc_html_e('Confirm weekly meeting attendance', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Cohort attendance', 'tfp-dashboard'); ?></small></span><a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-attendance')); ?>"><?php esc_html_e('Update', 'tfp-dashboard'); ?></a></div>
                    <div><span><strong><?php esc_html_e('Submit weekly meeting notes', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Cohort notes', 'tfp-dashboard'); ?></small></span><a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-meetings')); ?>"><?php esc_html_e('Action', 'tfp-dashboard'); ?></a></div>
                    <div><span><strong><?php esc_html_e('Follow up on students falling behind', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Student progress', 'tfp-dashboard'); ?></small></span><a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                </div>
            </section>
        </div>

        <section class="tfp-facilitator-panel tfp-facilitator-panel--recent">
            <div class="tfp-facilitator-panel__head">
                <div>
                    <h2><?php esc_html_e('Recent Activities', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e('Your recent facilitation activities', 'tfp-dashboard'); ?></p>
                </div>
            </div>
            <div class="tfp-facilitator-activity-list">
                <?php for ($i = 0; $i < 6; $i++) : ?>
                    <div>
                        <strong><?php esc_html_e('Task Completed', 'tfp-dashboard'); ?></strong>
                        <span><?php echo esc_html(date_i18n('m/d/Y')); ?></span>
                        <small><?php esc_html_e('Student activity', 'tfp-dashboard'); ?></small>
                        <span><?php echo esc_html(date_i18n('g:i A')); ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
    </div>
    <?php
}

function tfp_dashboard_render_facilitator_page_header($name)
{
    ?>
    <div class="tfp-facilitator-header">
        <div class="tfp-facilitator-breadcrumb">
            <span><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></span>
            <b>/</b>
            <strong><?php esc_html_e('Console', 'tfp-dashboard'); ?></strong>
        </div>
        <h1><?php printf(esc_html__('Welcome, %s', 'tfp-dashboard'), esc_html($name)); ?></h1>
        <p><?php esc_html_e("Here’s your teaching overview and recent activities.", 'tfp-dashboard'); ?></p>
    </div>
    <?php
}
