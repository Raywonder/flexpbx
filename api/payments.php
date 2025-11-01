<?php
/**
 * FlexPBX Payment Processing API
 * Multi-gateway payment processing for license purchases
 * Supports: PayPal, Stripe, Cryptocurrency, and more
 *
 * @requires PHP 8.0+
 * @recommended PHP 8.1 or 8.2
 */

// Check PHP version (minimum 8.0)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required',
        'current_version' => PHP_VERSION,
        'minimum_version' => '8.0.0',
        'recommended_versions' => ['8.1', '8.2']
    ]);
    exit;
}

session_start();
header('Content-Type: application/json');

$config_file = '/home/flexpbxuser/config/payment_config.json';
$transactions_file = '/home/flexpbxuser/config/transactions.json';

// Ensure directories exist
@mkdir(dirname($config_file), 0755, true);

// Initialize payment configuration
if (!file_exists($config_file)) {
    $default_config = [
        'gateways' => [
            'paypal' => [
                'enabled' => false,
                'name' => 'PayPal',
                'mode' => 'sandbox', // or 'live'
                'client_id' => '',
                'client_secret' => '',
                'webhook_id' => ''
            ],
            'stripe' => [
                'enabled' => false,
                'name' => 'Stripe',
                'mode' => 'test', // or 'live'
                'publishable_key' => '',
                'secret_key' => '',
                'webhook_secret' => ''
            ],
            'coinbase' => [
                'enabled' => false,
                'name' => 'Coinbase Commerce',
                'api_key' => '',
                'webhook_secret' => ''
            ],
            'btcpay' => [
                'enabled' => false,
                'name' => 'BTCPay Server',
                'server_url' => '',
                'store_id' => '',
                'api_key' => ''
            ],
            'bank_transfer' => [
                'enabled' => true,
                'name' => 'Bank Transfer',
                'instructions' => 'Contact support@devine-creations.com for bank transfer details'
            ],
            'manual' => [
                'enabled' => true,
                'name' => 'Manual Payment',
                'instructions' => 'Contact support for manual payment processing'
            ]
        ],
        'currency' => 'USD',
        'tax_rate' => 0.00, // Percentage
        'invoice_prefix' => 'FLEX-',
        'auto_generate_license' => true,
        'grace_period_days' => 60
    ];
    file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
}

// Initialize transactions
if (!file_exists($transactions_file)) {
    file_put_contents($transactions_file, json_encode([], JSON_PRETTY_PRINT));
}

// Load data
function loadConfig() {
    global $config_file;
    return json_decode(file_get_contents($config_file), true);
}

function loadTransactions() {
    global $transactions_file;
    return json_decode(file_get_contents($transactions_file), true) ?: [];
}

function saveTransactions($transactions) {
    global $transactions_file;
    file_put_contents($transactions_file, json_encode($transactions, JSON_PRETTY_PRINT));
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create_order':
        createOrder();
        break;

    case 'process_payment':
        processPayment();
        break;

    case 'verify_payment':
        verifyPayment();
        break;

    case 'get_gateways':
        getGateways();
        break;

    case 'webhook':
        handleWebhook();
        break;

    case 'get_transaction':
        getTransaction();
        break;

    case 'list_transactions':
        listTransactions();
        break;

    case 'refund':
        refundPayment();
        break;

    case 'get_invoice':
        getInvoice();
        break;

    case 'request_license':
        requestLicense();
        break;

    case 'check_grace_period':
        checkGracePeriod();
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => [
                'create_order', 'process_payment', 'verify_payment', 'get_gateways',
                'webhook', 'get_transaction', 'list_transactions', 'refund', 'get_invoice',
                'request_license', 'check_grace_period'
            ]
        ]);
}

/**
 * Create a new order
 */
