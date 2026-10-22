<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Facilitator Quiz & Test Review.
 *
 * Uses the existing cohort, LearnDash week, quiz and test helpers so this
 * screen stays fully data-driven and consistent with the other facilitator
 * dashboards.
 */

function tfp_facilitator_tests_state()
{
    return [
        'cohort' => isset($_GET['tfp_qt_cohort']) ? absint($_GET['tfp_qt_cohort']) : 0,
        'week' => isset($_GET['tfp_qt_week']) ? absint($_GET['tfp_qt_week']) : 0,
        'search' => isset($_GET['tfp_qt_search'])
            ? sanitize_text_field(wp_unslash($_GET['tfp_qt_search']))
            : '',
    ];
}

function tfp_facilitator_tests_cohort_options($cohorts)
{
    $options = [];

    foreach ((array) $cohorts as $cohort) {
        $options[(int) $cohort->ID] = $cohort->post_title;
    }

    uasort($options, static function ($a, $b) {
        return strcasecmp($a, $b);
    });

    return $options;
}

function tfp_facilitator_tests_visible_cohorts($cohorts, $state)
{
    if (!$state['cohort'] && $state['search'] === '') {
        return (array) $cohorts;
    }

    $visible = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;

        if ($state['cohort'] && $cohort_id !== (int) $state['cohort']) {
            continue;
        }

        if ($state['search'] !== '') {
            $needle = strtolower($state['search']);
            $course_id = function_exists('tfp_facilitator_cohort_course_id')
                ? (int) tfp_facilitator_cohort_course_id($cohort_id)
                : (int) get_post_meta($cohort_id, '_related_course', true);

            $haystack = strtolower(
                $cohort->post_title . ' ' .
                ($course_id ? get_the_title($course_id) : '')
            );

            $student_match = false;

            if (function_exists('tfp_facilitator_cohort_students')) {
                foreach (tfp_facilitator_cohort_students($cohort_id) as $student_id) {
                    $student = get_userdata($student_id);

                    if ($student && strpos(
                        strtolower($student->display_name . ' ' . $student->user_email),
                        $needle
                    ) !== false) {
                        $student_match = true;
                        break;
                    }
                }
            }

            if (strpos($haystack, $needle) === false && !$student_match) {
                continue;
            }
        }

        $visible[] = $cohort;
    }

    return $visible;
}

function tfp_facilitator_tests_weeks($course_id)
{
    if (!$course_id || !function_exists('tfp_ld_get_weeks')) {
        return [];
    }

    return array_values((array) tfp_ld_get_weeks($course_id));
}

function tfp_facilitator_tests_current_week($cohort_id, $course_id, $weeks)
{
    $current = function_exists('tfp_facilitator_cohort_week')
        ? (int) tfp_facilitator_cohort_week($cohort_id, $course_id)
        : 1;

    return $weeks ? min(max(1, $current), count($weeks)) : $current;
}

function tfp_facilitator_tests_score($function, $student_id, $lesson_id)
{
    if (!$lesson_id || !function_exists($function)) {
        return null;
    }

    $result = $function($student_id, $lesson_id);

    if (!is_array($result) || !isset($result['score']) || !is_numeric($result['score'])) {
        return null;
    }

    return (float) $result['score'];
}

function tfp_facilitator_tests_question_label($result_item)
{
    foreach (['topic', 'category', 'struggle', 'label', 'title'] as $key) {
        if (!empty($result_item[$key]) && is_scalar($result_item[$key])) {
            return wp_strip_all_tags((string) $result_item[$key]);
        }
    }

    if (!empty($result_item['prompt']) && is_scalar($result_item['prompt'])) {
        $prompt = wp_strip_all_tags((string) $result_item['prompt']);
        return wp_trim_words($prompt, 5, '…');
    }

    return '';
}

