<?php
/**
 * Registers our dashboard page templates so an admin can create normal
 * WordPress Pages (Home, Communication, ...) and pick a template from
 * Page Attributes — no hardcoded URLs anywhere in this plugin.
 */

if (!defined('ABSPATH'))
    exit;

/**
 * Map of template slug => [ label shown in Page Attributes, template file ].
 * Add a new row here whenever a new dashboard page is built
 * (Grades, Mastery, Documents, Calendar, Profile...).
 */
function tfp_dashboard_template_map()
{
    return [
        'tfp-dashboard-home' => [
            'label' => __('TFP Dashboard — Home', 'tfp-dashboard'),
            'file' => 'templates/template-home.php',
        ],
        'tfp-dashboard-facilitator-home' => [
            'label' => __('TFP Facilitator Dashboard — Home', 'tfp-dashboard'),
            'file' => 'templates/template-facilitator-home.php',
        ],
        'tfp-dashboard-facilitator-roster' => [
            'label' => __('TFP Facilitator Dashboard — Roster', 'tfp-dashboard'),
            'file' => 'templates/template-facilitator-roster.php',
        ],
        'tfp-dashboard-communication' => [
            'label' => __('TFP Dashboard — Communication', 'tfp-dashboard'),
            'file' => 'templates/template-communication.php',
        ],
        'tfp-dashboard-payment-details' => [
            'label' => __('TFP Dashboard — Payment Details', 'tfp-dashboard'),
            'file' => 'templates/template-payment-details.php',
        ],
        'tfp-dashboard-financial-aid' => [
            'label' => __('TFP Dashboard — Financial Aid', 'tfp-dashboard'),
            'file' => 'templates/template-financial-aid.php',
        ],
        'tfp-dashboard-profile' => [
            'label' => __('TFP Dashboard — Profile', 'tfp-dashboard'),
            'file' => 'templates/template-profile.php',
        ],
        'tfp-dashboard-update-profile' => [
            'label' => __('TFP Dashboard — Update Profile', 'tfp-dashboard'),
            'file' => 'templates/template-update-profile.php',
        ],
        'tfp-dashboard-week' => [
            'label' => __('TFP Dashboard — Week', 'tfp-dashboard'),
            'file' => 'templates/template-week.php',
        ],
        'tfp-dashboard-program' => [
            'label' => __('TFP Dashboard — Program', 'tfp-dashboard'),
            'file' => 'templates/template-program.php',
        ],
        'tfp-dashboard-grades' => [
            'label' => __('TFP Dashboard — Grades', 'tfp-dashboard'),
            'file' => 'templates/template-grades.php',
        ],
        'tfp-dashboard-documents' => [
            'label' => __('TFP Dashboard — Documents', 'tfp-dashboard'),
            'file' => 'templates/template-documents.php',
        ],
        'tfp-dashboard-calendar' => [
            'label' => __('TFP Dashboard — Calendar', 'tfp-dashboard'),
            'file' => 'templates/template-calendar.php',
        ],
    ];
}

add_action('init', function () {
    static $facilitator_checked = false;
    if ($facilitator_checked) {
        return;
    }
    $facilitator_checked = true;

    $existing = get_page_by_path('facilitator-console');
    if ($existing) {
        update_post_meta($existing->ID, '_wp_page_template', 'tfp-dashboard-facilitator-home');
        return;
    }

    $new_id = wp_insert_post([
        'post_title' => 'Facilitator Console',
        'post_name' => 'facilitator-console',
        'post_status' => 'publish',
        'post_type' => 'page',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ]);

    if ($new_id && !is_wp_error($new_id)) {
        update_post_meta($new_id, '_wp_page_template', 'tfp-dashboard-facilitator-home');
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }
});

/**
 * Ensure the Facilitator Roster page exists and uses our custom template.
 */
add_action('init', function () {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'draft', 'pending'],
        'meta_key' => '_wp_page_template',
        'meta_value' => 'tfp-dashboard-facilitator-roster',
        'numberposts' => 1,
        'fields' => 'ids',
    ]);

    if (!empty($pages)) {
        return;
    }

    $page = get_page_by_path('facilitator-roster');
    if ($page) {
        update_post_meta($page->ID, '_wp_page_template', 'tfp-dashboard-facilitator-roster');
        return;
    }

    $new_id = wp_insert_post([
        'post_title' => 'Facilitator Roster',
        'post_name' => 'facilitator-roster',
        'post_status' => 'publish',
        'post_type' => 'page',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ]);

    if ($new_id && !is_wp_error($new_id)) {
        update_post_meta($new_id, '_wp_page_template', 'tfp-dashboard-facilitator-roster');
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }
});

