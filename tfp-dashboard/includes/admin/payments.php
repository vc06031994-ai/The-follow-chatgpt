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
    // Include every registered WooCommerce order status in the transaction list.
    $available_statuses = function_exists('wc_get_order_statuses')
        ? array_keys(wc_get_order_statuses())
        : ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-refunded', 'wc-failed', 'wc-cancelled'];

    $defaults = [
        'limit' => 500,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => $available_statuses,
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

    // Pre-fill the current year's four quarters so empty quarters still appear on the chart.
    $chart_year = (int) current_time('Y');
    for ($quarter_number = 1; $quarter_number <= 4; $quarter_number++) {
        $quarter_key = $chart_year . ' Q' . $quarter_number;
        $quarters[$quarter_key] = [
            'label' => 'Q' . $quarter_number,
            'gross' => 0,
            'net' => 0,
            'products' => [],
        ];
    }
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
        // Keep every matching order visible in the ledger, but count only paid orders in revenue metrics.
        $filtered[] = $order;
        $revenue_statuses = ['processing', 'completed', 'refunded', 'partially-refunded'];
        if (!in_array($order->get_status(), $revenue_statuses, true)) {
            continue;
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


/**
 * Read-only Stripe payout data for the Admin Payments > Payouts dashboard.
 * All monetary values returned by Stripe are converted from minor units.
 */
function tfp_admin_payments_stripe_decimal($minor_amount) {
    $decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 2;
    return ((float) $minor_amount) / pow(10, max(0, $decimals));
}

function tfp_admin_payments_get_stripe_payout_data() {
    $result = [
        'configured' => function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured(),
        'error' => '',
        'balance' => [],
        'payouts' => [],
        'selected' => null,
        'transactions' => [],
        'totals' => ['available' => 0, 'transit' => 0, 'reconciled' => 0, 'failed' => 0],
        'filters' => [
            'status' => sanitize_key(wp_unslash($_GET['payout_status'] ?? '')),
            'from' => sanitize_text_field(wp_unslash($_GET['payout_from'] ?? '')),
            'to' => sanitize_text_field(wp_unslash($_GET['payout_to'] ?? '')),
            'search' => sanitize_text_field(wp_unslash($_GET['payout_search'] ?? '')),
        ],
    ];

    if (!$result['configured'] || !function_exists('tfp_stripe_api')) {
        $result['error'] = __('Stripe is not configured. Add the API keys in Settings → TFP Stripe to load live payout data.', 'tfp-dashboard');
        return $result;
    }

    $balance = tfp_stripe_api('GET', 'balance');
    if (is_wp_error($balance)) {
        $result['error'] = $balance->get_error_message();
        return $result;
    }
    $result['balance'] = $balance;
    $metric_currency = strtolower(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'usd');
    foreach (($balance['available'] ?? []) as $entry) {
        if (strtolower((string) ($entry['currency'] ?? '')) === $metric_currency) {
            $result['totals']['available'] += (int) ($entry['amount'] ?? 0);
        }
    }

    $params = ['limit' => 100, 'expand' => ['data.destination']];
    if ($result['filters']['from'] !== '') {
        $timestamp = strtotime($result['filters']['from'] . ' 00:00:00');
        if ($timestamp) { $params['created[gte]'] = $timestamp; }
    }
    if ($result['filters']['to'] !== '') {
        $timestamp = strtotime($result['filters']['to'] . ' 23:59:59');
        if ($timestamp) { $params['created[lte]'] = $timestamp; }
    }

    $payout_response = tfp_stripe_api('GET', 'payouts', $params);
    if (is_wp_error($payout_response)) {
        $result['error'] = $payout_response->get_error_message();
        return $result;
    }

    $payouts = $payout_response['data'] ?? [];
    if ($result['filters']['status'] !== '') {
        $payouts = array_values(array_filter($payouts, static function ($payout) use ($result) { return (string) ($payout['status'] ?? '') === $result['filters']['status']; }));
    }
    $reconciliation_filter = sanitize_key(wp_unslash($_GET['payout_reconciliation'] ?? ''));
    if ($reconciliation_filter !== '') {
        $payouts = array_values(array_filter($payouts, static function ($payout) use ($reconciliation_filter) {
            $status = (string) ($payout['reconciliation_status'] ?? 'not_applicable');
            return $reconciliation_filter === 'reconciled' ? in_array($status, ['completed', 'reconciled'], true) : !in_array($status, ['completed', 'reconciled'], true);
        }));
    }
    $search = strtolower($result['filters']['search']);
    if ($search !== '') {
        $payouts = array_values(array_filter($payouts, static function ($payout) use ($search) {
            return strpos(strtolower((string) ($payout['id'] ?? '')), $search) !== false
                || strpos(strtolower((string) ($payout['description'] ?? '')), $search) !== false;
        }));
    }
    $result['payouts'] = $payouts;

    $thirty_days_ago = current_time('timestamp') - (30 * DAY_IN_SECONDS);
    foreach ($payouts as $payout) {
        $amount = (int) ($payout['amount'] ?? 0);
        $status = (string) ($payout['status'] ?? '');
        if (strtolower((string) ($payout['currency'] ?? '')) !== $metric_currency) { continue; }
        if (in_array($status, ['pending', 'in_transit'], true)) {
            $result['totals']['transit'] += $amount;
        } elseif ($status === 'paid' && (int) ($payout['created'] ?? 0) >= $thirty_days_ago) {
            $result['totals']['reconciled'] += $amount;
        } elseif (in_array($status, ['failed', 'canceled'], true)) {
            $result['totals']['failed'] += $amount;
        }
    }

    $selected_id = sanitize_text_field(wp_unslash($_GET['payout'] ?? ''));
    if ($selected_id === '' && !empty($payouts[0]['id'])) {
        $selected_id = (string) $payouts[0]['id'];
    }
    if ($selected_id !== '' && preg_match('/^po_[A-Za-z0-9]+$/', $selected_id)) {
        $selected = tfp_stripe_api('GET', 'payouts/' . rawurlencode($selected_id), [
            'expand' => ['destination', 'balance_transaction'],
        ]);
        if (!is_wp_error($selected)) {
            $result['selected'] = $selected;
            $transactions = tfp_stripe_api('GET', 'balance_transactions', [
                'payout' => $selected_id,
                'limit' => 100,
            ]);
            if (!is_wp_error($transactions)) {
                $result['transactions'] = $transactions['data'] ?? [];
            }
        }
    }

    return $result;
}


/**
 * Download a remittance CSV using the live Stripe payout and balance transactions.
 */
add_action('admin_post_tfp_admin_payout_remittance', function () {
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('Access denied.', 'tfp-dashboard'), '', ['response' => 403]);
    }
    $payout_id = sanitize_text_field(wp_unslash($_GET['payout'] ?? ''));
    if (!preg_match('/^po_[A-Za-z0-9]+$/', $payout_id)) {
        wp_die(esc_html__('Invalid payout.', 'tfp-dashboard'), '', ['response' => 400]);
    }
    check_admin_referer('tfp_admin_payout_remittance_' . $payout_id);
    if (!function_exists('tfp_stripe_api') || !function_exists('tfp_stripe_is_configured') || !tfp_stripe_is_configured()) {
        wp_die(esc_html__('Stripe is not configured.', 'tfp-dashboard'), '', ['response' => 503]);
    }
    $payout = tfp_stripe_api('GET', 'payouts/' . rawurlencode($payout_id), ['expand' => ['destination']]);
    if (is_wp_error($payout)) {
        wp_die(esc_html($payout->get_error_message()), '', ['response' => 502]);
    }
    $transactions = tfp_stripe_api('GET', 'balance_transactions', ['payout' => $payout_id, 'limit' => 100]);
    if (is_wp_error($transactions)) {
        wp_die(esc_html($transactions->get_error_message()), '', ['response' => 502]);
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=remittance-' . sanitize_file_name($payout_id) . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Payout ID', 'Created', 'Status', 'Currency', 'Payout Amount', 'Transaction ID', 'Description', 'Type', 'Gross', 'Fee', 'Net']);
    $created = !empty($payout['created']) ? wp_date('Y-m-d H:i:s', (int) $payout['created']) : '';
    $currency = strtoupper((string) ($payout['currency'] ?? ''));
    $rows = $transactions['data'] ?? [];
    if (!$rows) {
        fputcsv($out, [$payout_id, $created, $payout['status'] ?? '', $currency, tfp_admin_payments_stripe_decimal($payout['amount'] ?? 0), '', 'No balance transactions returned by Stripe', '', '', '', '']);
    } else {
        foreach ($rows as $row) {
            fputcsv($out, [
                $payout_id, $created, $payout['status'] ?? '', $currency,
                tfp_admin_payments_stripe_decimal($payout['amount'] ?? 0),
                $row['id'] ?? '', $row['description'] ?? '', $row['type'] ?? '',
                tfp_admin_payments_stripe_decimal($row['amount'] ?? 0),
                tfp_admin_payments_stripe_decimal($row['fee'] ?? 0),
                tfp_admin_payments_stripe_decimal($row['net'] ?? 0),
            ]);
        }
    }
    fclose($out);
    exit;
});
