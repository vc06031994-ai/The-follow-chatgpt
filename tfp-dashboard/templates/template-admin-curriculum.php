<?php
/** Admin Curriculum Categories page. */
if (!defined('ABSPATH')) { exit; }
if (!is_user_logged_in()) { auth_redirect(); }
if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to access this page.','tfp-dashboard'),esc_html__('Access denied','tfp-dashboard'),['response'=>403]); }
show_admin_bar(false);
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
status_header(200); nocache_headers();
require_once dirname(__DIR__).'/includes/admin/dashboard.php';
require_once dirname(__DIR__).'/includes/admin/courses.php';
require_once dirname(__DIR__).'/includes/admin/curriculum.php';
$dash_url=defined('TFP_DASH_URL')?TFP_DASH_URL:plugin_dir_url(dirname(__DIR__));
$dash_ver=defined('TFP_DASH_VERSION')?TFP_DASH_VERSION:'1.0.0';
$css_file=dirname(__DIR__).'/assets/css/admin-dashboard.css';
$curr_css=dirname(__DIR__).'/assets/css/admin-curriculum.css';
$js_file=dirname(__DIR__).'/assets/js/admin-dashboard.js';
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base',$dash_url.'assets/css/core.css',[],$dash_ver);
wp_enqueue_style('tfp-dashboard-core',$dash_url.'assets/css/dashboard.css',['tfp-dashboard-base'],$dash_ver);
wp_enqueue_style('tfp-dashboard-components',$dash_url.'assets/css/components.css',['tfp-dashboard-core'],$dash_ver);
wp_enqueue_style('tfp-admin-dashboard',$dash_url.'assets/css/admin-dashboard.css',['tfp-dashboard-components'],file_exists($css_file)?(string)filemtime($css_file):$dash_ver);
wp_enqueue_style('tfp-admin-curriculum',$dash_url.'assets/css/admin-curriculum.css',['tfp-admin-dashboard'],file_exists($curr_css)?(string)filemtime($curr_css):$dash_ver);
wp_enqueue_script('tfp-admin-dashboard',$dash_url.'assets/js/admin-dashboard.js',[] ,file_exists($js_file)?(string)filemtime($js_file):$dash_ver,true);
?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body <?php body_class('tfp-admin-dashboard-view tfp-admin-curriculum-view'); ?>><?php wp_body_open(); tfp_admin_curriculum_render(); wp_footer(); ?></body></html>
