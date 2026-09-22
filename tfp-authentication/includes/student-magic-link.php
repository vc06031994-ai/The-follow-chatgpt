<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Student Magic Link Authentication
 *
 * Program registrants can authenticate without entering a password.
 * A single-use, short-lived token is stored as a hash in user meta.
 */

function tfp_student_magic_link_is_program_user($user_id) {
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return false;
    }

    if (function_exists('tfp_auth_user_is_program_registrant')) {
        return tfp_auth_user_is_program_registrant($user_id);
    }

    $program_id = (int) get_user_meta($user_id, 'tfp_program_choice', true);
    if ($program_id <= 0) {
        return false;
    }

    $program = get_post($program_id);

    return $program
        && 'sfwd-courses' === $program->post_type
        && 'publish' === $program->post_status;
}

function tfp_student_magic_link_create($user_id) {
    $token = wp_generate_password(64, false, false);
    $token_hash = hash_hmac('sha256', $token, wp_salt('auth'));
    $expires = time() + (15 * MINUTE_IN_SECONDS);

    update_user_meta($user_id, '_tfp_student_magic_token', $token_hash);
    update_user_meta($user_id, '_tfp_student_magic_token_expires', $expires);
    update_user_meta($user_id, '_tfp_student_magic_last_sent', time());

    return [
        'token'   => $token,
        'expires' => $expires,
    ];
}

function tfp_student_magic_link_get_login_url($user_id, $token) {
    $login_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/');

    return add_query_arg(
        [
            'tfp_student_magic_login' => '1',
            'uid' => (int) $user_id,
            'token' => rawurlencode($token),
        ],
        $login_url
    );
}

function tfp_student_magic_link_send($user_id) {
    $user = get_user_by('id', $user_id);

    if (!$user || empty($user->user_email)) {
        return false;
    }

    $magic = tfp_student_magic_link_create($user_id);
    $login_url = tfp_student_magic_link_get_login_url($user_id, $magic['token']);

    $first_name = get_user_meta($user_id, 'first_name', true);
    $display_name = $first_name ?: $user->display_name;

    $subject = __('Your The Follow Project Student Login Link', 'tfp-authentication');

    $message = sprintf(
        "Hi %s,\n\n"
        . "Use the secure link below to access your Student / Discipleship Dashboard:\n\n"
        . "%s\n\n"
        . "This link will expire in 15 minutes and can only be used once.\n\n"
        . "If you did not request this link, you can safely ignore this email.\n\n"
        . "The Follow Project",
        $display_name,
        $login_url
    );

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return wp_mail($user->user_email, $subject, $message, $headers);
}

function tfp_student_magic_link_request_ajax() {
    $email = isset($_POST['email'])
        ? sanitize_email(wp_unslash($_POST['email']))
        : '';

    if (!$email || !is_email($email)) {
        wp_send_json_error([
            'message' => __('Please enter a valid email address.', 'tfp-authentication'),
        ]);
    }

    $user = get_user_by('email', $email);

    // Keep the response generic so the login form does not expose whether
    // an email belongs to a student account.
    $generic_message = __(
        'If this email belongs to a Student account, a secure login link has been sent. Please check your inbox and spam folder.',
        'tfp-authentication'
    );

    if (!$user || !tfp_student_magic_link_is_program_user($user->ID)) {
        wp_send_json_success([
            'message' => $generic_message,
        ]);
    }

    $last_sent = (int) get_user_meta($user->ID, '_tfp_student_magic_last_sent', true);

    if ($last_sent && (time() - $last_sent) < 60) {
        wp_send_json_success([
            'message' => $generic_message,
        ]);
    }

    if (!tfp_student_magic_link_send($user->ID)) {
        wp_send_json_error([
            'message' => __('We could not send the login link right now. Please try again shortly.', 'tfp-authentication'),
        ]);
    }

    wp_send_json_success([
        'message' => $generic_message,
    ]);
}

add_action('wp_ajax_nopriv_tfp_student_magic_link', 'tfp_student_magic_link_request_ajax');
add_action('wp_ajax_tfp_student_magic_link', 'tfp_student_magic_link_request_ajax');

function tfp_student_magic_link_consume() {
    if (
        empty($_GET['tfp_student_magic_login'])
        || empty($_GET['uid'])
        || empty($_GET['token'])
    ) {
        return;
    }

    if (is_user_logged_in()) {
        return;
    }

    $user_id = absint($_GET['uid']);
    $token = sanitize_text_field(wp_unslash($_GET['token']));

    if (!$user_id || !$token) {
        return;
    }

    $user = get_user_by('id', $user_id);

    if (!$user || !tfp_student_magic_link_is_program_user($user_id)) {
        tfp_student_magic_link_redirect('invalid');
    }

    $stored_hash = (string) get_user_meta($user_id, '_tfp_student_magic_token', true);
    $expires = (int) get_user_meta($user_id, '_tfp_student_magic_token_expires', true);

    $token_hash = hash_hmac('sha256', $token, wp_salt('auth'));
    $valid = $stored_hash
        && $expires > time()
        && hash_equals($stored_hash, $token_hash);

    // Always remove the token after an attempted use so it cannot be replayed.
    delete_user_meta($user_id, '_tfp_student_magic_token');
    delete_user_meta($user_id, '_tfp_student_magic_token_expires');

    if (!$valid) {
        tfp_student_magic_link_redirect('invalid');
    }

    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());

    do_action('wp_login', $user->user_login, $user);

    $dashboard_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-home')
        : '';

    if (empty($dashboard_url) || '#' === $dashboard_url) {
        $dashboard_url = function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('myaccount')
            : home_url('/');
    }

    wp_safe_redirect($dashboard_url);
    exit;
}

function tfp_student_magic_link_redirect($status) {
    $redirect_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/');

    $redirect_url = add_query_arg(
        'tfp_student_magic',
        sanitize_key($status),
        $redirect_url
    );

    wp_safe_redirect($redirect_url);
    exit;
}

add_action('init', 'tfp_student_magic_link_consume', 20);
