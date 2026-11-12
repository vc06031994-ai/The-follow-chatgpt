<?php
/**
 * User Activity audit feed for TFP Admin.
 * Records real WordPress/WooCommerce/LearnDash events; never seeds demo rows.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_user_activity_table() {
    global $wpdb;
    return $wpdb->prefix . 'tfp_user_activity';
}

function tfp_user_activity_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = tfp_user_activity_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        event_type VARCHAR(80) NOT NULL,
        event_label VARCHAR(190) NOT NULL,
        context TEXT NULL,
        source VARCHAR(40) NOT NULL DEFAULT 'System',
        object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY event_type (event_type),
        KEY created_at (created_at)
    ) {$charset};";
    dbDelta($sql);
    update_option('tfp_user_activity_db_version', '1.0.0', false);
}

add_action('plugins_loaded', function () {
    if (get_option('tfp_user_activity_db_version') !== '1.0.0') {
        tfp_user_activity_install();
    }
}, 20);

/** Store a single genuine event. Call this from project-specific actions as needed. */
function tfp_user_activity_log($user_id, $event_type, $event_label, $context = '', $source = 'System', $object_id = 0, $actor_id = 0) {
    global $wpdb;
    $user_id = absint($user_id);
    $actor_id = $actor_id ? absint($actor_id) : get_current_user_id();
    $allowed = ['login','logout','registration','profile','role','homework','quiz','test','payment','document','flag','course','cohort','account'];
    if (!$user_id || !in_array($event_type, $allowed, true)) { return false; }
    return (bool) $wpdb->insert(tfp_user_activity_table(), [
        'user_id' => $user_id,
        'actor_id' => $actor_id,
        'event_type' => sanitize_key($event_type),
        'event_label' => sanitize_text_field($event_label),
        'context' => sanitize_textarea_field($context),
        'source' => sanitize_text_field($source),
        'object_id' => absint($object_id),
        'created_at' => current_time('mysql', true),
    ], ['%d','%d','%s','%s','%s','%s','%d','%s']);
}

add_action('wp_login', function ($login, $user) {
    if ($user instanceof WP_User) { tfp_user_activity_log($user->ID, 'login', 'Logged in', 'Successful authentication', 'Student/Facilitator'); }
}, 10, 2);

add_action('wp_logout', function () {
    $id = get_current_user_id();
    if ($id) { tfp_user_activity_log($id, 'logout', 'Logged out', 'User session ended', 'User'); }
});

add_action('user_register', function ($user_id) {
    tfp_user_activity_log($user_id, 'registration', 'Account created', 'New WordPress user registration', 'System', $user_id);
}, 10, 1);

add_action('profile_update', function ($user_id, $old_user_data) {
    tfp_user_activity_log($user_id, 'profile', 'Profile updated', 'User profile information changed', 'User', $user_id);
}, 10, 2);

add_action('set_user_role', function ($user_id, $role, $old_roles) {
    tfp_user_activity_log($user_id, 'role', 'Role changed', 'Assigned role: ' . sanitize_text_field($role), 'Administrator', $user_id);
}, 10, 3);

add_action('deleted_user', function ($user_id) {
    tfp_user_activity_log($user_id, 'account', 'Account deleted', 'WordPress user account deleted', 'Administrator', $user_id);
}, 10, 1);

add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) {
    if (!$order instanceof WC_Order) { return; }
    $user_id = (int) $order->get_customer_id();
    if (!$user_id) { return; }
    $labels = ['failed' => 'Payment failed', 'completed' => 'Payment completed', 'processing' => 'Payment processing', 'refunded' => 'Payment refunded', 'cancelled' => 'Payment cancelled'];
    $label = $labels[$to] ?? ('Payment status: ' . $to);
    tfp_user_activity_log($user_id, 'payment', $label, 'Order #' . $order_id . ' · ' . $from . ' → ' . $to, 'WooCommerce', $order_id);
}, 10, 4);

add_action('learndash_course_completed', function ($data) {
    $user_id = absint($data['user']->ID ?? 0);
    if (!$user_id) { return; }
    $course_id = absint($data['course']->ID ?? 0);
    tfp_user_activity_log($user_id, 'course', 'Course completed', $course_id ? get_the_title($course_id) : 'LearnDash course', 'LearnDash', $course_id);
}, 10, 1);

