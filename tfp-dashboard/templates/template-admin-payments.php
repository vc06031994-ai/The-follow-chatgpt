<?php
/**
 * TFP Admin Dashboard — Payments Revenue Overview.
 */
if (!defined('ABSPATH')) { exit; }
if (!is_user_logged_in()) { auth_redirect(); }
if (!current_user_can('manage_options') && !current_user_can('administrator')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
}
show_admin_bar(false);
status_header(200);
nocache_headers();
require_once dirname(__DIR__) . '/includes/admin/dashboard.php';
require_once dirname(__DIR__) . '/includes/admin/payments.php';
require_once dirname(__DIR__) . '/includes/admin/refunds.php';
$data = tfp_admin_payments_get_data();
$user = wp_get_current_user();
$nav = tfp_admin_dashboard_nav_items();
foreach ($nav as &$nav_item) {
    $nav_item['active'] = ($nav_item['slug'] === 'payments');
    if ($nav_item['slug'] === 'payments') { $nav_item['url'] = tfp_admin_payments_url(); }
}
unset($nav_item);
$name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
$avatar = get_avatar_url($user->ID, ['size' => 96]);
$dash_url = TFP_DASH_URL;
$dash_ver = TFP_DASH_VERSION;
$css_path = dirname(__DIR__) . '/assets/css/admin-payments.css';
$payments_section = sanitize_key(wp_unslash($_GET['section'] ?? ''));
$is_transactions = ($payments_section === 'transactions');
$is_payouts = ($payments_section === 'payouts');
$is_refunds = ($payments_section === 'refunds');
$payout_data = $is_payouts ? tfp_admin_payments_get_stripe_payout_data() : null;
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], file_exists(dirname(__DIR__) . '/assets/css/admin-dashboard.css') ? (string) filemtime(dirname(__DIR__) . '/assets/css/admin-dashboard.css') : $dash_ver);
wp_enqueue_style('tfp-admin-payments', $dash_url . 'assets/css/admin-payments.css', ['tfp-admin-dashboard'], file_exists($css_path) ? (string) filemtime($css_path) : $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $dash_ver, true);
if ($is_refunds) {
    $refund_css_path = dirname(__DIR__) . '/assets/css/admin-refunds.css';
    wp_enqueue_style('tfp-admin-refunds', $dash_url . 'assets/css/admin-refunds.css', ['tfp-admin-payments'], file_exists($refund_css_path) ? (string) filemtime($refund_css_path) : $dash_ver);
}
if ($is_transactions) {
    $ledger_css_path = dirname(__DIR__) . '/assets/css/admin-transaction-ledger.css';
    wp_enqueue_style('tfp-admin-transaction-ledger', $dash_url . 'assets/css/admin-transaction-ledger.css', ['tfp-admin-payments'], file_exists($ledger_css_path) ? (string) filemtime($ledger_css_path) : $dash_ver);

}
$products = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC']) : [];
$currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
$last_30 = 0.0;
foreach ($data['orders'] as $order) {
    $created = $order->get_date_created();
    if ($created && $created->getTimestamp() >= strtotime('-30 days', current_time('timestamp'))) { $last_30 += (float) $order->get_total(); }
}
$max_bar = 1;
foreach ($data['quarters'] as $q) { $max_bar = max($max_bar, $q['gross']); }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-payments-view'); ?>>
<?php wp_body_open(); ?>
<?php ?>




