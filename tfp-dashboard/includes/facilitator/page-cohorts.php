<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Facilitator Cohorts — all cohorts + selected cohort views.
 * Reuses the existing facilitator cohort, LearnDash, grades, homework, quiz,
 * test, meeting and student-detail data layers.
 */
function tfp_facilitator_cohorts_state()
{
    $cohort_id = isset($_GET['tfp_cohort_id']) ? absint($_GET['tfp_cohort_id']) : 0;
    $tab = isset($_GET['tfp_cohort_tab']) ? sanitize_key($_GET['tfp_cohort_tab']) : 'overview';
    $report_week = isset($_GET['tfp_cohort_report_week']) ? absint($_GET['tfp_cohort_report_week']) : 0;
    $page = max(1, isset($_GET['tfp_cohort_page']) ? absint($_GET['tfp_cohort_page']) : 1);

    if (!in_array($tab, ['overview', 'weekly', 'roster'], true)) {
        $tab = 'overview';
    }

    return [
        'cohort_id' => $cohort_id,
        'tab' => $tab,
        'report_week' => $report_week,
        'page' => $page,
    ];
}

function tfp_facilitator_cohorts_url($args = [])
{
    $url = tfp_dashboard_get_url('tfp-dashboard-facilitator-cohorts');
    return !empty($args) ? add_query_arg($args, $url) : $url;
}

function tfp_facilitator_cohorts_attendance_avg($student_ids)
{
    $values = [];

    foreach ((array) $student_ids as $student_id) {
        if (function_exists('tfp_attendance_get_user_rate')) {
            $value = tfp_attendance_get_user_rate((int) $student_id);
        } else {
            $value = get_user_meta((int) $student_id, 'tfp_attendance_percent', true);
        }

        if ($value !== '' && $value !== null && is_numeric($value)) {
            $values[] = (float) $value;
        }
    }

    return $values ? (int) round(array_sum($values) / count($values)) : null;
}

function tfp_facilitator_cohorts_grade_points($letter)
{
    if (!function_exists('tfp_grade_scale') || !$letter) {
        return null;
    }

    $scale = tfp_grade_scale();
    return isset($scale[$letter]) ? (int) $scale[$letter] : null;
}

function tfp_facilitator_cohorts_average_letter($letters)
{
    $points = [];

    foreach ((array) $letters as $letter) {
        $point = tfp_facilitator_cohorts_grade_points($letter);
        if ($point !== null) {
            $points[] = $point;
        }
    }

    if (!$points || !function_exists('tfp_grade_scale')) {
        return '';
    }

    $rounded = (int) round(array_sum($points) / count($points));
    $by_point = array_flip(tfp_grade_scale());

    return $by_point[$rounded] ?? '';
}

function tfp_facilitator_cohorts_current_lesson($cohort_id, $course_id)
{
    $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
    if (!$weeks) {
        return null;
    }

    $week_number = tfp_facilitator_cohort_week($cohort_id, $course_id);
    return $weeks[$week_number - 1] ?? end($weeks);
}

function tfp_facilitator_cohorts_student_completion($student_id, $lesson_id, $step)
{
    if (!$lesson_id || !function_exists('tfp_ld_get_week_progress')) {
        return false;
    }

    $progress = tfp_ld_get_week_progress((int) $student_id, (int) $lesson_id);
    return !empty($progress[$step]);
}

function tfp_facilitator_cohorts_homework_percent($student_id, $lesson_id)
{
    if (!$lesson_id || !function_exists('tfp_week_homework_progress')) {
        return null;
    }

    $progress = tfp_week_homework_progress((int) $student_id, (int) $lesson_id);
    if ((int) $progress['total'] <= 0) {
        return null;
    }

    return (int) round(((int) $progress['completed'] / (int) $progress['total']) * 100);
}

function tfp_facilitator_cohorts_test_score($student_id, $lesson_id)
{
    if (!$lesson_id || !function_exists('tfp_week_get_test_result')) {
        return null;
    }

    $result = tfp_week_get_test_result((int) $student_id, (int) $lesson_id);
    return is_array($result) && isset($result['score']) ? (int) $result['score'] : null;
}

function tfp_facilitator_cohorts_week_report($student_id, $lesson_id)
{
    $homework = tfp_facilitator_cohorts_homework_percent($student_id, $lesson_id);
    $test = tfp_facilitator_cohorts_test_score($student_id, $lesson_id);
    $grade = $test !== null
        ? (function_exists('tfp_grade_get_week') ? tfp_grade_get_week($student_id, $lesson_id) : '')
        : '';

    return [
        'homework' => $homework,
        'test' => $test,
        'grade' => $grade,
        'attendance' => function_exists('tfp_attendance_get_user_rate') ? tfp_attendance_get_user_rate($student_id) : get_user_meta($student_id, 'tfp_attendance_percent', true),
    ];
}