add_filter('theme_page_templates', function ($templates) {
    foreach (tfp_dashboard_template_map() as $slug => $data) {
        $templates[$slug] = $data['label'];
    }
    return $templates;
});

/**
 * Ensure Grades page exists in WordPress database on activation/init
 */
add_action('init', function () {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'draft', 'pending'],
        'meta_key' => '_wp_page_template',
        'meta_value' => 'tfp-dashboard-grades',
        'numberposts' => 1,
        'fields' => 'ids',
    ]);

    if (!empty($pages)) {
        return;
    }

    $page_by_slug = get_page_by_path('grades');
    if ($page_by_slug) {
        update_post_meta($page_by_slug->ID, '_wp_page_template', 'tfp-dashboard-grades');
        return;
    }

    if (function_exists('wp_insert_post')) {
        $new_id = wp_insert_post([
            'post_title' => 'Grades',
            'post_name' => 'grades',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
        if ($new_id && !is_wp_error($new_id)) {
            update_post_meta($new_id, '_wp_page_template', 'tfp-dashboard-grades');
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules(false);
            }
        }
    }
});

/**
 * Intercept 404s before WordPress displays the 404 page
 */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $parts = explode('/', $req_path);
    $last = end($parts);

    if ($last === 'grades' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'grades')) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    if ($last === 'documents' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'documents')) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    if ($last === 'calendar' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'calendar')) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }

    return $preempt;
}, 10, 2);

/**
 * Fallback template redirection for /grades/
 */
add_action('template_redirect', function () {
    global $wp_query;

    $req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $parts = explode('/', $req_path);
    $last = end($parts);

    if ($last === 'grades' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'grades')) {
        status_header(200);
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }

        $file = TFP_DASH_PATH . 'templates/template-grades.php';
        if (file_exists($file)) {
            include $file;
            exit;
        }
    }

    if ($last === 'documents' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'documents')) {
        status_header(200);
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }

        $file = TFP_DASH_PATH . 'templates/template-documents.php';
        if (file_exists($file)) {
            include $file;
            exit;
        }
    }

    if ($last === 'calendar' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'calendar')) {
        status_header(200);
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }

        $file = TFP_DASH_PATH . 'templates/template-calendar.php';
        if (file_exists($file)) {
            include $file;
            exit;
        }
    }
}, 2);

add_filter('template_include', function ($template) {
    $req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $parts = explode('/', $req_path);
    $last = end($parts);

    if ($last === 'grades' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'grades')) {
        $file = TFP_DASH_PATH . 'templates/template-grades.php';
        if (file_exists($file)) {
            return $file;
        }
    }

    if ($last === 'documents' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'documents')) {
        $file = TFP_DASH_PATH . 'templates/template-documents.php';
        if (file_exists($file)) {
            return $file;
        }
    }

    if ($last === 'calendar' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'calendar')) {
        $file = TFP_DASH_PATH . 'templates/template-calendar.php';
        if (file_exists($file)) {
            return $file;
        }
    }

    // The site's real front page must always remain the normal public
    // WordPress/Elementor homepage. Never replace it with a dashboard
    // template, even if an old page-template assignment or page slug
    // happens to match one of the dashboard templates.
    if (is_front_page() || is_home()) {
        return $template;
    }

    if (!is_page()) {
        return $template;
    }

    $slug = get_page_template_slug(get_the_ID());
    $map = tfp_dashboard_template_map();

    if (isset($map[$slug])) {
        $file = TFP_DASH_PATH . $map[$slug]['file'];
        if (file_exists($file)) {
            return $file;
        }
    }

    // Fallback: match by page slug if template meta isn't explicitly assigned yet
    $page_slug = get_post_field('post_name', get_the_ID());
    foreach ($map as $tmpl_slug => $data) {
        $clean = str_replace('tfp-dashboard-', '', $tmpl_slug);
        if ($page_slug === $clean) {
            $file = TFP_DASH_PATH . $data['file'];
            if (file_exists($file)) {
                return $file;
            }
        }
    }

    return $template;
});

/**
 * Is the currently-viewed request one of our dashboard templates?
 */