<div class="tfp-admin-app">
    <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url($dash_url . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url($dash_url . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
            <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url($avatar); ?>" alt=""><span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
    </aside>
    <div class="tfp-admin-main">
        <div class="tfp-admin-mobilebar"><button type="button" class="tfp-admin-mobilebar-toggle" data-tfp-admin-toggle aria-controls="tfp-admin-sidebar" aria-expanded="false" aria-label="<?php esc_attr_e('Open admin menu', 'tfp-dashboard'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><rect x="2.25" y="2.25" width="13.5" height="13.5" rx="1.4" stroke="currentColor" stroke-width="1.8"/><path d="M7 2.8V15.2" stroke="currentColor" stroke-width="1.8"/></svg></button><span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span></div>
        <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Payments', 'tfp-dashboard'); ?></span><b>/</b><strong><?php echo esc_html($is_transactions ? __('Transaction Ledger', 'tfp-dashboard') : ($is_payouts ? __('Payouts', 'tfp-dashboard') : ($is_refunds ? __('Refunds', 'tfp-dashboard') : __('Overview', 'tfp-dashboard')))); ?></strong></div>
        <header class="tfp-payments-page-heading"><div class="tfp-payments-heading-copy"><h1><?php echo esc_html($is_transactions ? __('Transaction Ledger', 'tfp-dashboard') : ($is_payouts ? __('Payouts', 'tfp-dashboard') : ($is_refunds ? __('Payment Refund History', 'tfp-dashboard') : __('Payments — Revenue Overview', 'tfp-dashboard')))); ?></h1><p><?php echo esc_html($is_transactions ? __('All payments, refunds, statuses, and reconciliation controls', 'tfp-dashboard') : ($is_payouts ? __('Track transfers from Stripe to your bank accounts.', 'tfp-dashboard') : ($is_refunds ? __('NEED TEST FOR HERE', 'tfp-dashboard') : __('Revenue metrics, product breakdown, and recent transactions', 'tfp-dashboard')))); ?></p></div><?php if ($is_payouts) : ?><a class="tfp-payouts-back-top" href="<?php echo esc_url(tfp_admin_payments_url()); ?>">↶ <?php esc_html_e('Back To All Sections', 'tfp-dashboard'); ?></a><?php endif; ?></header>
        <main class="tfp-payments-content">
            <nav class="tfp-payments-tabs" aria-label="<?php esc_attr_e('Payment sections', 'tfp-dashboard'); ?>">
                <a class="<?php echo (!$is_transactions && !$is_payouts && !$is_refunds) ? 'is-active' : ''; ?>" href="<?php echo esc_url(tfp_admin_payments_url()); ?>"><?php esc_html_e('Revenue Overview', 'tfp-dashboard'); ?></a>
                <a class="<?php echo $is_transactions ? 'is-active' : ''; ?>" href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'transactions'])); ?>"><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></a>
                
                <a class="<?php echo $is_payouts ? 'is-active' : ''; ?>" href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'payouts'])); ?>"><?php esc_html_e('Payouts', 'tfp-dashboard'); ?></a>
                <a class="<?php echo $is_refunds ? 'is-active' : ''; ?>" href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'refunds'])); ?>"><?php esc_html_e('Refund History', 'tfp-dashboard'); ?></a>
            </nav>
            <?php if ($is_transactions) : ?>
                <?php tfp_admin_transaction_ledger_render(true); ?>
            <?php elseif ($is_refunds) : ?>
                <?php tfp_admin_refunds_render_embedded(); ?>
            <?php elseif ($is_payouts) : ?>
                <?php $selected_payout = $payout_data['selected']; $payout_currency = $selected_payout['currency'] ?? get_woocommerce_currency(); $format_payout = static function ($minor, $currency) { return function_exists('wc_price') ? wc_price(tfp_admin_payments_stripe_decimal($minor), ['currency' => strtoupper($currency)]) : esc_html(number_format_i18n(tfp_admin_payments_stripe_decimal($minor), 2)); }; ?>
                
                <?php if ($payout_data['error'] !== '') : ?><div class="tfp-payouts-notice is-error"><?php echo esc_html($payout_data['error']); ?></div><?php endif; ?>
                <form class="tfp-payouts-filters" method="get" action="<?php echo esc_url(tfp_admin_payments_url()); ?>">
                    <input type="hidden" name="section" value="payouts">
                    <select name="payout_status" aria-label="<?php esc_attr_e('Filter by payout status', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option><?php foreach (['pending'=>'Pending','in_transit'=>'In Transit','paid'=>'Paid','failed'=>'Failed','canceled'=>'Canceled'] as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($payout_data['filters']['status'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                    <select name="payout_reconciliation" aria-label="<?php esc_attr_e('Reconciliation status', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Reconciliations', 'tfp-dashboard'); ?></option><option value="reconciled"><?php esc_html_e('Reconciled', 'tfp-dashboard'); ?></option><option value="pending"><?php esc_html_e('Pending', 'tfp-dashboard'); ?></option></select>
                    <select name="payout_processor" aria-label="<?php esc_attr_e('Payment processor', 'tfp-dashboard'); ?>"><option value="stripe">Stripe</option></select>
                    <input type="date" name="payout_from" value="<?php echo esc_attr($payout_data['filters']['from']); ?>" aria-label="<?php esc_attr_e('Start date', 'tfp-dashboard'); ?>">
                    <input type="date" name="payout_to" value="<?php echo esc_attr($payout_data['filters']['to']); ?>" aria-label="<?php esc_attr_e('End date', 'tfp-dashboard'); ?>">
                    <input class="tfp-payouts-search" type="search" name="payout_search" value="<?php echo esc_attr($payout_data['filters']['search']); ?>" placeholder="<?php esc_attr_e('Search Payouts', 'tfp-dashboard'); ?>">
                    <button type="submit"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button>
                </form>
                <section class="tfp-payouts-stats">
                    <article><small><?php esc_html_e('Available to Payout', 'tfp-dashboard'); ?></small><strong><?php echo wp_kses_post($format_payout($payout_data['totals']['available'], $payout_currency)); ?></strong></article>
                    <article><small><?php esc_html_e('In Transit', 'tfp-dashboard'); ?></small><strong><?php echo wp_kses_post($format_payout($payout_data['totals']['transit'], $payout_currency)); ?></strong></article>
                    <article><small><?php esc_html_e('Reconciled (30d)', 'tfp-dashboard'); ?></small><strong><?php echo wp_kses_post($format_payout($payout_data['totals']['reconciled'], $payout_currency)); ?></strong></article>
                    <article class="is-failed"><small><?php esc_html_e('Failed / Flagged', 'tfp-dashboard'); ?></small><strong><?php echo wp_kses_post($format_payout($payout_data['totals']['failed'], $payout_currency)); ?></strong></article>
                </section>
                <section class="tfp-payouts-table-card"><div class="tfp-payouts-table-toolbar"><label><input type="checkbox" disabled> <?php esc_html_e('Select all on page', 'tfp-dashboard'); ?></label><span><?php echo esc_html(sprintf(__('Showing %1$d payouts', 'tfp-dashboard'), count($payout_data['payouts']))); ?></span></div>
                    <div class="tfp-payouts-table-scroll"><table><thead><tr><th></th><th><?php esc_html_e('Payout ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Net Amount', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Destination', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Recon Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                    <?php foreach ($payout_data['payouts'] as $payout) : $dest = $payout['destination'] ?? null; $dest_label = is_array($dest) ? (($dest['bank_name'] ?? $dest['brand'] ?? 'Bank account') . (empty($dest['last4']) ? '' : ' •' . $dest['last4'])) : __('Stripe external account', 'tfp-dashboard'); $is_selected = $selected_payout && ($selected_payout['id'] ?? '') === ($payout['id'] ?? ''); $recon = $payout['reconciliation_status'] ?? 'not_applicable'; ?>
                        <tr class="<?php echo $is_selected ? 'is-selected' : ''; ?>"><td><input type="radio" disabled <?php checked($is_selected); ?>></td><td><strong><?php echo esc_html($payout['id'] ?? '—'); ?></strong></td><td><?php echo esc_html(!empty($payout['created']) ? wp_date('Y-m-d', (int)$payout['created']) : '—'); ?></td><td><span class="tfp-payout-status is-<?php echo esc_attr($payout['status'] ?? 'unknown'); ?>"><?php echo esc_html(ucwords(str_replace('_',' ', $payout['status'] ?? 'Unknown'))); ?></span></td><td><?php echo wp_kses_post($format_payout($payout['amount'] ?? 0, $payout['currency'] ?? $payout_currency)); ?></td><td><?php echo esc_html($dest_label); ?></td><td><span class="tfp-payout-recon is-<?php echo esc_attr($recon); ?>"><?php echo esc_html(ucwords(str_replace('_',' ', $recon))); ?></span></td><td><a href="<?php echo esc_url(tfp_admin_payments_url(['section'=>'payouts','payout'=>$payout['id']])); ?>" class="tfp-payout-action"><?php esc_html_e('Details', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(tfp_admin_payments_url(['section'=>'payouts','payout'=>$payout['id']]) . '#tfp-payout-included-transactions'); ?>" class="tfp-payout-action is-primary"><?php esc_html_e('Reconcile', 'tfp-dashboard'); ?></a></td></tr>
                    <?php endforeach; if (!$payout_data['payouts']) : ?><tr><td colspan="8" class="tfp-payouts-empty"><?php esc_html_e('No Stripe payouts found for these filters.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                    </tbody></table></div>
                    <div class="tfp-payouts-table-footer"><span><?php esc_html_e('Payouts are read directly from your connected Stripe account.', 'tfp-dashboard'); ?></span></div>
                </section>
                <?php if ($selected_payout) : $dest = $selected_payout['destination'] ?? null; $dest_name = is_array($dest) ? ($dest['bank_name'] ?? $dest['brand'] ?? 'Bank account') : 'Stripe external account'; ?>
                    <section class="tfp-payout-detail"><header><div><h2><?php echo esc_html($selected_payout['id']); ?> <span class="tfp-payout-status is-<?php echo esc_attr($selected_payout['status'] ?? 'unknown'); ?>"><?php echo esc_html(ucwords(str_replace('_',' ', $selected_payout['status'] ?? 'Unknown'))); ?></span></h2><small><?php echo esc_html(!empty($selected_payout['created']) ? wp_date('Y-m-d • H:i', (int)$selected_payout['created']) : ''); ?> · <?php echo esc_html($dest_name); ?></small></div><strong><small><?php esc_html_e('Net amount', 'tfp-dashboard'); ?></small><?php echo wp_kses_post($format_payout($selected_payout['amount'] ?? 0, $selected_payout['currency'] ?? $payout_currency)); ?></strong></header>
                        <h3><?php esc_html_e('Included transactions', 'tfp-dashboard'); ?> (<?php echo esc_html(count($payout_data['transactions'])); ?>)</h3>
                        <?php if ($payout_data['transactions']) : ?><div class="tfp-payout-included" id="tfp-payout-included-transactions"><?php foreach ($payout_data['transactions'] as $transaction) : ?><div><span><strong><?php echo esc_html($transaction['id'] ?? ''); ?></strong><small><?php echo esc_html($transaction['description'] ?? ($transaction['type'] ?? 'Balance transaction')); ?></small></span><strong><?php echo wp_kses_post($format_payout($transaction['net'] ?? $transaction['amount'] ?? 0, $transaction['currency'] ?? $payout_currency)); ?></strong></div><?php endforeach; ?></div><?php else : ?><p class="tfp-payouts-muted"><?php esc_html_e('Stripe has not returned balance transactions for this payout.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        <h3><?php esc_html_e('Timeline', 'tfp-dashboard'); ?></h3><p class="tfp-payout-timeline"><?php echo esc_html(!empty($selected_payout['created']) ? wp_date('Y-m-d', (int)$selected_payout['created']) : ''); ?> · <?php echo esc_html(ucwords(str_replace('_',' ', $selected_payout['status'] ?? 'Pending'))); ?><?php if (!empty($selected_payout['arrival_date'])) : ?> · <?php echo esc_html(sprintf(__('Expected arrival %s', 'tfp-dashboard'), wp_date('Y-m-d', (int)$selected_payout['arrival_date']))); ?><?php endif; ?></p>
                        <p class="tfp-payouts-muted"><?php esc_html_e('This dashboard is read-only for payouts. It will not initiate or resend bank transfers.', 'tfp-dashboard'); ?></p>
                    </section>
                <?php endif; ?>
            <?php else : ?>
            <form class="tfp-payments-filters" method="get" action="<?php echo esc_url(tfp_admin_payments_url()); ?>">
                <select name="product" aria-label="<?php esc_attr_e('Filter by product', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Products', 'tfp-dashboard'); ?></option><?php foreach ($products as $product) : ?><option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($data['product_filter'], $product->get_id()); ?>><?php echo esc_html($product->get_name()); ?></option><?php endforeach; ?></select>
                <select name="period" aria-label="<?php esc_attr_e('Select period', 'tfp-dashboard'); ?>"><option value="4q"><?php esc_html_e('Last 4 Quarters', 'tfp-dashboard'); ?></option><option value="12m"><?php esc_html_e('Last 12 Months', 'tfp-dashboard'); ?></option><option value="all"><?php esc_html_e('All Time', 'tfp-dashboard'); ?></option></select>
                <label class="tfp-payments-date"><input type="date" name="from" value="<?php echo esc_attr($data['from']); ?>" aria-label="<?php esc_attr_e('Start date', 'tfp-dashboard'); ?>"></label>
                <label class="tfp-payments-date"><input type="date" name="to" value="<?php echo esc_attr($data['to']); ?>" aria-label="<?php esc_attr_e('End date', 'tfp-dashboard'); ?>"></label>
                <button class="tfp-payments-apply" type="submit"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button>
                <a class="tfp-payments-export" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=tfp_admin_payments_export'), 'tfp_admin_payments_export')); ?>"><?php esc_html_e('Export CSV', 'tfp-dashboard'); ?></a>
            </form>
            <section class="tfp-payments-stat-grid">
                <article><small><?php esc_html_e('Gross Revenue', 'tfp-dashboard'); ?> (12mo)</small><strong><?php echo wp_kses_post(wc_price($data['gross'])); ?></strong></article>
                <article><small><?php esc_html_e('Net Revenue', 'tfp-dashboard'); ?> (12mo)</small><strong><?php echo wp_kses_post(wc_price($data['net'])); ?></strong></article>
                <article><small><?php esc_html_e('Monthly Revenue', 'tfp-dashboard'); ?> (30d)</small><strong><?php echo wp_kses_post(wc_price($last_30)); ?></strong></article>
                <article class="is-refund"><small><?php esc_html_e('Refunds', 'tfp-dashboard'); ?> (filtered)</small><strong><?php echo wp_kses_post(wc_price(-1 * $data['refunds'])); ?></strong></article>
            </section>
            <section class="tfp-payments-chart-grid">
                <article class="tfp-payments-panel tfp-payments-revenue-panel">
                    <div class="tfp-payments-panel-heading"><h2><?php esc_html_e('Revenue', 'tfp-dashboard'); ?></h2><small><?php esc_html_e('Quarterly gross and net revenue', 'tfp-dashboard'); ?></small></div>
                    <?php
                    $chart_palette = ['#00666e', '#bd9817'];
                    $chart_max = 1;
                    foreach ($data['quarters'] as $q) {
                        $sum = 0;
                        foreach ($data['chart_products'] as $product_row) { $sum += (float) ($q['products'][$product_row['id'] ?: sanitize_title($product_row['name'])] ?? 0); }
                        $chart_max = max($chart_max, $sum);
                    }
                    ?>
                    <div class="tfp-payments-chart">
                        <div class="tfp-payments-y-labels">
                            <?php for ($tick = 4; $tick >= 0; $tick--) : ?><span><?php echo esc_html(wp_strip_all_tags(wc_price($chart_max * $tick / 4))); ?></span><?php endfor; ?>
                        </div>
                        <div class="tfp-payments-bars">
                            <?php if ($data['quarters']) : foreach (array_reverse($data['quarters']) as $quarter) : ?>
                                <?php
                                $segments = [];
                                $quarter_total = 0;
                                foreach ($data['chart_products'] as $product_row) {
                                    $product_key = $product_row['id'] ?: sanitize_title($product_row['name']);
                                    $segment_value = (float) ($quarter['products'][$product_key] ?? 0);
                                    $segments[] = $segment_value;
                                    $quarter_total += $segment_value;
                                }
                                $bar_height = max(0, ($quarter_total / $chart_max) * 100);
                                ?>
                                <div class="tfp-payments-bar-group" title="<?php echo esc_attr($quarter['label'] . ': ' . wp_strip_all_tags(wc_price($quarter_total))); ?>">
                                    <div class="tfp-payments-bar" style="height:<?php echo esc_attr($bar_height); ?>%;background:transparent;flex-direction:column-reverse">
                                        <?php foreach ($segments as $segment_index => $segment_value) : ?>
                                            <span style="display:block;width:100%;height:<?php echo esc_attr($quarter_total > 0 ? ($segment_value / $quarter_total) * 100 : 0); ?>%;background:<?php echo esc_attr($chart_palette[$segment_index]); ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <small><?php echo esc_html($quarter['label']); ?></small>
                                </div>
                            <?php endforeach; else : ?><p class="tfp-payments-empty"><?php esc_html_e('No revenue data available for this period.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="tfp-payments-legend"><?php foreach ($data['chart_products'] as $index => $product_row) : ?><span><i style="background:<?php echo esc_attr($chart_palette[$index]); ?>"></i><?php echo esc_html($product_row['name']); ?></span><?php endforeach; ?></div>
                </article>
                <article class="tfp-payments-panel tfp-payments-breakdown-panel">
                    <h2><?php esc_html_e('Product Breakdown', 'tfp-dashboard'); ?></h2>
                    <?php
                    $breakdown_products = array_slice($data['products'], 0, 2);
                    $product_sum = array_sum(array_column($breakdown_products, 'total'));
                    $palette = ['#00666e', '#bd9817'];
                    $donut_segments = [];
                    $offset = 0;
                    foreach ($breakdown_products as $index => $product_row) {
                        $share = $product_sum > 0 ? ((float) $product_row['total'] / $product_sum) * 100 : 0;
                        $donut_segments[] = $palette[$index] . ' ' . $offset . '% ' . ($offset + $share) . '%';
                        $offset += $share;
                    }
                    $donut_background = $donut_segments ? 'conic-gradient(' . implode(', ', $donut_segments) . ')' : '#e5e5e5';
                    ?>
                    <div class="tfp-payments-product-breakdown">
                        <div class="tfp-payments-donut" style="background:<?php echo esc_attr($donut_background); ?>;"><span></span></div>
                        <div class="tfp-payments-product-list">
                            <?php foreach ($breakdown_products as $index => $product_row) : $share = $product_sum > 0 ? round(((float) $product_row['total'] / $product_sum) * 100) : 0; ?>
                                <div><strong style="color:<?php echo esc_attr($palette[$index]); ?>"><?php echo esc_html($product_row['name']); ?> (<?php echo wp_kses_post(wc_price($product_row['total'])); ?>)</strong><b><?php echo esc_html($share); ?>%</b></div>
                            <?php endforeach; ?>
                            <?php if (!$breakdown_products) : ?><p class="tfp-payments-empty"><?php esc_html_e('No product sales found.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="tfp-payments-methods"><h3><?php esc_html_e('Top Payment Methods', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('Payment gateway totals', 'tfp-dashboard'); ?></p><?php foreach (array_slice($data['methods'], 0, 2, true) as $method => $amount) : ?><div><span><?php echo esc_html($method); ?></span><strong><?php echo wp_kses_post(wc_price($amount)); ?></strong></div><?php endforeach; ?><?php if (!$data['methods']) : ?><p class="tfp-payments-empty"><?php esc_html_e('No payment methods found.', 'tfp-dashboard'); ?></p><?php endif; ?></div>
                </article>
            </section>
            <section class="tfp-payments-panel tfp-payments-transactions">
                <div class="tfp-payments-transactions-heading"><h2><?php esc_html_e('Recent Transactions', 'tfp-dashboard'); ?></h2><div><form method="get" action="<?php echo esc_url(tfp_admin_payments_url()); ?>"><input type="date" name="to" value="<?php echo esc_attr($data['to']); ?>" aria-label="<?php esc_attr_e('Transaction date', 'tfp-dashboard'); ?>"><button type="submit" class="tfp-payments-date-apply"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button></form><a href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'transactions'])); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div></div>
                <div class="tfp-dash-table-wrap"><table class="tfp-dash-table"><thead><tr><th><?php esc_html_e('Transaction ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Item / Product', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Net Amount', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                <?php foreach (array_slice($data['orders'], 0, 3) as $order) : $customer = $order->get_formatted_billing_full_name() ?: __('Guest', 'tfp-dashboard'); $items = array_map(static function ($item) { return $item->get_name(); }, $order->get_items()); $status = $order->get_status(); ?>
                    <tr><td><?php echo esc_html($order->get_transaction_id() ?: 'RF-' . $order->get_order_number()); ?></td><td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '—'); ?></td><td><?php echo esc_html($customer); ?></td><td><?php echo esc_html(implode(', ', $items)); ?></td><td><span class="tfp-payments-status is-<?php echo esc_attr($status); ?>"><?php echo esc_html(wc_get_order_status_name($status)); ?></span></td><td><?php echo wp_kses_post(wc_price((float) $order->get_total())); ?></td><td><a class="tfp-payments-details" href="<?php echo esc_url($order->get_edit_order_url()); ?>"><?php esc_html_e('Details', 'tfp-dashboard'); ?></a></td></tr>
                <?php endforeach; if (!$data['orders']) : ?><tr><td colspan="7" class="tfp-payments-empty"><?php esc_html_e('No transactions found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                </tbody></table></div>
                <div class="tfp-payments-table-footer"><span><?php echo esc_html(sprintf(_n('%s transaction found', '%s transactions found', count($data['orders']), 'tfp-dashboard'), number_format_i18n(count($data['orders'])))); ?></span><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=tfp_admin_payments_export'), 'tfp_admin_payments_export')); ?>"><?php esc_html_e('Export', 'tfp-dashboard'); ?></a></div>
            </section>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php wp_footer(); ?>
</body></html>
