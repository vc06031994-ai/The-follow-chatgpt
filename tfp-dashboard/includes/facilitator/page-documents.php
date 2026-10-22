<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Facilitator Documents
 *
 * Staff-only overview of facilitator-owned and disciple-owned documents.
 * The data layer reuses the existing TFP Authentication document helpers
 * where available, while cohort membership comes from the facilitator dashboard.
 */

function tfp_facilitator_documents_state()
{
    $cohort = isset($_GET['tfp_fd_cohort']) ? absint($_GET['tfp_fd_cohort']) : 0;
    $search = isset($_GET['tfp_fd_search'])
        ? sanitize_text_field(wp_unslash($_GET['tfp_fd_search']))
        : '';
    $tab = isset($_GET['tfp_fd_tab']) ? sanitize_key($_GET['tfp_fd_tab']) : 'agreements';

    $tabs = [
        'agreements' => __('Agreements', 'tfp-dashboard'),
        'grades' => __('Grades', 'tfp-dashboard'),
        'certificates' => __('Certificates', 'tfp-dashboard'),
        'receipts' => __('Receipts', 'tfp-dashboard'),
    ];

    if (!isset($tabs[$tab])) {
        $tab = 'agreements';
    }

    return [
        'cohort' => $cohort,
        'search' => $search,
        'tab' => $tab,
        'tabs' => $tabs,
    ];
}

function tfp_facilitator_documents_cohort_options($cohorts)
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