function tfp_facilitator_cohorts_all_weeks_report($student_id, $course_id, $weeks)
{
    $homework = [];
    $tests = [];

    foreach ((array) $weeks as $week) {
        $hw = tfp_facilitator_cohorts_homework_percent($student_id, $week->ID);
        $test = tfp_facilitator_cohorts_test_score($student_id, $week->ID);

        if ($hw !== null)
            $homework[] = $hw;
        if ($test !== null)
            $tests[] = $test;
    }

    return [
        'homework' => $homework ? (int) round(array_sum($homework) / count($homework)) : null,
        'test' => $tests ? (int) round(array_sum($tests) / count($tests)) : null,
        'grade' => function_exists('tfp_grade_overall') ? tfp_grade_overall($student_id, $course_id) : '',
        'attendance' => function_exists('tfp_attendance_get_user_rate') ? tfp_attendance_get_user_rate($student_id) : get_user_meta($student_id, 'tfp_attendance_percent', true),
    ];
}

function tfp_facilitator_cohorts_falling_behind($student_ids, $lesson_id)
{
    $count = 0;

    foreach ((array) $student_ids as $student_id) {
        $attendance = function_exists('tfp_attendance_get_user_rate') ? tfp_attendance_get_user_rate($student_id) : get_user_meta($student_id, 'tfp_attendance_percent', true);
        $homework = tfp_facilitator_cohorts_homework_percent($student_id, $lesson_id);
        $test = tfp_facilitator_cohorts_test_score($student_id, $lesson_id);

        $behind = ($attendance !== '' && $attendance !== null && is_numeric($attendance) && (float) $attendance < 80) ||
            ($homework !== null && $homework < 70) ||
            ($test !== null && $test < 70);

        if (!$behind && $lesson_id) {
            $quiz_done = function_exists('tfp_week_get_quiz_result') && tfp_week_get_quiz_result($student_id, $lesson_id) !== null;
            $test_done = $test !== null;
            $homework_done = tfp_facilitator_cohorts_student_completion($student_id, $lesson_id, 'homework');
            if (!$quiz_done && !$test_done && !$homework_done) {
                $behind = true;
            }
        }

        if ($behind)
            $count++;
    }

    return $count;
}

function tfp_facilitator_cohorts_section_groups($weeks)
{
    $weeks = array_values((array) $weeks);
    if (!$weeks)
        return [];

    $group_count = min(4, count($weeks));
    $groups = [];
    $chunk = (int) ceil(count($weeks) / $group_count);

    for ($i = 0; $i < $group_count; $i++) {
        $slice = array_slice($weeks, $i * $chunk, $chunk);
        if (!$slice)
            continue;

        $first_title = trim((string) $slice[0]->post_title);
        $section_name = preg_split('/\s+(?:–|—|-|:)\s+/', $first_title)[0];
        $section_name = trim((string) ($section_name ?: $first_title));

        $groups[] = [
            'number' => $i + 1,
            'name' => $section_name,
            'weeks' => $slice,
        ];
    }

    return $groups;
}

function tfp_facilitator_cohorts_section_progress($students, $group)
{
    $week_count = count($group['weeks']);
    $possible = max(1, count($students) * $week_count);
    $done = 0;
    $grades = [];

    foreach ((array) $students as $student_id) {
        foreach ($group['weeks'] as $week) {
            if (function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($student_id, $week->ID)) {
                $done++;
            }

            if (function_exists('tfp_grade_get_week')) {
                $letter = tfp_grade_get_week($student_id, $week->ID);
                $point = tfp_facilitator_cohorts_grade_points($letter);
                if ($point !== null)
                    $grades[] = $point;
            }
        }
    }

    $avg_grade = '';
    if ($grades && function_exists('tfp_grade_scale')) {
        $rounded = (int) round(array_sum($grades) / count($grades));
        $by_point = array_flip(tfp_grade_scale());
        $avg_grade = $by_point[$rounded] ?? '';
    }

    return [
        'percent' => (int) round(($done / $possible) * 100),
        'avg_grade' => $avg_grade,
    ];
}

