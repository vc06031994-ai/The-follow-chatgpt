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
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], file_exists(dirname(__DIR__) . '/assets/css/admin-dashboard.css') ? (string) filemtime(dirname(__DIR__) . '/assets/css/admin-dashboard.css') : $dash_ver);
wp_enqueue_style('tfp-admin-payments', $dash_url . 'assets/css/admin-payments.css', ['tfp-admin-dashboard'], file_exists($css_path) ? (string) filemtime($css_path) : $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $dash_ver, true);
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
        <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Payments', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Overview', 'tfp-dashboard'); ?></strong></div>
        <header class="tfp-payments-page-heading"><h1><?php esc_html_e('Payments — Revenue Overview', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Revenue metrics, product breakdown, and recent transactions', 'tfp-dashboard'); ?></p></header>
        <main class="tfp-payments-content">
            <nav class="tfp-payments-tabs" aria-label="<?php esc_attr_e('Payment sections', 'tfp-dashboard'); ?>">
                <a class="is-active" href="<?php echo esc_url(tfp_admin_payments_url()); ?>"><?php esc_html_e('Revenue Overview', 'tfp-dashboard'); ?></a>
                <a href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'transactions'])); ?>"><?php esc_html_e('Transaction Ledger', 'tfp-dashboard'); ?></a>
                
                <a href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'payouts'])); ?>"><?php esc_html_e('Payouts', 'tfp-dashboard'); ?></a>
                <a href="<?php echo esc_url(tfp_admin_payments_url(['section' => 'refunds'])); ?>"><?php esc_html_e('Refund History', 'tfp-dashboard'); ?></a>
            </nav>
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
        </main>
    </div>
</div>
<?php wp_footer(); ?>
</body></html>