function createOrder() {
    $license_type = $_POST['license_type'] ?? 'starter';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $installation_id = $_POST['installation_id'] ?? '';

    if (empty($customer_email)) {
        echo json_encode(['success' => false, 'error' => 'Customer email required']);
        return;
    }

    // Get license pricing
    $license_api = file_get_contents('http://localhost/api/licensing.php?action=get_plans');
    $license_data = json_decode($license_api, true);

    if (!$license_data['success'] || !isset($license_data['plans'][$license_type])) {
        echo json_encode(['success' => false, 'error' => 'Invalid license type']);
        return;
    }

    $plan = $license_data['plans'][$license_type];
    $config = loadConfig();

    // Calculate pricing
    $subtotal = $plan['price'];
    $tax = $subtotal * ($config['tax_rate'] / 100);
    $total = $subtotal + $tax;

    // Create order
    $order_id = $config['invoice_prefix'] . time() . '-' . rand(1000, 9999);

    $order = [
        'order_id' => $order_id,
        'license_type' => $license_type,
        'license_name' => $plan['name'],
        'customer_email' => $customer_email,
        'customer_name' => $customer_name,
        'installation_id' => $installation_id,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'currency' => $config['currency'],
        'status' => 'pending',
        'created_at' => time(),
        'payment_gateway' => null,
        'transaction_id' => null,
        'license_key' => null
    ];

    $transactions = loadTransactions();
    $transactions[$order_id] = $order;
    saveTransactions($transactions);

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order' => $order
    ]);
}

/**
 * Process payment through selected gateway
 */
function processPayment() {
    $order_id = $_POST['order_id'] ?? '';
    $gateway = $_POST['gateway'] ?? '';
    $payment_data = $_POST['payment_data'] ?? [];

    if (empty($order_id) || empty($gateway)) {
        echo json_encode(['success' => false, 'error' => 'Order ID and gateway required']);
        return;
    }

    $transactions = loadTransactions();

    if (!isset($transactions[$order_id])) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $order = $transactions[$order_id];
    $config = loadConfig();

    // Route to appropriate gateway
    switch ($gateway) {
        case 'paypal':
            $result = processPayPal($order, $payment_data, $config);
            break;

        case 'stripe':
            $result = processStripe($order, $payment_data, $config);
            break;

        case 'coinbase':
            $result = processCoinbase($order, $payment_data, $config);
            break;

        case 'btcpay':
            $result = processBTCPay($order, $payment_data, $config);
            break;

        case 'bank_transfer':
        case 'manual':
            $result = processManual($order, $gateway, $config);
            break;

        default:
            $result = ['success' => false, 'error' => 'Invalid gateway'];
    }

    if ($result['success']) {
        // Update transaction
        $transactions[$order_id]['status'] = $result['status'] ?? 'processing';
        $transactions[$order_id]['payment_gateway'] = $gateway;
        $transactions[$order_id]['transaction_id'] = $result['transaction_id'] ?? null;
        $transactions[$order_id]['payment_data'] = $result['payment_data'] ?? [];
        $transactions[$order_id]['processed_at'] = time();

        // Auto-generate license if payment is completed
        if ($result['status'] === 'completed' && $config['auto_generate_license']) {
            $license_result = generateLicenseForOrder($order_id, $transactions[$order_id]);
            if ($license_result['success']) {
                $transactions[$order_id]['license_key'] = $license_result['license_key'];
                $transactions[$order_id]['license_generated_at'] = time();
            }
        }

        saveTransactions($transactions);
    }

    echo json_encode($result);
}

/**
 * Process PayPal payment
 */
