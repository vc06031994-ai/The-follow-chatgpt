<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Facilitator Cohorts".
 */
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();

tfp_dashboard_shell_start('facilitator-cohorts');
?>
<div class="tfp-facilitator-page">
    <div class="tfp-dash-header">
        <h1 class="tfp-dash-title"><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></h1>
    </div>
    <div class="tfp-dash-card" style="padding: 32px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin-top: 24px;">
        <h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 0 0 12px 0;"><?php esc_html_e('Cohorts Overview', 'tfp-dashboard'); ?></h2>
        <p style="color: #64748b; font-size: 15px; margin: 0; line-height: 1.6;"><?php esc_html_e('Manage and monitor all active cohorts, participant enrollments, and schedules from this view.', 'tfp-dashboard'); ?></p>
    </div>
</div>
<?php
tfp_dashboard_shell_end();