function tfp_facilitator_cohorts_action_items($cohorts, $selected_cohort = null)
{
    $scope = $selected_cohort ? [$selected_cohort] : (array) $cohorts;
    $items = [];

    if ($scope) {
        $student_ids = [];
        $blocked = 0;
        $stalled = 0;
        $homework = 0;
        $falling = 0;
        $meeting_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-meetings');
        $homework_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-homework');
        $tests_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-tests');
        $grades_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-grades');

        foreach ($scope as $cohort) {
            $student_ids = array_merge($student_ids, tfp_facilitator_cohort_students($cohort->ID));
            $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
            $lesson = tfp_facilitator_cohorts_current_lesson($cohort->ID, $course_id);

            if (function_exists('tfp_facilitator_tests_summary')) {
                $summary = tfp_facilitator_tests_summary($cohort, 0);
                $blocked += (int) $summary['blocked'];
                $stalled += (int) $summary['stalled'];
            }

            $homework_items = function_exists('tfp_facilitator_homework_row_items')
                ? tfp_facilitator_homework_row_items(get_current_user_id(), 0, (int) $cohort->ID)
                : [];
            $homework += count(array_slice((array) $homework_items, 0, 200));
            $falling += $lesson ? tfp_facilitator_cohorts_falling_behind(tfp_facilitator_cohort_students($cohort->ID), $lesson->ID) : 0;
        }

        $items[] = [
            'title' => __('Join Cohort Meeting', 'tfp-dashboard'),
            'count' => '',
            'desc' => __('Open the facilitator meeting area for the selected cohort.', 'tfp-dashboard'),
            'button' => __('Join Meeting', 'tfp-dashboard'),
            'button_icon' => 'arrow',
            'url' => $meeting_url,
            'icon' => 'meeting',
            'tone' => 'teal',
        ];

        if ($blocked + $stalled > 0) {
            $items[] = [
                'title' => __('Manual Test Unlocks Required', 'tfp-dashboard'),
                'count' => $blocked + $stalled,
                'desc' => __('Students have incomplete or stalled test work requiring review.', 'tfp-dashboard'),
                'button' => __('Unlock Test', 'tfp-dashboard'),
                'button_icon' => 'unlock',
                'url' => $tests_url,
                'icon' => 'unlock',
                'tone' => 'warning',
            ];
        }

        if ($homework > 0) {
            $items[] = [
                'title' => __('Homework & Field Report Queue', 'tfp-dashboard'),
                'count' => $homework,
                'desc' => __('Submitted assignments are waiting for facilitator review.', 'tfp-dashboard'),
                'button' => __('Review All', 'tfp-dashboard'),
                'button_icon' => 'arrow',
                'url' => $homework_url,
                'icon' => 'homework',
                'tone' => 'teal',
            ];
        }

        if ($falling > 0) {
            $items[] = [
                'title' => __('T-24h Diagnostic Review Required', 'tfp-dashboard'),
                'count' => $falling,
                'desc' => __('Students currently falling behind should be reviewed before the next meeting.', 'tfp-dashboard'),
                'button' => __('View Gaps', 'tfp-dashboard'),
                'button_icon' => 'arrow',
                'url' => $grades_url,
                'icon' => 'diagnostic',
                'tone' => 'teal',
            ];
        }
    }

    return $items;
}

function tfp_dashboard_render_facilitator_cohorts_content()
{
    $all_cohorts = function_exists('tfp_facilitator_current_cohorts') ? (array) tfp_facilitator_current_cohorts() : [];
    $state = tfp_facilitator_cohorts_state();

    $selected = null;
    foreach ($all_cohorts as $cohort) {
        if ((int) $cohort->ID === $state['cohort_id']) {
            $selected = $cohort;
            break;
        }
    }

    if (!$selected) {
        tfp_facilitator_cohorts_render_all($all_cohorts);
        return;
    }

    tfp_facilitator_cohorts_render_selected($all_cohorts, $selected, $state);
}

function tfp_facilitator_cohorts_render_header($title, $subtitle, $crumbs, $back = false)
{
    ?>
    <header class="tfp-dash-pageheader tfp-cohort-pageheader">
        <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
            <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>
        <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
            <?php foreach ($crumbs as $index => $crumb): ?>
                <?php if ($index): ?><span aria-hidden="true">/</span><?php endif; ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?php echo esc_url($crumb['url']); ?>"><?php echo esc_html($crumb['label']); ?></a>
                <?php else: ?>
                    <strong><?php echo esc_html($crumb['label']); ?></strong>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="tfp-cohort-pageheader__main">
            <div>
                <h1 class="tfp-dash-pageheader__title"><?php echo esc_html($title); ?></h1>
                <p class="tfp-dash-pageheader__subtitle"><?php echo esc_html($subtitle); ?></p>
            </div>
            <?php if ($back): ?>
                <a class="tfp-cohort-back" href="<?php echo esc_url(tfp_facilitator_cohorts_url()); ?>"><span aria-hidden="true">↩</span><?php esc_html_e('Back to All Courses', 'tfp-dashboard'); ?></a>
            <?php endif; ?>
        </div>
    </header>
    <?php
}

