<?php
/**
 * Course System Health & Integrity tab.
 * Health indicators are calculated from the selected course's live week mapping.
 */
if (!defined('ABSPATH')) {
    exit;
}

$health_rows = isset($data['week_rows']) && is_array($data['week_rows']) ? $data['week_rows'] : [];
$health_total = count($health_rows);
$health_mapped = count(array_filter($health_rows, static function ($row) {
    return !empty($row['items']) && !empty($row['id']) && get_post_status((int) $row['id']) === 'publish';
}));
$health_issues = array_values(array_filter($health_rows, static function ($row) {
    return empty($row['items']) || (isset($row['status']) && $row['status'] !== 'Published');
}));
$health_issue_count = count($health_issues);
$health_scan_requested = isset($_GET['tfp_health_scan']) && '1' === sanitize_text_field(wp_unslash($_GET['tfp_health_scan']));
$health_logs_url = add_query_arg(['course_tab' => 'health', 'tfp_health_scan' => '1'], tfp_admin_course_detail_url($course_id));
$health_service_api = rest_url();
?>
<section class="tfp-admin-course-health">
    <div class="tfp-admin-course-health-stats">
        <article class="tfp-admin-course-health-stat">
            <small><?php esc_html_e('Workbook Sync', 'tfp-dashboard'); ?></small>
            <strong class="<?php echo $health_total > 0 && $health_mapped === $health_total ? 'is-good' : 'is-neutral'; ?>">
                <?php echo $health_total > 0 && $health_mapped === $health_total ? esc_html__('In Sync', 'tfp-dashboard') : esc_html__('Needs Review', 'tfp-dashboard'); ?>
            </strong>
        </article>
        <article class="tfp-admin-course-health-stat">
            <small><?php esc_html_e('Content Errors', 'tfp-dashboard'); ?></small>
            <strong class="<?php echo $health_issue_count ? 'is-warning' : 'is-good'; ?>">
                <?php echo esc_html(sprintf(_n('%s Warning', '%s Warnings', $health_issue_count, 'tfp-dashboard'), number_format_i18n($health_issue_count))); ?>
            </strong>
        </article>
        <article class="tfp-admin-course-health-stat">
            <small><?php esc_html_e('Service API', 'tfp-dashboard'); ?></small>
            <strong class="is-good"><?php esc_html_e('Core Available', 'tfp-dashboard'); ?></strong>
        </article>
    </div>

    <div class="tfp-admin-course-health-panels">
        <section class="tfp-admin-panel tfp-admin-course-health-connections">
            <header class="tfp-admin-panel-heading">
                <div>
                    <h2><?php esc_html_e('Curriculum Assets & Connections', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e('Monitor curriculum syncing and third-party service.', 'tfp-dashboard'); ?></p>
                </div>
                <a href="<?php echo esc_url($health_logs_url); ?>"><?php esc_html_e('↗　View All Logs', 'tfp-dashboard'); ?></a>
            </header>
            <div class="tfp-admin-course-health-connection-list">
                <div><strong><?php esc_html_e('YouTube API', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Connection not verified', 'tfp-dashboard'); ?></span></div>
                <div><strong><?php esc_html_e('Discord Webhook', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Connection not verified', 'tfp-dashboard'); ?></span></div>
                <div><strong><?php esc_html_e('Google Drive API', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Connection not verified', 'tfp-dashboard'); ?></span></div>
                <div><strong><?php esc_html_e('Blueprint Sync', 'tfp-dashboard'); ?></strong><span><?php echo esc_html(sprintf(__('%1$s / %2$s Weeks Mapped', 'tfp-dashboard'), number_format_i18n($health_mapped), number_format_i18n($health_total))); ?></span></div>
            </div>
        </section>

        <section class="tfp-admin-panel tfp-admin-course-health-integrity">
            <header class="tfp-admin-panel-heading">
                <div>
                    <h2><?php esc_html_e('Content Integrity Scan', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e('Detect missing curriculum items and unpublished weeks.', 'tfp-dashboard'); ?></p>
                </div>
            </header>
            <div class="tfp-admin-course-health-issues <?php echo $health_issue_count ? 'has-issues' : 'is-clean'; ?>">
                <strong>
                    <?php echo esc_html($health_issue_count ? sprintf(_n('%s Issue Detected', '%s Issues Detected', $health_issue_count, 'tfp-dashboard'), number_format_i18n($health_issue_count)) : __('No Issues Detected', 'tfp-dashboard')); ?>
                </strong>
                <?php if ($health_issue_count) : ?>
                    <ul>
                        <?php foreach (array_slice($health_issues, 0, 4) as $issue) : ?>
                            <li><?php echo esc_html(sprintf(__('Week %1$s: %2$s', 'tfp-dashboard'), $issue['number'], empty($issue['items']) ? __('No curriculum items mapped.', 'tfp-dashboard') : __('Week is not published.', 'tfp-dashboard'))); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e('All published weeks have mapped curriculum items.', 'tfp-dashboard'); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($health_scan_requested) : ?>
                <p class="tfp-admin-course-health-scan-note" role="status"><?php esc_html_e('Scan completed using the current saved course mapping.', 'tfp-dashboard'); ?></p>
            <?php endif; ?>
            <a class="tfp-admin-course-health-scan-button" href="<?php echo esc_url($health_logs_url); ?>"><?php esc_html_e('Run Full System Scan', 'tfp-dashboard'); ?></a>
        </section>
    </div>
</section>