function tfp_facilitator_tests_question_stats($student_ids, $lesson_id)
{
    $questions = [];

    if (!$lesson_id) {
        return $questions;
    }

    foreach (array_unique(array_map('absint', (array) $student_ids)) as $student_id) {
        foreach (['tfp_week_get_quiz_result', 'tfp_week_get_test_result'] as $function) {
            if (!function_exists($function)) {
                continue;
            }

            $result = $function($student_id, $lesson_id);

            if (!is_array($result) || empty($result['results']) || !is_array($result['results'])) {
                continue;
            }

            foreach ($result['results'] as $item) {
                $label = tfp_facilitator_tests_question_label($item);

                if ($label === '') {
                    continue;
                }

                if (!isset($questions[$label])) {
                    $questions[$label] = [
                        'answered' => 0,
                        'correct' => 0,
                    ];
                }

                $questions[$label]['answered']++;

                if (!empty($item['is_correct'])) {
                    $questions[$label]['correct']++;
                }
            }
        }
    }

    return $questions;
}

function tfp_facilitator_tests_gap_summary($student_ids, $lesson_id)
{
    $stats = tfp_facilitator_tests_question_stats($student_ids, $lesson_id);

    if (!$stats) {
        return [
            'critical_gap' => '',
            'critical_gap_rate' => null,
            'highest_mastery' => '',
            'highest_mastery_rate' => null,
        ];
    }

    $lowest = null;
    $highest = null;

    foreach ($stats as $label => $item) {
        if ((int) $item['answered'] < 1) {
            continue;
        }

        $rate = ($item['correct'] / $item['answered']) * 100;

        if ($lowest === null || $rate < $lowest['rate']) {
            $lowest = [
                'label' => $label,
                'rate' => $rate,
            ];
        }

        if ($highest === null || $rate > $highest['rate']) {
            $highest = [
                'label' => $label,
                'rate' => $rate,
            ];
        }
    }

    return [
        'critical_gap' => $lowest['label'] ?? '',
        'critical_gap_rate' => isset($lowest['rate']) ? (int) round(100 - $lowest['rate']) : null,
        'highest_mastery' => $highest['label'] ?? '',
        'highest_mastery_rate' => isset($highest['rate']) ? (int) round($highest['rate']) : null,
    ];
}