/** Activity URL and route. */
function tfp_admin_user_activity_url() { return home_url('/user-activity/'); }

add_filter('pre_handle_404', function ($preempt, $query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'user-activity') {
        if ($query) { $query->is_404 = false; $query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'user-activity') { return; }
    status_header(200);
    nocache_headers();
    include TFP_DASH_PATH . 'templates/template-admin-user-activity.php';
    exit;
}, 1);

/** Query filtered, paginated activity records. */
function tfp_admin_user_activity_query() {
    global $wpdb;
    $table = tfp_user_activity_table();
    $per_page = 20;
    $page = max(1, absint($_GET['activity_page'] ?? 1));
    $role = sanitize_key(wp_unslash($_GET['activity_role'] ?? ''));
    $event = sanitize_key(wp_unslash($_GET['activity_event'] ?? ''));
    $date = sanitize_key(wp_unslash($_GET['activity_date'] ?? 'today'));
    $search = sanitize_text_field(wp_unslash($_GET['activity_search'] ?? ''));
    $where = ['1=1'];
    $args = [];

    if ($role !== '') {
        $where[] = 'u.ID IN (SELECT user_id FROM ' . $wpdb->usermeta . ' WHERE meta_key = %s AND meta_value = %s)';
        $args[] = $wpdb->get_blog_prefix() . 'capabilities';
        $args[] = '%' . $wpdb->esc_like('"' . $role . '"') . '%';
    }
    if ($event !== '') { $where[] = 'a.event_type = %s'; $args[] = $event; }
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    if ($date === 'today') { $start = $now->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    elseif ($date === '7days') { $start = $now->modify('-7 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    elseif ($date === '30days') { $start = $now->modify('-30 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    else { $start = ''; }
    if ($start) { $where[] = 'a.created_at >= %s'; $args[] = $start; }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s OR a.event_label LIKE %s OR a.context LIKE %s)';
        array_push($args, $like, $like, $like, $like);
    }
    $join = " FROM {$table} a INNER JOIN {$wpdb->users} u ON u.ID = a.user_id ";
    $condition = ' WHERE ' . implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*)" . $join . $condition;
    $total = (int) $wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);
    $offset = ($page - 1) * $per_page;
    $rows_sql = "SELECT a.*, u.display_name, u.user_email FROM {$table} a INNER JOIN {$wpdb->users} u ON u.ID = a.user_id" . $condition . " ORDER BY a.created_at DESC, a.id DESC LIMIT %d OFFSET %d";
    $row_args = array_merge($args, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, $row_args));
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per_page)), 'per_page' => $per_page];
}

