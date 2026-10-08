<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator Roster data + renderer.
 *
 * The roster is built on the custom TFP cohort CPT. Cohort assignment is the
 * source of truth for which students a facilitator can see.
 */

function tfp_facilitator_roster_attendance_percent($user_id)
{
    if (function_exists('tfp_attendance_get_user_rate')) {
        $rate = tfp_attendance_get_user_rate((int) $user_id);
        return $rate === null || $rate === '' ? '' : (float) $rate;
    }

    $stored = get_user_meta((int) $user_id, 'tfp_attendance_percent', true);
    if ($stored !== '' && is_numeric($stored)) {
        return (float) $stored;
    }

    return '';
}

function tfp_facilitator_roster_status($label, $tone = 'neutral')
{
    return [
        'label' => $label,
        'tone'  => $tone,
    ];
}

function tfp_facilitator_roster_work_status($user_id, $lesson_id, $type)
{
    if (!$lesson_id) {
        return tfp_facilitator_roster_status(__('Not Started', 'tfp-dashboard'), 'neutral');
    }

    if ($type === 'homework' && function_exists('tfp_week_homework_progress')) {
        $progress = tfp_week_homework_progress($user_id, $lesson_id);
        if ((int) $progress['total'] <= 0 || (int) $progress['completed'] <= 0) {
            return tfp_facilitator_roster_status(__('Not Started', 'tfp-dashboard'), 'neutral');
        }
        if ((int) $progress['completed'] >= (int) $progress['total']) {
            return tfp_facilitator_roster_status(__('Complete', 'tfp-dashboard'), 'success');
        }
        return tfp_facilitator_roster_status(__('In Progress', 'tfp-dashboard'), 'warning');
    }

    if ($type === 'quiz' && function_exists('tfp_week_get_quiz_result')) {
        $result = tfp_week_get_quiz_result($user_id, $lesson_id);
        if (is_array($result) && isset($result['passed'])) {
            return tfp_facilitator_roster_status(
                $result['passed'] ? __('Passed', 'tfp-dashboard') : __('Needs Review', 'tfp-dashboard'),
                $result['passed'] ? 'success' : 'danger'
            );
        }
        if (function_exists('tfp_week_quiz_progress')) {
            $progress = tfp_week_quiz_progress($user_id, $lesson_id);
            if ((int) $progress['completed'] > 0) {
                return tfp_facilitator_roster_status(__('In Progress', 'tfp-dashboard'), 'warning');
            }
        }
        return tfp_facilitator_roster_status(__('Not Started', 'tfp-dashboard'), 'neutral');
    }

    if ($type === 'test' && function_exists('tfp_week_get_test_result')) {
        $result = tfp_week_get_test_result($user_id, $lesson_id);
        if (is_array($result) && isset($result['passed'])) {
            return tfp_facilitator_roster_status(
                $result['passed'] ? __('Passed', 'tfp-dashboard') : __('Needs Review', 'tfp-dashboard'),
                $result['passed'] ? 'success' : 'danger'
            );
        }
        if (function_exists('tfp_week_test_progress')) {
            $progress = tfp_week_test_progress($user_id, $lesson_id);
            if ((int) $progress['completed'] > 0) {
                return tfp_facilitator_roster_status(__('In Progress', 'tfp-dashboard'), 'warning');
            }
        }
    }

    return tfp_facilitator_roster_status(__('Not Started', 'tfp-dashboard'), 'neutral');
}

function tfp_facilitator_roster_current_lesson($user_id, $course_id)
{
    if (!$course_id || !function_exists('tfp_ld_get_weeks')) {
        return null;
    }

    if (function_exists('tfp_ld_get_current_week')) {
        $current = tfp_ld_get_current_week($user_id, $course_id);
        if ($current) {
            return $current;
        }
    }

    $weeks = tfp_ld_get_weeks($course_id);
    return !empty($weeks) ? end($weeks) : null;
}