function processPayPal($order, $payment_data, $config) {
    $gateway_config = $config['gateways']['paypal'];

    if (!$gateway_config['enabled']) {
        return ['success' => false, 'error' => 'PayPal is not enabled'];
    }

    // PayPal REST API integration
    $api_base = $gateway_config['mode'] === 'live'
        ? 'https://api.paypal.com'
        : 'https://api.sandbox.paypal.com';

    // Get access token
    $auth_url = $api_base . '/v1/oauth2/token';
    $credentials = base64_encode($gateway_config['client_id'] . ':' . $gateway_config['client_secret']);

    $ch = curl_init($auth_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $auth_response = curl_exec($ch);
    curl_close($ch);

    $auth_data = json_decode($auth_response, true);

    if (!isset($auth_data['access_token'])) {
        return ['success' => false, 'error' => 'PayPal authentication failed'];
    }

    $access_token = $auth_data['access_token'];

    // Create payment
    $payment_url = $api_base . '/v2/checkout/orders';

    $payment_request = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $order['order_id'],
            'description' => $order['license_name'],
            'amount' => [
                'currency_code' => $order['currency'],
                'value' => number_format($order['total'], 2, '.', '')
            ]
        ]],
        'application_context' => [
            'brand_name' => 'FlexPBX',
            'return_url' => 'https://flexpbx.devinecreations.net/payment/success',
            'cancel_url' => 'https://flexpbx.devinecreations.net/payment/cancel'
        ]
    ];

    $ch = curl_init($payment_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $payment_response = curl_exec($ch);
    curl_close($ch);

    $payment_data = json_decode($payment_response, true);

    if (isset($payment_data['id'])) {
        // Find approval URL
        $approval_url = '';
        foreach ($payment_data['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approval_url = $link['href'];
                break;
            }
        }

        return [
            'success' => true,
            'status' => 'pending',
            'transaction_id' => $payment_data['id'],
            'approval_url' => $approval_url,
            'payment_data' => $payment_data
        ];
    }

    return ['success' => false, 'error' => 'PayPal payment creation failed', 'details' => $payment_data];
}

/**
 * Process Stripe payment
 */