function tfp_facilitator_documents_visible_cohorts($cohorts, $state)
{
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

function tfp_facilitator_documents_merge_unique_users($cohorts)
{
    $student_ids = [];

    foreach ((array) $cohorts as $cohort) {
        if (function_exists('tfp_facilitator_cohort_students')) {
            $student_ids = array_merge(
                $student_ids,
                tfp_facilitator_cohort_students($cohort->ID)
            );
        }
    }

    return array_values(array_unique(array_map('absint', $student_ids)));
}

function tfp_facilitator_documents_user_rows($user_id, $tab)
{
    $rows = [];

    switch ($tab) {
        case 'agreements':
            if (function_exists('tfp_dashboard_user_documents')) {
                $rows = (array) tfp_dashboard_user_documents($user_id, 'Agreement');
            }
            break;

        case 'certificates':
            if (function_exists('tfp_dashboard_user_certificates')) {
                $rows = (array) tfp_dashboard_user_certificates($user_id);
            }
            break;

        case 'receipts':
            if (function_exists('tfp_dashboard_user_receipts')) {
                $rows = (array) tfp_dashboard_user_receipts($user_id);
            }
            break;

        case 'grades':
            foreach ([
                'tfp_dashboard_user_grades',
                'tfp_dashboard_user_grade_documents',
                'tfp_dashboard_user_grade_reports',
                'tfp_dashboard_user_grades_documents',
            ] as $helper) {
                if (function_exists($helper)) {
                    $rows = (array) call_user_func($helper, $user_id);
                    break;
                }
            }
            break;
    }

    return array_values(array_filter(array_map(
        'tfp_facilitator_documents_normalize_row',
        $rows
    )));
}

function tfp_facilitator_documents_normalize_row($row)
{
    if (!is_array($row)) {
        return null;
    }

    return [
        'id' => isset($row['id']) ? $row['id'] : '',
        'title' => !empty($row['title']) ? $row['title'] : __('Document', 'tfp-dashboard'),
        'type' => !empty($row['type']) ? $row['type'] : 'Document',
        'date' => !empty($row['date']) ? $row['date'] : '',
        'status' => !empty($row['status']) ? sanitize_key($row['status']) : 'not_started',
        'url' => !empty($row['url']) ? $row['url'] : '',
        'amount' => isset($row['amount']) ? $row['amount'] : '',
        'signed_at' => !empty($row['signed_at']) ? $row['signed_at'] : '',
    ];
}

function tfp_facilitator_documents_add_fallback_grade_row($user_id, $cohort_id = 0)
{
    if (!function_exists('tfp_grade_overall')) {
        return null;
    }

    $course_id = $cohort_id && function_exists('tfp_facilitator_cohort_course_id')
        ? (int) tfp_facilitator_cohort_course_id($cohort_id)
        : 0;

    if (!$course_id) {
        return null;
    }

    $grade = tfp_grade_overall($user_id, $course_id);

    if ($grade === '' || $grade === null) {
        return null;
    }

    return [
        'id' => 'grade-' . $user_id . '-' . $course_id,
        'title' => __('Grade Report', 'tfp-dashboard'),
        'type' => __('Grade Report', 'tfp-dashboard'),
        'date' => current_time('mysql'),
        'status' => 'completed',
        'url' => add_query_arg([
            'tfp_fg_cohort' => $cohort_id,
        ], tfp_dashboard_get_url('tfp-dashboard-facilitator-grades')),
    ];
}

function tfp_facilitator_documents_get_rows_for_user($user_id, $tab, $cohort_id = 0)
{
    $rows = tfp_facilitator_documents_user_rows($user_id, $tab);

    if ($tab === 'grades' && !$rows) {
        $fallback = tfp_facilitator_documents_add_fallback_grade_row($user_id, $cohort_id);

        if ($fallback) {
            $rows[] = tfp_facilitator_documents_normalize_row($fallback);
        }
    }

    return $rows;
}

function tfp_facilitator_documents_status_label($status)
{
    $map = [
        'signed' => __('Completed', 'tfp-dashboard'),
        'completed' => __('Completed', 'tfp-dashboard'),
        'active' => __('Needs Review', 'tfp-dashboard'),
        'pending' => __('Needs Review', 'tfp-dashboard'),
        'needs_review' => __('Needs Review', 'tfp-dashboard'),
        'not_started' => __('Not Started', 'tfp-dashboard'),
    ];

    return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function tfp_facilitator_documents_status_class($status)
{
    if (in_array($status, ['signed', 'completed'], true)) {
        return 'is-completed';
    }

    if (in_array($status, ['active', 'pending', 'needs_review'], true)) {
        return 'is-review';
    }

    return 'is-not-started';
}

function tfp_facilitator_documents_action_label($status)
{
    if (in_array($status, ['signed', 'completed'], true)) {
        return __('View', 'tfp-dashboard');
    }

    if (in_array($status, ['active', 'pending', 'needs_review'], true)) {
        return __('Review', 'tfp-dashboard');
    }

    return __('Add', 'tfp-dashboard');
}

function tfp_facilitator_documents_row_matches_search($row, $user = null, $cohort_name = '')
{
    $search = isset($_GET['tfp_fd_search'])
        ? sanitize_text_field(wp_unslash($_GET['tfp_fd_search']))
        : '';

    if ($search === '') {
        return true;
    }

    $needle = strtolower($search);
    $haystack = strtolower(
        ($row['title'] ?? '') . ' ' .
        ($row['type'] ?? '') . ' ' .
        ($cohort_name ?? '')
    );

    if ($user instanceof WP_User) {
        $haystack .= ' ' . strtolower($user->display_name . ' ' . $user->user_email);
    }

    return strpos($haystack, $needle) !== false;
}

function tfp_facilitator_documents_filter_rows($rows, $tab, $search_user = null, $cohort_name = '')
{
    $filtered = [];

    foreach ((array) $rows as $row) {
        if (!$row || !tfp_facilitator_documents_row_matches_search($row, $search_user, $cohort_name)) {
            continue;
        }

        $filtered[] = $row;
    }

    return $filtered;
}

function tfp_dashboard_render_facilitator_documents_content()
{
    $all_cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? (array) tfp_facilitator_current_cohorts()
        : [];

    $state = tfp_facilitator_documents_state();
    $options = tfp_facilitator_documents_cohort_options($all_cohorts);
    $visible_cohorts = tfp_facilitator_documents_visible_cohorts($all_cohorts, $state);
    $current_user = wp_get_current_user();

    ?>
    <div class="tfp-facilitator-documents-page">
        <header class="tfp-dash-pageheader tfp-facilitator-documents-header">
            <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
                <?php echo tfp_dashboard_icon('toggle'); ?>
            </button>

            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>">
                    <?php esc_html_e('Facilitator', 'tfp-dashboard'); ?>
                </a>
                <span aria-hidden="true">/</span>
                <strong><?php esc_html_e('Documents', 'tfp-dashboard'); ?></strong>
            </div>

            <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Documents', 'tfp-dashboard'); ?></h1>
            <p class="tfp-dash-pageheader__subtitle">
                <?php esc_html_e('Manage and review facilitator and disciple documents.', 'tfp-dashboard'); ?>
            </p>
        </header>

        <form class="tfp-facilitator-filters tfp-fd-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>">
            <select name="tfp_fd_cohort" aria-label="<?php esc_attr_e('All Cohorts', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
                <option value="0" <?php selected($state['cohort'], 0); ?>><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                <?php foreach ($options as $id => $title): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($state['cohort'], $id); ?>><?php echo esc_html($title); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="tfp-dash-search tfp-facilitator-search">
                <span class="tfp-dash-search__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M16 16L13 13M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5 14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <input type="search" name="tfp_fd_search" value="<?php echo esc_attr($state['search']); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search documents', 'tfp-dashboard'); ?>">
            </div>

            <input type="hidden" name="tfp_fd_tab" value="<?php echo esc_attr($state['tab']); ?>">
        </form>

        <nav class="tfp-fd-tabs" aria-label="<?php esc_attr_e('Document categories', 'tfp-dashboard'); ?>">
            <?php foreach ($state['tabs'] as $key => $label): ?>
                <?php
                $tab_url = add_query_arg([
                    'tfp_fd_tab' => $key,
                    'tfp_fd_cohort' => $state['cohort'],
                    'tfp_fd_search' => $state['search'],
                ], get_permalink());
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="tfp-fd-tabs__link <?php echo $state['tab'] === $key ? 'is-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php
        $facilitator_rows = tfp_facilitator_documents_get_rows_for_user(
            (int) $current_user->ID,
            $state['tab'],
            $state['cohort']
        );
        $facilitator_rows = tfp_facilitator_documents_filter_rows($facilitator_rows, $state['tab'], $current_user);

        if ($facilitator_rows):
            ?>
            <section class="tfp-dash-panel tfp-fd-panel">
                <div class="tfp-fd-panel__head">
                    <h2><?php esc_html_e('Facilitator Documents', 'tfp-dashboard'); ?></h2>
                </div>

                <div class="tfp-dash-table-wrap">
                    <table class="tfp-dash-table tfp-fd-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Document', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Date Issues', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                                <th aria-label="<?php esc_attr_e('Document action', 'tfp-dashboard'); ?>"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facilitator_rows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($row['title']); ?></strong>
                                        <?php if ($row['amount'] !== ''): ?>
                                            <small class="tfp-fd-muted"><?php echo wp_kses_post($row['amount']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($row['type']); ?></td>
                                    <td><?php echo $row['date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))) : '—'; ?></td>
                                    <td>
                                        <span class="tfp-fd-status <?php echo esc_attr(tfp_facilitator_documents_status_class($row['status'])); ?>">
                                            <?php echo esc_html(tfp_facilitator_documents_status_label($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['url']): ?>
                                            <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-fd-inline-action">
                                                <?php esc_html_e('View / Download', 'tfp-dashboard'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="tfp-fd-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['url']): ?>
                                            <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-dash-btn-small tfp-dash-btn--primary">
                                                <?php echo esc_html(tfp_facilitator_documents_action_label($row['status'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="tfp-fd-action-placeholder">
                                                <?php echo esc_html(tfp_facilitator_documents_action_label($row['status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php
        $disciple_rows = [];

        foreach ($visible_cohorts as $cohort) {
            $cohort_name = $cohort->post_title;
            $student_ids = function_exists('tfp_facilitator_cohort_students')
                ? tfp_facilitator_cohort_students($cohort->ID)
                : [];

            foreach ($student_ids as $student_id) {
                $student = get_userdata($student_id);

                if (!$student) {
                    continue;
                }

                $user_rows = tfp_facilitator_documents_get_rows_for_user(
                    $student_id,
                    $state['tab'],
                    (int) $cohort->ID
                );

                foreach ($user_rows as $row) {
                    if (!tfp_facilitator_documents_row_matches_search($row, $student, $cohort_name)) {
                        continue;
                    }

                    $row['_student_id'] = $student_id;
                    $row['_student_name'] = $student->display_name ?: $student->user_email;
                    $row['_cohort_name'] = $cohort_name;
                    $disciple_rows[] = $row;
                }
            }
        }
        ?>

        <section class="tfp-dash-panel tfp-fd-panel">
            <div class="tfp-fd-panel__head">
                <h2><?php esc_html_e('Disciples Documents', 'tfp-dashboard'); ?></h2>
            </div>

            <?php if ($disciple_rows): ?>
                <div class="tfp-dash-table-wrap">
                    <table class="tfp-dash-table tfp-fd-table tfp-fd-table--disciples">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Document', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Date Issues', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                                <th aria-label="<?php esc_attr_e('Document action', 'tfp-dashboard'); ?>"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disciple_rows as $row): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($row['_student_name']); ?></strong></td>
                                    <td><?php echo esc_html($row['title']); ?></td>
                                    <td><?php echo esc_html($row['type']); ?></td>
                                    <td><?php echo $row['date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($row['date']))) : '—'; ?></td>
                                    <td>
                                        <span class="tfp-fd-status <?php echo esc_attr(tfp_facilitator_documents_status_class($row['status'])); ?>">
                                            <?php echo esc_html(tfp_facilitator_documents_status_label($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['url']): ?>
                                            <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-fd-inline-action">
                                                <?php esc_html_e('View / Download', 'tfp-dashboard'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="tfp-fd-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['url']): ?>
                                            <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener" class="tfp-dash-btn-small tfp-dash-btn--primary">
                                                <?php echo esc_html(tfp_facilitator_documents_action_label($row['status'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="tfp-fd-action-placeholder">
                                                <?php echo esc_html(tfp_facilitator_documents_action_label($row['status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tfp-fd-empty">
                    <?php
                    printf(
                        esc_html__('No %s documents are available for the selected cohort(s).', 'tfp-dashboard'),
                        strtolower($state['tabs'][$state['tab']])
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