function tfp_facilitator_tests_summary($cohort, $week_number = 0)
{
    $cohort_id = (int) $cohort->ID;
    $course_id = function_exists('tfp_facilitator_cohort_course_id')
        ? (int) tfp_facilitator_cohort_course_id($cohort_id)
        : (int) get_post_meta($cohort_id, '_related_course', true);

    $students = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_unique(array_map('absint', tfp_facilitator_cohort_students($cohort_id))))
        : [];

    $weeks = tfp_facilitator_tests_weeks($course_id);
    $current_week = tfp_facilitator_tests_current_week($cohort_id, $course_id, $weeks);
    $selected_week = $week_number > 0 ? min($week_number, max(1, count($weeks))) : $current_week;
    $lesson = isset($weeks[$selected_week - 1]) ? $weeks[$selected_week - 1] : null;
    $lesson_id = $lesson ? (int) $lesson->ID : 0;

    if ($week_number > 0) {
        $target_lessons = $lesson_id ? [$lesson_id] : [];
    } else {
        $target_lessons = array_map(
            static function ($week) {
                return (int) $week->ID;
            },
            $weeks
        );
    }

    $quiz_scores = [];
    $test_scores = [];
    $test_submitted = 0;
    $active_signal = 0;

    foreach ($students as $student_id) {
        if ($lesson_id) {
            $quiz = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $lesson_id);
            $test = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $lesson_id);

            if ($quiz !== null) {
                $quiz_scores[] = $quiz;
                $active_signal++;
            }

            if ($test !== null) {
                $test_scores[] = $test;
                $test_submitted++;
                $active_signal++;
            }
        }

        if ($week_number === 0 && !$lesson_id) {
            $active_signal += 0;
        }
    }

    if ($week_number === 0) {
        $quiz_scores = [];
        $test_scores = [];

        foreach ($students as $student_id) {
            foreach ($target_lessons as $target_lesson_id) {
                $quiz = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $target_lesson_id);
                $test = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $target_lesson_id);

                if ($quiz !== null) {
                    $quiz_scores[] = $quiz;
                }

                if ($test !== null) {
                    $test_scores[] = $test;
                }
            }
        }
    }

    $quiz_avg = $quiz_scores ? (int) round(array_sum($quiz_scores) / count($quiz_scores)) : null;
    $test_avg = $test_scores ? (int) round(array_sum($test_scores) / count($test_scores)) : null;

    $completion_pct = null;
    $pending_tests = count($students);

    if ($lesson_id && $students) {
        $completion = 0;

        foreach ($students as $student_id) {
            $quiz_done = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $lesson_id) !== null;
            $test_done = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $lesson_id) !== null;

            if ($quiz_done || $test_done) {
                $completion++;
            }
        }

        $completion_pct = (int) round(($completion / count($students)) * 100);
        $pending_tests = max(0, count($students) - $test_submitted);
    } elseif (!$lesson_id && $students) {
        $completion_pct = null;
        $pending_tests = count($students);
    }

    $blocked = 0;
    $stalled = 0;

    if ($lesson_id) {
        foreach ($students as $student_id) {
            $quiz_done = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $lesson_id) !== null;
            $test_done = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $lesson_id) !== null;

            if (!$quiz_done && !$test_done) {
                $blocked++;
            } elseif (!$test_done) {
                $stalled++;
            }
        }
    }

    $gap = tfp_facilitator_tests_gap_summary($students, $lesson_id);

    return [
        'course_id' => $course_id,
        'students' => $students,
        'weeks' => $weeks,
        'current_week' => $current_week,
        'selected_week' => $selected_week,
        'lesson' => $lesson,
        'lesson_id' => $lesson_id,
        'quiz_avg' => $quiz_avg,
        'test_avg' => $test_avg,
        'completion_pct' => $completion_pct,
        'pending_tests' => $pending_tests,
        'stalled' => $stalled,
        'blocked' => $blocked,
        'total_alerts' => $stalled + $blocked,
        'gap' => $gap,
    ];
}

function tfp_facilitator_tests_overview_metrics($cohorts)
{
    $active_cohorts = count($cohorts);
    $student_ids = [];
    $quiz_scores = [];
    $test_scores = [];
    $pending = 0;
    $submitted = 0;
    $eligible_students = 0;
    $gap_counter = [];
    $mastery_counter = [];

    foreach ((array) $cohorts as $cohort) {
        $summary = tfp_facilitator_tests_summary($cohort, 0);

        $student_ids = array_merge($student_ids, $summary['students']);
        $eligible_students += count($summary['students']);

        if ($summary['quiz_avg'] !== null) {
            $quiz_scores[] = $summary['quiz_avg'];
        }

        if ($summary['test_avg'] !== null) {
            $test_scores[] = $summary['test_avg'];
        }

        $pending += (int) $summary['pending_tests'];

        if ($summary['lesson_id'] && $summary['students']) {
            foreach ($summary['students'] as $student_id) {
                $quiz_done = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $summary['lesson_id']) !== null;
                $test_done = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $summary['lesson_id']) !== null;

                if ($quiz_done || $test_done) {
                    $submitted++;
                }
            }
        }

        if ($summary['gap']['critical_gap'] !== '') {
            $gap_counter[$summary['gap']['critical_gap']] = ($gap_counter[$summary['gap']['critical_gap']] ?? 0) + 1;
        }

        if ($summary['gap']['highest_mastery'] !== '') {
            $mastery_counter[$summary['gap']['highest_mastery']] = ($mastery_counter[$summary['gap']['highest_mastery']] ?? 0) + 1;
        }
    }

    $student_ids = array_values(array_unique(array_map('absint', $student_ids)));

    $highest_quiz = $quiz_scores ? (int) round(array_sum($quiz_scores) / count($quiz_scores)) : null;
    $highest_test = $test_scores ? (int) round(array_sum($test_scores) / count($test_scores)) : null;

    arsort($gap_counter);
    arsort($mastery_counter);

    $critical_gap = $gap_counter ? (string) array_key_first($gap_counter) : '';
    $highest_mastery = $mastery_counter ? (string) array_key_first($mastery_counter) : '';

    return [
        'active_cohorts' => $active_cohorts,
        'students' => count($student_ids),
        'quiz_avg' => $highest_quiz,
        'test_avg' => $highest_test,
        'pending' => $pending,
        'completion_pct' => $eligible_students > 0 ? (int) round(($submitted / $eligible_students) * 100) : null,
        'critical_gap' => $critical_gap,
        'highest_mastery' => $highest_mastery,
    ];
}

