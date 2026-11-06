<?php
/**
 * Facilitator Meetings template.
 */
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
tfp_dashboard_shell_start('facilitator-meetings');
tfp_facilitator_meetings_render();
tfp_dashboard_shell_end();
