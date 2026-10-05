<?php
if (!defined('ABSPATH'))
    exit;


/**
 * Programs navigation page — course/program chooser.
 *
 * This page intentionally uses the existing dashboard/program-card styles from
 * course-checkout.css plus the shared dashboard buttons/tokens. It is separate
 * from the enrolled Home overview dashboard.
 */
function tfp_dashboard_render_program_content()
{
    $user_id = get_current_user_id();
    $program_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-program')
        : '#';

    tfp_dashboard_render_page_header(
        __('Home', 'tfp-dashboard'),
        sprintf(esc_html__('Welcome, %s', 'tfp-dashboard'), esc_html(tfp_dashboard_user_name())),
        __('Welcome back. Select a course to continue where you left off or review what you\'ve completed.', 'tfp-dashboard')
    );

    $course_ids = [];

    if (function_exists('learndash_user_get_enrolled_courses')) {
        $course_ids = array_map('absint', (array) learndash_user_get_enrolled_courses($user_id));
    }

    // Keep the current program available as a fallback for installations where
    // LearnDash does not expose the enrolled-course helper.
    $state = function_exists('tfp_dashboard_get_program_state') ? tfp_dashboard_get_program_state() : null;
    if (empty($course_ids) && $state && !empty($state['course_id'])) {
        $course_ids[] = (int) $state['course_id'];
    }

    $course_ids = array_values(array_unique(array_filter($course_ids)));

    // Clicking Start/Continue/Review on a program card opens that course's
    // existing overall program dashboard rather than looping back to the list.
    $requested_course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    if ($requested_course_id && in_array($requested_course_id, $course_ids, true)) {
        tfp_dashboard_render_overall_dashboard_content();
        return;
    }

    if (empty($course_ids)) {
        ?>
        <div class="tfp-dash-panel tfp-program-empty">
            <h3><?php esc_html_e('No programs available yet', 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('Your available courses will appear here once they are assigned to your account.', 'tfp-dashboard'); ?></p>
        </div>
        <?php
        return;
    }

    ?>
    <div class="tfp-program-list">
        <?php foreach ($course_ids as $course_id): ?>
            <?php
            $course = get_post($course_id);
            if (!$course || $course->post_type !== 'sfwd-courses') {
                continue;
            }

            $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
            $progress = function_exists('tfp_ld_get_journey_progress')
                ? tfp_ld_get_journey_progress($user_id, $course_id)
                : ['completed' => 0, 'total' => count($weeks)];

            $total = max(0, (int) ($progress['total'] ?? count($weeks)));
            $completed = max(0, (int) ($progress['completed'] ?? 0));
            $percent = $total > 0 ? min(100, (int) round(($completed / $total) * 100)) : 0;

            $is_complete = function_exists('learndash_course_completed')
                ? (bool) learndash_course_completed($user_id, $course_id)
                : ($total > 0 && $completed >= $total);

            if ($is_complete) {
                $status = 'completed';
                $button_label = __('Review Program', 'tfp-dashboard');
            } elseif ($percent > 0) {
                $status = 'in-progress';
                $button_label = __('Continue Program', 'tfp-dashboard');
            } else {
                $status = 'not-started';
                $button_label = __('Start Program', 'tfp-dashboard');
            }

            $image_html = get_the_post_thumbnail($course_id, 'medium');
            if (!$image_html && function_exists('wc_placeholder_img')) {
                $image_html = wc_placeholder_img('medium');
            }

            $course_name = get_the_title($course_id);
            $description = get_the_excerpt($course_id);
            if ($description === '') {
                $description = __('Continue your course, complete each week, and keep your progress moving forward.', 'tfp-dashboard');
            }

            $meta_bits = [];
            if ($total > 0) {
                $meta_bits[] = sprintf(
                    _n('%d Week', '%d Weeks', $total, 'tfp-dashboard'),
                    $total
                );
            }

            // Reuse the current cohort schedule when this is the active program.
            $meta = $state && !empty($state['course_id']) && (int) $state['course_id'] === $course_id
                ? $state
                : null;

            if ($meta && !empty($meta['cohort']['schedule'])) {
                $meta_bits[] = sprintf(__('Meets %s', 'tfp-dashboard'), $meta['cohort']['schedule']);
            }
            if ($meta && !empty($meta['cohort']['facilitator'])) {
                $meta_bits[] = sprintf(__('Taught by %s', 'tfp-dashboard'), $meta['cohort']['facilitator']);
            }

            if (empty($meta_bits)) {
                $meta_bits[] = __('Course-paced program', 'tfp-dashboard');
            }

            $details = get_permalink($course_id);
            $continue_url = add_query_arg(['course_id' => $course_id], $program_url);
            ?>
            <article class="tfp-program-card tfp-program-card--<?php echo esc_attr($status); ?>">
                <div class="tfp-program-card__visual">
                    <?php echo $image_html; ?>
                </div>

                <div class="tfp-program-card__content">
                    <div class="tfp-program-card__topline">
                        <p class="tfp-program-card__meta"><?php echo esc_html(implode(' • ', $meta_bits)); ?></p>
                    </div>

                    <h3 class="tfp-program-card__title"><?php echo esc_html($course_name); ?></h3>
                    <p class="tfp-program-card__desc"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($description), 34)); ?></p>

                    <div class="tfp-program-card__actions">
                        <a href="<?php echo esc_url($continue_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                            <?php echo esc_html($button_label); ?>
                        </a>
                    </div>
                </div>

                <div class="tfp-program-card__progress" aria-label="<?php echo esc_attr(sprintf(__('%d%% complete', 'tfp-dashboard'), $percent)); ?>">
                    <?php echo tfp_dashboard_render_progress_ring($percent, 76, 7); ?>
                    <span class="tfp-program-card__progress-label"><?php esc_html_e('complete', 'tfp-dashboard'); ?></span>
                    <span class="tfp-program-card__badge tfp-program-card__badge--<?php echo esc_attr($status === 'in-progress' ? 'enrolled' : ($status === 'completed' ? 'invited' : 'unpaid')); ?>">
                        <?php echo esc_html($status === 'in-progress' ? __('In Progress', 'tfp-dashboard') : ($status === 'completed' ? __('Completed', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard'))); ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}


/**
 * Enrolled Home overview dashboard.
 *
 * Home owns the page header and this renderer supplies the overall progress
 * dashboard: 4 stat tiles, the discipleship journey, Current Lesson, PAST DUE
 * and Pending Tasks. The journey's "Continue Lesson" still routes into the
 * lesson player (tfp-dashboard-week).
 */
function tfp_dashboard_render_overall_dashboard_content()
{
    $user_id = get_current_user_id();
    $state = function_exists('tfp_dashboard_get_program_state') ? tfp_dashboard_get_program_state() : null;

    $requested_course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    $enrolled_course_ids = function_exists('learndash_user_get_enrolled_courses')
        ? array_map('absint', (array) learndash_user_get_enrolled_courses($user_id))
        : [];

    $course_id = ($requested_course_id && in_array($requested_course_id, $enrolled_course_ids, true))
        ? $requested_course_id
        : (($state && !empty($state['course_id']))
            ? (int) $state['course_id']
            : (function_exists('tfp_ld_get_program_course_id') ? (int) tfp_ld_get_program_course_id() : 0));

    $is_enrolled = $requested_course_id && in_array($requested_course_id, $enrolled_course_ids, true)
        ? true
        : ($state
            ? ($state['status'] === 'enrolled')
            : (function_exists('tfp_billing_user_has_paid') && tfp_billing_user_has_paid()));

    $has_weeks = $course_id && function_exists('tfp_ld_get_weeks') && !empty(tfp_ld_get_weeks($course_id));

    // Enrolled-only page. Anyone else (or a program with no weekly content yet)
    // gets a friendly notice back to Home instead of an empty dashboard.
    if (!$is_enrolled || !$has_weeks) {
        $home_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-home') : home_url('/');
        ?>
        <div class="tfp-dash-panel tfp-program-empty">
            <h3><?php esc_html_e('Your program isn\'t ready yet', 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('Once you\'re enrolled and your class content is available, your weekly journey and progress will appear here.', 'tfp-dashboard'); ?>
            </p>
            <a href="<?php echo esc_url($home_url); ?>"
                class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Back to Home', 'tfp-dashboard'); ?></a>
        </div>
        <?php
        return;
    }

    $overdue = function_exists('tfp_program_overdue_weeks') ? tfp_program_overdue_weeks($user_id, $course_id, $state) : [];
    $tasks = function_exists('tfp_program_get_pending_tasks') ? tfp_program_get_pending_tasks($user_id, $course_id, $state) : [];
    $week_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-week') : '#';

    // Stat tiles span the full width above the two-column body.
    tfp_dashboard_render_program_stats($user_id, $course_id, $state);
    ?>
    <div>
        <?php
        tfp_dashboard_render_program_journey($course_id, $state);
        ?>
    </div>


    <div class="tfp-program-grid">
        <div class="tfp-program-grid__main">
            <?php

            tfp_dashboard_render_program_current_lesson($user_id, $course_id, $week_url);

            // PAST DUE — the single most-overdue week (earliest due date).
            if (!empty($overdue)) {
                tfp_dashboard_render_program_pastdue($overdue[0], $week_url);
            }
            ?>
        </div>
        <aside class="tfp-program-grid__aside">
            <?php tfp_dashboard_render_program_tasks($tasks, $week_url); ?>
        </aside>
    </div>
    <?php
}

/**
 * The four headline stat tiles: Lessons Completed, Current Week, Overall Grade,
 * Next Meeting.
 */
function tfp_dashboard_render_program_stats($user_id, $course_id, $state)
{
    $progress = tfp_ld_get_journey_progress($user_id, $course_id);
    $total = (int) $progress['total'];

    $start_ts = function_exists('tfp_program_start_ts') ? tfp_program_start_ts($state) : 0;

    // Current week: calendar-based when the cohort has a start date, otherwise
    // the completion-based current week (first not-yet-complete week).
    $current_week_num = function_exists('tfp_program_calendar_week') ? tfp_program_calendar_week($start_ts, $total) : 0;
    if ($current_week_num <= 0) {
        $current_week_num = 0;
        $cw = tfp_ld_get_current_week($user_id, $course_id);
        if ($cw) {
            foreach (tfp_ld_get_weeks($course_id) as $idx => $w) {
                if ($w->ID === $cw->ID) {
                    $current_week_num = $idx + 1;
                    break;
                }
            }
        }
        if ($current_week_num <= 0) {
            $current_week_num = $total > 0 ? $total : 1; // all complete → last week
        }
    }

    $current_week = function_exists('tfp_ld_get_current_week') ? tfp_ld_get_current_week($user_id, $course_id) : null;
    $current_week_sub = $total ? sprintf(__('of %d', 'tfp-dashboard'), $total) : '';

    if ($current_week) {
        $current_week_title = $current_week->post_title;
        $current_week_section = '';

        if (function_exists('learndash_30_get_course_sections')) {
            $course_sections = learndash_30_get_course_sections($course_id);
            if (!empty($course_sections[$current_week->ID])) {
                $section_obj = $course_sections[$current_week->ID];
                $current_week_section = !empty($section_obj->post_title) ? $section_obj->post_title : '';
            }
        }

        $current_week_sub_parts = array_filter([
            $current_week_section,
            $current_week_title,
        ]);

        if (!empty($current_week_sub_parts)) {
            $current_week_sub = implode(' — ', $current_week_sub_parts);
        }
    }

    $overall = function_exists('tfp_grade_overall') ? tfp_grade_overall($user_id, $course_id) : '';
    $meeting = tfp_dashboard_program_next_meeting($user_id, $course_id, $state);

    $tiles = [
        [
            'icon' => 'check-circle',
            'label' => __('Lessons Completed', 'tfp-dashboard'),
            'value' => number_format_i18n((int) $progress['completed']),
            'sub' => sprintf(__('of %d total lessons', 'tfp-dashboard'), $total),
        ],
        [
            'icon' => 'calendar',
            'label' => __('Current Week', 'tfp-dashboard'),
            'value' => sprintf(__('Week %d', 'tfp-dashboard'), $current_week_num),
            'sub' => $current_week_sub,
        ],
        [
            'icon' => 'grades',
            'label' => __('Overall Grade', 'tfp-dashboard'),
            'value' => $overall !== '' ? $overall : '—',
            'sub' => $overall !== '' ? __('Current Course Average', 'tfp-dashboard') : __('Not graded yet', 'tfp-dashboard'),
            'url' => function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-grades') : '#',
        ],
        [
            'icon' => 'clock',
            'label' => __('Next Meeting', 'tfp-dashboard'),
            'value' => $meeting['value'],
            'sub' => $meeting['sub'],
        ],
    ];
    ?>
    <div class="tfp-stat-tiles">
        <?php foreach ($tiles as $t): ?>
            <?php if (!empty($t['url']) && $t['url'] !== '#'): ?>
                <a href="<?php echo esc_url($t['url']); ?>" class="tfp-stat-tile tfp-stat-tile--link"
                    style="text-decoration: none; color: inherit;">
                    <span class="tfp-stat-tile__label"><?php echo esc_html($t['label']); ?></span>
                    <span class="tfp-stat-tile__value"><?php echo esc_html($t['value']); ?></span>
                    <?php if (!empty($t['sub'])): ?>
                        <span class="tfp-stat-tile__sub"><?php echo esc_html($t['sub']); ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <div class="tfp-stat-tile">
                    <span class="tfp-stat-tile__label"><?php echo esc_html($t['label']); ?></span>
                    <span class="tfp-stat-tile__value"><?php echo esc_html($t['value']); ?></span>
                    <?php if (!empty($t['sub'])): ?>
                        <span class="tfp-stat-tile__sub"><?php echo esc_html($t['sub']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * The "Next Meeting" tile value/sub: the current week's meeting date + time if
 * set, otherwise the cohort's recurring schedule text. Sub = cohort name.
 */
function tfp_dashboard_program_next_meeting($user_id, $course_id, $state)
{
    $value = '';
    $sub = '';

    $cw = tfp_ld_get_current_week($user_id, $course_id);

    if ($cw) {

        $date = get_post_meta($cw->ID, 'tfp_week_meeting_date', true);
        $time = get_post_meta($cw->ID, 'tfp_week_meeting_time', true);

        // Format date like: Thu, Jun 12
        if ($date) {
            $date_timestamp = strtotime($date);

            if ($date_timestamp) {
                $value = date_i18n('D, M j', $date_timestamp);
            } else {
                $value = $date;
            }
        }

        $cohort_name = !empty($state['cohort']['name']) ? $state['cohort']['name'] : '';
        if ($time && $cohort_name) {
            $sub = $time . ' · ' . $cohort_name;
        } elseif ($time) {
            $sub = $time;
        } elseif ($cohort_name) {
            $sub = $cohort_name;
        }
    }

    // If there is no specific meeting date/time
    if ($value === '' && !empty($state['cohort']['schedule'])) {
        $value = $state['cohort']['schedule'];
        if (empty($sub) && !empty($state['cohort']['name'])) {
            $sub = $state['cohort']['name'];
        }
    }

    if ($value === '') {
        $value = __('TBD', 'tfp-dashboard');
    }

    return [
        'value' => $value,
        'sub' => $sub,
    ];
}

/**
 * The 58-week journey tracker. Moved here from Home (was
 * tfp_dashboard_render_class_journey_home) and split from the Current Lesson
 * card. The cohort meta line is now read from the real enrolled cohort rather
 * than being hardcoded.
 */
function tfp_dashboard_render_program_journey($course_id, $state)
{
    $user_id = get_current_user_id();
    $weeks = tfp_ld_get_weeks($course_id);
    $current_week = tfp_ld_get_current_week($user_id, $course_id);
    $progress = tfp_ld_get_journey_progress($user_id, $course_id);

    // Group lessons into sections (unchanged from the former Home journey).
    $course_sections = [];
    if (function_exists('learndash_30_get_course_sections')) {
        $course_sections = learndash_30_get_course_sections($course_id);
    }

    $sections_data = [];
    $current_section_idx = -1;

    foreach ($weeks as $week) {
        if (isset($course_sections[$week->ID]) || $current_section_idx === -1) {
            $current_section_idx++;
            $sections_data[$current_section_idx] = [
                'title' => isset($course_sections[$week->ID]->post_title) ? $course_sections[$week->ID]->post_title : 'Section ' . ($current_section_idx + 1),
                'lessons' => [],
            ];
        }
        $sections_data[$current_section_idx]['lessons'][] = $week;
    }

    foreach ($sections_data as $index => &$section) {
        $all_complete = true;
        $is_current = false;

        foreach ($section['lessons'] as $week) {
            if (!tfp_ld_is_week_complete($user_id, $week->ID)) {
                $all_complete = false;
            }
            if ($current_week && $current_week->ID === $week->ID) {
                $is_current = true;
            }
        }

        if ($all_complete) {
            $section['status'] = 'complete';
        } elseif ($is_current) {
            $section['status'] = 'current';
        } else {
            $section['status'] = 'upcoming';
        }
    }
    unset($section);

    $percent = $progress['total'] > 0 ? round(($progress['completed'] / $progress['total']) * 100) : 0;

    $current_week_number = 1;
    foreach ($weeks as $idx => $week) {
        if ($current_week && $week->ID === $current_week->ID) {
            $current_week_number = $idx + 1;
            break;
        }
    }

    // Real cohort meta line (was hardcoded "Spring 2026 Cohort · Facilitator …").
    $meta_bits = [];
    if (!empty($state['cohort'])) {
        $c = $state['cohort'];
        if (!empty($c['name'])) {
            $meta_bits[] = $c['name'];
        }
        if (!empty($c['facilitator'])) {
            $meta_bits[] = sprintf(__('Facilitator: %s', 'tfp-dashboard'), $c['facilitator']);
        }
        if (!empty($c['schedule'])) {
            $meta_bits[] = $c['schedule'];
        }
    }
    $meta_line = implode(' · ', $meta_bits);
    ?>
    <div class="tfp-journey">
        <div class="tfp-journey__header">
            <h3><?php printf(esc_html__('%d Week Discipleship Journey', 'tfp-dashboard'), $progress['total']); ?></h3>
            <div class="tfp-journey__legend">
                <span class="tfp-journey__legend-item"><span
                        class="tfp-journey__circle tfp-journey__circle--complete tfp-journey__circle--small"></span>
                    <?php esc_html_e('Complete', 'tfp-dashboard'); ?></span>
                <span class="tfp-journey__legend-item"><span
                        class="tfp-journey__circle tfp-journey__circle--current tfp-journey__circle--small"></span>
                    <?php esc_html_e('Current', 'tfp-dashboard'); ?></span>
                <span class="tfp-journey__legend-item"><span
                        class="tfp-journey__circle tfp-journey__circle--upcoming tfp-journey__circle--small"></span>
                    <?php esc_html_e('Upcoming', 'tfp-dashboard'); ?></span>
            </div>
        </div>

        <?php if ($meta_line !== ''): ?>
            <div class="tfp-journey__desc"><?php echo esc_html($meta_line); ?></div>
        <?php endif; ?>

        <div class="tfp-journey__circles">
            <?php foreach ($sections_data as $index => $section): ?>
                <span class="tfp-journey__circle tfp-journey__circle--<?php echo esc_attr($section['status']); ?>"
                    title="<?php echo esc_attr($section['title']); ?>">
                    <?php echo esc_html($index + 1); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="tfp-journey__progressbar">
            <div class="tfp-journey__progressbar-fill" style="width: <?php echo esc_attr($percent); ?>%;"></div>
        </div>
        <div class="tfp-journey__progress-label-wrap">
            <span
                class="tfp-journey__progress-label"><?php printf(esc_html__('%1$d of %2$d lessons completed', 'tfp-dashboard'), $progress['completed'], $progress['total']); ?></span>
            <span
                class="tfp-journey__progress-label-right"><?php printf(esc_html__('Week %1$d of %2$d', 'tfp-dashboard'), $current_week_number, $progress['total']); ?></span>
        </div>
    </div>
    <?php
}

/**
 * The Current Lesson card (Continue Lesson + Go to Discord). Split out of the
 * old Home journey so it can be ordered independently on the program page.
 */
function tfp_dashboard_render_program_current_lesson($user_id, $course_id, $week_url)
{
    $current_week = tfp_ld_get_current_week($user_id, $course_id);

    if ($current_week) {
        $continue_url = add_query_arg(['lesson_id' => $current_week->ID], $week_url);
        $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
        $week_num = 1;
        foreach ($weeks as $idx => $w) {
            if ($w->ID === $current_week->ID) {
                $week_num = $idx + 1;
                break;
            }
        }
        $sub_title = sprintf(__('In Progress · Week %1$d %2$s', 'tfp-dashboard'), $week_num, $current_week->post_title);
        ?>
        <div class="tfp-dash-panel tfp-journey__current">
            <h3><?php esc_html_e('Current Lesson', 'tfp-dashboard'); ?></h3>
            <p class="tfp-journey__current-title"><?php echo esc_html($sub_title); ?></p>
            <div class="tfp-journey__current-actions">
                <a href="<?php echo esc_url($continue_url); ?>" class="tfp-current-lesson-btn">
                    <?php esc_html_e('Continue Lesson', 'tfp-dashboard'); ?>
                </a>
                <a href="<?php echo esc_url(apply_filters('tfp_dashboard_discord_url', '#')); ?>" target="_blank" rel="noopener"
                    class="tfp-current-lesson-btn">
                    <?php esc_html_e('Go to Discord', 'tfp-dashboard'); ?>
                </a>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="tfp-dash-panel tfp-journey__current">
            <h3><?php esc_html_e("You've completed every week!", 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('Great work finishing your program.', 'tfp-dashboard'); ?></p>
        </div>
        <?php
    }
}

/**
 * The PAST DUE card for the single most-overdue week.
 *
 * @param array  $item     ['week'=>WP_Post,'index'=>int,'due_ts'=>int]
 * @param string $week_url Lesson-player URL to append lesson_id + homework tab.
 */
function tfp_dashboard_render_program_pastdue($item, $week_url)
{
    $week = $item['week'];
    $index = (int) $item['index'];
    $complete_url = add_query_arg(['lesson_id' => $week->ID, 'tab' => 'homework'], $week_url);
    $due_label = date_i18n('M j', $item['due_ts']); // e.g. Jun 5
    ?>
    <div class="tfp-dash-panel tfp-pastdue-card">
        <h3 class="tfp-pastdue-card__tag"><?php esc_html_e('PAST DUE', 'tfp-dashboard'); ?></h3>
        <h4 class="tfp-pastdue-card__title"><?php printf(esc_html__('Week %d Homework', 'tfp-dashboard'), $index + 1); ?>
        </h4>
        <p class="tfp-pastdue-card__meta">
            <?php echo esc_html($week->post_title); ?> &middot;
            <?php printf(esc_html__('Due %s', 'tfp-dashboard'), esc_html($due_label)); ?>
        </p>
        <a href="<?php echo esc_url($complete_url); ?>" class="tfp-pastdue-card__btn">
            <?php esc_html_e('Complete Now', 'tfp-dashboard'); ?>
        </a>
    </div>
    <?php
}

/**
 * The Pending Tasks aside — full list of tasks with custom actions, or an all-caught-up
 * empty state.
 */
function tfp_dashboard_render_program_tasks($tasks, $week_url)
{
    ?>
    <div class="tfp-dash-panel tfp-tasks-panel">
        <div class="tfp-tasks-panel__header">
            <h3 class="tfp-tasks-panel__title"><?php esc_html_e('Pending Tasks', 'tfp-dashboard'); ?></h3>
            <a href="#tfp-tasks-list" class="tfp-tasks-panel__view-all"
                id="tfp-tasks-view-all"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
        </div>
        <p class="tfp-tasks-panel__subtitle"><?php esc_html_e('Tasks that need your Attention', 'tfp-dashboard'); ?></p>

        <?php if (empty($tasks)): ?>
            <div class="tfp-tasks-empty">
                <p><?php esc_html_e("You're all caught up", 'tfp-dashboard'); ?></p>
            </div>
        <?php else: ?>
            <ul class="tfp-tasks-list" id="tfp-tasks-list">
                <?php foreach ($tasks as $task):
                    $btn_cls = (!empty($task['btn_type']) && $task['btn_type'] === 'gray') ? 'tfp-task-btn--gray' : 'tfp-task-btn--teal';
                    ?>
                    <li class="tfp-task-row">
                        <div class="tfp-task-row__info">
                            <span class="tfp-task-row__title"><?php echo esc_html($task['title']); ?></span>
                            <?php if (!empty($task['meta'])): ?>
                                <span class="tfp-task-row__meta"><?php echo esc_html($task['meta']); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url($task['url']); ?>" class="tfp-task-btn <?php echo esc_attr($btn_cls); ?>">
                            <?php echo esc_html($task['btn_text']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}