function tfp_facilitator_tests_student_rows($cohort, $week_number)
{
    $summary = tfp_facilitator_tests_summary($cohort, $week_number);
    $rows = [];

    foreach ($summary['students'] as $student_id) {
        $student = get_userdata($student_id);

        if (!$student || !$summary['lesson_id']) {
            continue;
        }

        $quiz_score = tfp_facilitator_tests_score('tfp_week_get_quiz_result', $student_id, $summary['lesson_id']);
        $test_score = tfp_facilitator_tests_score('tfp_week_get_test_result', $student_id, $summary['lesson_id']);

        $quiz_result = function_exists('tfp_week_get_quiz_result')
            ? tfp_week_get_quiz_result($student_id, $summary['lesson_id'])
            : null;

        $test_result = function_exists('tfp_week_get_test_result')
            ? tfp_week_get_test_result($student_id, $summary['lesson_id'])
            : null;

        $wrong_labels = [];

        foreach ([$quiz_result, $test_result] as $result) {
            if (!is_array($result) || empty($result['results']) || !is_array($result['results'])) {
                continue;
            }

            foreach ($result['results'] as $item) {
                if (isset($item['is_correct']) && !$item['is_correct']) {
                    $label = tfp_facilitator_tests_question_label($item);
                    if ($label !== '') {
                        $wrong_labels[] = $label;
                    }
                }
            }
        }

        $top_struggle = '';
        if ($wrong_labels) {
            $counts = array_count_values($wrong_labels);
            arsort($counts);
            $top_struggle = (string) array_key_first($counts);
        }

        $rows[] = [
            'id' => $student_id,
            'name' => $student->display_name ?: $student->user_email,
            'quiz' => $quiz_score !== null ? tfp_facilitator_tests_letter($quiz_score) : '',
            'test' => $test_score !== null ? tfp_facilitator_tests_letter($test_score) : '',
            'top_struggle' => $top_struggle,
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return [$summary, $rows];
}

function tfp_facilitator_tests_letter($score)
{
    if ($score === null || $score === '') {
        return '';
    }

    $score = (float) $score;

    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';

    return 'F';
}

function tfp_facilitator_tests_grade_badge($letter)
{
    $letter = strtoupper((string) $letter);

    if ($letter === '') {
        return '<span class="tfp-qt-grade">—</span>';
    }

    $class = in_array($letter, ['A', 'B', 'C', 'D', 'F'], true)
        ? strtolower($letter)
        : 'empty';

    return '<span class="tfp-qt-grade tfp-qt-grade--' . esc_attr($class) . '">' . esc_html($letter) . '</span>';
}

function tfp_facilitator_tests_render_progress($current, $total)
{
    $total = max(1, (int) $total);
    $current = min(max(1, (int) $current), $total);

    ob_start();
    ?>
    <div class="tfp-qt-week-progress">
        <div class="tfp-qt-week-progress__label">
            <span>Week</span>
            <strong><?php echo esc_html($current); ?></strong>
            <span>/</span>
            <span><?php echo esc_html($total); ?></span>
        </div>
        <div class="tfp-qt-week-progress__track" aria-hidden="true">
            <?php for ($i = 1; $i <= $total; $i++): ?>
                <span class="<?php echo $i === $current ? 'is-current' : ($i < $current ? 'is-complete' : ''); ?>"></span>
            <?php endfor; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function tfp_facilitator_tests_render_back_url()
{
    return tfp_dashboard_get_url('tfp-dashboard-facilitator-tests');
}

function tfp_dashboard_render_facilitator_tests_content()
{
    $all_cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? (array) tfp_facilitator_current_cohorts()
        : [];

    $state = tfp_facilitator_tests_state();
    $options = tfp_facilitator_tests_cohort_options($all_cohorts);
    $visible = tfp_facilitator_tests_visible_cohorts($all_cohorts, $state);

    $selected_cohort = null;
    foreach ($all_cohorts as $cohort) {
        if ((int) $cohort->ID === (int) $state['cohort']) {
            $selected_cohort = $cohort;
            break;
        }
    }

    if ($selected_cohort instanceof WP_Post) {
        $summary = tfp_facilitator_tests_summary($selected_cohort, $state['week']);
        $course_title = $summary['course_id'] ? get_the_title($summary['course_id']) : __('Course', 'tfp-dashboard');

        if ($state['week'] > count($summary['weeks'])) {
            $state['week'] = 0;
            $summary = tfp_facilitator_tests_summary($selected_cohort, 0);
        }

        [$summary, $rows] = tfp_facilitator_tests_student_rows($selected_cohort, $state['week']);

        ?>
        <div class="tfp-facilitator-tests-page">
            <header class="tfp-dash-pageheader tfp-facilitator-tests-header">
                <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
                    <?php echo tfp_dashboard_icon('toggle'); ?>
                </button>
                <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
                    <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
                    <span aria-hidden="true">/</span>
                    <a href="<?php echo esc_url(tfp_facilitator_tests_render_back_url()); ?>"><?php esc_html_e('Quiz & Test Review', 'tfp-dashboard'); ?></a>
                    <span aria-hidden="true">/</span>
                    <strong><?php echo esc_html($selected_cohort->post_title); ?></strong>
                    <?php if ($state['week']): ?>
                        <span aria-hidden="true">/</span>
                        <strong><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $state['week']); ?></strong>
                    <?php endif; ?>
                </div>
                <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Quiz & Test Review', 'tfp-dashboard'); ?></h1>
                <p class="tfp-dash-pageheader__subtitle"><?php echo esc_html($course_title . ' • Cohort ' . $selected_cohort->post_title); ?></p>
                <a class="tfp-qt-back-button" href="<?php echo esc_url(tfp_facilitator_tests_render_back_url()); ?>">
                    <span aria-hidden="true">↩</span>
                    <?php esc_html_e('Back to List', 'tfp-dashboard'); ?>
                </a>
            </header>

            <form class="tfp-facilitator-filters tfp-qt-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>">
                <select name="tfp_qt_cohort" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>">
                    <?php foreach ($options as $id => $title): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_cohort->ID, $id); ?>><?php echo esc_html($title); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tfp_qt_week" aria-label="<?php esc_attr_e('Week', 'tfp-dashboard'); ?>">
                    <?php foreach ($summary['weeks'] as $index => $week): ?>
                        <option value="<?php echo esc_attr($index + 1); ?>" <?php selected($summary['selected_week'], $index + 1); ?>><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $index + 1); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <section class="tfp-qt-week-card">
                <div class="tfp-qt-week-card__title"><?php echo esc_html($summary['lesson'] ? 'Week ' . $summary['selected_week'] . ': ' . $summary['lesson']->post_title : 'Week ' . $summary['selected_week']); ?></div>
                <?php echo tfp_facilitator_tests_render_progress($summary['selected_week'], count($summary['weeks'])); ?>
            </section>

            <section class="tfp-facilitator-metrics tfp-qt-metrics">
                <article class="tfp-facilitator-metric">
                    <span><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></span>
                    <strong><?php echo esc_html(count($all_cohorts)); ?></strong>
                    <small><?php echo esc_html(count($summary['students']) . ' Total Students'); ?></small>
                </article>
                <article class="tfp-facilitator-metric">
                    <span><?php esc_html_e('Critical Gap (Active)', 'tfp-dashboard'); ?></span>
                    <strong><?php echo esc_html($summary['gap']['critical_gap'] ?: '—'); ?></strong>
                    <small><?php echo $summary['gap']['critical_gap_rate'] !== null ? esc_html($summary['gap']['critical_gap_rate'] . '% Error Rate (Week ' . $summary['selected_week'] . ')') : esc_html__('No graded data yet', 'tfp-dashboard'); ?></small>
                </article>
                <article class="tfp-facilitator-metric">
                    <span><?php esc_html_e('Highest Mastery', 'tfp-dashboard'); ?></span>
                    <strong><?php echo esc_html($summary['gap']['highest_mastery'] ?: '—'); ?></strong>
                    <small><?php echo $summary['gap']['highest_mastery_rate'] !== null ? esc_html($summary['gap']['highest_mastery_rate'] . '% Clarity (Week ' . $summary['selected_week'] . ')') : esc_html__('No graded data yet', 'tfp-dashboard'); ?></small>
                </article>
                <article class="tfp-facilitator-metric">
                    <span><?php esc_html_e('Submission Status', 'tfp-dashboard'); ?></span>
                    <strong><?php echo $summary['completion_pct'] !== null ? esc_html($summary['completion_pct'] . '%') : '—'; ?></strong>
                    <small><?php echo esc_html($summary['pending_tests'] . ' Required Tests Pending'); ?></small>
                </article>
            </section>

            <section class="tfp-dash-panel tfp-qt-table-card">
                <div class="tfp-qt-card-head">
                    <h2><?php printf(esc_html__('Cohort Performance: Week %d', 'tfp-dashboard'), $summary['selected_week']); ?></h2>
                </div>

                <div class="tfp-dash-table-wrap">
                    <table class="tfp-dash-table tfp-qt-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Disciple Name', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Quiz Avg.', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Test Avg.', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Top Struggle', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $student_url = add_query_arg([
                                    'tfp_student_id' => $row['id'],
                                    'tfp_student_course' => $summary['course_id'],
                                    'tfp_student_cohort' => $selected_cohort->ID,
                                    'tfp_student_week' => $summary['selected_week'],
                                ], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                                ?>
                                <tr>
                                    <td><?php echo esc_html($row['name']); ?></td>
                                    <td><?php echo tfp_facilitator_tests_grade_badge($row['quiz']); ?></td>
                                    <td><?php echo tfp_facilitator_tests_grade_badge($row['test']); ?></td>
                                    <td class="<?php echo $row['top_struggle'] !== '' ? 'tfp-qt-struggle' : ''; ?>"><?php echo esc_html($row['top_struggle'] ?: '—'); ?></td>
                                    <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($student_url); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <?php
        return;
    }

    $metrics = tfp_facilitator_tests_overview_metrics($visible);
    ?>
    <div class="tfp-facilitator-tests-page">
        <header class="tfp-dash-pageheader tfp-facilitator-tests-header">
            <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
                <?php echo tfp_dashboard_icon('toggle'); ?>
            </button>
            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <strong><?php esc_html_e('Quiz & Test Review', 'tfp-dashboard'); ?></strong>
            </div>
            <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Quiz & Test Review', 'tfp-dashboard'); ?></h1>
            <p class="tfp-dash-pageheader__subtitle"><?php esc_html_e('Review class performance across all quiz and test questions for all active Cohorts.', 'tfp-dashboard'); ?></p>
        </header>

        <form class="tfp-facilitator-filters tfp-qt-filters tfp-qt-filters--overview" method="get" action="<?php echo esc_url(get_permalink()); ?>">
            <select name="tfp_qt_cohort" aria-label="<?php esc_attr_e('All Cohorts', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
                <option value="0" <?php selected($state['cohort'], 0); ?>><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                <?php foreach ($options as $id => $title): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($state['cohort'], $id); ?>><?php echo esc_html($title); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="tfp-dash-search tfp-facilitator-search">
                <span class="tfp-dash-search__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M16 16L13 13M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5 14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <input type="search" name="tfp_qt_search" value="<?php echo esc_attr($state['search']); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search quiz and test review', 'tfp-dashboard'); ?>">
            </div>
        </form>

        <section class="tfp-facilitator-metrics tfp-qt-metrics">
            <article class="tfp-facilitator-metric">
                <span><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html($metrics['active_cohorts']); ?></strong>
                <small><?php echo esc_html($metrics['students'] . ' Total Students'); ?></small>
            </article>
            <article class="tfp-facilitator-metric">
                <span><?php esc_html_e('Critical Gap (Active)', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html($metrics['critical_gap'] ?: '—'); ?></strong>
                <small><?php esc_html_e('Current active assessment gap', 'tfp-dashboard'); ?></small>
            </article>
            <article class="tfp-facilitator-metric">
                <span><?php esc_html_e('Highest Mastery', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html($metrics['highest_mastery'] ?: '—'); ?></strong>
                <small><?php esc_html_e('Best-performing assessment topic', 'tfp-dashboard'); ?></small>
            </article>
            <article class="tfp-facilitator-metric">
                <span><?php esc_html_e('Submission Status', 'tfp-dashboard'); ?></span>
                <strong><?php echo ($metrics['test_avg'] !== null || $metrics['quiz_avg'] !== null) ? esc_html((int) round(array_sum(array_filter([$metrics['quiz_avg'], $metrics['test_avg'], $metrics['quiz_avg']])) / count(array_filter([$metrics['quiz_avg'], $metrics['test_avg'], $metrics['quiz_avg']])))) . '%' : '—'; ?></strong>
                <small><?php echo esc_html($metrics['pending'] . ' Required Tests Pending'); ?></small>
            </article>
        </section>

        <section class="tfp-dash-panel tfp-qt-table-card">
            <div class="tfp-qt-card-head">
                <h2><?php esc_html_e('Cohort Performance Index', 'tfp-dashboard'); ?></h2>
            </div>
            <div class="tfp-dash-table-wrap">
                <table class="tfp-dash-table tfp-qt-table tfp-qt-table--overview">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Cohort Name', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Active Week', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Quiz Avg.', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Test Avg.', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Completion', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Alerts', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visible as $cohort): ?>
                            <?php
                            $summary = tfp_facilitator_tests_summary($cohort, 0);
                            $detail_url = add_query_arg([
                                'tfp_qt_cohort' => $cohort->ID,
                                'tfp_qt_week' => $summary['current_week'],
                            ], tfp_facilitator_tests_render_back_url());
                            ?>
                            <tr>
                                <td><?php echo esc_html($cohort->post_title); ?></td>
                                <td><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $summary['current_week']); ?></td>
                                <td><?php echo tfp_facilitator_tests_grade_badge(tfp_facilitator_tests_letter($summary['quiz_avg'])); ?></td>
                                <td><?php echo tfp_facilitator_tests_grade_badge(tfp_facilitator_tests_letter($summary['test_avg'])); ?></td>
                                <td class="<?php echo $summary['completion_pct'] !== null && $summary['completion_pct'] < 80 ? 'tfp-qt-completion tfp-qt-completion--warn' : 'tfp-qt-completion'; ?>"><?php echo $summary['completion_pct'] !== null ? esc_html($summary['completion_pct'] . '%') : '—'; ?></td>
                                <td class="tfp-qt-alerts">
                                    <?php echo $summary['total_alerts'] ? esc_html($summary['total_alerts'] . ' Blocked') : '—'; ?>
                                </td>
                                <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($detail_url); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php
}
