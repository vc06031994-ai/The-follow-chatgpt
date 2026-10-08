<?php
if (!defined('ABSPATH')) exit;

/**
 * Placeholder destination for the Facilitator Roster "View" action.
 * The final student detail UI will be implemented from the supplied Figma.
 */
function tfp_dashboard_render_facilitator_student_content()
{
    $student_id = isset($_GET['tfp_student_id']) ? absint($_GET['tfp_student_id']) : 0;
    $cohorts = tfp_facilitator_current_cohorts();
    $rows = tfp_facilitator_roster_student_rows($cohorts);
    $student_row = null;

    foreach ($rows as $row) {
        if ((int) $row['student_id'] === $student_id) {
            $student_row = $row;
            break;
        }
    }

    if (!$student_row) {
        wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster'));
        exit;
    }

    ?>
    <div class="tfp-dash-pageheader tfp-facilitator-header">
        <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <strong><?php esc_html_e('Student', 'tfp-dashboard'); ?></strong>
        </div>
        <h1 class="tfp-dash-pageheader__title"><?php echo esc_html($student_row['name']); ?></h1>
        <p class="tfp-dash-pageheader__subtitle"><?php esc_html_e('Student details', 'tfp-dashboard'); ?></p>
    </div>

    <section class="tfp-dash-panel tfp-facilitator-panel">
        <p><?php esc_html_e('Student detail UI is ready for the Figma implementation.', 'tfp-dashboard'); ?></p>
    </section>
    <?php
}
