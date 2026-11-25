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

    $eligible_orders = function_exists('wc_get_orders') ? wc_get_orders([
        'limit' => 100, 'orderby' => 'date', 'order' => 'DESC',
        'status' => ['processing', 'completed'], 'payment_method' => 'tfp_stripe', 'return' => 'objects',
    ]) : [];
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


/**
 * Embedded Refund History section for the unified Payments dashboard.
 * Reads actual WooCommerce refund objects; it does not create or issue refunds.
 */

/**
 * Process a real WooCommerce refund through the custom Stripe gateway.
 * This endpoint is deliberately restricted to Stripe TEST mode.
 */
function tfp_admin_refunds_handle_test_stripe_refund() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || empty($_POST['tfp_test_refund_submit'])) { return; }
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Permission denied.', 'tfp-dashboard'), '', ['response' => 403]); }
    check_admin_referer('tfp_test_stripe_refund', 'tfp_test_refund_nonce');

    $redirect = tfp_admin_payments_url(['section' => 'refunds']);
    $fail = static function($message) use ($redirect) {
        wp_safe_redirect(add_query_arg('refund_error', rawurlencode($message), $redirect));
        exit;
    };

    if (!function_exists('tfp_stripe_api') || !function_exists('tfp_stripe_is_test_mode') || !tfp_stripe_is_test_mode()) {
        $fail(__('Stripe Test Mode is not enabled. No refund was sent.', 'tfp-dashboard'));
    }
    $secret = function_exists('tfp_stripe_get_secret_key') ? tfp_stripe_get_secret_key() : '';
    $publishable = function_exists('tfp_stripe_get_publishable_key') ? tfp_stripe_get_publishable_key() : '';
    if (strpos($secret, 'sk_test_') !== 0 || strpos($publishable, 'pk_test_') !== 0) {
        $fail(__('Test API keys are required. Live keys are not accepted for this refund workflow.', 'tfp-dashboard'));
    }

    $order_id = absint($_POST['tfp_refund_order_id'] ?? 0);
    $amount_raw = isset($_POST['tfp_refund_amount']) ? wc_format_decimal(wp_unslash($_POST['tfp_refund_amount'])) : '';
    $reason = sanitize_text_field(wp_unslash($_POST['tfp_refund_reason'] ?? ''));
    $request_id = sanitize_key(wp_unslash($_POST['tfp_refund_request_id'] ?? ''));
    if (!$order_id || !$request_id || !preg_match('/^[a-f0-9]{32}$/', $request_id)) {
        $fail(__('Invalid refund request. Please refresh and try again.', 'tfp-dashboard'));
    }
    $order = wc_get_order($order_id);
    if (!$order || $order->get_type() !== 'shop_order' || $order->get_payment_method() !== 'tfp_stripe') {
        $fail(__('Select a valid order paid through the TFP Stripe gateway.', 'tfp-dashboard'));
    }
    if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
        $fail(__('Only paid processing or completed orders can be refunded here.', 'tfp-dashboard'));
    }
    $intent_id = (string) $order->get_meta('_tfp_stripe_intent_id');
    if ($intent_id === '') { $intent_id = (string) $order->get_transaction_id(); }
    if (strpos($intent_id, 'pi_') !== 0) {
        $fail(__('This order does not have a valid Stripe PaymentIntent.', 'tfp-dashboard'));
    }

    $amount = (float) $amount_raw;
    $remaining = max(0, (float) $order->get_total() - (float) $order->get_total_refunded());
    if ($amount <= 0 || $amount > $remaining + 0.00001) {
        $fail(sprintf(__('Refund amount must be greater than zero and no more than %s.', 'tfp-dashboard'), wp_strip_all_tags(wc_price($remaining, ['currency' => $order->get_currency()]))));
    }
    $minor = tfp_stripe_to_minor_units($amount, $order->get_currency());
    if ($minor < 1) { $fail(__('Refund amount is too small for this currency.', 'tfp-dashboard')); }

    $intent = tfp_stripe_api('GET', 'payment_intents/' . rawurlencode($intent_id));
    if (is_wp_error($intent) || empty($intent['id']) || ($intent['status'] ?? '') !== 'succeeded' || !empty($intent['livemode'])) {
        $fail(is_wp_error($intent) ? $intent->get_error_message() : __('Stripe could not verify a successful Test Mode payment. No refund was sent.', 'tfp-dashboard'));
    }
    if (strtolower((string) ($intent['currency'] ?? '')) !== strtolower($order->get_currency())) {
        $fail(__('Payment currency does not match the WooCommerce order.', 'tfp-dashboard'));
    }
    $intent_amount = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
    if ($intent_amount < $minor) {
        $fail(__('Refund amount exceeds the captured Stripe payment amount.', 'tfp-dashboard'));
    }

    $request_meta = '_tfp_stripe_refund_request_' . $request_id;
    $request_state = $order->get_meta($request_meta, true);
    if (!is_array($request_state)) { $request_state = []; }
    $stripe_refund = null;
    if (!empty($request_state['stripe_refund_id'])) {
        $stripe_refund = ['id' => $request_state['stripe_refund_id'], 'status' => $request_state['stripe_status'] ?? 'succeeded'];
    } else {
        $stripe_reason = '';
        if (stripos($reason, 'duplicate') !== false) { $stripe_reason = 'duplicate'; }
        elseif (stripos($reason, 'fraud') !== false) { $stripe_reason = 'fraudulent'; }
        elseif ($reason !== '') { $stripe_reason = 'requested_by_customer'; }
        $params = [
            'payment_intent' => $intent_id,
            'amount' => $minor,
            'metadata' => ['wc_order_id' => (string) $order_id, 'source' => 'tfp_admin_refunds'],
        ];
        if ($stripe_reason !== '') { $params['reason'] = $stripe_reason; }
        $idempotency = 'tfp-refund-' . $order_id . '-' . $request_id;
        $stripe_refund = tfp_stripe_api('POST', 'refunds', $params, $idempotency);
        if (is_wp_error($stripe_refund) || empty($stripe_refund['id'])) {
            $fail(is_wp_error($stripe_refund) ? $stripe_refund->get_error_message() : __('Stripe did not return a refund reference.', 'tfp-dashboard'));
        }
        $request_state = ['stripe_refund_id' => sanitize_text_field($stripe_refund['id']), 'stripe_status' => sanitize_text_field($stripe_refund['status'] ?? 'pending'), 'amount' => $amount, 'created_at' => time()];
        $order->update_meta_data($request_meta, $request_state);
        $order->save();
    }

    $existing_refund_id = absint($request_state['wc_refund_id'] ?? 0);
    if ($existing_refund_id && wc_get_order($existing_refund_id)) {
        wp_safe_redirect(add_query_arg('refund_success', '1', $redirect));
        exit;
    }
    $wc_refund = wc_create_refund([
        'order_id' => $order_id,
        'amount' => $amount,
        'reason' => $reason,
        'refund_payment' => false,
        'restock_items' => false,
    ]);
    if (is_wp_error($wc_refund) || !$wc_refund) {
        $order->add_order_note(sprintf(__('Stripe Test Mode refund %1$s succeeded, but the WooCommerce refund record could not be created. Do not issue another Stripe refund; request reference: %2$s', 'tfp-dashboard'), $stripe_refund['id'], $request_id));
        $order->save();
        $fail(is_wp_error($wc_refund) ? $wc_refund->get_error_message() : __('Stripe refund succeeded, but the WooCommerce refund record needs administrator attention. Do not retry with a new request.', 'tfp-dashboard'));
    }
    $wc_refund->update_meta_data('_tfp_stripe_refund_id', sanitize_text_field($stripe_refund['id']));
    $wc_refund->update_meta_data('_tfp_stripe_refund_status', sanitize_text_field($stripe_refund['status'] ?? 'pending'));
    $wc_refund->save();
    $request_state['wc_refund_id'] = $wc_refund->get_id();
    $order->update_meta_data($request_meta, $request_state);
    $order->add_order_note(sprintf(__('Stripe Test Mode refund created: %1$s (%2$s). WooCommerce refund #%3$s.', 'tfp-dashboard'), $stripe_refund['id'], $stripe_refund['status'] ?? 'pending', $wc_refund->get_id()));
    $order->save();

    wp_safe_redirect(add_query_arg('refund_success', '1', $redirect));
    exit;
}
add_action('template_redirect', 'tfp_admin_refunds_handle_test_stripe_refund', 1);