function tfp_dashboard_is_dashboard_page()
{
    $req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $parts = explode('/', $req_path);
    $last = end($parts);

    if ($last === 'grades' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'grades')) {
        return true;
    }
    if ($last === 'documents' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'documents')) {
        return true;
    }
    if ($last === 'calendar' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'calendar')) {
        return true;
    }

    // The public site homepage is never a dashboard page. This also prevents
    // the guest -> homepage redirect from becoming a redirect loop when an
    // old dashboard template is still assigned to the front-page record.
    if (is_front_page() || is_home()) {
        return false;
    }

    if (!is_page()) {
        return false;
    }
    $slug = get_page_template_slug(get_the_ID());
    $map = tfp_dashboard_template_map();
    if (array_key_exists($slug, $map)) {
        return true;
    }
    $page_slug = get_post_field('post_name', get_the_ID());
    foreach ($map as $tmpl_slug => $data) {
        $clean = str_replace('tfp-dashboard-', '', $tmpl_slug);
        if ($page_slug === $clean) {
            return true;
        }
    }
    return false;
}

function tfp_dashboard_current_template_slug()
{
    $req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $parts = explode('/', $req_path);
    $last = end($parts);

    if ($last === 'grades' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'grades')) {
        return 'tfp-dashboard-grades';
    }
    if ($last === 'documents' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'documents')) {
        return 'tfp-dashboard-documents';
    }
    if ($last === 'calendar' || (isset($_GET['tfp_page']) && $_GET['tfp_page'] === 'calendar')) {
        return 'tfp-dashboard-calendar';
    }

    // Keep the real public homepage outside dashboard template resolution.
    if (is_front_page() || is_home()) {
        return '';
    }

    if (!is_page()) {
        return '';
    }
    $slug = get_page_template_slug(get_the_ID());
    if ($slug) {
        return $slug;
    }
    $page_slug = get_post_field('post_name', get_the_ID());
    $map = tfp_dashboard_template_map();
    foreach ($map as $tmpl_slug => $data) {
        $clean = str_replace('tfp-dashboard-', '', $tmpl_slug);
        if ($page_slug === $clean) {
            return $tmpl_slug;
        }
    }
    return '';
}

/**
 * Resolve the URL of whichever Page has a given dashboard template
 * assigned. Used by the sidebar nav so links always point at whatever
 * page the admin actually created/renamed — never hardcoded.
 */
function tfp_dashboard_get_url($template_slug)
{
    static $cache = [];

    if (isset($cache[$template_slug])) {
        return $cache[$template_slug];
    }

    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_key' => '_wp_page_template',
        'meta_value' => $template_slug,
        'fields' => 'ids',
    ]);

    if (!empty($pages)) {
        $url = get_permalink($pages[0]);
    } else {
        // Fallback by slug
        $clean_slug = str_replace('tfp-dashboard-', '', $template_slug);
        $page = get_page_by_path($clean_slug);
        if ($page) {
            $url = get_permalink($page->ID);
        } else {
            if ($template_slug === 'tfp-dashboard-grades' && function_exists('wp_insert_post')) {
                $new_id = wp_insert_post([
                    'post_title' => 'Grades',
                    'post_name' => 'grades',
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                ]);
                if ($new_id && !is_wp_error($new_id)) {
                    update_post_meta($new_id, '_wp_page_template', 'tfp-dashboard-grades');
                    $url = get_permalink($new_id);
                } else {
                    $url = home_url('/' . $clean_slug . '/');
                }
            } else {
                $url = home_url('/' . $clean_slug . '/');
            }
        }
    }

    $cache[$template_slug] = $url;
    return $url;
}

/**
 * Guard: redirect guests away from any dashboard template straight to
 * the homepage. Call this at the top of every template file.
 */
function tfp_dashboard_require_login()
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    // During registration/payment setup, students should only be able to use
    // Home/Profile-related dashboard pages. Program/class features stay hidden
    // and cannot be opened directly until enrollment is fully confirmed.
    $restricted_templates = [
        'tfp-dashboard-program',
        'tfp-dashboard-week',
        'tfp-dashboard-grades',
        'tfp-dashboard-communication',
        'tfp-dashboard-documents',
        'tfp-dashboard-calendar',
    ];

    $current_template = tfp_dashboard_current_template_slug();
    $user_id = get_current_user_id();
    $is_staff = current_user_can('manage_options') || (function_exists('tfp_dashboard_user_is_staff') && tfp_dashboard_user_is_staff($user_id));

    if (
        !$is_staff &&
        in_array($current_template, $restricted_templates, true) &&
        function_exists('tfp_dashboard_user_has_full_access') &&
        !tfp_dashboard_user_has_full_access()
    ) {
        $home_url = function_exists('tfp_dashboard_get_url')
            ? tfp_dashboard_get_url('tfp-dashboard-home')
            : home_url('/');

        wp_safe_redirect($home_url ?: home_url('/'));
        exit;
    }
}