function tfp_facilitator_cohorts_render_queue($items)
{
    ?>
    <section class="tfp-dash-panel tfp-cohort-queue">
        <div class="tfp-cohort-panel-head">
            <div>
                <h5><?php esc_html_e('Action Fulfillment Queue', 'tfp-dashboard'); ?></h5>
                <p><?php esc_html_e('Tasks that need your Attention, Actions and Submission.', 'tfp-dashboard'); ?></p>
            </div>
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>#tfp-facilitator-review-list"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
        </div>
        <div class="tfp-cohort-queue__list">
            <?php if ($items):
                foreach ($items as $item): ?>
                <article class="tfp-cohort-queue__item">
                    <span class="tfp-cohort-queue__icon tfp-cohort-queue__icon--<?php echo esc_attr($item['tone']); ?>" aria-hidden="true">
                        <?php
                        $queue_icon = [
                            'meeting' => 'action-meeting',
                            'unlock' => 'action-warning',
                            'diagnostic' => 'action-course',
                        ][$item['icon']] ?? 'action-homework';
                        echo tfp_dashboard_icon($queue_icon);
                        ?>
                    </span>
                    <div class="tfp-cohort-queue__copy">
                        <h3><?php echo esc_html($item['title']); ?><?php if ($item['count'] !== ''): ?> <em>· <?php echo esc_html($item['count']); ?> <?php echo $item['count'] === 1 ? esc_html__('Submission', 'tfp-dashboard') : esc_html__('Submissions', 'tfp-dashboard'); ?></em><?php endif; ?></h3>
                        <p><?php echo esc_html($item['desc']); ?></p>
                    </div>
                    <a class="tfp-dash-btn tfp-dash-btn--primary tfp-cohort-queue__button" href="<?php echo esc_url($item['url']); ?>">
                        <span><?php echo esc_html($item['button']); ?></span>
                        <span class="tfp-cohort-queue__button-icon" aria-hidden="true"><?php echo tfp_dashboard_icon($item['button_icon'] ?? 'arrow'); ?></span>
                    </a>
                </article>
            <?php endforeach;
            else: ?>
                <div class="tfp-facilitator-empty"><?php esc_html_e('No action items require attention right now.', 'tfp-dashboard'); ?></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function tfp_facilitator_cohorts_render_metrics($metrics)
{
    ?>
    <div class="tfp-facilitator-metrics tfp-cohort-metrics">
        <?php foreach ($metrics as $metric): ?>
            <article class="tfp-facilitator-metric <?php echo !empty($metric['class']) ? esc_attr($metric['class']) : ''; ?>">
                <span><?php echo esc_html($metric['label']); ?></span>
                <strong><?php echo esc_html($metric['value']); ?></strong>
                <small><?php echo esc_html($metric['sub']); ?></small>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

function tfp_facilitator_cohorts_render_all($cohorts)
{
    $student_ids = [];
    $grades = [];
    $course_ids = [];
    $completed = 0;
    $possible = 0;
    $reviews = 0;

    foreach ($cohorts as $cohort) {
        $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
        $course_ids[$course_id] = true;
        $students = tfp_facilitator_cohort_students($cohort->ID);
        $student_ids = array_merge($student_ids, $students);
        $lesson = tfp_facilitator_cohorts_current_lesson($cohort->ID, $course_id);

        foreach ($students as $student_id) {
            if ($lesson && function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($student_id, $lesson->ID))
                $completed++;
            if ($lesson)
                $possible++;
            if ($course_id && function_exists('tfp_grade_overall')) {
                $letter = tfp_grade_overall($student_id, $course_id);
                if ($letter)
                    $grades[] = $letter;
            }
        }

        $reviews += function_exists('tfp_facilitator_homework_row_items')
            ? count((array) tfp_facilitator_homework_row_items(get_current_user_id(), 0, (int) $cohort->ID))
            : 0;
        $reviews += function_exists('tfp_facilitator_test_items')
            ? count((array) tfp_facilitator_test_items([$cohort]))
            : 0;
    }

    $student_ids = array_values(array_unique(array_map('absint', $student_ids)));
    $weekly = $possible ? (int) round(($completed / $possible) * 100) : null;
    $attendance = tfp_facilitator_cohorts_attendance_avg($student_ids);
    $avg_grade = tfp_facilitator_cohorts_average_letter($grades);

    tfp_facilitator_cohorts_render_header(
        __('Cohort Dashboard', 'tfp-dashboard'),
        __('Manage your cohorts, track progress, and complete weekly facilitator tasks.', 'tfp-dashboard'),
        [
            ['label' => __('Facilitator', 'tfp-dashboard'), 'url' => tfp_dashboard_get_url('tfp-dashboard-facilitator-home')],
            ['label' => __('Cohorts', 'tfp-dashboard')],
        ]
    );
    ?>
    <div class="tfp-cohort-all-page">
        <?php
        tfp_facilitator_cohorts_render_metrics([
            ['label' => __('Weekly Progress', 'tfp-dashboard'), 'value' => $weekly !== null ? $weekly . '%' : '—', 'sub' => __('Completed This Week', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--teal'],
            ['label' => __('Attendance', 'tfp-dashboard'), 'value' => $attendance !== null ? $attendance . '%' : '—', 'sub' => __('Across Entire Course', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--teal'],
            ['label' => __('Overall Grade', 'tfp-dashboard'), 'value' => $avg_grade ?: '—', 'sub' => __('Current Course Average', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--gold'],
            ['label' => __('Awaiting Review', 'tfp-dashboard'), 'value' => (string) $reviews, 'sub' => __('Pending Facilitator Actions', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--red'],
        ]);
        tfp_facilitator_cohorts_render_queue(tfp_facilitator_cohorts_action_items($cohorts));
        ?>

        <section class="tfp-dash-panel tfp-cohort-table-card">
            <div class="tfp-cohort-table-card__head">
                <h2><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></h2>
            </div>
            <div class="tfp-dash-table-wrap">
                <table class="tfp-dash-table tfp-cohort-table">
                    <thead><tr>
                        <th><?php esc_html_e('Cohort Name', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Meeting Schedule', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Current Module', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Avg. Grade', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Alerts', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php
                    foreach ($cohorts as $cohort):
                        $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
                        $students = tfp_facilitator_cohort_students($cohort->ID);
                        $grades = [];
                        foreach ($students as $student_id) {
                            if (function_exists('tfp_grade_overall')) {
                                $g = tfp_grade_overall($student_id, $course_id);
                                if ($g)
                                    $grades[] = $g;
                            }
                        }
                        $lesson = tfp_facilitator_cohorts_current_lesson($cohort->ID, $course_id);
                        $alerts = ['blocked' => 0, 'stalled' => 0];
                        if (function_exists('tfp_facilitator_tests_summary')) {
                            $sum = tfp_facilitator_tests_summary($cohort, 0);
                            $alerts['blocked'] = $sum['blocked'];
                            $alerts['stalled'] = $sum['stalled'];
                        }
                        $view_url = tfp_facilitator_cohorts_url(['tfp_cohort_id' => $cohort->ID, 'tfp_cohort_tab' => 'overview']);
                        ?>
                        <tr>
                            <td><?php echo esc_html($cohort->post_title); ?></td>
                            <td><?php echo esc_html(get_post_meta($cohort->ID, '_schedule_text', true) ?: '—'); ?></td>
                            <td><?php echo esc_html($lesson ? $lesson->post_title : '—'); ?></td>
                            <td><span class="tfp-cohort-grade"><?php echo esc_html(tfp_facilitator_cohorts_average_letter($grades) ?: '—'); ?></span></td>
                            <td class="tfp-cohort-teal"><?php echo ($a = tfp_facilitator_cohorts_attendance_avg($students)) !== null ? esc_html($a . '%') : '—'; ?></td>
                            <td class="tfp-cohort-alert"><?php echo esc_html($alerts['stalled'] . ' Stalled'); ?><?php if ($alerts['blocked']): ?> • <strong><?php echo esc_html($alerts['blocked'] . ' Blocked'); ?></strong><?php endif; ?></td>
                            <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cohorts): ?><tr><td colspan="7"><div class="tfp-facilitator-empty"><?php esc_html_e('No active cohorts are assigned.', 'tfp-dashboard'); ?></div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php
}

function tfp_facilitator_cohorts_render_tabs($cohort_id, $active)
{
    $tabs = [
        'weekly' => __('Weekly View', 'tfp-dashboard'),
        'overview' => __('Course Overview', 'tfp-dashboard'),
        'roster' => __('Roster', 'tfp-dashboard'),
    ];
    ?>
    <nav class="tfp-cohort-tabs" aria-label="<?php esc_attr_e('Cohort views', 'tfp-dashboard'); ?>">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="<?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(tfp_facilitator_cohorts_url(['tfp_cohort_id' => $cohort_id, 'tfp_cohort_tab' => $key])); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function tfp_facilitator_cohorts_metrics_for_selected($cohort, $week_number)
{
    $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
    $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
    $week_number = $week_number > 0 ? min($week_number, max(1, count($weeks))) : tfp_facilitator_cohort_week($cohort->ID, $course_id);
    $lesson = $weeks[$week_number - 1] ?? null;
    $students = tfp_facilitator_cohort_students($cohort->ID);

    $homework = [];
    $tests = [];
    foreach ($students as $student_id) {
        $hw = $lesson ? tfp_facilitator_cohorts_homework_percent($student_id, $lesson->ID) : null;
        $test = $lesson ? tfp_facilitator_cohorts_test_score($student_id, $lesson->ID) : null;
        if ($hw !== null)
            $homework[] = $hw;
        if ($test !== null)
            $tests[] = $test;
    }

    return [
        'week_number' => $week_number,
        'lesson' => $lesson,
        'students' => $students,
        'weeks' => $weeks,
        'homework' => $homework ? (int) round(array_sum($homework) / count($homework)) : null,
        'test' => $tests ? (int) round(array_sum($tests) / count($tests)) : null,
        'attendance' => tfp_facilitator_cohorts_attendance_avg($students),
        'falling' => $lesson ? tfp_facilitator_cohorts_falling_behind($students, $lesson->ID) : 0,
    ];
}

function tfp_facilitator_cohorts_render_selected($all_cohorts, $cohort, $state)
{
    $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
    $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
    $schedule = get_post_meta($cohort->ID, '_schedule_text', true);
    $subtitle = trim($course_title . ($schedule ? ' • ' . $schedule : ''));

    tfp_facilitator_cohorts_render_header(
        $cohort->post_title,
        $subtitle,
        [
            ['label' => __('Facilitator', 'tfp-dashboard'), 'url' => tfp_dashboard_get_url('tfp-dashboard-facilitator-home')],
            ['label' => __('Cohort Overview', 'tfp-dashboard'), 'url' => tfp_facilitator_cohorts_url()],
            ['label' => $cohort->post_title],
        ],
        true
    );

    $metrics = tfp_facilitator_cohorts_metrics_for_selected($cohort, 0);
    tfp_facilitator_cohorts_render_tabs($cohort->ID, $state['tab']);

    tfp_facilitator_cohorts_render_metrics([
        ['label' => __('Homework Average', 'tfp-dashboard'), 'value' => $metrics['homework'] !== null ? $metrics['homework'] . '%' : '—', 'sub' => __('Across All Modules', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--teal'],
        ['label' => __('Test Average', 'tfp-dashboard'), 'value' => $metrics['test'] !== null ? $metrics['test'] . '%' : '—', 'sub' => __('Content Mastery', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--teal'],
        ['label' => __('Attendance Rate', 'tfp-dashboard'), 'value' => $metrics['attendance'] !== null ? $metrics['attendance'] . '%' : '—', 'sub' => __('Session Consistency', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--teal'],
        ['label' => __('Disciples Falling Behind', 'tfp-dashboard'), 'value' => (string) $metrics['falling'], 'sub' => __('Requires Intervention', 'tfp-dashboard'), 'class' => 'tfp-facilitator-metric--red'],
    ]);

    if ($state['tab'] === 'roster') {
        tfp_facilitator_cohorts_render_roster($cohort, $state);
    } elseif ($state['tab'] === 'weekly') {
        tfp_facilitator_cohorts_render_weekly($cohort, $state, $metrics);
    } else {
        tfp_facilitator_cohorts_render_course_overview($cohort, $state, $metrics);
    }
}

function tfp_facilitator_cohorts_render_course_overview($cohort, $state, $metrics)
{
    $weeks = $metrics['weeks'];
    $students = $metrics['students'];
    ?>
    <div class="tfp-cohort-two-col tfp-cohort-two-col--overview">
        <div>
            <?php tfp_facilitator_cohorts_render_queue(tfp_facilitator_cohorts_action_items([$cohort], $cohort)); ?>
        </div>
        <div>
            <section class="tfp-dash-panel tfp-cohort-section-mastery">
                <div class="tfp-cohort-panel-head">
                    <div>
                        <h5><?php esc_html_e('Section Mastery', 'tfp-dashboard'); ?></h5>
                        <p><?php esc_html_e('Global Disciple progress by section.', 'tfp-dashboard'); ?></p>
                    </div>
                </div>
                <div class="tfp-cohort-section-mastery__list">
                    <?php
                    foreach (tfp_facilitator_cohorts_section_groups($weeks) as $group):
                        $progress = tfp_facilitator_cohorts_section_progress($students, $group);
                        $status_class = $progress['percent'] >= 90 ? 'complete' : ($progress['percent'] > 0 ? 'progress' : 'not-started');
                        $status_label = $progress['percent'] >= 90 ? __('Completed', 'tfp-dashboard') : ($progress['percent'] > 0 ? __('In Progress', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard'));
                        ?>
                        <article class="tfp-cohort-section-item">
                            <div class="tfp-cohort-section-item__top">
                                <strong><?php printf(esc_html__('Section %d: %s', 'tfp-dashboard'), $group['number'], $group['name']); ?></strong>
                                <span class="tfp-cohort-status tfp-cohort-status--<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                            </div>
                            <div class="tfp-cohort-progress"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div>
                            <small>
                                <?php
                                echo $progress['avg_grade']
                                    ? esc_html__('Avg Grade: ', 'tfp-dashboard') . esc_html($progress['avg_grade'])
                                    : esc_html__('No graded data yet', 'tfp-dashboard');
                                ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
    <?php
    tfp_facilitator_cohorts_render_performance_table($cohort, $state, 'course');
}

function tfp_facilitator_cohorts_render_weekly($cohort, $state, $metrics)
{
    $students = $metrics['students'];
    $items = tfp_facilitator_cohorts_action_items([$cohort], $cohort);
    $lesson = $metrics['lesson'];
    $student_count = count($students);
    $meeting_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-meetings');
    ?>
    <div class="tfp-cohort-two-col tfp-cohort-two-col--weekly">
        <div>
            <?php tfp_facilitator_cohorts_render_queue($items); ?>
        </div>
        <div>
            <section class="tfp-dash-panel tfp-cohort-info-card">
                <h2><?php esc_html_e('Current Week', 'tfp-dashboard'); ?></h2>
                <p><?php echo esc_html('Week ' . $metrics['week_number'] . ': ' . ($lesson ? $lesson->post_title : '')); ?></p>
                <?php
                foreach (['reading', 'homework', 'quiz', 'test'] as $step):
                    $done = 0;
                    foreach ($students as $student_id) {
                        if ($lesson && tfp_facilitator_cohorts_student_completion($student_id, $lesson->ID, $step)) {
                            $done++;
                        }
                    }
                    $percent = $student_count ? (int) round(($done / $student_count) * 100) : 0;
                    $label = $step === 'quiz' ? __("Quiz's", 'tfp-dashboard') : ucfirst($step);
                    ?>
                    <div class="tfp-cohort-step">
                        <div>
                            <strong><?php echo esc_html($label); ?></strong>
                            <small><?php echo esc_html($done . '/' . $student_count . ' Students'); ?></small>
                        </div>
                        <span class="tfp-cohort-step__bar"><i style="width:<?php echo esc_attr($percent); ?>%"></i></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="tfp-dash-panel tfp-cohort-info-card tfp-cohort-meeting-card">
                <h2><?php esc_html_e('Weekly Meeting', 'tfp-dashboard'); ?></h2>
                <p><?php echo esc_html(get_post_meta($cohort->ID, '_schedule_text', true) ?: __('Meeting schedule not configured.', 'tfp-dashboard')); ?></p>
                <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($meeting_url); ?>"><?php esc_html_e('Go to Meeting', 'tfp-dashboard'); ?></a>
            </section>
        </div>
    </div>
    <?php
    tfp_facilitator_cohorts_render_performance_table($cohort, $state, 'weekly');
}

function tfp_facilitator_cohorts_render_performance_table($cohort, $state, $mode)
{
    $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
    $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
    $report_week = min($state['report_week'], count($weeks));
    $students = tfp_facilitator_cohort_students($cohort->ID);
    $rows = [];

    foreach ($students as $student_id) {
        $student = get_userdata($student_id);
        if (!$student)
            continue;

        $report = $report_week > 0
            ? tfp_facilitator_cohorts_week_report($student_id, $weeks[$report_week - 1]->ID)
            : tfp_facilitator_cohorts_all_weeks_report($student_id, $course_id, $weeks);

        $current_lesson = tfp_facilitator_cohorts_current_lesson($cohort->ID, $course_id);
        $rows[] = [
            'id' => $student_id,
            'name' => $student->display_name ?: $student->user_email,
            'module' => $current_lesson ? $current_lesson->post_title : '—',
            'homework' => $report['homework'],
            'test' => $report['test'],
            'attendance' => $report['attendance'],
            'grade' => $report['grade'],
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $page = max(1, $state['page']);
    $per_page = 20;
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $pages);
    $paged = array_slice($rows, ($page - 1) * $per_page, $per_page);
    ?>
    <section class="tfp-dash-panel tfp-cohort-table-card tfp-cohort-performance">
        <div class="tfp-cohort-table-card__head">
            <h2><?php echo esc_html($mode === 'weekly' ? __('Cohort Performance Reports', 'tfp-dashboard') : __('Cohort Performance Reports', 'tfp-dashboard')); ?></h2>
            <form method="get" class="tfp-cohort-week-filter">
                <input type="hidden" name="tfp_cohort_id" value="<?php echo esc_attr($cohort->ID); ?>">
                <input type="hidden" name="tfp_cohort_tab" value="<?php echo esc_attr($state['tab']); ?>">
                <select name="tfp_cohort_report_week" onchange="this.form.submit()">
                    <option value="0" <?php selected($report_week, 0); ?>><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
                    <?php foreach ($weeks as $index => $week): ?><option value="<?php echo esc_attr($index + 1); ?>" <?php selected($report_week, $index + 1); ?>><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $index + 1); ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="tfp-dash-table-wrap">
            <table class="tfp-dash-table tfp-cohort-table tfp-cohort-performance-table">
                <thead><tr>
                    <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Current Module', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Homework Avg.', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Test Avg.', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Overall Grade Avg.', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                </tr></thead>
                <tbody>
                <?php
                foreach ($paged as $row):
                    $view = add_query_arg('tfp_student_id', $row['id'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><?php echo esc_html($row['module']); ?></td>
                        <td><span class="tfp-cohort-value"><?php echo $row['homework'] !== null ? esc_html($row['homework'] . '%') : '—'; ?></span></td>
                        <td><span class="tfp-cohort-value"><?php echo $row['test'] !== null ? esc_html($row['test'] . '%') : '—'; ?></span></td>
                        <td class="tfp-cohort-teal"><?php echo is_numeric($row['attendance']) ? esc_html(round((float) $row['attendance']) . '%') : '—'; ?></td>
                        <td><span class="tfp-cohort-grade"><?php echo esc_html($row['grade'] ?: '—'); ?></span></td>
                        <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($view); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$paged): ?><tr><td colspan="7"><div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are available for this cohort.', 'tfp-dashboard'); ?></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php tfp_facilitator_cohorts_render_pagination($page, $pages, $total, $state); ?>
    </section>
    <?php
}

function tfp_facilitator_cohorts_render_roster($cohort, $state)
{
    $course_id = tfp_facilitator_cohort_course_id($cohort->ID);
    $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
    $report_week = min($state['report_week'], count($weeks));
    $lesson = $report_week > 0 ? ($weeks[$report_week - 1] ?? null) : tfp_facilitator_cohorts_current_lesson($cohort->ID, $course_id);
    $rows = [];

    foreach (tfp_facilitator_cohort_students($cohort->ID) as $student_id) {
        $student = get_userdata($student_id);
        if (!$student)
            continue;

        $id = $student->ID;
        $homework = $quiz = $test = ['label' => __('Not Started', 'tfp-dashboard'), 'tone' => 'neutral'];
        if ($lesson) {
            $homework = tfp_facilitator_roster_work_status($id, $lesson->ID, 'homework');
            $quiz = tfp_facilitator_roster_work_status($id, $lesson->ID, 'quiz');
            $test = tfp_facilitator_roster_work_status($id, $lesson->ID, 'test');
        }

        $grade = function_exists('tfp_grade_get_week') && $lesson ? tfp_grade_get_week($id, $lesson->ID) : '';
        $rows[] = ['id' => $id, 'name' => $student->display_name ?: $student->user_email, 'module' => $lesson ? $lesson->post_title : '—', 'homework' => $homework, 'quiz' => $quiz, 'test' => $test, 'grade' => $grade];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $page = max(1, $state['page']);
    $per_page = 20;
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $pages);
    $paged = array_slice($rows, ($page - 1) * $per_page, $per_page);
    ?>
    <section class="tfp-dash-panel tfp-cohort-table-card tfp-cohort-performance">
        <div class="tfp-cohort-table-card__head"><h2><?php esc_html_e('Class Roster & Current Performance Reports', 'tfp-dashboard'); ?></h2>
            <form method="get" class="tfp-cohort-week-filter">
                <input type="hidden" name="tfp_cohort_id" value="<?php echo esc_attr($cohort->ID); ?>">
                <input type="hidden" name="tfp_cohort_tab" value="roster">
                <select name="tfp_cohort_report_week" onchange="this.form.submit()">
                    <option value="0" <?php selected($report_week, 0); ?>><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
                    <?php foreach ($weeks as $index => $week): ?><option value="<?php echo esc_attr($index + 1); ?>" <?php selected($report_week, $index + 1); ?>><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $index + 1); ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="tfp-dash-table-wrap">
            <table class="tfp-dash-table tfp-roster-table tfp-cohort-table">
                <thead><tr>
                    <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Current Module', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Homework', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Quiz', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Test', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Overall Grade', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                </tr></thead>
                <tbody><?php foreach ($paged as $row): ?><tr>
                    <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                    <td><?php echo esc_html($row['module']); ?></td>
                    <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['homework']['tone']); ?>"><?php echo esc_html($row['homework']['label']); ?></span></td>
                    <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['quiz']['tone']); ?>"><?php echo esc_html($row['quiz']['label']); ?></span></td>
                    <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['test']['tone']); ?>"><?php echo esc_html($row['test']['label']); ?></span></td>
                    <td><span class="tfp-cohort-grade"><?php echo esc_html($row['grade'] ?: '—'); ?></span></td>
                    <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url(add_query_arg('tfp_student_id', $row['id'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'))); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                </tr><?php endforeach; ?>
                <?php if (!$paged): ?><tr><td colspan="7"><div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are enrolled in this cohort.', 'tfp-dashboard'); ?></div></td></tr><?php endif; ?></tbody>
            </table>
        </div>
        <?php tfp_facilitator_cohorts_render_pagination($page, $pages, $total, $state); ?>
    </section>
    <?php
}

function tfp_facilitator_cohorts_render_pagination($page, $pages, $total, $state)
{
    $from = $total ? (($page - 1) * 20) + 1 : 0;
    $to = min($page * 20, $total);
    ?>
    <div class="tfp-cohort-pagination">
        <span><?php echo esc_html($from . ' - ' . $to . ' of ' . $total . ' items'); ?></span>
        <div>
            <?php if ($page > 1): ?><a href="<?php echo esc_url(tfp_facilitator_cohorts_url(['tfp_cohort_id' => $state['cohort_id'], 'tfp_cohort_tab' => $state['tab'], 'tfp_cohort_report_week' => $state['report_week'], 'tfp_cohort_page' => $page - 1])); ?>"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a><?php else: ?><a class="is-disabled" href="#" aria-disabled="true"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="tfp-cohort-pagination__next" href="<?php echo esc_url(tfp_facilitator_cohorts_url(['tfp_cohort_id' => $state['cohort_id'], 'tfp_cohort_tab' => $state['tab'], 'tfp_cohort_report_week' => $state['report_week'], 'tfp_cohort_page' => $page + 1])); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> <span aria-hidden="true">›</span></a><?php endif; ?>
        </div>
    </div>
    <?php
}