function tfp_facilitator_roster_student_rows($cohorts)
{
    $rows = [];
    $student_ids = [];

    foreach ($cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);

        foreach (tfp_facilitator_cohort_students($cohort_id) as $student_id) {
            $student_ids[(int) $student_id] = [
                'cohort_id' => $cohort_id,
                'course_id' => $course_id,
            ];
        }
    }

    foreach ($student_ids as $student_id => $assignment) {
        $student = get_userdata($student_id);
        if (!$student) {
            continue;
        }

        $course_id = (int) $assignment['course_id'];
        $lesson = tfp_facilitator_roster_current_lesson($student_id, $course_id);
        $overall = function_exists('tfp_grade_overall') ? tfp_grade_overall($student_id, $course_id) : '';
        $attendance = tfp_facilitator_roster_attendance_percent($student_id);

        $rows[] = [
            'student_id' => $student_id,
            'name'       => $student->display_name ?: $student->user_email,
            'email'      => $student->user_email,
            'course_id'  => $course_id,
            'cohort_id'  => (int) $assignment['cohort_id'],
            'module'     => $lesson ? get_the_title($lesson->ID) : '—',
            'lesson_id'  => $lesson ? (int) $lesson->ID : 0,
            'homework'   => tfp_facilitator_roster_work_status($student_id, $lesson ? (int) $lesson->ID : 0, 'homework'),
            'quiz'       => tfp_facilitator_roster_work_status($student_id, $lesson ? (int) $lesson->ID : 0, 'quiz'),
            'test'       => tfp_facilitator_roster_work_status($student_id, $lesson ? (int) $lesson->ID : 0, 'test'),
            'grade'      => $overall,
            'attendance' => $attendance,
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $rows;
}

function tfp_facilitator_roster_filtered_rows($rows)
{
    $course = isset($_GET['tfp_roster_course']) ? absint($_GET['tfp_roster_course']) : 0;
    $cohort = isset($_GET['tfp_roster_cohort']) ? absint($_GET['tfp_roster_cohort']) : 0;
    $search = isset($_GET['tfp_roster_search']) ? sanitize_text_field(wp_unslash($_GET['tfp_roster_search'])) : '';

    if (!$course && !$cohort && !$search) {
        return $rows;
    }

    return array_values(array_filter($rows, static function ($row) use ($course, $cohort, $search) {
        if ($course && (int) $row['course_id'] !== $course) {
            return false;
        }
        if ($cohort && (int) $row['cohort_id'] !== $cohort) {
            return false;
        }
        if ($search) {
            $needle = strtolower($search);
            $haystack = strtolower($row['name'] . ' ' . $row['email']);
            if (strpos($haystack, $needle) === false) {
                return false;
            }
        }
        return true;
    }));
}

function tfp_facilitator_roster_grade_average($rows)
{
    if (!function_exists('tfp_grade_scale')) {
        return '';
    }

    $scale = tfp_grade_scale();
    $points = [];

    foreach ($rows as $row) {
        $grade = $row['grade'];
        if (isset($scale[$grade])) {
            $points[] = $scale[$grade];
        }
    }

    if (!$points) {
        return '';
    }

    $rounded = (int) round(array_sum($points) / count($points));
    $by_point = array_flip($scale);
    return $by_point[$rounded] ?? '';
}

function tfp_dashboard_render_facilitator_roster_content()
{
    $all_cohorts = tfp_facilitator_current_cohorts();
    $all_rows = tfp_facilitator_roster_student_rows($all_cohorts);
    $rows = tfp_facilitator_roster_filtered_rows($all_rows);

    $course_options = [];
    $cohort_options = [];
    foreach ($all_cohorts as $cohort) {
        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
        if ($course_id) {
            $course_options[$course_id] = get_the_title($course_id);
        }
        $cohort_options[$cohort->ID] = get_the_title($cohort->ID);
    }

    $selected_course = isset($_GET['tfp_roster_course']) ? absint($_GET['tfp_roster_course']) : 0;
    $selected_cohort = isset($_GET['tfp_roster_cohort']) ? absint($_GET['tfp_roster_cohort']) : 0;
    $search_term = isset($_GET['tfp_roster_search']) ? sanitize_text_field(wp_unslash($_GET['tfp_roster_search'])) : '';
    $page = max(1, isset($_GET['tfp_roster_page']) ? absint($_GET['tfp_roster_page']) : 1);
    $per_page = 20;
    $total = count($rows);
    $pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
    // Never render an empty out-of-range page.
    $page = min($page, $pages);
    $paged_rows = array_slice($rows, ($page - 1) * $per_page, $per_page);

    if ($selected_course) {
        foreach ($all_cohorts as $cohort) {
            if ((int) get_post_meta($cohort->ID, '_related_course', true) !== $selected_course) {
                unset($cohort_options[$cohort->ID]);
            }
        }
    }

    $attendance_values = array_filter(array_map(static function ($row) {
        return $row['attendance'] === '' ? null : (float) $row['attendance'];
    }, $all_rows), static function ($value) {
        return $value !== null;
    });
    $attendance_average = $attendance_values ? round(array_sum($attendance_values) / count($attendance_values)) : '';
    $avg_grade = tfp_facilitator_roster_grade_average($all_rows);
    $tests_to_review = function_exists('tfp_facilitator_test_items') ? count(tfp_facilitator_test_items($all_cohorts)) : 0;
    $clear_url = remove_query_arg(['tfp_roster_course', 'tfp_roster_cohort', 'tfp_roster_search', 'tfp_roster_page']);
    ?>
    <div class="tfp-dash-pageheader tfp-facilitator-header">
        <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <strong><?php esc_html_e('Roster', 'tfp-dashboard'); ?></strong>
        </div>
        <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Roster', 'tfp-dashboard'); ?></h1>
        <p class="tfp-dash-pageheader__subtitle"><?php esc_html_e('Overview of student performance and attendance', 'tfp-dashboard'); ?></p>
    </div>

    <form class="tfp-facilitator-filters tfp-facilitator-roster-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>">
        <select name="tfp_roster_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
            <?php foreach ($course_options as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_course, $id); ?>><?php echo esc_html($title); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tfp_roster_cohort" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
            <?php foreach ($cohort_options as $id => $title): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_cohort, $id); ?>><?php echo esc_html($title); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="tfp-dash-search tfp-facilitator-search">
            <span class="tfp-dash-search__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.21799 15.9079C1 15.4801 1 14.9203 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1.40973 2.0918 1.21799C2.51962 1 3.08009 1 4.2002 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.2837 1.40973 16.5905 1.71547 16.7822 2.0918C17 2.5192 17 3.07899 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </span>
            <input type="search" name="tfp_roster_search" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search students', 'tfp-dashboard'); ?>">
        </div>
        <?php if ($selected_course || $selected_cohort || $search_term): ?>
            <a class="tfp-facilitator-clear-filter" href="<?php echo esc_url($clear_url); ?>"><?php esc_html_e('Clear Filters', 'tfp-dashboard'); ?></a>
        <?php endif; ?>
    </form>

    <div class="tfp-facilitator-metrics tfp-roster-metrics">
        <article class="tfp-facilitator-metric">
            <span><?php esc_html_e('Total Students', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html(count($all_rows)); ?></strong>
            <small><?php esc_html_e('Current Courses Facilitating', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--teal">
            <span><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></span>
            <strong><?php echo $attendance_average === '' ? '—' : esc_html($attendance_average . '%'); ?></strong>
            <small><?php esc_html_e('Across All Cohorts', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--gold">
            <span><?php esc_html_e('Average Grade', 'tfp-dashboard'); ?></span>
            <strong><?php echo $avg_grade ? esc_html($avg_grade) : '—'; ?></strong>
            <small><?php esc_html_e('Submitted Assignments', 'tfp-dashboard'); ?></small>
        </article>
        <article class="tfp-facilitator-metric tfp-facilitator-metric--red">
            <span><?php esc_html_e('Tests to Review', 'tfp-dashboard'); ?></span>
            <strong><?php echo esc_html($tests_to_review); ?></strong>
            <small><?php esc_html_e('Awaiting Review', 'tfp-dashboard'); ?></small>
        </article>
    </div>

    <section class="tfp-dash-panel tfp-facilitator-panel tfp-roster-panel">
        <div class="tfp-facilitator-panel__head tfp-roster-panel__head">
            <div>
                <h6><?php esc_html_e('Class Roster & Current Performance Reports', 'tfp-dashboard'); ?></h6>
            </div>
        </div>

        <div class="tfp-roster-table-wrap">
            <table class="tfp-roster-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Current Module', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Homework', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Quiz', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Test', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Overall Grade', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($paged_rows): ?>
                    <?php foreach ($paged_rows as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($row['name']); ?></strong>
                                <small><?php echo esc_html($row['email']); ?></small>
                            </td>
                            <td><?php echo esc_html($row['module']); ?></td>
                            <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['homework']['tone']); ?>"><?php echo esc_html($row['homework']['label']); ?></span></td>
                            <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['quiz']['tone']); ?>"><?php echo esc_html($row['quiz']['label']); ?></span></td>
                            <td><span class="tfp-roster-status tfp-roster-status--<?php echo esc_attr($row['test']['tone']); ?>"><?php echo esc_html($row['test']['label']); ?></span></td>
                            <td><span class="tfp-roster-grade"><?php echo $row['grade'] ? esc_html($row['grade']) : '—'; ?></span></td>
                            <td><a class="tfp-roster-view-btn" href="<?php echo esc_url(add_query_arg('tfp_student_id', $row['student_id'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'))); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7"><div class="tfp-facilitator-empty"><?php esc_html_e('No students match the selected filters.', 'tfp-dashboard'); ?></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > $per_page): ?>
            <div class="tfp-roster-pagination">
                <span><?php printf(esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), (($page - 1) * $per_page) + 1, min($page * $per_page, $total), $total); ?></span>
                <div>
                    <?php
                    $pagination_base = remove_query_arg('tfp_roster_page', get_permalink());
                    $pagination_args = array_filter([
                        'tfp_roster_course' => $selected_course,
                        'tfp_roster_cohort' => $selected_cohort,
                        'tfp_roster_search' => $search_term,
                    ]);

                    $prev_url = '';
                    $next_url = '';

                    if ($page > 1) {
                        $prev_url = add_query_arg(
                            array_merge($pagination_args, ['tfp_roster_page' => $page - 1]),
                            $pagination_base
                        );
                    }

                    if ($page < $pages) {
                        $next_url = add_query_arg(
                            array_merge($pagination_args, ['tfp_roster_page' => $page + 1]),
                            $pagination_base
                        );
                    }
                    ?>
                    <?php if ($prev_url): ?>
                        <a href="<?php echo esc_url($prev_url); ?>"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                    <?php else: ?>
                        <a class="is-disabled" href="#" aria-disabled="true"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                    <?php endif; ?>

                    <?php if ($next_url): ?>
                        <a href="<?php echo esc_url($next_url); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> <span aria-hidden="true">›</span></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

<?php
}
