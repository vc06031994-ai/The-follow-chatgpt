<?php
/**
 * Admin Refunds — live WooCommerce refund records.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_refunds_enqueue_assets() {
    if (!is_page() || !isset($_GET['view']) || sanitize_key(wp_unslash($_GET['view'])) !== 'refunds') { return; }
    $base = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(dirname(__FILE__)));
    $file = dirname(__DIR__, 2) . '/assets/css/admin-refunds.css';
    wp_enqueue_style('tfp-admin-refunds', $base . 'assets/css/admin-refunds.css', ['tfp-admin-dashboard'], file_exists($file) ? (string) filemtime($file) : '1.0.0');
}
add_action('wp_enqueue_scripts', 'tfp_admin_refunds_enqueue_assets', 25);

function tfp_admin_refunds_render() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    $orders = function_exists('wc_get_orders') ? wc_get_orders([
        'limit' => 100,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses())),
        'return' => 'objects',
    ]) : [];
    $refund_rows = [];
    $refund_total = 0.0;
    $search = sanitize_text_field(wp_unslash($_GET['refund_search'] ?? ''));
    $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
    $date_to = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));

    foreach ((array) $orders as $order) {
        if (!$order instanceof WC_Order) { continue; }
        $refunds = $order->get_refunds();
        foreach ($refunds as $refund) {
            $refund_date = $refund->get_date_created();
            $customer = $order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard');
            $email = $order->get_billing_email();
            $refund_id = $refund->get_id();
            $date_value = $refund_date ? $refund_date->date('Y-m-d') : '';
            if ($search && stripos($customer . ' ' . $email . ' ' . $order->get_order_number() . ' ' . $refund_id, $search) === false) { continue; }
            if ($date_from && $date_value && $date_value < $date_from) { continue; }
            if ($date_to && $date_value && $date_value > $date_to) { continue; }
            $amount = abs((float) $refund->get_amount());
            $refund_total += $amount;
            $refund_rows[] = [
                'id' => $refund_id,
                'order' => $order,
                'customer' => $customer,
                'email' => $email,
                'date' => $refund_date ? $refund_date->date_i18n(get_option('date_format')) : '—',
                'amount' => $amount,
                'reason' => $refund->get_reason(),
                'method' => $order->get_payment_method_title() ?: __('Manual / Offline', 'tfp-dashboard'),
            ];
        }
    }
    $selected_id = absint($_GET['refund_id'] ?? 0);
    $selected = null;
    foreach ($refund_rows as $row) {
        if ($row['id'] === $selected_id) { $selected = $row; break; }
    }
    $nav = tfp_admin_dashboard_nav_items();
    $base_url = add_query_arg('view', 'refunds', tfp_admin_dashboard_page_url());
    ?>
    <div class="tfp-admin-app tfp-refunds-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : $active = ($item['slug'] === 'payments'); ?>
                    <a class="tfp-admin-nav-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <?php $user = wp_get_current_user(); ?>
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url(get_avatar_url($user->ID, ['size' => 96])); ?>" alt="">
                    <span><strong><?php echo esc_html($user->display_name ?: __('Administrator', 'tfp-dashboard')); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><?php esc_html_e('Logout', 'tfp-dashboard'); ?></a>
            </div>
        </aside>
        <div class="tfp-admin-content">
            <div class="tfp-refunds-breadcrumb">ADMIN / Payment / Refunds</div>
            <main class="tfp-admin-main tfp-refunds-main">
                <div class="tfp-admin-topbar"><span>Admin</span><b>/</b> Payments <b>/</b><strong>Refunds</strong></div>
                <header class="tfp-refunds-header">
                    <div><h1>Refunds</h1><p>Review refunded transactions, customer details, and refund records</p></div>
                </header>
                <div class="tfp-refunds-tabs">
                    <a href="<?php echo esc_url(add_query_arg('view', 'refunds', tfp_admin_dashboard_page_url())); ?>" class="is-active">Refund History</a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>">WooCommerce Orders</a>
                </div>
                <form class="tfp-refunds-filters" method="get" action="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <input type="hidden" name="view" value="refunds">
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" aria-label="Start date">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" aria-label="End date">
                    <div class="tfp-refunds-search"><input type="search" name="refund_search" value="<?php echo esc_attr($search); ?>" placeholder="Search refund ID, order, customer or email"><button type="submit">Apply</button></div>
                </form>
                <section class="tfp-refunds-stats">
                    <article><span>Total Refunds</span><strong><?php echo esc_html(number_format_i18n(count($refund_rows))); ?></strong></article>
                    <article><span>Refund Amount</span><strong><?php echo wp_kses_post(wc_price($refund_total)); ?></strong></article>
                    <article><span>Orders Refunded</span><strong><?php echo esc_html(number_format_i18n(count(array_unique(array_map(static function ($row) { return $row['order']->get_id(); }, $refund_rows))))); ?></strong></article>
                </section>
                <section class="tfp-refunds-table-card">
                    <div class="tfp-refunds-toolbar"><strong>Refund History</strong><span><?php echo esc_html(sprintf(__('Showing %d refund records', 'tfp-dashboard'), count($refund_rows))); ?></span></div>
                    <div class="tfp-refunds-table-scroll">
                        <table class="tfp-refunds-table">
                            <thead><tr><th>Refund ID</th><th>Date</th><th>Order</th><th>Customer</th><th>Payment Method</th><th>Reason</th><th>Refund Amount</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($refund_rows as $row) : ?>
                                <tr>
                                    <td>#<?php echo esc_html($row['id']); ?></td>
                                    <td><?php echo esc_html($row['date']); ?></td>
                                    <td>#<?php echo esc_html($row['order']->get_order_number()); ?></td>
                                    <td><?php echo esc_html($row['customer']); ?><small><?php echo esc_html($row['email']); ?></small></td>
                                    <td><?php echo esc_html($row['method']); ?></td>
                                    <td><?php echo esc_html($row['reason'] ?: '—'); ?></td>
                                    <td class="tfp-refunds-amount"><?php echo wp_kses_post(wc_price($row['amount'], ['currency' => $row['order']->get_currency()])); ?></td>
                                    <td><a class="tfp-refunds-detail-btn" href="<?php echo esc_url(add_query_arg(['view' => 'refunds', 'refund_id' => $row['id']], tfp_admin_dashboard_page_url())); ?>">Details</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$refund_rows) : ?><tr><td colspan="8" class="tfp-refunds-empty">No refund records found for the selected filters.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php if ($selected) : ?>
                    <section class="tfp-refunds-detail">
                        <div><h2>Refund #<?php echo esc_html($selected['id']); ?></h2><span class="tfp-refunds-status">Refunded</span></div>
                        <div class="tfp-refunds-detail-grid">
                            <p><small>Customer</small><strong><?php echo esc_html($selected['customer']); ?></strong><span><?php echo esc_html($selected['email']); ?></span></p>
                            <p><small>Order</small><strong>#<?php echo esc_html($selected['order']->get_order_number()); ?></strong></p>
                            <p><small>Processed date</small><strong><?php echo esc_html($selected['date']); ?></strong></p>
                            <p><small>Payment method</small><strong><?php echo esc_html($selected['method']); ?></strong></p>
                            <p><small>Reason</small><strong><?php echo esc_html($selected['reason'] ?: '—'); ?></strong></p>
                            <p><small>Refund amount</small><strong><?php echo wp_kses_post(wc_price($selected['amount'], ['currency' => $selected['order']->get_currency()])); ?></strong></p>
                        </div>
                        <a class="tfp-refunds-detail-btn" href="<?php echo esc_url($selected['order']->get_edit_order_url()); ?>">Open WooCommerce Order</a>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php
}