/** Render the activity page. */
function tfp_admin_user_activity_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    $data = tfp_admin_user_activity_query();
    $current = wp_get_current_user();
    $nav = tfp_admin_dashboard_nav_items();
    foreach ($nav as &$item) { $item['active'] = ($item['slug'] === 'users'); }
    unset($item);
    $base = remove_query_arg(['activity_page']);
    ?>
    <div class="tfp-admin-app tfp-admin-activity-app">
      <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation','tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt=""></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation','tfp-dashboard'); ?>">
          <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['slug'] === 'users' ? tfp_admin_users_home_url() : $item['url']); ?>"><span class="tfp-admin-nav-icon"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><?php echo get_avatar($current->ID, 43); ?><span><strong><?php echo esc_html($current->display_name); ?></strong><small><?php esc_html_e('Administrator','tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout','tfp-dashboard'); ?></span></a></div>
      </aside>
      <div class="tfp-admin-main">
        <div class="tfp-admin-mobilebar"><button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu','tfp-dashboard'); ?>">☰</button><span><?php esc_html_e('Admin Console','tfp-dashboard'); ?></span></div>
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">Admin</a><b>/</b><a href="<?php echo esc_url(tfp_admin_users_home_url()); ?>">Users</a><b>/</b><strong>Activity</strong><form class="tfp-admin-activity-top-search" method="get"><input type="search" name="activity_search" value="<?php echo esc_attr($search = sanitize_text_field(wp_unslash($_GET['activity_search'] ?? ''))); ?>" placeholder="Search" aria-label="Search activity"><button type="submit" aria-label="Search"><span class="dashicons dashicons-search"></span></button></form></div>
        <header class="tfp-admin-activity-header"><h1><?php esc_html_e('User Activity','tfp-dashboard'); ?></h1><p><?php esc_html_e('A chronological activity feed scoped to users only.','tfp-dashboard'); ?></p></header>
        <main class="tfp-admin-activity-content">
          <form class="tfp-admin-activity-filters" method="get">
            <label><span class="screen-reader-text">Role</span><select name="activity_role"><option value="">All Roles</option><?php foreach (wp_roles()->roles as $slug => $role_data) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($_GET['activity_role'] ?? '', $slug); ?>><?php echo esc_html(translate_user_role($role_data['name'])); ?></option><?php endforeach; ?></select></label>
            <label><span class="screen-reader-text">Event type</span><select name="activity_event"><option value="">All Event Types</option><?php foreach (['login'=>'Logged in','logout'=>'Logged out','registration'=>'Account created','profile'=>'Profile updated','role'=>'Role changed','homework'=>'Homework submitted','quiz'=>'Quiz passed','test'=>'Test completed','payment'=>'Payment','document'=>'Document','flag'=>'Flag','course'=>'Course completed','cohort'=>'Cohort','account'=>'Account'] as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($_GET['activity_event'] ?? '', $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
            <label><span class="screen-reader-text">Date</span><select name="activity_date"><option value="today" <?php selected($_GET['activity_date'] ?? 'today','today'); ?>>Date: Today</option><option value="7days" <?php selected($_GET['activity_date'] ?? '','7days'); ?>>Last 7 Days</option><option value="30days" <?php selected($_GET['activity_date'] ?? '','30days'); ?>>Last 30 Days</option><option value="all" <?php selected($_GET['activity_date'] ?? '','all'); ?>>All Time</option></select></label>
            <label class="tfp-admin-activity-inline-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" name="activity_search" value="<?php echo esc_attr($search); ?>" placeholder="Search Name or Event..."></label>
            <button type="submit" class="tfp-admin-activity-filter-submit">Apply</button>
          </form>
          <section class="tfp-admin-activity-table-wrap">
            <div class="tfp-admin-activity-table-scroll"><table class="tfp-admin-activity-table"><thead><tr><th>Time</th><th>User</th><th>Event</th><th>Context</th><th>Source</th><th>Action</th></tr></thead><tbody>
            <?php if ($data['rows']) : foreach ($data['rows'] as $row) : $user_obj = get_userdata($row->user_id); $roles = $user_obj ? array_map('translate_user_role', (array) $user_obj->roles) : []; ?>
              <tr><td><time datetime="<?php echo esc_attr(mysql2date('c', get_date_from_gmt($row->created_at))); ?>"><?php echo esc_html(wp_date('M j, g:i A T', strtotime(get_date_from_gmt($row->created_at)))); ?></time></td><td><strong><?php echo esc_html($row->display_name); ?></strong></td><td><?php echo esc_html($row->event_label); ?></td><td><?php echo esc_html($row->context ?: '—'); ?></td><td><?php echo esc_html($row->source); ?></td><td><a class="tfp-admin-activity-view" href="<?php echo esc_url(add_query_arg(['user_id' => $row->user_id], tfp_admin_users_page_url())); ?>">View</a></td></tr>
            <?php endforeach; else : ?><tr><td colspan="6" class="tfp-admin-activity-empty"><?php esc_html_e('No recorded user activity matches these filters. New events will appear here as users interact with the site.','tfp-dashboard'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
            <footer class="tfp-admin-activity-pagination"><span><?php echo esc_html(sprintf(__('%1$s–%2$s of %3$s events','tfp-dashboard'), $data['total'] ? (($data['page']-1)*$data['per_page']+1) : 0, min($data['page']*$data['per_page'],$data['total']), number_format_i18n($data['total']))); ?></span><nav aria-label="Activity pages"><?php if ($data['page'] > 1) : ?><a href="<?php echo esc_url(add_query_arg('activity_page',$data['page']-1,$base)); ?>">‹ <span>Previous</span></a><?php else : ?><span class="is-disabled">‹ Previous</span><?php endif; ?><?php if ($data['page'] < $data['pages']) : ?><a class="next" href="<?php echo esc_url(add_query_arg('activity_page',$data['page']+1,$base)); ?>">Next <span>›</span></a><?php else : ?><span class="next is-disabled">Next ›</span><?php endif; ?></nav></footer>
          </section>
        </main>
      </div>
    </div>
    <?php
}
