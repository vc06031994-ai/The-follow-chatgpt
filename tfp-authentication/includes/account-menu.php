<?php
/**
 * TFP Account Menu
 *
 * Adds [tfp_guest_only] and [tfp_account_menu] shortcodes so the Elementor
 * header can automatically swap the Login/Register buttons for a logged-in
 * account dropdown, purely based on WordPress login state (PHP-rendered,
 * no JS state detection).
 *
 * This file does NOT touch login.php, register.php, AJAX handlers, or
 * Elementor popup logic in any way.
 */

if (!defined('ABSPATH')) exit;

/**
 * A program registrant is a user created through the TFP program registration
 * flow. E-commerce-only customers do not have a valid TFP program choice.
 *
 * This is intentionally separate from payment/enrollment status: registering
 * for a program makes the user a Disciple and gives them the program dashboard
 * entry point, while tfp_dashboard_user_has_full_access() continues to control
 * full course/class access after payment + cohort enrollment.
 */
function tfp_auth_user_is_program_registrant($user_id = null)
{
    $user_id = $user_id ?: get_current_user_id();
    if (!$user_id) {
        return false;
    }

    $program_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);
    if ($program_id <= 0) {
        return false;
    }

    $program = get_post($program_id);
    if (!$program || $program->post_type !== 'sfwd-courses' || $program->post_status !== 'publish') {
        return false;
    }

    return true;
}

/**
 * Adds login/program-state classes to <body>.
 */
add_filter('body_class', function ($classes) {
    if (!is_user_logged_in()) {
        $classes[] = 'tfp-logged-out';
        return $classes;
    }

    $classes[] = 'tfp-logged-in';

    $user_id = get_current_user_id();
    $is_program_user = tfp_auth_user_is_program_registrant($user_id);
    $is_staff = function_exists('tfp_dashboard_user_is_staff') && tfp_dashboard_user_is_staff($user_id);

    if ($is_program_user || $is_staff) {
        $classes[] = 'tfp-program-user';
    } else {
        $classes[] = 'tfp-ecommerce-user';
    }

    return $classes;
});

add_shortcode('tfp_guest_only', function ($atts, $content = null) {
    if (is_user_logged_in()) {
        return '';
    }
    return do_shortcode(shortcode_unautop((string) $content));
});

/**
 * Build the list of account dropdown items.
 *
 * @param int $user_id
 * @return array[]
 */
function tfp_get_account_menu_items($user_id)
{
    $items = [];

    $has_wc = function_exists('wc_get_account_endpoint_url') && function_exists('wc_get_page_permalink');

    $items[] = [
        'id'    => 'my-account',
        'label' => __('My Account', 'tfp-authentication'),
        'url'   => $has_wc ? wc_get_account_endpoint_url('dashboard') : admin_url('profile.php'),
    ];

    // The program dashboard belongs only to program registrants (and staff).
    // A normal WooCommerce customer who only bought a book/merchandise must
    // never see or receive this link.
    $can_enter_program_dashboard = tfp_auth_user_is_program_registrant($user_id)
        || (function_exists('tfp_dashboard_user_is_staff') && tfp_dashboard_user_is_staff($user_id));

    if ($can_enter_program_dashboard && function_exists('tfp_dashboard_get_url')) {
        $dashboard_url = tfp_dashboard_get_url('tfp-dashboard-home');
        if (!empty($dashboard_url) && $dashboard_url !== '#') {
            $items[] = [
                'id'    => 'dashboard',
                'label' => __('Dashboard', 'tfp-authentication'),
                'url'   => $dashboard_url,
            ];
        }
    }

    $items = array_values(array_filter($items, function ($item) {
        return !empty($item['url']);
    }));

    $items = apply_filters('tfp_account_menu_items', $items, $user_id);

    $items[] = [
        'id'        => 'logout',
        'label'     => __('Logout', 'tfp-authentication'),
        'url'       => wp_logout_url(home_url()),
        'is_logout' => true,
    ];

    return $items;
}

/**
 * Resolve the display name for the account menu.
 */
function tfp_get_account_display_name($user)
{
    if (!empty($user->display_name)) {
        return $user->display_name;
    }
    if (!empty($user->first_name)) {
        return $user->first_name;
    }
    return $user->user_login;
}

/**
 * [tfp_account_menu]
 */
add_shortcode('tfp_account_menu', function ($atts) {
    if (!is_user_logged_in()) {
        return '';
    }

    $atts = shortcode_atts([
        'avatar_size' => 40,
    ], $atts, 'tfp_account_menu');

    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
        return '';
    }

    $display_name = tfp_get_account_display_name($user);
    $custom_avatar_id = get_user_meta($user->ID, 'tfp_custom_avatar_id', true);
    if ($custom_avatar_id && wp_attachment_is_image($custom_avatar_id)) {
        $avatar = wp_get_attachment_image($custom_avatar_id, [(int) $atts['avatar_size'], (int) $atts['avatar_size']], false, [
            'class' => 'tfp-account__avatar-img',
            'alt'   => $display_name,
        ]);
    } else {
        $avatar = get_avatar($user->ID, (int) $atts['avatar_size'], '', $display_name, [
            'class' => 'tfp-account__avatar-img',
        ]);
    }

    $items       = tfp_get_account_menu_items($user->ID);
    $instance_id = 'tfp-account-' . $user->ID . '-' . wp_unique_id();

    ob_start();
    ?>
    <div class="tfp-account" data-tfp-account>
        <button
            type="button"
            id="<?php echo esc_attr($instance_id); ?>-trigger"
            class="tfp-account__trigger"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="<?php echo esc_attr($instance_id); ?>-menu"
        >
            <span class="tfp-account__avatar"><?php echo $avatar; ?></span>
            <span class="tfp-account__name"><?php echo esc_html($display_name); ?></span>
            <span class="tfp-account__arrow" aria-hidden="true">
                <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </button>

        <ul
            id="<?php echo esc_attr($instance_id); ?>-menu"
            class="tfp-account__dropdown"
            role="menu"
            aria-hidden="true"
            aria-labelledby="<?php echo esc_attr($instance_id); ?>-trigger"
        >
            <?php foreach ($items as $item) : ?>
                <li class="tfp-account__item<?php echo !empty($item['is_logout']) ? ' tfp-account__item--logout' : ''; ?>" role="none">
                    <a
                        href="<?php echo esc_url($item['url']); ?>"
                        class="tfp-account__link"
                        role="menuitem"
                        tabindex="-1"
                        <?php if (!empty($item['id'])) : ?>data-tfp-account-item="<?php echo esc_attr($item['id']); ?>"<?php endif; ?>
                    ><?php echo esc_html($item['label']); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Register + enqueue the account menu's own CSS/JS.
 */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('wp_register_style')) return;

    wp_register_style(
        'tfp-account-menu',
        plugins_url('assets/css/account-menu.css', TFP_AUTH_PATH . 'tfp-authentication.php'),
        [],
        defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null
    );

    if (function_exists('wp_register_script')) {
        wp_register_script(
            'tfp-account-menu',
            plugins_url('assets/js/account-menu.js', TFP_AUTH_PATH . 'tfp-authentication.php'),
            [],
            defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null,
            true
        );
    }
});

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('wp_enqueue_style')) return;

    wp_enqueue_style('tfp-account-menu');

    if (function_exists('wp_enqueue_script')) {
        wp_enqueue_script('tfp-account-menu');
    }
});