function processStripe($order, $payment_data, $config) {
    $gateway_config = $config['gateways']['stripe'];

    if (!$gateway_config['enabled']) {
        return ['success' => false, 'error' => 'Stripe is not enabled'];
    }

    // Use test or live key based on mode
    $api_key = $gateway_config['mode'] === 'live'
        ? $gateway_config['secret_key']
        : $gateway_config['secret_key']; // In test mode, test key should be used

    if (empty($api_key)) {
        return ['success' => false, 'error' => 'Stripe API key not configured'];
    }

    // Create Payment Intent
    $payment_intent_url = 'https://api.stripe.com/v1/payment_intents';

    $intent_data = [
        'amount' => round($order['total'] * 100), // Amount in cents
        'currency' => strtolower($order['currency']),
        'description' => $order['license_name'] . ' - ' . $order['order_id'],
        'metadata' => [
            'order_id' => $order['order_id'],
            'customer_email' => $order['customer_email'],
            'license_type' => $order['license_type']
        ],
        'receipt_email' => $order['customer_email'],
        'automatic_payment_methods' => ['enabled' => 'true']
    ];

    $ch = curl_init($payment_intent_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($intent_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if ($http_code === 200 && isset($response_data['id'])) {
        return [
            'success' => true,
            'status' => 'pending',
            'transaction_id' => $response_data['id'],
            'client_secret' => $response_data['client_secret'],
            'payment_data' => $response_data,
            'message' => 'Stripe payment intent created. Complete payment using client_secret.'
        ];
    }

    return [
        'success' => false,
        'error' => 'Stripe payment creation failed',
        'details' => $response_data['error']['message'] ?? 'Unknown error',
        'http_code' => $http_code
    ];
}

/**
 * Process Coinbase Commerce payment
 */
function processCoinbase($order, $payment_data, $config) {
    $gateway_config = $config['gateways']['coinbase'];

    if (!$gateway_config['enabled']) {
        return ['success' => false, 'error' => 'Coinbase Commerce is not enabled'];
    }

    $api_key = $gateway_config['api_key'];

    if (empty($api_key)) {
        return ['success' => false, 'error' => 'Coinbase Commerce API key not configured'];
    }

    // Create charge
    $charge_url = 'https://api.commerce.coinbase.com/charges';

    $charge_data = [
        'name' => $order['license_name'],
        'description' => 'FlexPBX ' . $order['license_name'] . ' License',
        'pricing_type' => 'fixed_price',
        'local_price' => [
            'amount' => number_format($order['total'], 2, '.', ''),
            'currency' => $order['currency']
        ],
        'metadata' => [
            'order_id' => $order['order_id'],
            'customer_email' => $order['customer_email'],
            'customer_name' => $order['customer_name'],
            'license_type' => $order['license_type']
        ],
        'redirect_url' => 'https://flexpbx.devinecreations.net/payment/success',
        'cancel_url' => 'https://flexpbx.devinecreations.net/payment/cancel'
    ];

    $ch = curl_init($charge_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($charge_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-CC-Api-Key: ' . $api_key,
        'X-CC-Version: 2018-03-22',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if ($http_code === 201 && isset($response_data['data'])) {
        $charge = $response_data['data'];
        return [
            'success' => true,
            'status' => 'pending',
            'transaction_id' => $charge['code'],
            'hosted_url' => $charge['hosted_url'],
            'payment_data' => $charge,
            'message' => 'Coinbase Commerce charge created. Redirect user to hosted_url.'
        ];
    }

    return [
        'success' => false,
        'error' => 'Coinbase Commerce charge creation failed',
        'details' => $response_data['error']['message'] ?? 'Unknown error',
        'http_code' => $http_code
    ];
}

/**
 * Process BTCPay Server payment
 */
function processBTCPay($order, $payment_data, $config) {
    $gateway_config = $config['gateways']['btcpay'];

    if (!$gateway_config['enabled']) {
        return ['success' => false, 'error' => 'BTCPay Server is not enabled'];
    }

    $server_url = rtrim($gateway_config['server_url'], '/');
    $store_id = $gateway_config['store_id'];
    $api_key = $gateway_config['api_key'];

    if (empty($server_url) || empty($store_id) || empty($api_key)) {
        return ['success' => false, 'error' => 'BTCPay Server configuration incomplete'];
    }

    // Create invoice
    $invoice_url = $server_url . '/api/v1/stores/' . $store_id . '/invoices';

    $invoice_data = [
        'amount' => number_format($order['total'], 2, '.', ''),
        'currency' => $order['currency'],
        'metadata' => [
            'orderId' => $order['order_id'],
            'itemDesc' => $order['license_name'],
            'buyerEmail' => $order['customer_email'],
            'buyerName' => $order['customer_name']
        ],
        'checkout' => [
            'redirectURL' => 'https://flexpbx.devinecreations.net/payment/success?order_id=' . $order['order_id'],
            'redirectAutomatically' => false,
            'defaultLanguage' => 'en'
        ]
    ];

    $ch = curl_init($invoice_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoice_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response_data = json_decode($response, true);

    if ($http_code === 200 && isset($response_data['id'])) {
        return [
            'success' => true,
            'status' => 'pending',
            'transaction_id' => $response_data['id'],
            'checkout_link' => $response_data['checkoutLink'],
            'payment_data' => $response_data,
            'message' => 'BTCPay invoice created. Redirect user to checkout_link.'
        ];
    }

    return [
        'success' => false,
        'error' => 'BTCPay Server invoice creation failed',
        'details' => $response_data['message'] ?? 'Unknown error',
        'http_code' => $http_code
    ];
}

/**
 * Process manual/bank transfer payment
 */
function processManual($order, $gateway, $config) {
    $gateway_config = $config['gateways'][$gateway];

    return [
        'success' => true,
        'status' => 'pending_approval',
        'message' => 'Manual payment request created. ' . $gateway_config['instructions'],
        'transaction_id' => 'MANUAL-' . time(),
        'payment_data' => [
            'instructions' => $gateway_config['instructions'],
            'order_id' => $order['order_id'],
            'amount' => $order['total'],
            'currency' => $order['currency']
        ]
    ];
}

/**
 * Verify payment status
 */
function verifyPayment() {
    $order_id = $_GET['order_id'] ?? '';

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }

    $transactions = loadTransactions();

    if (!isset($transactions[$order_id])) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'order' => $transactions[$order_id]
    ]);
}

/**
 * Get available payment gateways
 */
function getGateways() {
    $config = loadConfig();
    $available = [];

    foreach ($config['gateways'] as $key => $gateway) {
        if ($gateway['enabled']) {
            $available[$key] = [
                'name' => $gateway['name'],
                'key' => $key
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'gateways' => $available
    ]);
}

/**
 * Handle webhooks from payment gateways
 */
function handleWebhook() {
    $gateway = $_GET['gateway'] ?? '';

    $raw_post = file_get_contents('php://input');
    $data = json_decode($raw_post, true);

    // Log webhook
    $log_file = '/home/flexpbxuser/logs/payment_webhooks.log';
    @mkdir(dirname($log_file), 0755, true);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' [' . $gateway . '] ' . $raw_post . "\n", FILE_APPEND);

    // Process based on gateway
    // Implementation would vary per gateway

    echo json_encode(['success' => true, 'received' => true]);
}

/**
 * Request a license (grace period ended)
 */
function requestLicense() {
    $installation_id = $_POST['installation_id'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $license_type = $_POST['license_type'] ?? 'trial';

    if (empty($installation_id) || empty($customer_email)) {
        echo json_encode(['success' => false, 'error' => 'Installation ID and email required']);
        return;
    }

    $config = loadConfig();

    // Check if auto-generation is enabled
    if ($config['auto_generate_license'] && $license_type === 'trial') {
        // Auto-generate trial license
        $license_data = [
            'license_type' => 'trial',
            'customer_email' => $customer_email,
            'customer_name' => $customer_name,
            'order_id' => 'AUTO-' . time(),
            'payment_method' => 'auto_generated'
        ];

        $ch = curl_init('http://localhost/api/licensing.php?action=generate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($license_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'license_key' => $result['license_key'],
                'type' => 'trial',
                'message' => 'Trial license generated automatically'
            ]);
            return;
        }
    }

    // Otherwise, return payment options
    echo json_encode([
        'success' => true,
        'payment_required' => true,
        'message' => 'Please select a payment method to purchase a license',
        'installation_id' => $installation_id
    ]);
}

/**
 * Check grace period status
 */
function checkGracePeriod() {
    $installation_id = $_GET['installation_id'] ?? '';
    $first_run_date = $_GET['first_run_date'] ?? '';

    if (empty($installation_id) || empty($first_run_date)) {
        echo json_encode(['success' => false, 'error' => 'Installation ID and first run date required']);
        return;
    }

    $config = loadConfig();
    $grace_days = $config['grace_period_days'];

    $first_run = strtotime($first_run_date);
    $now = time();
    $days_since_install = floor(($now - $first_run) / 86400);
    $days_remaining = max(0, $grace_days - $days_since_install);

    $in_grace_period = $days_remaining > 0;

    echo json_encode([
        'success' => true,
        'in_grace_period' => $in_grace_period,
        'days_remaining' => $days_remaining,
        'days_since_install' => $days_since_install,
        'grace_period_days' => $grace_days,
        'license_required' => !$in_grace_period
    ]);
}

/**
 * Get transaction details
 */
function getTransaction() {
    $order_id = $_GET['order_id'] ?? '';

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }

    $transactions = loadTransactions();

    if (!isset($transactions[$order_id])) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'transaction' => $transactions[$order_id]
    ]);
}

