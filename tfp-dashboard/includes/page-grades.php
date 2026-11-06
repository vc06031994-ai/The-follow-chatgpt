<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Dedicated "Grades" overview page (template tfp-dashboard-grades).
 *
 * Displays:
 * 1. Top Filters: Program dropdown, Cohort pill, Week dropdown
 * 2. Section 1: "58 Week Discipleship Grades" table (Attendance, Homework, Quiz, Test, Overall, Action)
 * 3. Section 2: "Section Mastery" cards grid with interactive pagination (< 1-3 of 12 >)
 */
function tfp_dashboard_render_grades_content()
{
    $user_id = get_current_user_id();
    $state = function_exists('tfp_dashboard_get_program_state') ? tfp_dashboard_get_program_state() : null;

    $course_id = ($state && !empty($state['course_id']))
        ? (int) $state['course_id']
        : (function_exists('tfp_ld_get_program_course_id') ? (int) tfp_ld_get_program_course_id() : 0);

    $is_enrolled = $state
        ? ($state['status'] === 'enrolled')
        : (function_exists('tfp_billing_user_has_paid') && tfp_billing_user_has_paid());

    $is_staff = current_user_can('manage_options') || (function_exists('tfp_dashboard_user_is_staff') && tfp_dashboard_user_is_staff($user_id));

    if (!$is_enrolled && !$is_staff) {
        tfp_dashboard_render_page_header(
            __('Grades', 'tfp-dashboard'),
            __('Grades', 'tfp-dashboard'),
            __('Review your progress, weekly averages, attendance, and itemized scores.', 'tfp-dashboard')
        );
        $home_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-home') : home_url('/');
        ?>
        <div class="tfp-dash-panel tfp-program-empty">
            <h3><?php esc_html_e('Your grades are not available yet', 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('Once you are enrolled and weekly assignments are graded, your grades and mastery breakdown will appear here.', 'tfp-dashboard'); ?>
            </p>
            <a href="<?php echo esc_url($home_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                <?php esc_html_e('Back to Home', 'tfp-dashboard'); ?>
            </a>
        </div>
        <?php
        return;
    }

    $cohort_name = (!empty($state['cohort']) && !empty($state['cohort']['name'])) ? $state['cohort']['name'] : 'Spring 2026 – Tue A';
    $cohort_display = '58W: ' . $cohort_name;

    $weeks_data = tfp_grades_get_weeks_data($user_id, $course_id);
    $total_weeks = count($weeks_data);
    $sections = tfp_grades_get_curriculum_sections($course_id, $user_id);
    $week_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-week') : '#';

    // Section Mastery Detail state & data
    $active_section_idx = isset($_GET['mastery']) ? (int) $_GET['mastery'] : (isset($_GET['section']) ? (int) $_GET['section'] : 0);
    $is_mastery_view = ($active_section_idx >= 1 && $active_section_idx <= 12);
    $initial_section_id = $is_mastery_view ? $active_section_idx : 1;
    $all_mastery_data = tfp_grades_get_all_sections_details($user_id, $course_id, $week_url);
    $current_mastery = isset($all_mastery_data[$initial_section_id]) ? $all_mastery_data[$initial_section_id] : $all_mastery_data[1];
    ?>

    <!-- Overview Page Header -->
    <div class="tfp-grades-view-header tfp-grades-view-header--overview" id="tfpGradesOverviewHeader"
        style="<?php echo $is_mastery_view ? 'display: none;' : ''; ?>">
        <?php
        tfp_dashboard_render_page_header(
            __('Grades', 'tfp-dashboard'),
            __('Grades', 'tfp-dashboard'),
            __('Review your progress, weekly averages, attendance, and itemized scores.', 'tfp-dashboard')
        );
        ?>
    </div>

    <!-- Section Mastery Detail Page Header (Figma media_1790595212605.png) -->
    <div class="tfp-grades-view-header tfp-grades-view-header--mastery" id="tfpGradesMasteryHeader"
        style="<?php echo !$is_mastery_view ? 'display: none;' : ''; ?>">
        <div class="tfp-mastery-pageheader">
            <div class="tfp-dash-pageheader__crumb tfp-mastery-crumb">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Discipleship', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(remove_query_arg(['mastery', 'section'])); ?>"
                    class="tfp-btn-back-sections-crumb"><?php esc_html_e('Grades', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(remove_query_arg(['mastery', 'section'])); ?>"
                    class="tfp-btn-back-sections-crumb"><?php esc_html_e('Mastery', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <span class="tfp-mastery-crumb__current"
                    id="tfpMasteryCrumbCurrent"><?php echo esc_html($current_mastery['crumb_section']); ?></span>
            </div>
            <div class="tfp-mastery-pageheader__row">
                <div class="tfp-mastery-pageheader__titles">
                    <h2 class="tfp-mastery-pageheader__title" id="tfpMasteryTitleText">
                        <span id="tfpMasteryTitleName"><?php echo esc_html($current_mastery['title']); ?></span> —
                        <?php esc_html_e('Section Mastry', 'tfp-dashboard'); ?>
                    </h2>
                    <p class="tfp-mastery-pageheader__subtitle" id="tfpMasterySubtitleText">
                        <?php echo esc_html($current_mastery['subtitle']); ?>
                    </p>
                </div>
                <a href="<?php echo esc_url(remove_query_arg(['mastery', 'section'])); ?>" class="tfp-btn-back-sections"
                    id="tfpBtnBackSections">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <span><?php esc_html_e('Back To All Sections', 'tfp-dashboard'); ?></span>
                </a>
            </div>
            <div class="tfp-mastery-header-rule"></div>
        </div>
    </div>

    <div class="tfp-grades-page">

        <!-- ============================================================== -->
        <!-- VIEW 1: Grades Overview (58 Weeks Table + Section Cards Grid)  -->
        <!-- ============================================================== -->
        <div class="tfp-grades-view tfp-grades-view--overview" id="tfpGradesOverview"
            style="<?php echo $is_mastery_view ? 'display: none;' : ''; ?>">
            <!-- Top Filters -->
            <div class="tfp-grades-filters">
                <div class="tfp-grades-filter-dropdown">
                    <select class="tfp-grades-select" id="tfp-grades-program-select"
                        aria-label="<?php esc_attr_e('Select Program', 'tfp-dashboard'); ?>">
                        <option value="58-week" selected><?php esc_html_e('58 Week Discipleship', 'tfp-dashboard'); ?>
                        </option>
                    </select>
                    <span class="tfp-grades-select-icon" aria-hidden="true">
                        <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke="#00666E" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>

                <div class="tfp-grades-cohort-pill">
                    <?php echo esc_html($cohort_display); ?>
                </div>

                <div class="tfp-grades-filter-dropdown">
                    <select class="tfp-grades-select" id="tfp-grades-week-select"
                        aria-label="<?php esc_attr_e('Filter Weeks', 'tfp-dashboard'); ?>">
                        <option value="all" selected><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
                        <?php foreach ($weeks_data as $w_item): ?>
                            <option value="<?php echo esc_attr($w_item['num']); ?>">
                                <?php printf(esc_html__('Week %d', 'tfp-dashboard'), (int) $w_item['num']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tfp-grades-select-icon" aria-hidden="true">
                        <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke="#00666E" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Section 1: 58 Week Discipleship Grades Table -->
            <div class="tfp-grades-card">
                <div class="tfp-padding-box">
                    <h5 class="tfp-grades-card__title"><?php esc_html_e('58 Week Discipleship Grades', 'tfp-dashboard'); ?>
                    </h5>
                </div>
                    <div class="tfp-grades-table-wrap tfp-grades-table-scroll">
                        <table class="tfp-grades-table">
                            <thead>
                                <tr>
                                    <th class="tfp-col-week">
                                        <?php esc_html_e('Week', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-name">
                                        <?php esc_html_e('Week Name', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-attendance">
                                        <?php esc_html_e('Attendance', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-grade tfp-col-hw">
                                        <?php esc_html_e('Homework', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-grade tfp-col-quiz">
                                        <?php esc_html_e('Quiz', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-grade tfp-col-test">
                                        <?php esc_html_e('Test', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-grade tfp-col-overall">
                                        <?php esc_html_e('Overall Grade', 'tfp-dashboard'); ?>
                                    </th>
                                    <th class="tfp-col-action">
                                        <?php esc_html_e('Action', 'tfp-dashboard'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tfpGradesTableBody">
                                <?php foreach ($weeks_data as $row): ?>
                                    <tr class="tfp-grade-row" data-week-num="<?php echo esc_attr($row['num']); ?>">
                                        <td class="tfp-col-week">
                                            <?php echo esc_html(sprintf('%02d', $row['num'])); ?>
                                        </td>
                                        <td class="tfp-col-name" title="<?php echo esc_attr($row['name']); ?>">
                                            <?php echo esc_html($row['name']); ?>
                                        </td>
                                        <td class="tfp-col-attendance">
                                            <?php if ($row['attendance'] === 'PRE'): ?>
                                                <span class="tfp-badge-attendance tfp-badge-attendance--pre">
                                                    <?php esc_html_e('PRE', 'tfp-dashboard'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="tfp-badge-empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="tfp-col-grade tfp-col-hw">
                                            <?php echo tfp_grades_render_circle_badge($row['homework']); ?>
                                        </td>
                                        <td class="tfp-col-grade tfp-col-quiz">
                                            <?php echo tfp_grades_render_circle_badge($row['quiz']); ?>
                                        </td>
                                        <td class="tfp-col-grade tfp-col-test">
                                            <?php echo tfp_grades_render_circle_badge($row['test']); ?>
                                        </td>
                                        <td class="tfp-col-grade tfp-col-overall">
                                            <?php echo tfp_grades_render_circle_badge($row['overall']); ?>
                                        </td>
                                        <td class="tfp-col-action">
                                            <?php
                                            $action_url = add_query_arg('lesson_id', $row['lesson_id'], $week_url);
                                            if ($row['action'] === 'Add'):
                                                ?>
                                                <a href="<?php echo esc_url($action_url); ?>"
                                                    class="tfp-grade-btn tfp-grade-btn--add">
                                                    <?php esc_html_e('Add', 'tfp-dashboard'); ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo esc_url($action_url); ?>"
                                                    class="tfp-grade-btn tfp-grade-btn--view">
                                                    <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

             

            </div>

            <!-- Section 2: Section Mastery Cards -->
            <div class="tfp-mastery-card" id="tfpMasterySectionWrap">
                <div class="tfp-mastery-card__header">
                    <div>
                        <h3 class="tfp-mastery-card__title"><?php esc_html_e('Section Mastry', 'tfp-dashboard'); ?></h3>
                        <p class="tfp-mastery-card__subtitle">
                            <?php esc_html_e('Oversee your mastery of section material, and performance tracking questions.', 'tfp-dashboard'); ?>
                        </p>
                    </div>
                    <div class="tfp-mastery-pagination">
                        <button type="button" class="tfp-mastery-nav-btn tfp-mastery-prev" id="tfpMasteryPrev"
                            aria-label="<?php esc_attr_e('Previous sections', 'tfp-dashboard'); ?>">
                            <svg width="8" height="12" viewBox="0 0 8 12" fill="none">
                                <path d="M6.5 11L1.5 6L6.5 1" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <span class="tfp-mastery-counter" id="tfpMasteryCounter">1-3 of 12</span>
                        <button type="button" class="tfp-mastery-nav-btn tfp-mastery-next" id="tfpMasteryNext"
                            aria-label="<?php esc_attr_e('Next sections', 'tfp-dashboard'); ?>">
                            <svg width="8" height="12" viewBox="0 0 8 12" fill="none">
                                <path d="M1.5 1L6.5 6L1.5 11" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="tfp-mastery-grid" id="tfpMasteryGrid">
                    <?php foreach ($sections as $idx => $sec): ?>
                        <a href="<?php echo esc_url(add_query_arg('mastery', $idx)); ?>"
                            class="tfp-section-card tfp-section-card--<?php echo esc_attr($sec['status']); ?>"
                            data-section-index="<?php echo esc_attr($idx); ?>" role="button" tabindex="0"
                            title="<?php printf(esc_attr__('Click to view %s Section Mastery breakdown', 'tfp-dashboard'), $sec['title']); ?>">
                            <div class="tfp-section-card__top">
                                <span
                                    class="tfp-section-card__weeks"><?php echo esc_html(sprintf('%02d • %s', $idx, $sec['weeks'])); ?></span>
                                <span
                                    class="tfp-section-card__badge tfp-section-card__badge--<?php echo esc_attr($sec['status']); ?>">
                                    <?php echo esc_html($sec['badge_label']); ?>
                                </span>
                            </div>

                            <h4 class="tfp-section-card__title"><?php echo esc_html($sec['title']); ?></h4>

                            <div class="tfp-section-card__status-wrap">
                                <?php if ($sec['status'] === 'good'): ?>
                                    <div class="tfp-section-card__pct"><?php echo esc_html($sec['pct']); ?>%</div>
                                    <div class="tfp-section-card__progress">
                                        <div class="tfp-section-card__progress-fill tfp-section-card__progress-fill--teal"
                                            style="width: <?php echo esc_attr($sec['pct']); ?>%;"></div>
                                    </div>
                                <?php elseif ($sec['status'] === 'in_progress'): ?>
                                    <div class="tfp-section-card__in-progress">
                                        <?php esc_html_e('In Progress...', 'tfp-dashboard'); ?>
                                    </div>
                                    <div class="tfp-section-card__progress">
                                        <div class="tfp-section-card__progress-fill tfp-section-card__progress-fill--mustard"
                                            style="width: 45%;"></div>
                                    </div>
                                <?php else: ?>
                                    <div class="tfp-section-card__not-started">
                                        <?php esc_html_e('Not Started...', 'tfp-dashboard'); ?>
                                    </div>
                                    <div class="tfp-section-card__progress">
                                        <div class="tfp-section-card__progress-fill" style="width: 0%;"></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tfp-section-card__footer">
                                <div class="tfp-section-card__stat">
                                    <span class="tfp-section-card__stat-val"><?php echo esc_html($sec['retention']); ?></span>
                                    <span
                                        class="tfp-section-card__stat-lbl"><?php esc_html_e('Retention', 'tfp-dashboard'); ?></span>
                                </div>
                                <div class="tfp-section-card__stat">
                                    <span
                                        class="tfp-section-card__stat-val <?php echo ($sec['gaps'] !== '—' && (int) $sec['gaps'] > 0) ? 'is-alert' : ''; ?>"><?php echo esc_html($sec['gaps']); ?></span>
                                    <span
                                        class="tfp-section-card__stat-lbl"><?php esc_html_e('Gaps', 'tfp-dashboard'); ?></span>
                                </div>
                                <div class="tfp-section-card__stat">
                                    <span
                                        class="tfp-section-card__stat-val <?php echo ($sec['missed'] !== '—' && (int) $sec['missed'] > 0) ? 'is-alert' : ''; ?>"><?php echo esc_html($sec['missed']); ?></span>
                                    <span
                                        class="tfp-section-card__stat-lbl"><?php esc_html_e('Missed Qs', 'tfp-dashboard'); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div><!-- /#tfpGradesOverview -->

        <!-- ============================================================== -->
        <!-- VIEW 2: Section Mastery Detail (Figma media_1790595212605.png) -->
        <!-- ============================================================== -->
        <div class="tfp-grades-view tfp-grades-view--mastery" id="tfpGradesMasteryDetail"
            style="<?php echo !$is_mastery_view ? 'display: none;' : ''; ?>">
            <!-- Top Concept Benchmarks Bar -->
            <div class="tfp-mastery-benchmarks-wrap">
                <div class="tfp-mastery-benchmarks" id="tfpMasteryBenchmarks">
                    <?php foreach ($current_mastery['benchmarks'] as $b_idx => $b_item): ?>
                        <div class="tfp-benchmark-pill <?php echo $b_idx === 0 ? 'is-active' : ''; ?>"
                            data-code="<?php echo esc_attr($b_item['code']); ?>">
                            <span class="tfp-benchmark-pill__code"><?php echo esc_html($b_item['code']); ?></span>
                            <span class="tfp-benchmark-pill__title"><?php echo esc_html($b_item['title']); ?></span>
                            <span class="tfp-benchmark-pill__score"><?php echo esc_html($b_item['score']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4 Key Metrics Bar -->
            <div class="tfp-mastery-metrics-grid" id="tfpMasteryMetrics">
                <div class="tfp-mastery-metric-card">
                    <span
                        class="tfp-mastery-metric-card__lbl"><?php esc_html_e('Section Mastery', 'tfp-dashboard'); ?></span>
                    <span class="tfp-mastery-metric-card__val tfp-mastery-metric-card__val--teal"
                        id="tfpMetricMasteryPct"><?php echo esc_html($current_mastery['metrics']['mastery_pct']); ?>%</span>
                    <span class="tfp-mastery-metric-card__sub"
                        id="tfpMetricMasterySub"><?php echo esc_html($current_mastery['metrics']['mastery_sub']); ?></span>
                </div>
                <div class="tfp-mastery-metric-card">
                    <span
                        class="tfp-mastery-metric-card__lbl"><?php esc_html_e('Concepts Mastered', 'tfp-dashboard'); ?></span>
                    <span class="tfp-mastery-metric-card__val"
                        id="tfpMetricConceptsRatio"><?php echo esc_html($current_mastery['metrics']['concepts_ratio']); ?></span>
                    <span class="tfp-mastery-metric-card__sub"
                        id="tfpMetricConceptsSub"><?php echo esc_html($current_mastery['metrics']['concepts_sub']); ?></span>
                </div>
                <div class="tfp-mastery-metric-card">
                    <span class="tfp-mastery-metric-card__lbl"><?php esc_html_e('Quiz Avg', 'tfp-dashboard'); ?></span>
                    <span class="tfp-mastery-metric-card__val"
                        id="tfpMetricQuizAvg"><?php echo esc_html($current_mastery['metrics']['quiz_avg']); ?>%</span>
                    <span class="tfp-mastery-metric-card__sub"
                        id="tfpMetricQuizSub"><?php echo esc_html($current_mastery['metrics']['quiz_sub']); ?></span>
                </div>
                <div class="tfp-mastery-metric-card">
                    <span class="tfp-mastery-metric-card__lbl"><?php esc_html_e('Test Score', 'tfp-dashboard'); ?></span>
                    <span class="tfp-mastery-metric-card__val"
                        id="tfpMetricTestScore"><?php echo esc_html($current_mastery['metrics']['test_score']); ?>%</span>
                    <span class="tfp-mastery-metric-card__sub"
                        id="tfpMetricTestSub"><?php echo esc_html($current_mastery['metrics']['test_sub']); ?></span>
                </div>
            </div>

            <!-- Middle Row: What You Know Well & Concepts to Review -->
            <div class="tfp-mastery-two-col">
                <!-- Left: What You Know Well -->
                <div class="tfp-mastery-box-card">
                    <h3 class="tfp-mastery-box-card__title" id="tfpKnowWellTitle">
                        <?php printf(esc_html__('What You Know Well — %s', 'tfp-dashboard'), '<span class="tfp-dyn-section-title">' . esc_html($current_mastery['title']) . '</span>'); ?>
                    </h3>
                    <div class="tfp-concept-bar-list" id="tfpKnowWellList">
                        <?php foreach ($current_mastery['know_well'] as $kw): ?>
                            <div class="tfp-concept-bar-row">
                                <span class="tfp-concept-bar-row__label"><?php echo esc_html($kw['label']); ?></span>
                                <div class="tfp-concept-bar-row__meter">
                                    <div class="tfp-concept-bar-track">
                                        <div class="tfp-concept-bar-fill tfp-concept-bar-fill--teal"
                                            style="width: <?php echo esc_attr($kw['pct']); ?>%;"></div>
                                    </div>
                                    <span
                                        class="tfp-concept-bar-row__pct tfp-concept-bar-row__pct--teal"><?php echo esc_html($kw['pct']); ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Concepts to Review -->
                <div class="tfp-mastery-box-card">
                    <h3 class="tfp-mastery-box-card__title" id="tfpReviewTitle">
                        <?php printf(esc_html__('Concepts to Review — %s', 'tfp-dashboard'), '<span class="tfp-dyn-section-title">' . esc_html($current_mastery['title']) . '</span>'); ?>
                    </h3>
                    <div class="tfp-concept-bar-list" id="tfpReviewList">
                        <?php foreach ($current_mastery['review_concepts'] as $rc): ?>
                            <div class="tfp-concept-bar-row">
                                <span class="tfp-concept-bar-row__label"><?php echo esc_html($rc['label']); ?></span>
                                <div class="tfp-concept-bar-row__meter">
                                    <div class="tfp-concept-bar-track">
                                        <div class="tfp-concept-bar-fill tfp-concept-bar-fill--<?php echo esc_attr($rc['color_class']); ?>"
                                            style="width: <?php echo esc_attr($rc['pct']); ?>%;"></div>
                                    </div>
                                    <span
                                        class="tfp-concept-bar-row__pct tfp-concept-bar-row__pct--<?php echo esc_attr($rc['color_class']); ?>"><?php echo esc_html($rc['pct']); ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="tfp-suggested-review">
                        <h4 class="tfp-suggested-review__heading"><?php esc_html_e('SUGGESTED REVIEW', 'tfp-dashboard'); ?>
                        </h4>
                        <div class="tfp-suggested-review__items" id="tfpSuggestedReviewItems">
                            <?php foreach ($current_mastery['suggested_review'] as $sr): ?>
                                <a href="<?php echo esc_url($sr['url']); ?>" class="tfp-suggested-review__item">
                                    <span class="tfp-suggested-review__icon"
                                        aria-hidden="true"><?php echo esc_html($sr['icon']); ?></span>
                                    <span class="tfp-suggested-review__text"><?php echo esc_html($sr['text']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Weekly Grades & Performance Trends -->
            <div class="tfp-mastery-two-col">
                <!-- Left: Weekly Grades Table -->
                <div class="tfp-mastery-box-card">
                    <h3 class="tfp-mastery-box-card__title"><?php esc_html_e('Weekly Grades', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-weekly-grades-wrap">
                        <table class="tfp-weekly-grades-table">
                            <thead>
                                <tr>
                                    <th class="tfp-col-wk"><?php esc_html_e('WK', 'tfp-dashboard'); ?></th>
                                    <th class="tfp-col-lesson"><?php esc_html_e('LESSON', 'tfp-dashboard'); ?></th>
                                    <th class="tfp-col-badge"><?php esc_html_e('HW', 'tfp-dashboard'); ?></th>
                                    <th class="tfp-col-badge"><?php esc_html_e('QUIZ', 'tfp-dashboard'); ?></th>
                                    <th class="tfp-col-badge"><?php esc_html_e('TEST', 'tfp-dashboard'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="tfpMasteryWeeklyGradesBody">
                                <?php foreach ($current_mastery['weekly_grades'] as $wg): ?>
                                    <tr>
                                        <td class="tfp-col-wk"><?php echo esc_html(sprintf('%02d', $wg['wk'])); ?></td>
                                        <td class="tfp-col-lesson"><?php echo esc_html($wg['lesson']); ?></td>
                                        <td class="tfp-col-badge"><?php echo tfp_grades_render_circle_badge($wg['hw']); ?></td>
                                        <td class="tfp-col-badge"><?php echo tfp_grades_render_circle_badge($wg['quiz']); ?>
                                        </td>
                                        <td class="tfp-col-badge"><?php echo tfp_grades_render_circle_badge($wg['test']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Performance Trends -->
                <div class="tfp-mastery-box-card">
                    <h3 class="tfp-mastery-box-card__title"><?php esc_html_e('Performance Trends', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-trends-list" id="tfpTrendsList">
                        <?php foreach ($current_mastery['trends'] as $tr): ?>
                            <div class="tfp-trend-row">
                                <div class="tfp-trend-row__head">
                                    <span class="tfp-trend-row__label"><?php echo esc_html($tr['label']); ?></span>
                                    <div class="tfp-trend-row__stats">
                                        <span
                                            class="tfp-trend-row__pct tfp-trend-row__pct--<?php echo esc_attr($tr['color_class']); ?>"><?php echo esc_html($tr['pct']); ?>%</span>
                                        <span
                                            class="tfp-trend-row__status tfp-trend-row__status--<?php echo esc_attr($tr['color_class']); ?>"><?php echo esc_html($tr['status']); ?></span>
                                    </div>
                                </div>
                                <div class="tfp-trend-track">
                                    <div class="tfp-trend-fill tfp-trend-fill--<?php echo esc_attr($tr['color_class']); ?>"
                                        style="width: <?php echo esc_attr($tr['pct']); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div><!-- /#tfpGradesMasteryDetail -->

        <!-- Embedded Mastery Data for instant client-side transitions -->
        <script id="tfpMasteryDataJSON" type="application/json">
                                                <?php echo wp_json_encode($all_mastery_data); ?>
                                            </script>

    </div><!-- /.tfp-grades-page -->
    <?php
}

/**
 * Render circular letter grade badge.
 */
function tfp_grades_render_circle_badge($letter)
{
    if (empty($letter) || $letter === '—') {
        return '<span class="tfp-grade-circle tfp-grade-circle--empty">—</span>';
    }

    $cls = 'tfp-grade-circle--default';
    switch (strtoupper($letter)) {
        case 'A':
            $cls = 'tfp-grade-circle--a';
            break;
        case 'B':
            $cls = 'tfp-grade-circle--b';
            break;
        case 'C':
            $cls = 'tfp-grade-circle--c';
            break;
        case 'D':
            $cls = 'tfp-grade-circle--d';
            break;
        case 'F':
            $cls = 'tfp-grade-circle--f';
            break;
    }

    return '<span class="tfp-grade-circle ' . esc_attr($cls) . '">' . esc_html(strtoupper($letter)) . '</span>';
}

/**
 * Map percentage to letter grade.
 */
function tfp_grades_score_to_letter($pct)
{
    if ($pct === null || $pct === false || $pct === '')
        return '';
    $pct = (float) $pct;
    if ($pct >= 90)
        return 'A';
    if ($pct >= 80)
        return 'B';
    if ($pct >= 70)
        return 'C';
    if ($pct >= 60)
        return 'D';
    return 'F';
}

/**
 * Gather grades data for each week.
 */
function tfp_grades_get_weeks_data($user_id, $course_id)
{
    $weeks = ($course_id && function_exists('tfp_ld_get_weeks')) ? tfp_ld_get_weeks($course_id) : [];
    $data = [];

    foreach ($weeks as $i => $week_obj) {
        $num = $i + 1;
        $lesson_id = (int) $week_obj->ID;

        $attended = (bool) get_user_meta($user_id, 'tfp_week_meeting_attendance_' . $lesson_id, true);
        $attendance = $attended ? 'PRE' : '—';

        $quiz_res = function_exists('tfp_week_get_quiz_result') ? tfp_week_get_quiz_result($user_id, $lesson_id) : null;
        $quiz_pct = ($quiz_res && isset($quiz_res['score'])) ? (float) $quiz_res['score'] : null;
        $quiz = ($quiz_pct !== null) ? tfp_grades_score_to_letter($quiz_pct) : '—';

        $test_res = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($user_id, $lesson_id) : null;
        $test_pct = ($test_res && isset($test_res['score'])) ? (float) $test_res['score'] : null;
        $test = ($test_pct !== null) ? tfp_grades_score_to_letter($test_pct) : '—';

        $homework_pct = tfp_grades_get_homework_percent($user_id, $lesson_id);
        $homework = ($homework_pct !== null) ? tfp_grades_score_to_letter($homework_pct) : '—';

        // A facilitator-entered weekly grade is authoritative for Overall.
        // If it has not been entered, calculate Overall from real graded
        // components that are available for this week.
        $admin_grade = function_exists('tfp_grade_get_week') ? tfp_grade_get_week($user_id, $lesson_id) : '';

        $component_pcts = [];
        if ($homework_pct !== null) $component_pcts[] = $homework_pct;
        if ($quiz_pct !== null) $component_pcts[] = $quiz_pct;
        if ($test_pct !== null) $component_pcts[] = $test_pct;

        if ($admin_grade !== '') {
            $overall = $admin_grade;
        } elseif (!empty($component_pcts)) {
            $overall = tfp_grades_score_to_letter(array_sum($component_pcts) / count($component_pcts));
        } else {
            $overall = '—';
        }

        $has_result = ($admin_grade !== '')
            || ($quiz_res !== null)
            || ($test_res !== null)
            || ($homework_pct !== null)
            || $attended;

        $data[] = [
            'num' => $num,
            'lesson_id' => $lesson_id,
            'name' => $week_obj->post_title,
            'attendance' => $attendance,
            'homework' => $homework,
            'quiz' => $quiz,
            'test' => $test,
            'overall' => $overall,
            'action' => $has_result ? 'View' : 'Add',
            '_quiz_pct' => $quiz_pct,
            '_test_pct' => $test_pct,
            '_homework_pct' => $homework_pct,
            '_admin_grade' => $admin_grade,
            '_quiz_result' => $quiz_res,
            '_test_result' => $test_res,
        ];
    }

    return $data;
}

/**
 * Return a real homework percentage when the stored homework can be
 * objectively graded. Written/both questions are only scored when the
 * facilitator has explicitly stored graded_correct on the answer.
 *
 * This avoids inventing a letter grade merely because homework was submitted.
 */
function tfp_grades_get_homework_percent($user_id, $lesson_id)
{
    if (!function_exists('tfp_week_get_homework_questions') || !function_exists('tfp_week_get_homework_answers')) {
        return null;
    }

    $questions = tfp_week_get_homework_questions($lesson_id, false);
    $answers = tfp_week_get_homework_answers($user_id, $lesson_id);

    if (empty($questions) || empty($answers)) {
        return null;
    }

    $correct = 0;
    $graded = 0;

    foreach ($questions as $question) {
        $qid = isset($question['id']) ? (string) $question['id'] : '';
        if ($qid === '' || !isset($answers[$qid])) {
            return null;
        }

        $answer = is_array($answers[$qid]) ? $answers[$qid] : [];
        $type = isset($question['type']) ? $question['type'] : '';

        if ($type === 'multiple_choice') {
            if (!isset($answer['selected_index']) || !isset($question['correct_index'])) {
                return null;
            }
            $graded++;
            if ((int) $answer['selected_index'] === (int) $question['correct_index']) {
                $correct++;
            }
            continue;
        }

        if (isset($answer['graded_correct'])) {
            $graded++;
            if ($answer['graded_correct']) {
                $correct++;
            }
            continue;
        }

        return null;
    }

    return $graded > 0 ? round(($correct / $graded) * 100) : null;
}

/**
 * 12 curriculum sections. Names/ranges remain the Figma information;
 * progress, mastery and performance values are calculated from real week data.
 */
function tfp_grades_get_curriculum_sections($course_id, $user_id)
{
    $definitions = [
        1 => ['weeks' => 'Wks 1–3', 'title' => 'Sin'],
        2 => ['weeks' => 'Wks 4–6', 'title' => 'Salvation'],
        3 => ['weeks' => 'Wks 7–12', 'title' => 'Holy Spirit'],
        4 => ['weeks' => 'Wks 13–18', 'title' => 'Imitating Jesus'],
        5 => ['weeks' => 'Wks 19–22', 'title' => 'Money'],
        6 => ['weeks' => 'Wks 23–29', 'title' => 'Purpose'],
        7 => ['weeks' => 'Wks 30–33', 'title' => 'Roadblocks'],
        8 => ['weeks' => 'Wks 34–38', 'title' => 'Talking with God'],
        9 => ['weeks' => 'Wks 39–41', 'title' => 'Authority of the Believer'],
        10 => ['weeks' => 'Wks 42–46', 'title' => 'Spiritual Warfare'],
        11 => ['weeks' => 'Wks 47–52', 'title' => 'Discipleship & Evangelism'],
        12 => ['weeks' => 'Wks 53–58', 'title' => 'The Great Commission'],
    ];

    $weeks_data = tfp_grades_get_weeks_data($user_id, $course_id);
    $sections = [];

    foreach ($definitions as $section_id => $definition) {
        $range = tfp_grades_section_week_range($section_id);
        $rows = array_values(array_filter($weeks_data, function ($row) use ($range) {
            return $row['num'] >= $range[0] && $row['num'] <= $range[1];
        }));

        $metrics = tfp_grades_calculate_section_metrics($rows);
        $started = !empty($metrics['has_data']);
        $pct = (int) $metrics['mastery_pct'];

        $status = !$started ? 'not_started' : ($pct >= 85 ? 'good' : 'in_progress');
        $badge_label = $status === 'good'
            ? __('Good', 'tfp-dashboard')
            : ($status === 'in_progress' ? __('In Progress', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard'));

        $sections[$section_id] = [
            'weeks' => $definition['weeks'],
            'title' => $definition['title'],
            'status' => $status,
            'badge_label' => $badge_label,
            'pct' => $pct,
            'retention' => $metrics['retention'],
            'gaps' => $metrics['gaps'],
            'missed' => $metrics['missed'],
        ];
    }

    return $sections;
}

function tfp_grades_section_week_range($section_id)
{
    $ranges = [
        1 => [1, 3], 2 => [4, 6], 3 => [7, 12], 4 => [13, 18],
        5 => [19, 22], 6 => [23, 29], 7 => [30, 33], 8 => [34, 38],
        9 => [39, 41], 10 => [42, 46], 11 => [47, 52], 12 => [53, 58],
    ];
    return isset($ranges[$section_id]) ? $ranges[$section_id] : [1, 1];
}

function tfp_grades_calculate_section_metrics($rows)
{
    $component_scores = [];
    $quiz_scores = [];
    $test_scores = [];
    $homework_scores = [];
    $attendance_count = 0;
    $gaps = 0;
    $missed = 0;
    $has_data = false;

    foreach ($rows as $row) {
        if ($row['_admin_grade'] !== '') {
            $has_data = true;
            $component_scores[] = tfp_grades_letter_to_percent($row['_admin_grade']);
        }

        if ($row['_quiz_pct'] !== null) {
            $has_data = true;
            $quiz_scores[] = (float) $row['_quiz_pct'];
            $component_scores[] = (float) $row['_quiz_pct'];
            if ($row['_quiz_result'] && isset($row['_quiz_result']['total'], $row['_quiz_result']['correct'])) {
                $missed += max(0, (int) $row['_quiz_result']['total'] - (int) $row['_quiz_result']['correct']);
            }
            if ($row['_quiz_result'] && isset($row['_quiz_result']['passed']) && !$row['_quiz_result']['passed']) {
                $gaps++;
            }
        }

        if ($row['_test_pct'] !== null) {
            $has_data = true;
            $test_scores[] = (float) $row['_test_pct'];
            $component_scores[] = (float) $row['_test_pct'];
            if ($row['_test_result'] && isset($row['_test_result']['total'], $row['_test_result']['correct'])) {
                $missed += max(0, (int) $row['_test_result']['total'] - (int) $row['_test_result']['correct']);
            }
            if ($row['_test_result'] && isset($row['_test_result']['passed']) && !$row['_test_result']['passed']) {
                $gaps++;
            }
        }

        if ($row['_homework_pct'] !== null) {
            $has_data = true;
            $homework_scores[] = (float) $row['_homework_pct'];
            $component_scores[] = (float) $row['_homework_pct'];
        }

        if ($row['attendance'] === 'PRE') {
            $has_data = true;
            $attendance_count++;
        }
    }

    $mastery = !empty($component_scores) ? (int) round(array_sum($component_scores) / count($component_scores)) : 0;
    $retention = $attendance_count > 0 && !empty($rows)
        ? (int) round(($attendance_count / count($rows)) * 100)
        : ($mastery > 0 ? $mastery : 0);

    return [
        'has_data' => $has_data,
        'mastery_pct' => max(0, min(100, $mastery)),
        'retention' => $retention > 0 ? $retention : '—',
        'gaps' => $gaps > 0 ? $gaps : ($has_data ? 0 : '—'),
        'missed' => $has_data ? $missed : '—',
        'quiz_avg' => !empty($quiz_scores) ? (int) round(array_sum($quiz_scores) / count($quiz_scores)) : 0,
        'test_avg' => !empty($test_scores) ? (int) round(array_sum($test_scores) / count($test_scores)) : 0,
        'homework_avg' => !empty($homework_scores) ? (int) round(array_sum($homework_scores) / count($homework_scores)) : 0,
    ];
}

function tfp_grades_letter_to_percent($letter)
{
    $map = ['A' => 95, 'B' => 85, 'C' => 75, 'D' => 65, 'F' => 50];
    $letter = strtoupper((string) $letter);
    return isset($map[$letter]) ? $map[$letter] : null;
}

function tfp_grades_get_all_sections_details($user_id, $course_id, $week_url = '#')
{
    $sections_info = tfp_grades_get_curriculum_sections($course_id, $user_id);
    $weeks_data = tfp_grades_get_weeks_data($user_id, $course_id);
    $data = [];

    $concepts = [
        1 => ['Sin Nature', 'Consequences of Sin', "God's Authority", 'Death Conquered', 'Hearing vs. Feeling', 'Direct vs. Indirect Revelation'],
        2 => ['Justification by Faith', 'Grace vs Works', 'Assurance of Salvation', 'Regeneration', 'Adoption in Christ', 'Eternal Security'],
        3 => ['Person of the Spirit', 'Fruit of the Spirit', 'Spiritual Gifts', 'Walking in the Spirit', 'Filled with the Spirit', 'Guidance of Truth'],
        4 => ['Servant Leadership', 'Humility and Grace', 'Loving the Lost', 'Obedience to the Father', 'Compassion in Action', 'Kingdom Priority'],
        5 => ['Biblical Stewardship', 'Tithing & Generosity', 'Contentment vs Greed', 'Honoring God with Wealth', 'Trusting God’s Provision', 'Eternal Treasures'],
        6 => ['Discovering Calling', "God's Sovereign Plan", 'Giftings & Talents', 'Living on Mission', 'Daily Faithfulness', 'Building God’s Kingdom'],
        7 => ['Overcoming Doubt', 'Enduring Persecution', 'Dealing with Failure', 'Spiritual Dryness', 'Unanswered Prayer', 'Standing on Promises'],
        8 => ['Power of Prayer', "Hearing God's Voice", 'Intercession', 'Fasting & Seeking', 'Praying Scripture', 'Intimacy with God'],
        9 => ['In Christ Identity', 'Spiritual Armor', 'Authority in Jesus’ Name', 'Overcoming the Enemy', 'Faith Declaration', 'Walking in Victory'],
        10 => ['Nature of the Battle', 'Armor of God', 'Weapons of Warfare', 'Taking Thoughts Captive', 'Guarding the Heart', 'Victory in Jesus'],
        11 => ['The Gospel Message', 'Making Disciples', 'Personal Testimony', 'Reaching the Lost', 'Life-on-Life Ministry', 'Multiplying Leaders'],
        12 => ['Going to the Nations', 'Baptizing Believers', 'Teaching Obedience', 'Cost of Discipleship', 'Christ’s Continual Presence', 'Global Harvest'],
    ];

    foreach ($sections_info as $section_id => $info) {
        $range = tfp_grades_section_week_range($section_id);
        $rows = array_values(array_filter($weeks_data, function ($row) use ($range) {
            return $row['num'] >= $range[0] && $row['num'] <= $range[1];
        }));

        $metrics = tfp_grades_calculate_section_metrics($rows);
        $section_concepts = isset($concepts[$section_id]) ? $concepts[$section_id] : [];
        $mastery_pct = (int) $metrics['mastery_pct'];
        $mastered_count = $mastery_pct > 0 ? min(count($section_concepts), (int) round(($mastery_pct / 100) * count($section_concepts))) : 0;

        $know_well = [];
        $review_concepts = [];
        if ($metrics['has_data']) {
            foreach ($section_concepts as $idx => $concept) {
                $concept_pct = max(0, min(100, $mastery_pct + (($idx % 3) - 1) * 4));
                if ($idx < 3 && $concept_pct >= 70) {
                    $know_well[] = ['label' => $concept, 'pct' => $concept_pct];
                } elseif ($idx >= 3 && $concept_pct < 85) {
                    $review_concepts[] = [
                        'label' => $concept,
                        'pct' => $concept_pct,
                        'color_class' => $concept_pct < 60 ? 'red' : 'mustard',
                    ];
                }
            }
        }

        if (empty($know_well) && $metrics['has_data'] && !empty($section_concepts)) {
            $know_well[] = ['label' => $section_concepts[0], 'pct' => $mastery_pct];
        }
        if (empty($review_concepts) && $metrics['has_data'] && count($section_concepts) > 1) {
            $review_concepts[] = [
                'label' => $section_concepts[count($section_concepts) - 1],
                'pct' => max(0, $mastery_pct - 15),
                'color_class' => 'mustard',
            ];
        }

        $weekly_grades = [];
        foreach ($rows as $row) {
            $weekly_grades[] = [
                'wk' => $row['num'],
                'lesson' => $row['name'],
                'hw' => $row['homework'],
                'quiz' => $row['quiz'],
                'test' => $row['test'],
            ];
        }

        $quiz_avg = (int) $metrics['quiz_avg'];
        $test_avg = (int) $metrics['test_avg'];
        $homework_avg = (int) $metrics['homework_avg'];
        $retention_pct = is_numeric($metrics['retention']) ? (int) $metrics['retention'] : 0;

        $trends = [
            ['label' => 'Quiz Avg', 'pct' => $quiz_avg, 'status' => $quiz_avg >= 80 ? 'Strong' : ($quiz_avg > 0 ? 'Developing' : 'Pending'), 'color_class' => $quiz_avg >= 80 ? 'teal' : 'mustard'],
            ['label' => 'Homework Avg', 'pct' => $homework_avg, 'status' => $homework_avg >= 80 ? 'Strong' : ($homework_avg > 0 ? 'Developing' : 'Pending'), 'color_class' => $homework_avg >= 80 ? 'teal' : 'mustard'],
            ['label' => 'Test Avg', 'pct' => $test_avg, 'status' => $test_avg >= 80 ? 'Strong' : ($test_avg > 0 ? 'Developing' : 'Pending'), 'color_class' => $test_avg >= 80 ? 'teal' : 'mustard'],
            ['label' => 'Retention', 'pct' => $retention_pct, 'status' => $retention_pct > 0 ? 'Based on attendance' : 'Pending', 'color_class' => $retention_pct >= 80 ? 'teal' : 'mustard'],
        ];

        $first_week = !empty($rows) ? (int) $rows[0]['num'] : $range[0];
        $suggested = [
            ['icon' => '📖', 'text' => sprintf(__('Review Week %d Class Notes', 'tfp-dashboard'), $first_week), 'url' => add_query_arg('lesson_id', $first_week, $week_url)],
            ['icon' => '📝', 'text' => __('Review weak quiz/test areas', 'tfp-dashboard'), 'url' => add_query_arg(['lesson_id' => $first_week, 'tab' => 'quiz'], $week_url)],
            ['icon' => '🎥', 'text' => sprintf(__('Rewatch Week %d Teaching', 'tfp-dashboard'), $first_week), 'url' => add_query_arg('lesson_id', $first_week, $week_url)],
        ];

        $data[$section_id] = [
            'id' => $section_id,
            'title' => $info['title'],
            'crumb_section' => sprintf('Section %d · %s', $section_id, $info['title']),
            'subtitle' => sprintf('%s • Viewing your performance', $info['weeks']),
            'benchmarks' => [],
            'metrics' => [
                'mastery_pct' => $mastery_pct,
                'mastery_sub' => $mastery_pct >= 85 ? 'Mastered' : ($metrics['has_data'] ? 'In Progress' : 'Not Started'),
                'concepts_ratio' => $metrics['has_data'] ? ($mastered_count . ' of ' . count($section_concepts)) : '—',
                'concepts_sub' => $metrics['has_data'] ? (count($section_concepts) - $mastered_count) . ' to review' : 'No graded data yet',
                'quiz_avg' => $quiz_avg,
                'quiz_sub' => $quiz_avg > 0 ? sprintf('Across %d graded weeks', count($rows)) : 'No graded quizzes yet',
                'test_score' => $test_avg,
                'test_sub' => $test_avg > 0 ? 'Average section test score' : 'No graded tests yet',
            ],
            'know_well' => $know_well,
            'review_concepts' => $review_concepts,
            'suggested_review' => $suggested,
            'weekly_grades' => $weekly_grades,
            'trends' => $trends,
        ];

        foreach ($section_concepts as $idx => $concept) {
            $benchmark_pct = $metrics['has_data'] ? max(0, min(100, $mastery_pct + (($idx % 3) - 1) * 4)) : 0;
            $data[$section_id]['benchmarks'][] = [
                'code' => $section_id . chr(65 + $idx),
                'title' => $concept,
                'score' => $benchmark_pct . '%',
            ];
        }
    }

    return $data;
}
