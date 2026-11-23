<?php
/**
 * Admin Payments — Revenue Overview.
 * Uses WooCommerce orders/refunds as the source of truth.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_payments_url($args = []) {
    $url = home_url('/admin-payments/');
    return $args ? add_query_arg($args, $url) : $url;
}

add_filter('pre_handle_404', function ($preempt, $query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-payments') {
        if ($query) { $query->is_404 = false; $query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-payments') { return; }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-payments.php';
    if (is_readable($file)) { include $file; exit; }
}, 1);

function tfp_admin_payments_get_orders($args = []) {
    if (!function_exists('wc_get_orders')) { return []; }
    $defaults = [
        'limit' => 500,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => ['processing', 'completed', 'refunded', 'partially-refunded'],
        'return' => 'objects',
    ];
    return wc_get_orders(wp_parse_args($args, $defaults));
}

function tfp_admin_payments_get_data() {
    $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
    $to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';
    $product_filter = isset($_GET['product']) ? absint($_GET['product']) : 0;
    $orders = tfp_admin_payments_get_orders(['limit' => 500]);
    $filtered = [];
    $gross = 0.0; $refunds = 0.0; $net = 0.0;
    $products = []; $methods = []; $quarters = [];
    foreach ($orders as $order) {
        if (!$order instanceof WC_Order) { continue; }
        $date = $order->get_date_created();
        $ymd = $date ? $date->date('Y-m-d') : '';
        if ($from && $ymd && $ymd < $from) { continue; }
        if ($to && $ymd && $ymd > $to) { continue; }
        if ($product_filter) {
            $contains = false;
            foreach ($order->get_items() as $line) {
                if ((int) $line->get_product_id() === $product_filter || (int) $line->get_variation_id() === $product_filter) { $contains = true; break; }
            }
            if (!$contains) { continue; }
        }
        $order_gross = (float) $order->get_subtotal();
        $order_refund = (float) $order->get_total_refunded();
        $order_net = max(0, (float) $order->get_total());
        $gross += $order_gross;
        $refunds += $order_refund;
        $net += $order_net;
        $method = $order->get_payment_method_title() ?: __('Other', 'tfp-dashboard');
        $methods[$method] = ($methods[$method] ?? 0) + $order_net;
        $qkey = '';
        if ($date) {
            $quarter = 'Q' . ceil((int) $date->date('n') / 3);
            $year = $date->date('Y');
            $qkey = $year . ' ' . $quarter;
            if (!isset($quarters[$qkey])) { $quarters[$qkey] = ['label' => $quarter, 'gross' => 0, 'net' => 0, 'products' => []]; }
            $quarters[$qkey]['gross'] += $order_gross;
            $quarters[$qkey]['net'] += $order_net;
        }
        foreach ($order->get_items() as $line) {
            $pid = (int) $line->get_product_id();
            $key = $pid ?: sanitize_title($line->get_name());
            if (!isset($products[$key])) { $products[$key] = ['id' => $pid, 'name' => $line->get_name(), 'total' => 0]; }
            $line_total = (float) $line->get_total();
            $products[$key]['total'] += $line_total;
            if ($qkey !== '') {
                if (!isset($quarters[$qkey]['products'][$key])) { $quarters[$qkey]['products'][$key] = 0; }
                $quarters[$qkey]['products'][$key] += $line_total;
            }
        }
        $filtered[] = $order;
    }
    uasort($products, static function ($a, $b) { return $b['total'] <=> $a['total']; });
    arsort($methods);
    krsort($quarters);
    return [
        'orders' => $filtered, 'gross' => $gross, 'refunds' => $refunds, 'net' => $net,
        'products' => array_slice(array_values($products), 0, 5),
        'chart_products' => array_slice(array_values($products), 0, 2),
        'methods' => $methods, 'quarters' => array_slice(array_values($quarters), 0, 4),
        'from' => $from, 'to' => $to, 'product_filter' => $product_filter,
    ];
}

add_action('admin_post_tfp_admin_payments_export', function () {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'tfp-dashboard'), '', ['response' => 403]); }
    check_admin_referer('tfp_admin_payments_export');
    $data = tfp_admin_payments_get_data();
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tfp-payment-transactions.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Transaction ID', 'Date', 'Customer', 'Products', 'Status', 'Gross', 'Refunded', 'Net', 'Payment Method']);
    foreach ($data['orders'] as $order) {
        fputcsv($out, [
            $order->get_transaction_id() ?: $order->get_order_number(),
            $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '',
            $order->get_formatted_billing_full_name(),
            implode(', ', array_map(static function ($item) { return $item->get_name(); }, $order->get_items())),
            wc_get_order_status_name($order->get_status()),
            $order->get_subtotal(), $order->get_total_refunded(), $order->get_total(),
            $order->get_payment_method_title(),
        ]);
    }
    fclose($out);
    exit;
});