function tfp_admin_refunds_render_embedded() {
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        echo '<p class="tfp-refund-dashboard-empty">' . esc_html__('You do not have permission to view refunds.', 'tfp-dashboard') . '</p>';
        return;
    }

    // Safe visual-only demo: sample values are never sent to WooCommerce or Stripe.
    if (isset($_GET['refund_demo']) && (string) wp_unslash($_GET['refund_demo']) === '1' && current_user_can('manage_options')) {
        $v = sanitize_key(wp_unslash($_GET['refund_demo_view'] ?? 'manual'));
        $base = tfp_admin_payments_url(['section'=>'refunds','refund_demo'=>'1']);
        $url = static function($view) use ($base) { return esc_url(add_query_arg('refund_demo_view',$view,$base)); };
        ?>
        <div class="tfp-refund-demo">
          <div class="tfp-refund-demo-banner"><strong>Refund workflow demo</strong><span>Sample data only. No real refund is created or processed.</span><a href="<?php echo esc_url(tfp_admin_payments_url(['section'=>'refunds'])); ?>">Exit demo · Back to live Refund History</a></div>
          <nav><?php foreach (['confirm'=>'Issue Refund','manual'=>'Manual Refund · Full','partial'=>'Manual Refund · Partial','detail'=>'Refund Details','final'=>'Confirm Refund'] as $key=>$label) : ?><a class="<?php echo $v===$key?'active':''; ?>" href="<?php echo $url($key); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></nav>
          <?php if ($v==='confirm') : ?>
            <section class="refund-demo-confirm"><div class="alert">!</div><h2>Issue Refund?</h2><p>You are about to reverse this transaction<br>through the payment gateway.</p><div class="summary"><div><b>Customer</b><span>Daniel K.</span></div><div><b>Refund ID</b><span>RF-100305</span></div><hr><div><b>Amount to Reverse</b><strong>$199.00</strong></div></div><footer><a href="<?php echo $url('manual'); ?>">⊘ &nbsp; Cancel</a><a class="primary" href="<?php echo $url('final'); ?>">✓ &nbsp; Confirm Refund</a></footer></section>
          <?php elseif ($v==='manual' || $v==='partial') : ?>
            <section class="refund-demo-manual"><header><b>Manual Refund</b><small>Process new refund entry</small></header><label>Customer / Transaction<input readonly value="Daniel K. · daniel@example.com · PMT-55099"></label><div class="twocol"><label>Refund Reason<select><option>Course cancellation</option><option>Duplicate charge</option><option>Gateway error</option></select></label><div><b>Refund Type</b><div class="toggle"><a class="<?php echo $v==='manual'?'active':''; ?>" href="<?php echo $url('manual'); ?>">Full</a><a class="<?php echo $v==='partial'?'active':''; ?>" href="<?php echo $url('partial'); ?>">Partial</a></div></div></div><?php if($v==='partial'): ?><label>Partial Refund Amount ($)<input readonly value="0.00"></label><?php endif; ?><label>Internal Admin Notes<input readonly placeholder="Explain the context for this refund..."></label><footer><a href="<?php echo $url('confirm'); ?>">⊘ &nbsp; Cancel</a><a class="primary" href="<?php echo $url('final'); ?>">✓ &nbsp; Issue Refund</a></footer></section>
          <?php elseif ($v==='detail') : ?>
            <section class="refund-demo-detail"><header><div><h2>RF-100341 <i>Completed</i></h2><small>Original Payment: PMT-55321</small></div><strong><small>REFUND AMOUNT</small>$299.00</strong></header><div class="detailgrid"><div><h3>General Information</h3><b>Customer</b><small>Mary J. (mary.j@example.com)</small><b>Refund Reason</b><small>Course cancellation,<br>Manual Refund - (Shannon S.)</small><h3>Timeline</h3><b>Refund Completed</b><small>May 04, 14:32 PST · Funds released to bank</small><b>Approved by Admin</b><small>May 04, 11:15 PST · Approved by Sarah M.</small><b>Refund Requested</b><small>May 03, 16:40 PST · Initiated via customer portal</small></div><aside><h3>Admin Actions</h3><button type="button" onclick="tfpPrintRefundReceipt(this);"><svg class="tfp-refund-demo-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/><path d="M18 12h.01"/></svg>Print Receipt</button><script>
function tfpPrintRefundReceipt(button) {
    var receipt = button.closest('.refund-demo-detail');
    if (!receipt) return;
    var printable = receipt.cloneNode(true);
    var actions = printable.querySelector('aside');
    if (actions) actions.remove();
    var styles = Array.prototype.map.call(document.querySelectorAll('link[rel="stylesheet"]'), function(link) {
        return '<link rel="stylesheet" href="' + link.href.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '">';
    }).join('');
    var win = window.open('', '_blank', 'width=900,height=700');
    if (!win) { window.print(); return; }
    win.document.open();
    win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Refund Receipt RF-100341</title>' + styles + '<style>body{margin:32px;background:#fff;font-family:Arial,sans-serif;color:#171717}.refund-demo-detail{display:block!important;width:100%;max-width:900px;margin:0 auto;padding:24px;border:1px solid #ddd;border-radius:12px;box-sizing:border-box}.refund-demo-detail header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #ddd;padding-bottom:18px;margin-bottom:24px}.refund-demo-detail header h2{margin:0 0 8px}.refund-demo-detail header strong{text-align:right}.refund-demo-detail .detailgrid{display:block}.refund-demo-detail .detailgrid>div{display:block}.refund-demo-detail h3{margin:18px 0 10px}.refund-demo-detail b,.refund-demo-detail small{display:block;margin:8px 0}.refund-demo-detail aside{display:none!important}@media print{body{margin:0}.refund-demo-detail{border:0}}</style></head><body>' + printable.outerHTML + '</body></html>');
    win.document.close();
    win.onload = function() { win.focus(); win.print(); };
}
</script><a class="tfp-refund-demo-email" href="mailto:mary.j@example.com?subject=Refund%20RF-100341%20receipt"><svg class="tfp-refund-demo-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Email Customer</a><a class="primary" href="<?php echo $url('manual'); ?>">✓ &nbsp; Done</a><h3>Processor Metadata</h3><small>TXN_TYPE: full_refund<br>GATEWAY_REF: rev_928130<br>SRC_METHOD: visa_4242<br>AVS_CHECK: passed</small></aside></div></section>
          <?php else : ?>
            <section class="refund-demo-final"><h2>Confirm refund for Daniel K.</h2><small>Refund ID: RF-100305 · PMT-55099</small><p>Amount<br><b>$199.00</b></p><p>Reason<br>Course cancellation</p><footer><a href="<?php echo $url('manual'); ?>">⊘ &nbsp; Cancel</a><a class="primary" href="<?php echo $url('detail'); ?>">✓ &nbsp; Issue Refund</a></footer></section>
          <?php endif; ?>
        </div>
        <?php return;
    }
    $search = sanitize_text_field(wp_unslash($_GET['refund_search'] ?? ''));
    $date_from = sanitize_text_field(wp_unslash($_GET['refund_from'] ?? ''));
    $date_to = sanitize_text_field(wp_unslash($_GET['refund_to'] ?? ''));
    $reason_filter = sanitize_key(wp_unslash($_GET['refund_reason'] ?? ''));
    $status_filter = sanitize_key(wp_unslash($_GET['refund_status'] ?? 'completed'));
    $orders = function_exists('wc_get_orders') ? wc_get_orders([
        'limit' => 500,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses())),
        'return' => 'objects',
    ]) : [];

    $rows = [];
    $ytd_total = 0.0;
    $month_total = 0.0;
    $ytd_count = 0;
    $month_count = 0;
    $gross_ytd = 0.0;
    $now = current_time('timestamp');
    $year_start = strtotime(wp_date('Y-01-01 00:00:00', $now));
    $month_start = strtotime(wp_date('Y-m-01 00:00:00', $now));

    foreach ((array) $orders as $order) {
        if (!$order instanceof WC_Order) { continue; }
        $created = $order->get_date_created();
        if ($created && $created->getTimestamp() >= $year_start && $order->get_type() === 'shop_order') {
            $gross_ytd += (float) $order->get_total();
        }
        foreach ($order->get_refunds() as $refund) {
            $refund_date = $refund->get_date_created();
            $timestamp = $refund_date ? $refund_date->getTimestamp() : 0;
            $date_value = $refund_date ? $refund_date->date('Y-m-d') : '';
            $amount = abs((float) $refund->get_amount());
            if ($timestamp >= $year_start) { $ytd_total += $amount; $ytd_count++; }
            if ($timestamp >= $month_start) { $month_total += $amount; $month_count++; }
            $reason = (string) $refund->get_reason();
            $customer = $order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard');
            $email = (string) $order->get_billing_email();
            $id = (int) $refund->get_id();
            if ($search && stripos($id . ' ' . $order->get_order_number() . ' ' . $customer . ' ' . $email, $search) === false) { continue; }
            if ($date_from && $date_value && $date_value < $date_from) { continue; }
            if ($date_to && $date_value && $date_value > $date_to) { continue; }
            if ($reason_filter && stripos($reason, str_replace('-', ' ', $reason_filter)) === false) { continue; }
            $rows[] = [
                'id' => $id,
                'order' => $order,
                'customer' => $customer,
                'email' => $email,
                'date' => $refund_date ? $refund_date->date_i18n(get_option('date_format')) : '—',
                'timestamp' => $timestamp,
                'amount' => $amount,
                'reason' => $reason,
                'status' => __('Completed', 'tfp-dashboard'),
            ];
        }
    }

    usort($rows, static function ($a, $b) { return $b['timestamp'] <=> $a['timestamp']; });
    $selected_id = absint($_GET['refund_id'] ?? 0);
    $selected = null;
    foreach ($rows as $row) { if ($row['id'] === $selected_id) { $selected = $row; break; } }
    if (!$selected && !empty($rows)) { $selected = $rows[0]; }
    $refund_rate = $gross_ytd > 0 ? ($ytd_total / $gross_ytd) * 100 : 0;
    $base_url = tfp_admin_payments_url(['section' => 'refunds']);
    $refunds_url = static function ($args = []) use ($base_url) {
        return esc_url(add_query_arg(array_merge(['section' => 'refunds'], $args), remove_query_arg(['refund_id', 'refund_reason'], $base_url)));
    };
    $manual_count = count(array_filter($rows, static function ($row) { return stripos($row['reason'], 'manual') !== false; }));
    ?>
    <div class="tfp-refund-dashboard">

      <?php if (!empty($_GET['refund_success'])) : ?><div class="notice notice-success" role="status"><p><?php esc_html_e('Stripe Test Mode refund processed and recorded in WooCommerce.', 'tfp-dashboard'); ?></p></div><?php endif; ?>
      <?php if (!empty($_GET['refund_error'])) : ?><div class="notice notice-error" role="alert"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['refund_error']))); ?></p></div><?php endif; ?>
      <section class="tfp-refund-issue-panel">
        <h3><?php esc_html_e('Issue Stripe Test Refund', 'tfp-dashboard'); ?></h3>
        <p><?php esc_html_e('Test Mode only. This sends a real refund request to your Stripe TEST account and creates a matching WooCommerce refund record. It does not affect live payments.', 'tfp-dashboard'); ?></p>
        <?php if (!function_exists('tfp_stripe_is_test_mode') || !tfp_stripe_is_test_mode() || strpos((string) tfp_stripe_get_secret_key(), 'sk_test_') !== 0 || strpos((string) tfp_stripe_get_publishable_key(), 'pk_test_') !== 0) : ?>
          <p><strong><?php esc_html_e('Unavailable: enable Stripe Test Mode and configure pk_test_ / sk_test_ keys first.', 'tfp-dashboard'); ?></strong></p>
        <?php else : ?>
          <form method="post">
            <?php wp_nonce_field('tfp_test_stripe_refund', 'tfp_test_refund_nonce'); ?>
            <input type="hidden" name="tfp_test_refund_request_id" value="<?php echo esc_attr(str_replace('-', '', wp_generate_uuid4())); ?>">
            <label><?php esc_html_e('Paid Stripe Test Order', 'tfp-dashboard'); ?>
              <select name="tfp_refund_order_id" required>
                <option value=""><?php esc_html_e('Select an order', 'tfp-dashboard'); ?></option>
                <?php foreach ((array) $eligible_orders as $eligible_order) :
                  if (!$eligible_order instanceof WC_Order) { continue; }
                  $intent_ref = (string) $eligible_order->get_meta('_tfp_stripe_intent_id');
                  if ($intent_ref === '') { $intent_ref = (string) $eligible_order->get_transaction_id(); }
                  $left = max(0, (float) $eligible_order->get_total() - (float) $eligible_order->get_total_refunded());
                  if (strpos($intent_ref, 'pi_') !== 0 || $left <= 0) { continue; }
                  ?>
                  <option value="<?php echo esc_attr($eligible_order->get_id()); ?>"><?php echo esc_html('#' . $eligible_order->get_order_number() . ' · ' . ($eligible_order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard')) . ' · ' . wp_strip_all_tags(wc_price($left, ['currency' => $eligible_order->get_currency()])) . ' refundable'); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label><?php esc_html_e('Refund Amount', 'tfp-dashboard'); ?>
              <input type="number" name="tfp_refund_amount" min="0.01" step="0.01" required placeholder="0.00">
            </label>
            <label><?php esc_html_e('Reason / Admin Note', 'tfp-dashboard'); ?>
              <input type="text" name="tfp_refund_reason" maxlength="250" placeholder="<?php esc_attr_e('Reason for refund', 'tfp-dashboard'); ?>">
            </label>
            <button type="submit" name="tfp_test_refund_submit" value="1" onclick="return confirm('<?php echo esc_js(__('Send this refund to your Stripe TEST account? This action cannot be undone.', 'tfp-dashboard')); ?>');"><?php esc_html_e('Confirm Test Refund', 'tfp-dashboard'); ?></button>
          </form>
        <?php endif; ?>
      </section>
      <div class="tfp-refund-demo-launch"><span>Preview refund workflows</span><a href="<?php echo esc_url(tfp_admin_payments_url(['section'=>'refunds','refund_demo'=>'1','refund_demo_view'=>'manual'])); ?>">Open demo preview</a></div>
        <form class="tfp-refund-filters" method="get" action="<?php echo esc_url(tfp_admin_payments_url()); ?>">
            <input type="hidden" name="section" value="refunds">
            <select name="refund_status" aria-label="<?php esc_attr_e('Refund status', 'tfp-dashboard'); ?>">
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'tfp-dashboard'); ?></option>
                <option value="all" <?php selected($status_filter, 'all'); ?>><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
            </select>
            <label class="tfp-refund-date"><input type="date" name="refund_from" value="<?php echo esc_attr($date_from); ?>" aria-label="<?php esc_attr_e('Start date', 'tfp-dashboard'); ?>"></label>
            <label class="tfp-refund-date"><input type="date" name="refund_to" value="<?php echo esc_attr($date_to); ?>" aria-label="<?php esc_attr_e('End date', 'tfp-dashboard'); ?>"></label>
            <button type="submit" class="tfp-refund-apply"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button>
            <span class="tfp-refund-selected"><?php esc_html_e('Selected: 0', 'tfp-dashboard'); ?></span>
            <button type="button" class="tfp-refund-bulk-issue" data-tfp-refund-bulk disabled><?php esc_html_e('Issue selected refunds', 'tfp-dashboard'); ?></button>
        </form>

        <section class="tfp-refund-stats">
            <article><span><?php esc_html_e('Total Refunded (YTD)', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(wc_price($ytd_total)); ?></strong><small><?php echo esc_html(sprintf(__('Refund rate: %s%%', 'tfp-dashboard'), number_format_i18n($refund_rate, 1))); ?></small></article>
            <article class="is-month"><span><?php esc_html_e('Refunds This Month', 'tfp-dashboard'); ?></span><strong><?php echo wp_kses_post(wc_price($month_total)); ?></strong><small><?php echo esc_html(sprintf(_n('%s refund', '%s refunds', $month_count, 'tfp-dashboard'), number_format_i18n($month_count))); ?></small></article>
            <article class="is-pending"><span><?php esc_html_e('Pending Refunds', 'tfp-dashboard'); ?></span><strong>0</strong><small><?php esc_html_e('No pending gateway reversals reported by WooCommerce', 'tfp-dashboard'); ?></small></article>
        </section>

        <section class="tfp-refund-table-card">
            <div class="tfp-refund-toolbar">
                <label><input type="checkbox" data-tfp-refund-select-all> <?php esc_html_e('Select all on page', 'tfp-dashboard'); ?></label>
                <span><?php echo esc_html(sprintf(__('Showing %1$d refund records', 'tfp-dashboard'), count($rows))); ?></span>
                <div class="tfp-refund-quick-filters">
                    <span><?php esc_html_e('Quick filter:', 'tfp-dashboard'); ?></span>
                    <a class="<?php echo $reason_filter === 'manual-refund' ? 'is-active' : ''; ?>" href="<?php echo $refunds_url(['refund_reason' => 'manual-refund']); ?>"><?php esc_html_e('Manual Refunds', 'tfp-dashboard'); ?></a>
                    <a class="<?php echo $reason_filter === 'duplicate-charge' ? 'is-active' : ''; ?>" href="<?php echo $refunds_url(['refund_reason' => 'duplicate-charge']); ?>"><?php esc_html_e('Duplicate charge', 'tfp-dashboard'); ?></a>
                    <a class="<?php echo $reason_filter === 'gateway-error' ? 'is-active' : ''; ?>" href="<?php echo $refunds_url(['refund_reason' => 'gateway-error']); ?>"><?php esc_html_e('Gateway error', 'tfp-dashboard'); ?></a>
                </div>
            </div>
            <div class="tfp-refund-table-scroll">
                <table class="tfp-refund-table">
                    <thead><tr><th class="check-col"></th><th><?php esc_html_e('Refund ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Reason', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Amount', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td class="check-col"><input type="checkbox" class="tfp-refund-row-check" value="<?php echo esc_attr($row['id']); ?>" data-order-edit="<?php echo esc_url($row['order']->get_edit_order_url()); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select refund %s', 'tfp-dashboard'), $row['id'])); ?>"></td>
                            <td><strong>#<?php echo esc_html($row['id']); ?></strong></td>
                            <td><?php echo esc_html($row['date']); ?></td>
                            <td><?php echo esc_html($row['customer']); ?><small><?php echo esc_html($row['email']); ?></small></td>
                            <td><?php echo esc_html($row['reason'] ?: '—'); ?></td>
                            <td><span class="tfp-refund-status"><?php echo esc_html($row['status']); ?></span></td>
                            <td class="tfp-refund-amount"><?php echo wp_kses_post(wc_price($row['amount'], ['currency' => $row['order']->get_currency()])); ?></td>
                            <td class="tfp-refund-actions"><a class="tfp-refund-action is-issue" href="<?php echo esc_url($row['order']->get_edit_order_url()); ?>"><?php esc_html_e('Issue', 'tfp-dashboard'); ?></a><a class="tfp-refund-action" href="<?php echo esc_url(add_query_arg(['section' => 'refunds', 'refund_id' => $row['id']], tfp_admin_payments_url())); ?>"><?php esc_html_e('Details', 'tfp-dashboard'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows) : ?><tr><td colspan="8" class="tfp-refund-empty"><?php esc_html_e('No refund records found for the selected filters.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="tfp-refund-table-footer"><span><?php esc_html_e('Refund records are read directly from WooCommerce orders.', 'tfp-dashboard'); ?></span><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('View all orders', 'tfp-dashboard'); ?></a></div>
        </section>

        <?php if ($selected) : ?>
            <section class="tfp-refund-detail">
                <header><div><h2><?php echo esc_html(sprintf(__('Refund details — #%s', 'tfp-dashboard'), $selected['id'])); ?></h2><small><?php echo esc_html($selected['customer'] . ' · ' . $selected['date']); ?></small></div><strong><?php echo wp_kses_post(wc_price($selected['amount'], ['currency' => $selected['order']->get_currency()])); ?></strong><a class="tfp-refund-action" href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'refunds'])); ?>"><?php esc_html_e('Close', 'tfp-dashboard'); ?></a></header>
                <div class="tfp-refund-detail-grid">
                    <p><small><?php esc_html_e('Payment', 'tfp-dashboard'); ?></small><strong>#<?php echo esc_html($selected['order']->get_order_number()); ?></strong></p>
                    <p><small><?php esc_html_e('Customer', 'tfp-dashboard'); ?></small><strong><?php echo esc_html($selected['customer']); ?></strong><span><?php echo esc_html($selected['email']); ?></span></p>
                    <p><small><?php esc_html_e('Reason', 'tfp-dashboard'); ?></small><strong><?php echo esc_html($selected['reason'] ?: '—'); ?></strong></p>
                </div>
                <a class="tfp-refund-action" href="<?php echo esc_url($selected['order']->get_edit_order_url()); ?>"><?php esc_html_e('Open WooCommerce Order', 'tfp-dashboard'); ?></a>
            </section>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('change',function(e){
        if(e.target.matches('[data-tfp-refund-select-all]')){
            document.querySelectorAll('.tfp-refund-row-check').forEach(function(el){el.checked=e.target.checked;});
        }
        if(e.target.matches('.tfp-refund-row-check') || e.target.matches('[data-tfp-refund-select-all]')){
            var all=document.querySelectorAll('.tfp-refund-row-check');
            var selected=Array.prototype.filter.call(all,function(el){return el.checked;});
            var count=document.querySelector('.tfp-refund-selected');
            var bulk=document.querySelector('[data-tfp-refund-bulk]');
            if(count){count.textContent='Selected: '+selected.length;}
            if(bulk){bulk.disabled=!selected.length;}
        }
    });
    document.addEventListener('click',function(e){
        var bulk=e.target.closest('[data-tfp-refund-bulk]');
        if(!bulk)return;
        var selected=document.querySelector('.tfp-refund-row-check:checked');
        if(selected && selected.dataset.orderEdit){window.location.href=selected.dataset.orderEdit;}
    });
    </script>
    <?php
}