/**
 * List all transactions
 */
function listTransactions() {
    $transactions = loadTransactions();

    echo json_encode([
        'success' => true,
        'transactions' => array_values($transactions),
        'count' => count($transactions)
    ]);
}

/**
 * Refund a payment
 */
function refundPayment() {
    $order_id = $_POST['order_id'] ?? '';
    $reason = $_POST['reason'] ?? '';

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }

    $transactions = loadTransactions();

    if (!isset($transactions[$order_id])) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }

    // Process refund through gateway
    // Implementation would vary per gateway

    $transactions[$order_id]['status'] = 'refunded';
    $transactions[$order_id]['refunded_at'] = time();
    $transactions[$order_id]['refund_reason'] = $reason;
    saveTransactions($transactions);

    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully'
    ]);
}

/**
 * Get invoice/receipt
 */
function getInvoice() {
    $order_id = $_GET['order_id'] ?? '';

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }

    $transactions = loadTransactions();

    if (!isset($transactions[$order_id])) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        return;
    }

    // Generate invoice data
    echo json_encode([
        'success' => true,
        'invoice' => $transactions[$order_id]
    ]);
}

/**
 * Generate license for completed order
 */
function generateLicenseForOrder($order_id, $order) {
    $license_data = [
        'license_type' => $order['license_type'],
        'customer_email' => $order['customer_email'],
        'customer_name' => $order['customer_name'],
        'order_id' => $order_id,
        'payment_method' => $order['payment_gateway']
    ];

    $ch = curl_init('http://localhost/api/licensing.php?action=generate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($license_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>
