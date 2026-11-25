<?php
/**
 * Admin Transaction Ledger — live WooCommerce order and refund ledger.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_transaction_ledger_enqueue_assets() {
    if (!is_page() || (sanitize_key(wp_unslash($_GET['section'] ?? '')) !== 'transactions' && sanitize_key(wp_unslash($_GET['view'] ?? '')) !== 'transaction-ledger')) {
        return;
    }
    $base = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(dirname(__FILE__)));
    $css = dirname(__DIR__, 2) . '/assets/css/admin-transaction-ledger.css';
    $js  = dirname(__DIR__, 2) . '/assets/js/admin-transaction-ledger.js';
    wp_enqueue_style('tfp-admin-transaction-ledger', $base . 'assets/css/admin-transaction-ledger.css', ['tfp-admin-dashboard'], file_exists($css) ? (string) filemtime($css) : '1.0.0');
    wp_enqueue_script('tfp-admin-transaction-ledger', $base . 'assets/js/admin-transaction-ledger.js', [], file_exists($js) ? (string) filemtime($js) : '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'tfp_admin_transaction_ledger_enqueue_assets', 20);

function tfp_admin_transaction_ledger_money($amount, $currency = '') {
    $amount = (float) $amount;
    $currency = $currency ?: (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
    $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol($currency) : '

function tfp_admin_transaction_ledger_order_rows($args) {
    if (!function_exists('wc_get_orders')) { return ['orders' => [], 'total' => 0, 'pages' => 0]; }
    $query = [
        'limit' => 10,
        'page' => max(1, absint($args['page'] ?? 1)),
        'paginate' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
    ];
    if (!empty($args['status'])) {
        $query['status'] = [$args['status']];
    } else {
        $query['status'] = array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses()));
    }
    if (!empty($args['search'])) { $query['search'] = '*' . wc_clean($args['search']) . '*'; }
    if (!empty($args['after']) || !empty($args['before'])) {
        $query['date_created'] = trim(($args['after'] ?: '') . '...' . ($args['before'] ?: ''), '.');
    }
    if (!empty($args['product'])) {
        $query['product'] = absint($args['product']);
    }
    $result = wc_get_orders($query);
    return [
        'orders' => is_object($result) && isset($result->orders) ? $result->orders : [],
        'total' => is_object($result) && isset($result->total) ? (int) $result->total : 0,
        'pages' => is_object($result) && isset($result->max_num_pages) ? (int) $result->max_num_pages : 1,
    ];
}

function tfp_admin_transaction_ledger_totals() {
    $totals = ['gross' => 0.0, 'refunds' => 0.0, 'net' => 0.0, 'month' => 0.0];
    if (!function_exists('wc_get_orders')) { return $totals; }
    $page = 1;
    do {
        $batch = wc_get_orders([
            'status' => ['processing', 'completed'],
            'date_created' => '>=' . (time() - 365 * DAY_IN_SECONDS),
            'limit' => 100,
            'page' => $page,
            'paginate' => true,
            'return' => 'objects',
        ]);
        $orders = is_object($batch) && isset($batch->orders) ? $batch->orders : [];
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) { continue; }
            $created = $order->get_date_created();
            $amount = (float) $order->get_total();
            $refund = (float) $order->get_total_refunded();
            $totals['gross'] += $amount;
            $totals['refunds'] += $refund;
            $totals['net'] += ($amount - $refund);
            if ($created && $created->date('Y-m') === current_time('Y-m')) {
                $totals['month'] += ($amount - $refund);
            }
        }
        $max = is_object($batch) && isset($batch->max_num_pages) ? (int) $batch->max_num_pages : 0;
        $page++;
    } while ($page <= $max);
    return $totals;
}

function tfp_admin_transaction_ledger_handle_actions() {
    if (empty($_POST['tfp_ledger_action']) || !current_user_can('manage_options')) { return; }
    check_admin_referer('tfp_transaction_ledger_action', 'tfp_ledger_nonce');
    $order_id = absint($_POST['order_id'] ?? 0);
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    if (!$order) { return; }

    if (sanitize_key(wp_unslash($_POST['tfp_ledger_action'])) === 'refund' && function_exists('wc_create_refund')) {
        $amount = isset($_POST['refund_amount']) && $_POST['refund_amount'] !== '' ? wc_format_decimal(wp_unslash($_POST['refund_amount'])) : (float) $order->get_remaining_refund_amount();
        $reason = sanitize_text_field(wp_unslash($_POST['refund_reason'] ?? 'Admin refund'));
        $refund = wc_create_refund([
            'order_id' => $order_id,
            'amount' => $amount,
            'reason' => $reason,
            'refund_payment' => true,
            'restock_items' => false,
        ]);
        $notice = is_wp_error($refund) ? 'refund_error' : 'refund_success';
        wp_safe_redirect(add_query_arg(['section' => 'transactions', 'order' => $order_id, 'ledger_notice' => $notice], tfp_admin_payments_url()));
        exit;
    }
    if (sanitize_key(wp_unslash($_POST['tfp_ledger_action'])) === 'email') {
        $email = $order->get_billing_email();
        if ($email) {
            $subject = sprintf(__('Payment receipt for order #%s', 'tfp-dashboard'), $order->get_order_number());
            $message = sprintf(__('Hello %1$s, your payment of %2$s for order #%3$s has been recorded.', 'tfp-dashboard'), $order->get_formatted_billing_full_name(), wp_strip_all_tags(wc_price($order->get_total(), ['currency' => $order->get_currency()])), $order->get_order_number());
            wp_mail($email, $subject, $message);
        }
        wp_safe_redirect(add_query_arg(['section' => 'transactions', 'order' => $order_id, 'ledger_notice' => 'email_sent'], tfp_admin_payments_url()));
        exit;
    }
}

function tfp_admin_transaction_ledger_render($embedded = false) {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    if (!function_exists('wc_get_orders') || !function_exists('wc_get_order')) {
        echo '<div class="tfp-ledger-inner"><p>' . esc_html__('WooCommerce must be active to display payment transactions.', 'tfp-dashboard') . '</p></div>';
        return;
    }
    tfp_admin_transaction_ledger_handle_actions();

    $filters = [
        'status' => sanitize_key(wp_unslash($_GET['status'] ?? '')),
        'product' => absint($_GET['product'] ?? 0),
        'after' => sanitize_text_field(wp_unslash($_GET['after'] ?? '')),
        'before' => sanitize_text_field(wp_unslash($_GET['before'] ?? '')),
        'search' => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
        'page' => max(1, absint($_GET['ledger_page'] ?? 1)),
    ];
    $result = tfp_admin_transaction_ledger_order_rows($filters);
    $orders = $result['orders'];
    $selected_id = absint($_GET['order'] ?? 0);
    $selected = $selected_id ? wc_get_order($selected_id) : (!empty($orders) ? $orders[0] : false);
    $totals = tfp_admin_transaction_ledger_totals();
    $refund_30 = 0.0;
    if (function_exists('wc_get_orders')) {
        $recent = wc_get_orders(['status' => array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses())), 'limit' => 100, 'date_created' => '>' . (time() - 30 * DAY_IN_SECONDS), 'return' => 'objects']);
        foreach ((array) $recent as $recent_order) {
            if ($recent_order instanceof WC_Order) { $refund_30 += (float) $recent_order->get_total_refunded(); }
        }
    }
    $nav = tfp_admin_dashboard_nav_items();
    $base_url = tfp_admin_payments_url(['section' => 'transactions']);
    $notice = sanitize_key(wp_unslash($_GET['ledger_notice'] ?? ''));
    ?>
    <?php if (!$embedded) : ?>
    <div class="tfp-admin-app tfp-ledger-app">
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
        <main class="tfp-admin-main tfp-ledger-main">
            <div class="tfp-ledger-breadcrumb"><?php esc_html_e('ADMIN / Payment / Transaction Ledger', 'tfp-dashboard'); ?></div>
    <?php endif; ?>
            <div class="tfp-ledger-inner">
                <?php if (!$embedded) : ?>
                <div class="tfp-admin-topbar"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><?php esc_html_e('Payments', 'tfp-dashboard'); ?><b>/</b><strong><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></strong></div>

                <?php endif; ?>                <?php if (!$embedded) : ?>
                <header class="tfp-ledger-header">
                    <div><h1><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('All payments, refunds, statuses, and reconciliation controls', 'tfp-dashboard'); ?></p></div>
                </header>
                <?php endif; ?>
                <?php if ($notice === 'refund_success') : ?><div class="tfp-ledger-notice is-success"><?php esc_html_e('Refund request was sent to the payment gateway.', 'tfp-dashboard'); ?></div>
                <?php elseif ($notice === 'refund_error') : ?><div class="tfp-ledger-notice is-error"><?php esc_html_e('The refund could not be processed. Check the order and gateway response.', 'tfp-dashboard'); ?></div>
                <?php elseif ($notice === 'email_sent') : ?><div class="tfp-ledger-notice is-success"><?php esc_html_e('Customer receipt email sent.', 'tfp-dashboard'); ?></div><?php endif; ?>
                <?php if (!$embedded) : ?>
                <div class="tfp-ledger-tabs" role="tablist"><a class="is-active" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('WooCommerce Orders', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('Refund History', 'tfp-dashboard'); ?></a></div>

                <?php endif; ?>                <form class="tfp-ledger-filters" method="get" action="<?php echo esc_url($base_url); ?>">
                    <input type="hidden" name="section" value="transactions">
                    <select name="status" aria-label="<?php esc_attr_e('Filter by status', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
                        <?php foreach (wc_get_order_statuses() as $status_key => $status_label) : ?><option value="<?php echo esc_attr(str_replace('wc-', '', $status_key)); ?>" <?php selected($filters['status'], str_replace('wc-', '', $status_key)); ?>><?php echo esc_html($status_label); ?></option><?php endforeach; ?>
                    </select>
                    <select name="product" aria-label="<?php esc_attr_e('Filter by product', 'tfp-dashboard'); ?>"><option value="0"><?php esc_html_e('All Products', 'tfp-dashboard'); ?></option>
                        <?php foreach (wc_get_products(['limit' => 100, 'status' => 'publish']) as $product) : ?><option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($filters['product'], $product->get_id()); ?>><?php echo esc_html($product->get_name()); ?></option><?php endforeach; ?>
                    </select>
                    <input type="date" name="after" value="<?php echo esc_attr($filters['after']); ?>" aria-label="<?php esc_attr_e('Start date', 'tfp-dashboard'); ?>">
                    <input type="date" name="before" value="<?php echo esc_attr($filters['before']); ?>" aria-label="<?php esc_attr_e('End date', 'tfp-dashboard'); ?>">
                    <div class="tfp-ledger-search"><input type="search" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search ID, email, user name', 'tfp-dashboard'); ?>"><button type="submit"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button></div>
                </form>
                <section class="tfp-ledger-stats" aria-label="<?php esc_attr_e('Revenue summary', 'tfp-dashboard'); ?>">
                    <article><span><?php esc_html_e('Gross Revenue (12mo)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['gross'])); ?></strong></article>
                    <article><span><?php esc_html_e('Net Revenue (12mo)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['net'])); ?></strong></article>
                    <article><span><?php esc_html_e('Monthly Revenue', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['month'])); ?></strong></article>
                    <article class="is-refund"><span><?php esc_html_e('Refunds (30d)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($refund_30)); ?></strong></article>
                </section>
                <section class="tfp-ledger-table-card">
                    <div class="tfp-ledger-table-toolbar"><label><input type="checkbox" data-ledger-select-all> <?php esc_html_e('Select all on page', 'tfp-dashboard'); ?></label><span><?php echo esc_html(sprintf(__('Showing %1$d–%2$d of %3$d', 'tfp-dashboard'), $result['total'] ? (($filters['page'] - 1) * 10 + 1) : 0, min($filters['page'] * 10, $result['total']), $result['total'])); ?></span><a href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                    <div class="tfp-ledger-table-scroll"><table class="tfp-ledger-table"><thead><tr><th></th><th><?php esc_html_e('Transaction ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Item / Product', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gross', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Fees', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Net', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                    <?php foreach ($orders as $order) :
                        if (!$order instanceof WC_Order) { continue; }
                        $items = $order->get_items();
                        $product_names = [];
                        foreach ($items as $item) { $product_names[] = $item->get_name(); }
                        $is_refunded = $order->get_total_refunded() > 0;
                        $status_label = $order->get_status() === 'refunded' ? __('Refunded', 'tfp-dashboard') : wc_get_order_status_name($order->get_status());
                        $payment_method = $order->get_payment_method_title() ?: __('Manual / Offline', 'tfp-dashboard');
                        $selected_row = $selected && $selected->get_id() === $order->get_id();
                        ?>
                        <tr class="<?php echo $selected_row ? 'is-selected' : ''; ?>">
                            <td><input type="checkbox" class="tfp-ledger-row-check" value="<?php echo esc_attr($order->get_id()); ?>" aria-label="<?php esc_attr_e('Select transaction', 'tfp-dashboard'); ?>"></td>
                            <td><a class="tfp-ledger-id" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id()], tfp_admin_payments_url())); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                            <td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d') : '—'); ?></td>
                            <td><?php echo esc_html($order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard')); ?><small><?php echo esc_html($order->get_billing_email()); ?></small></td>
                            <td><?php echo esc_html(implode(', ', array_slice($product_names, 0, 2))); ?></td>
                            <td><?php echo esc_html($payment_method); ?></td>
                            <td><span class="tfp-ledger-status <?php echo esc_attr($is_refunded ? 'is-refunded' : 'is-complete'); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td><?php echo wp_kses_post(wc_price($order->get_total(), ['currency' => $order->get_currency()])); ?></td>
                            <td>—</td>
                            <td><?php echo wp_kses_post(wc_price($order->get_total() - $order->get_total_refunded(), ['currency' => $order->get_currency()])); ?></td>
                            <td><a class="tfp-ledger-button" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id()], tfp_admin_payments_url())); ?>"><?php esc_html_e('Details', 'tfp-dashboard'); ?></a><a class="tfp-ledger-button is-primary" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id(), 'refund' => 1], tfp_admin_payments_url())); ?>"><?php esc_html_e('Refund', 'tfp-dashboard'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)) : ?><tr><td colspan="11" class="tfp-ledger-empty"><?php esc_html_e('No transactions match these filters.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                    </tbody></table></div>
                    <div class="tfp-ledger-pagination"><span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'tfp-dashboard'), $filters['page'], max(1, $result['pages']))); ?></span><div>
                        <?php if ($filters['page'] > 1) : ?><a href="<?php echo esc_url(add_query_arg(array_merge($filters, ['section' => 'transactions', 'ledger_page' => $filters['page'] - 1]), tfp_admin_payments_url())); ?>">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></a><?php endif; ?>
                        <?php if ($filters['page'] < $result['pages']) : ?><a class="is-primary" href="<?php echo esc_url(add_query_arg(array_merge($filters, ['section' => 'transactions', 'ledger_page' => $filters['page'] + 1]), tfp_admin_payments_url())); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a><?php endif; ?>
                    </div></div>
                </section>
                <?php if ($selected) : ?>
                    <section class="tfp-ledger-detail">
                        <div class="tfp-ledger-detail-head"><div><h2>#<?php echo esc_html($selected->get_order_number()); ?> <span class="tfp-ledger-status is-complete"><?php echo esc_html(wc_get_order_status_name($selected->get_status())); ?></span></h2><small><?php echo esc_html($selected->get_date_created() ? $selected->get_date_created()->date_i18n('Y-m-d · H:i') : '—'); ?></small></div><strong><?php echo wp_kses_post(wc_price($selected->get_total() - $selected->get_total_refunded(), ['currency' => $selected->get_currency()])); ?><small><?php esc_html_e('Net amount', 'tfp-dashboard'); ?></small></strong></div>
                        <div class="tfp-ledger-detail-grid"><div><h3><?php esc_html_e('General Information', 'tfp-dashboard'); ?></h3><dl>
                            <div><dt><?php esc_html_e('Customer', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_formatted_billing_full_name()); ?><small><?php echo esc_html($selected->get_billing_email()); ?></small></dd></div>
                            <div><dt><?php esc_html_e('Date Processed', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_date_paid() ? $selected->get_date_paid()->date_i18n('M j, Y · H:i T') : '—'); ?></dd></div>
                            <div><dt><?php esc_html_e('Processor ref', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_transaction_id() ?: '—'); ?><small><?php echo esc_html($selected->get_payment_method_title()); ?></small></dd></div>
                            <div><dt><?php esc_html_e('Reconciliation', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_status() === 'completed' || $selected->get_status() === 'processing' ? __('Payment recorded', 'tfp-dashboard') : __('Review required', 'tfp-dashboard')); ?><small><?php echo esc_html($selected->get_status()); ?></small></dd></div>
                        </dl><h3><?php esc_html_e('Timeline', 'tfp-dashboard'); ?></h3><p class="tfp-ledger-timeline"><?php echo esc_html($selected->get_status() === 'refunded' ? __('Refund recorded', 'tfp-dashboard') : __('Payment recorded', 'tfp-dashboard')); ?><small><?php echo esc_html($selected->get_date_modified() ? $selected->get_date_modified()->date_i18n('M j, Y · H:i T') : '—'); ?></small></p></div>
                        <aside class="tfp-ledger-admin-actions"><h3><?php esc_html_e('Admin Actions', 'tfp-dashboard'); ?></h3>
                            <button type="button" class="tfp-ledger-action" data-ledger-print><?php esc_html_e('Print Receipt', 'tfp-dashboard'); ?></button>
                            <form method="post"><?php wp_nonce_field('tfp_transaction_ledger_action', 'tfp_ledger_nonce'); ?><input type="hidden" name="order_id" value="<?php echo esc_attr($selected->get_id()); ?>"><button class="tfp-ledger-action" name="tfp_ledger_action" value="email"><?php esc_html_e('Email Customer', 'tfp-dashboard'); ?></button></form>
                            <button type="button" class="tfp-ledger-action is-primary" data-ledger-refund-open><?php esc_html_e('Issue Refund', 'tfp-dashboard'); ?></button>
                            <form method="post" class="tfp-ledger-refund-form" data-ledger-refund-form hidden><?php wp_nonce_field('tfp_transaction_ledger_action', 'tfp_ledger_nonce'); ?><input type="hidden" name="order_id" value="<?php echo esc_attr($selected->get_id()); ?>"><input type="hidden" name="tfp_ledger_action" value="refund"><label><?php esc_html_e('Refund amount', 'tfp-dashboard'); ?><input type="number" min="0.01" max="<?php echo esc_attr($selected->get_remaining_refund_amount()); ?>" step="0.01" name="refund_amount" value="<?php echo esc_attr($selected->get_remaining_refund_amount()); ?>" required></label><label><?php esc_html_e('Reason', 'tfp-dashboard'); ?><input type="text" name="refund_reason" value="<?php esc_attr_e('Admin refund', 'tfp-dashboard'); ?>"></label><button class="tfp-ledger-action is-primary" type="submit"><?php esc_html_e('Confirm Refund', 'tfp-dashboard'); ?></button></form>
                            <a class="tfp-ledger-action" href="<?php echo esc_url($selected->get_edit_order_url()); ?>"><?php esc_html_e('Open WooCommerce Order', 'tfp-dashboard'); ?></a>
                        </aside></div>
                    </section>
                <?php else : ?><section class="tfp-ledger-detail"><p class="tfp-ledger-empty"><?php esc_html_e('Select a transaction to view its details.', 'tfp-dashboard'); ?></p></section><?php endif; ?>
            </div>
            <?php if (!$embedded) : ?>
        </main>
    </div>
            <?php endif; ?>
    <?php
}
;
    $formatted = number_format_i18n(abs($amount), 2);
    $value = ($amount < 0 ? '-' : '') . $symbol . $formatted;
    return '<span class="tfp-ledger-money">' . esc_html($value) . '</span>';
}

function tfp_admin_transaction_ledger_order_rows($args) {
    if (!function_exists('wc_get_orders')) { return ['orders' => [], 'total' => 0, 'pages' => 0]; }
    $query = [
        'limit' => 10,
        'page' => max(1, absint($args['page'] ?? 1)),
        'paginate' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
    ];
    if (!empty($args['status'])) {
        $query['status'] = [$args['status']];
    } else {
        $query['status'] = array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses()));
    }
    if (!empty($args['search'])) { $query['search'] = '*' . wc_clean($args['search']) . '*'; }
    if (!empty($args['after']) || !empty($args['before'])) {
        $query['date_created'] = trim(($args['after'] ?: '') . '...' . ($args['before'] ?: ''), '.');
    }
    if (!empty($args['product'])) {
        $query['product'] = absint($args['product']);
    }
    $result = wc_get_orders($query);
    return [
        'orders' => is_object($result) && isset($result->orders) ? $result->orders : [],
        'total' => is_object($result) && isset($result->total) ? (int) $result->total : 0,
        'pages' => is_object($result) && isset($result->max_num_pages) ? (int) $result->max_num_pages : 1,
    ];
}

function tfp_admin_transaction_ledger_totals() {
    $totals = ['gross' => 0.0, 'refunds' => 0.0, 'net' => 0.0, 'month' => 0.0];
    if (!function_exists('wc_get_orders')) { return $totals; }
    $page = 1;
    do {
        $batch = wc_get_orders([
            'status' => ['processing', 'completed'],
            'date_created' => '>=' . (time() - 365 * DAY_IN_SECONDS),
            'limit' => 100,
            'page' => $page,
            'paginate' => true,
            'return' => 'objects',
        ]);
        $orders = is_object($batch) && isset($batch->orders) ? $batch->orders : [];
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) { continue; }
            $created = $order->get_date_created();
            $amount = (float) $order->get_total();
            $refund = (float) $order->get_total_refunded();
            $totals['gross'] += $amount;
            $totals['refunds'] += $refund;
            $totals['net'] += ($amount - $refund);
            if ($created && $created->date('Y-m') === current_time('Y-m')) {
                $totals['month'] += ($amount - $refund);
            }
        }
        $max = is_object($batch) && isset($batch->max_num_pages) ? (int) $batch->max_num_pages : 0;
        $page++;
    } while ($page <= $max);
    return $totals;
}

function tfp_admin_transaction_ledger_handle_actions() {
    if (empty($_POST['tfp_ledger_action']) || !current_user_can('manage_options')) { return; }
    check_admin_referer('tfp_transaction_ledger_action', 'tfp_ledger_nonce');
    $order_id = absint($_POST['order_id'] ?? 0);
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
    if (!$order) { return; }

    if (sanitize_key(wp_unslash($_POST['tfp_ledger_action'])) === 'refund' && function_exists('wc_create_refund')) {
        $amount = isset($_POST['refund_amount']) && $_POST['refund_amount'] !== '' ? wc_format_decimal(wp_unslash($_POST['refund_amount'])) : (float) $order->get_remaining_refund_amount();
        $reason = sanitize_text_field(wp_unslash($_POST['refund_reason'] ?? 'Admin refund'));
        $refund = wc_create_refund([
            'order_id' => $order_id,
            'amount' => $amount,
            'reason' => $reason,
            'refund_payment' => true,
            'restock_items' => false,
        ]);
        $notice = is_wp_error($refund) ? 'refund_error' : 'refund_success';
        wp_safe_redirect(add_query_arg(['section' => 'transactions', 'order' => $order_id, 'ledger_notice' => $notice], tfp_admin_payments_url()));
        exit;
    }
    if (sanitize_key(wp_unslash($_POST['tfp_ledger_action'])) === 'email') {
        $email = $order->get_billing_email();
        if ($email) {
            $subject = sprintf(__('Payment receipt for order #%s', 'tfp-dashboard'), $order->get_order_number());
            $message = sprintf(__('Hello %1$s, your payment of %2$s for order #%3$s has been recorded.', 'tfp-dashboard'), $order->get_formatted_billing_full_name(), wp_strip_all_tags(wc_price($order->get_total(), ['currency' => $order->get_currency()])), $order->get_order_number());
            wp_mail($email, $subject, $message);
        }
        wp_safe_redirect(add_query_arg(['section' => 'transactions', 'order' => $order_id, 'ledger_notice' => 'email_sent'], tfp_admin_payments_url()));
        exit;
    }
}

function tfp_admin_transaction_ledger_render($embedded = false) {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    if (!function_exists('wc_get_orders') || !function_exists('wc_get_order')) {
        echo '<div class="tfp-ledger-inner"><p>' . esc_html__('WooCommerce must be active to display payment transactions.', 'tfp-dashboard') . '</p></div>';
        return;
    }
    tfp_admin_transaction_ledger_handle_actions();

    $filters = [
        'status' => sanitize_key(wp_unslash($_GET['status'] ?? '')),
        'product' => absint($_GET['product'] ?? 0),
        'after' => sanitize_text_field(wp_unslash($_GET['after'] ?? '')),
        'before' => sanitize_text_field(wp_unslash($_GET['before'] ?? '')),
        'search' => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
        'page' => max(1, absint($_GET['ledger_page'] ?? 1)),
    ];
    $result = tfp_admin_transaction_ledger_order_rows($filters);
    $orders = $result['orders'];
    $selected_id = absint($_GET['order'] ?? 0);
    $selected = $selected_id ? wc_get_order($selected_id) : (!empty($orders) ? $orders[0] : false);
    $totals = tfp_admin_transaction_ledger_totals();
    $refund_30 = 0.0;
    if (function_exists('wc_get_orders')) {
        $recent = wc_get_orders(['status' => array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses())), 'limit' => 100, 'date_created' => '>' . (time() - 30 * DAY_IN_SECONDS), 'return' => 'objects']);
        foreach ((array) $recent as $recent_order) {
            if ($recent_order instanceof WC_Order) { $refund_30 += (float) $recent_order->get_total_refunded(); }
        }
    }
    $nav = tfp_admin_dashboard_nav_items();
    $base_url = tfp_admin_payments_url(['section' => 'transactions']);
    $notice = sanitize_key(wp_unslash($_GET['ledger_notice'] ?? ''));
    ?>
    <?php if (!$embedded) : ?>
    <div class="tfp-admin-app tfp-ledger-app">
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
        <main class="tfp-admin-main tfp-ledger-main">
            <div class="tfp-ledger-breadcrumb"><?php esc_html_e('ADMIN / Payment / Transaction Ledger', 'tfp-dashboard'); ?></div>
    <?php endif; ?>
            <div class="tfp-ledger-inner">
                <?php if (!$embedded) : ?>
                <div class="tfp-admin-topbar"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><?php esc_html_e('Payments', 'tfp-dashboard'); ?><b>/</b><strong><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></strong></div>

                <?php endif; ?>                <?php if (!$embedded) : ?>
                <header class="tfp-ledger-header">
                    <div><h1><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('All payments, refunds, statuses, and reconciliation controls', 'tfp-dashboard'); ?></p></div>
                </header>
                <?php endif; ?>
                <?php if ($notice === 'refund_success') : ?><div class="tfp-ledger-notice is-success"><?php esc_html_e('Refund request was sent to the payment gateway.', 'tfp-dashboard'); ?></div>
                <?php elseif ($notice === 'refund_error') : ?><div class="tfp-ledger-notice is-error"><?php esc_html_e('The refund could not be processed. Check the order and gateway response.', 'tfp-dashboard'); ?></div>
                <?php elseif ($notice === 'email_sent') : ?><div class="tfp-ledger-notice is-success"><?php esc_html_e('Customer receipt email sent.', 'tfp-dashboard'); ?></div><?php endif; ?>
                <?php if (!$embedded) : ?>
                <div class="tfp-ledger-tabs" role="tablist"><a class="is-active" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('WooCommerce Orders', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('Refund History', 'tfp-dashboard'); ?></a></div>

                <?php endif; ?>                <form class="tfp-ledger-filters" method="get" action="<?php echo esc_url($base_url); ?>">
                    <input type="hidden" name="section" value="transactions">
                    <select name="status" aria-label="<?php esc_attr_e('Filter by status', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
                        <?php foreach (wc_get_order_statuses() as $status_key => $status_label) : ?><option value="<?php echo esc_attr(str_replace('wc-', '', $status_key)); ?>" <?php selected($filters['status'], str_replace('wc-', '', $status_key)); ?>><?php echo esc_html($status_label); ?></option><?php endforeach; ?>
                    </select>
                    <select name="product" aria-label="<?php esc_attr_e('Filter by product', 'tfp-dashboard'); ?>"><option value="0"><?php esc_html_e('All Products', 'tfp-dashboard'); ?></option>
                        <?php foreach (wc_get_products(['limit' => 100, 'status' => 'publish']) as $product) : ?><option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($filters['product'], $product->get_id()); ?>><?php echo esc_html($product->get_name()); ?></option><?php endforeach; ?>
                    </select>
                    <input type="date" name="after" value="<?php echo esc_attr($filters['after']); ?>" aria-label="<?php esc_attr_e('Start date', 'tfp-dashboard'); ?>">
                    <input type="date" name="before" value="<?php echo esc_attr($filters['before']); ?>" aria-label="<?php esc_attr_e('End date', 'tfp-dashboard'); ?>">
                    <div class="tfp-ledger-search"><input type="search" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search ID, email, user name', 'tfp-dashboard'); ?>"><button type="submit"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button></div>
                </form>
                <section class="tfp-ledger-stats" aria-label="<?php esc_attr_e('Revenue summary', 'tfp-dashboard'); ?>">
                    <article><span><?php esc_html_e('Gross Revenue (12mo)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['gross'])); ?></strong></article>
                    <article><span><?php esc_html_e('Net Revenue (12mo)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['net'])); ?></strong></article>
                    <article><span><?php esc_html_e('Monthly Revenue', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($totals['month'])); ?></strong></article>
                    <article class="is-refund"><span><?php esc_html_e('Refunds (30d)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(tfp_admin_transaction_ledger_money($refund_30)); ?></strong></article>
                </section>
                <section class="tfp-ledger-table-card">
                    <div class="tfp-ledger-table-toolbar"><label><input type="checkbox" data-ledger-select-all> <?php esc_html_e('Select all on page', 'tfp-dashboard'); ?></label><span><?php echo esc_html(sprintf(__('Showing %1$d–%2$d of %3$d', 'tfp-dashboard'), $result['total'] ? (($filters['page'] - 1) * 10 + 1) : 0, min($filters['page'] * 10, $result['total']), $result['total'])); ?></span><a href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                    <div class="tfp-ledger-table-scroll"><table class="tfp-ledger-table"><thead><tr><th></th><th><?php esc_html_e('Transaction ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Item / Product', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gross', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Fees', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Net', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                    <?php foreach ($orders as $order) :
                        if (!$order instanceof WC_Order) { continue; }
                        $items = $order->get_items();
                        $product_names = [];
                        foreach ($items as $item) { $product_names[] = $item->get_name(); }
                        $is_refunded = $order->get_total_refunded() > 0;
                        $status_label = $order->get_status() === 'refunded' ? __('Refunded', 'tfp-dashboard') : wc_get_order_status_name($order->get_status());
                        $payment_method = $order->get_payment_method_title() ?: __('Manual / Offline', 'tfp-dashboard');
                        $selected_row = $selected && $selected->get_id() === $order->get_id();
                        ?>
                        <tr class="<?php echo $selected_row ? 'is-selected' : ''; ?>">
                            <td><input type="checkbox" class="tfp-ledger-row-check" value="<?php echo esc_attr($order->get_id()); ?>" aria-label="<?php esc_attr_e('Select transaction', 'tfp-dashboard'); ?>"></td>
                            <td><a class="tfp-ledger-id" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id()], tfp_admin_payments_url())); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                            <td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d') : '—'); ?></td>
                            <td><?php echo esc_html($order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard')); ?><small><?php echo esc_html($order->get_billing_email()); ?></small></td>
                            <td><?php echo esc_html(implode(', ', array_slice($product_names, 0, 2))); ?></td>
                            <td><?php echo esc_html($payment_method); ?></td>
                            <td><span class="tfp-ledger-status <?php echo esc_attr($is_refunded ? 'is-refunded' : 'is-complete'); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td><?php echo wp_kses_post(wc_price($order->get_total(), ['currency' => $order->get_currency()])); ?></td>
                            <td>—</td>
                            <td><?php echo wp_kses_post(wc_price($order->get_total() - $order->get_total_refunded(), ['currency' => $order->get_currency()])); ?></td>
                            <td><a class="tfp-ledger-button" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id()], tfp_admin_payments_url())); ?>"><?php esc_html_e('Details', 'tfp-dashboard'); ?></a><a class="tfp-ledger-button is-primary" href="<?php echo esc_url(add_query_arg(['section' => 'transactions', 'order' => $order->get_id(), 'refund' => 1], tfp_admin_payments_url())); ?>"><?php esc_html_e('Refund', 'tfp-dashboard'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)) : ?><tr><td colspan="11" class="tfp-ledger-empty"><?php esc_html_e('No transactions match these filters.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                    </tbody></table></div>
                    <div class="tfp-ledger-pagination"><span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'tfp-dashboard'), $filters['page'], max(1, $result['pages']))); ?></span><div>
                        <?php if ($filters['page'] > 1) : ?><a href="<?php echo esc_url(add_query_arg(array_merge($filters, ['section' => 'transactions', 'ledger_page' => $filters['page'] - 1]), tfp_admin_payments_url())); ?>">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></a><?php endif; ?>
                        <?php if ($filters['page'] < $result['pages']) : ?><a class="is-primary" href="<?php echo esc_url(add_query_arg(array_merge($filters, ['section' => 'transactions', 'ledger_page' => $filters['page'] + 1]), tfp_admin_payments_url())); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a><?php endif; ?>
                    </div></div>
                </section>
                <?php if ($selected) : ?>
                    <section class="tfp-ledger-detail">
                        <div class="tfp-ledger-detail-head"><div><h2>#<?php echo esc_html($selected->get_order_number()); ?> <span class="tfp-ledger-status is-complete"><?php echo esc_html(wc_get_order_status_name($selected->get_status())); ?></span></h2><small><?php echo esc_html($selected->get_date_created() ? $selected->get_date_created()->date_i18n('Y-m-d · H:i') : '—'); ?></small></div><strong><?php echo wp_kses_post(wc_price($selected->get_total() - $selected->get_total_refunded(), ['currency' => $selected->get_currency()])); ?><small><?php esc_html_e('Net amount', 'tfp-dashboard'); ?></small></strong></div>
                        <div class="tfp-ledger-detail-grid"><div><h3><?php esc_html_e('General Information', 'tfp-dashboard'); ?></h3><dl>
                            <div><dt><?php esc_html_e('Customer', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_formatted_billing_full_name()); ?><small><?php echo esc_html($selected->get_billing_email()); ?></small></dd></div>
                            <div><dt><?php esc_html_e('Date Processed', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_date_paid() ? $selected->get_date_paid()->date_i18n('M j, Y · H:i T') : '—'); ?></dd></div>
                            <div><dt><?php esc_html_e('Processor ref', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_transaction_id() ?: '—'); ?><small><?php echo esc_html($selected->get_payment_method_title()); ?></small></dd></div>
                            <div><dt><?php esc_html_e('Reconciliation', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($selected->get_status() === 'completed' || $selected->get_status() === 'processing' ? __('Payment recorded', 'tfp-dashboard') : __('Review required', 'tfp-dashboard')); ?><small><?php echo esc_html($selected->get_status()); ?></small></dd></div>
                        </dl><h3><?php esc_html_e('Timeline', 'tfp-dashboard'); ?></h3><p class="tfp-ledger-timeline"><?php echo esc_html($selected->get_status() === 'refunded' ? __('Refund recorded', 'tfp-dashboard') : __('Payment recorded', 'tfp-dashboard')); ?><small><?php echo esc_html($selected->get_date_modified() ? $selected->get_date_modified()->date_i18n('M j, Y · H:i T') : '—'); ?></small></p></div>
                        <aside class="tfp-ledger-admin-actions"><h3><?php esc_html_e('Admin Actions', 'tfp-dashboard'); ?></h3>
                            <button type="button" class="tfp-ledger-action" data-ledger-print><?php esc_html_e('Print Receipt', 'tfp-dashboard'); ?></button>
                            <form method="post"><?php wp_nonce_field('tfp_transaction_ledger_action', 'tfp_ledger_nonce'); ?><input type="hidden" name="order_id" value="<?php echo esc_attr($selected->get_id()); ?>"><button class="tfp-ledger-action" name="tfp_ledger_action" value="email"><?php esc_html_e('Email Customer', 'tfp-dashboard'); ?></button></form>
                            <button type="button" class="tfp-ledger-action is-primary" data-ledger-refund-open><?php esc_html_e('Issue Refund', 'tfp-dashboard'); ?></button>
                            <form method="post" class="tfp-ledger-refund-form" data-ledger-refund-form hidden><?php wp_nonce_field('tfp_transaction_ledger_action', 'tfp_ledger_nonce'); ?><input type="hidden" name="order_id" value="<?php echo esc_attr($selected->get_id()); ?>"><input type="hidden" name="tfp_ledger_action" value="refund"><label><?php esc_html_e('Refund amount', 'tfp-dashboard'); ?><input type="number" min="0.01" max="<?php echo esc_attr($selected->get_remaining_refund_amount()); ?>" step="0.01" name="refund_amount" value="<?php echo esc_attr($selected->get_remaining_refund_amount()); ?>" required></label><label><?php esc_html_e('Reason', 'tfp-dashboard'); ?><input type="text" name="refund_reason" value="<?php esc_attr_e('Admin refund', 'tfp-dashboard'); ?>"></label><button class="tfp-ledger-action is-primary" type="submit"><?php esc_html_e('Confirm Refund', 'tfp-dashboard'); ?></button></form>
                            <a class="tfp-ledger-action" href="<?php echo esc_url($selected->get_edit_order_url()); ?>"><?php esc_html_e('Open WooCommerce Order', 'tfp-dashboard'); ?></a>
                        </aside></div>
                    </section>
                <?php else : ?><section class="tfp-ledger-detail"><p class="tfp-ledger-empty"><?php esc_html_e('Select a transaction to view its details.', 'tfp-dashboard'); ?></p></section><?php endif; ?>
            </div>
            <?php if (!$embedded) : ?>
        </main>
    </div>
            <?php endif; ?>
    <?php
}
