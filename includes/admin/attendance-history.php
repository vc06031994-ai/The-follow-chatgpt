<?php
/**
 * TFP Dashboard — Attendance Edit History
 *
 * Admin-only read-only audit log for facilitator attendance changes.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'tfp_dashboard_register_attendance_history_page');

function tfp_dashboard_register_attendance_history_page() {
    add_submenu_page(
        'edit.php?post_type=tfp_cohort',
        __('Attendance History', 'tfp-dashboard'),
        __('Attendance History', 'tfp-dashboard'),
        'manage_options',
        'tfp-attendance-history',
        'tfp_dashboard_render_attendance_history_page'
    );
}

function tfp_dashboard_attendance_history_rows($student_filter = '') {
    global $wpdb;

    $like = $wpdb->esc_like('tfp_facilitator_student_attendance_history_') . '%';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, meta_key, meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key LIKE %s
             ORDER BY umeta_id DESC",
            $like
        )
    );

    $history_rows = [];

    foreach ($rows as $meta) {
        $student_id = (int) $meta->user_id;
        if ($student_filter && $student_id !== (int) $student_filter) {
            continue;
        }

        $history = maybe_unserialize($meta->meta_value);
        if (!is_array($history)) {
            continue;
        }

        preg_match('/_(d+)$/', (string) $meta->meta_key, $matches);
        $lesson_id = !empty($matches[1]) ? (int) $matches[1] : 0;
        $student = get_userdata($student_id);

        foreach ($history as $entry_index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $history_rows[] = [
                'student_id'  => $student_id,
                'student'     => $student,
                'lesson_id'   => $lesson_id,
                'lesson'      => $lesson_id ? get_post($lesson_id) : null,
                'index'       => (int) $entry_index,
                'old_status'  => (string) ($entry['old_status'] ?? ''),
                'old_note'    => (string) ($entry['old_note'] ?? ''),
                'status'      => (string) ($entry['status'] ?? ''),
                'note'        => (string) ($entry['note'] ?? ''),
                'changed_at'  => (string) ($entry['changed_at'] ?? ''),
                'author_id'   => (int) ($entry['author_id'] ?? 0),
                'action'      => (string) ($entry['action'] ?? 'updated'),
            ];
        }
    }

    usort($history_rows, static function ($a, $b) {
        return strcmp((string) $b['changed_at'], (string) $a['changed_at']);
    });

    return $history_rows;
}

function tfp_dashboard_render_attendance_history_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view attendance history.', 'tfp-dashboard'));
    }

    $student_filter = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    $students = get_users([
        'meta_key' => '',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID', 'display_name', 'user_email'],
    ]);

    $all_rows = tfp_dashboard_attendance_history_rows($student_filter);

    if ($search !== '') {
        $search_lc = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $all_rows = array_values(array_filter($all_rows, static function ($row) use ($search_lc) {
            $student = $row['student'];
            $lesson = $row['lesson'];

            $haystack = implode(' ', [
                $student ? $student->display_name : '',
                $student ? $student->user_email : '',
                $lesson ? $lesson->post_title : '',
                $row['old_status'],
                $row['status'],
                $row['note'],
            ]);

            $haystack_lc = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
            return strpos($haystack_lc, $search_lc) !== false;
        }));
    }

    $per_page = 25;
    $current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $total = count($all_rows);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $current_page = min($current_page, $total_pages);
    $page_rows = array_slice($all_rows, ($current_page - 1) * $per_page, $per_page);

    $base_url = admin_url('edit.php?post_type=tfp_cohort&page=tfp-attendance-history');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Attendance Edit History', 'tfp-dashboard'); ?></h1>
        <p><?php esc_html_e('Read-only audit trail of facilitator attendance changes. The current attendance shown on the student dashboard remains the latest saved value.', 'tfp-dashboard'); ?></p>

        <form method="get" style="margin:18px 0;">
            <input type="hidden" name="post_type" value="tfp_cohort">
            <input type="hidden" name="page" value="tfp-attendance-history">

            <select name="student_id" style="min-width:260px;">
                <option value="0"><?php esc_html_e('All Students', 'tfp-dashboard'); ?></option>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo esc_attr($student->ID); ?>" <?php selected($student_filter, $student->ID); ?>>
                        <?php echo esc_html($student->display_name . ' (' . $student->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="search"
                name="s"
                value="<?php echo esc_attr($search); ?>"
                placeholder="<?php esc_attr_e('Search student, week, status or note…', 'tfp-dashboard'); ?>"
                style="min-width:320px;"
            >

            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'tfp-dashboard'); ?></button>
            <a href="<?php echo esc_url($base_url); ?>" class="button"><?php esc_html_e('Reset', 'tfp-dashboard'); ?></a>
        </form>

        <p>
            <strong><?php echo esc_html(number_format_i18n($total)); ?></strong>
            <?php esc_html_e('attendance history entries', 'tfp-dashboard'); ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:18%;"><?php esc_html_e('Student', 'tfp-dashboard'); ?></th>
                    <th style="width:16%;"><?php esc_html_e('Week', 'tfp-dashboard'); ?></th>
                    <th style="width:11%;"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></th>
                    <th style="width:11%;"><?php esc_html_e('New Status', 'tfp-dashboard'); ?></th>
                    <th style="width:20%;"><?php esc_html_e('Note', 'tfp-dashboard'); ?></th>
                    <th style="width:12%;"><?php esc_html_e('Changed By', 'tfp-dashboard'); ?></th>
                    <th style="width:12%;"><?php esc_html_e('Changed At', 'tfp-dashboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($page_rows): ?>
                    <?php foreach ($page_rows as $row):
                        $student = $row['student'];
                        $lesson = $row['lesson'];
                        $author = $row['author_id'] ? get_userdata($row['author_id']) : null;
                        $old_status = $row['old_status'] !== '' ? $row['old_status'] : '—';
                        $new_status = $row['status'] !== '' ? $row['status'] : '—';
                        ?>
                        <tr>
                            <td>
                                <?php if ($student): ?>
                                    <strong><?php echo esc_html($student->display_name); ?></strong><br>
                                    <span class="description"><?php echo esc_html($student->user_email); ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $lesson ? esc_html($lesson->post_title) : '—'; ?>
                                <?php if ($row['lesson_id']): ?>
                                    <br><span class="description">ID: <?php echo esc_html($row['lesson_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($old_status); ?></strong></td>
                            <td><strong><?php echo esc_html($new_status); ?></strong></td>
                            <td>
                                <?php echo $row['note'] !== '' ? nl2br(esc_html($row['note'])) : '—'; ?>
                            </td>
                            <td>
                                <?php echo $author ? esc_html($author->display_name) : '—'; ?>
                            </td>
                            <td>
                                <?php
                                echo $row['changed_at']
                                    ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $row['changed_at']))
                                    : '—';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No attendance history found.', 'tfp-dashboard'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom" style="margin-top:14px;">
                <?php
                echo wp_kses_post(paginate_links([
                    'base'      => add_query_arg('paged', '%#%', $base_url),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'type'      => 'plain',
                    'add_args'  => [
                        'student_id' => $student_filter,
                        's' => $search,
                    ],
                    'prev_text' => '&lsaquo;',
                    'next_text' => '&rsaquo;',
                ]));
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
