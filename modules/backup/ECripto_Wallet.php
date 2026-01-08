<?php
/**
 * eCripto Wallet Integration for FlexPBX
 * Handles wallet connection, payments, and balance checks
 *
 * @package FlexPBX
 * @version 1.0.0
 */

class ECripto_Wallet {

    private $api_endpoint = 'https://api.ecripto.app/v1';
    private $app_url = 'https://ecripto.app';
    private $api_key;
    private $wallet_address;

    public function __construct($config = []) {
        $this->api_key = $config['api_key'] ?? '';
        $this->wallet_address = $config['wallet_address'] ?? '';
    }

    /**
     * Generate wallet connection request
     */
    public function generateConnectRequest($callback_url) {
        $request_id = bin2hex(random_bytes(16));

        $payload = [
            'request_id' => $request_id,
            'app_name' => 'FlexPBX Backup',
            'app_icon' => 'https://flexpbx.devinecreations.net/images/logo.png',
            'callback_url' => $callback_url,
            'permissions' => ['read_balance', 'send_payment'],
            'created' => time(),
            'expires' => time() + 3600 // 1 hour
        ];

        // Store request for verification
        $this->storeConnectRequest($request_id, $payload);

        return [
            'request_id' => $request_id,
            'connect_url' => "{$this->app_url}/connect?request={$request_id}",
            'qr_data' => json_encode($payload),
            'expires_in' => 3600
        ];
    }

    /**
     * Verify wallet connection callback
     */
    public function verifyConnection($request_id, $signature, $wallet_address) {
        $stored = $this->getConnectRequest($request_id);

        if (!$stored || $stored['expires'] < time()) {
            return ['success' => false, 'error' => 'Request expired'];
        }

        // Verify signature from eCripto app
        $valid = $this->verifySignature($request_id, $signature, $wallet_address);

        if ($valid) {
            $this->wallet_address = $wallet_address;
            $this->storeWalletConnection($wallet_address);

            return [
                'success' => true,
                'wallet_address' => $wallet_address,
                'balance' => $this->getBalance($wallet_address)
            ];
        }

        return ['success' => false, 'error' => 'Invalid signature'];
    }

    /**
     * Get wallet balance
     */
    public function getBalance($wallet_address = null) {
        $address = $wallet_address ?? $this->wallet_address;

        if (empty($address)) {
            return ['error' => 'No wallet connected'];
        }

        $response = $this->apiRequest('GET', "/wallet/{$address}/balance");

        if ($response['success']) {
            return [
                'ecripto' => $response['data']['balance'] ?? 0,
                'ecripto_formatted' => number_format($response['data']['balance'] ?? 0, 2) . ' ECR',
                'usd_value' => $response['data']['usd_value'] ?? 0,
                'pending' => $response['data']['pending'] ?? 0
            ];
        }

        return ['ecripto' => 0, 'error' => $response['error'] ?? 'Failed to get balance'];
    }

    /**
     * Create payment request
     */
    public function createPayment($amount_ecripto, $description, $metadata = []) {
        $payment_id = 'PAY-' . strtoupper(bin2hex(random_bytes(8)));

        $payload = [
            'payment_id' => $payment_id,
            'amount' => $amount_ecripto,
            'currency' => 'ECR',
            'description' => $description,
            'recipient' => $this->getServiceWallet(),
            'metadata' => $metadata,
            'created' => time(),
            'expires' => time() + 1800 // 30 minutes
        ];

        // Store payment request
        $this->storePaymentRequest($payment_id, $payload);

        return [
            'payment_id' => $payment_id,
            'amount' => $amount_ecripto,
            'amount_formatted' => number_format($amount_ecripto, 2) . ' ECR',
            'pay_url' => "{$this->app_url}/pay/{$payment_id}",
            'qr_data' => json_encode($payload),
            'expires_in' => 1800
        ];
    }

    /**
     * Process direct wallet payment
     */
    public function processPayment($payment_id, $from_wallet, $signature) {
        $payment = $this->getPaymentRequest($payment_id);

        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found'];
        }

        if ($payment['expires'] < time()) {
            return ['success' => false, 'error' => 'Payment expired'];
        }

        // Verify signature and process
        $response = $this->apiRequest('POST', '/payments/process', [
            'payment_id' => $payment_id,
            'from_wallet' => $from_wallet,
            'to_wallet' => $payment['recipient'],
            'amount' => $payment['amount'],
            'signature' => $signature
        ]);

        if ($response['success']) {
            $this->markPaymentComplete($payment_id, $response['data']['transaction_id']);

            return [
                'success' => true,
                'transaction_id' => $response['data']['transaction_id'],
                'amount' => $payment['amount'],
                'timestamp' => time()
            ];
        }

        return ['success' => false, 'error' => $response['error'] ?? 'Payment failed'];
    }

    /**
     * Setup auto-renewal subscription
     */
    public function setupAutoRenewal($wallet_address, $plan_id, $amount_monthly) {
        $subscription_id = 'SUB-' . strtoupper(bin2hex(random_bytes(8)));

        $payload = [
            'subscription_id' => $subscription_id,
            'wallet_address' => $wallet_address,
            'plan_id' => $plan_id,
            'amount' => $amount_monthly,
            'frequency' => 'monthly',
            'next_billing' => strtotime('+1 month'),
            'status' => 'pending_approval'
        ];

        // Request approval from wallet
        $approval = $this->requestSubscriptionApproval($wallet_address, $payload);

        return [
            'subscription_id' => $subscription_id,
            'approval_url' => $approval['url'],
            'status' => 'pending_approval'
        ];
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory($wallet_address = null, $limit = 20) {
        $address = $wallet_address ?? $this->wallet_address;

        $response = $this->apiRequest('GET', "/wallet/{$address}/transactions", [
            'limit' => $limit,
            'type' => 'flexpbx_backup'
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Calculate rewards
     */
    public function calculateRewards($plan_id, $payment_type) {
        $rewards = [
            'referral_available' => true,
            'referral_bonus' => 50, // ECR
            'annual_bonus_percent' => 10
        ];

        if ($payment_type === 'yearly') {
            $rewards['annual_bonus_applied'] = true;
        }

        return $rewards;
    }

    /**
     * API request helper
     */
    private function apiRequest($method, $endpoint, $data = []) {
        $url = $this->api_endpoint . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'X-App-ID: flexpbx-backup'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        return [
            'success' => $http_code >= 200 && $http_code < 300,
            'http_code' => $http_code,
            'data' => $result['data'] ?? $result,
            'error' => $result['error'] ?? null
        ];
    }

    private function getServiceWallet() {
        return 'ECR-FLEXPBX-BACKUP-001'; // Service wallet address
    }

    private function storeConnectRequest($id, $data) {
        file_put_contents("/tmp/ecripto_connect_{$id}.json", json_encode($data));
    }

    private function getConnectRequest($id) {
        $file = "/tmp/ecripto_connect_{$id}.json";
        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function storePaymentRequest($id, $data) {
        file_put_contents("/tmp/ecripto_payment_{$id}.json", json_encode($data));
    }

    private function getPaymentRequest($id) {
        $file = "/tmp/ecripto_payment_{$id}.json";
        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }

    private function markPaymentComplete($id, $tx_id) {
        $file = "/tmp/ecripto_payment_{$id}.json";
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $data['status'] = 'complete';
            $data['transaction_id'] = $tx_id;
            file_put_contents($file, json_encode($data));
        }
    }

    private function storeWalletConnection($address) {
        // Store in database/config
    }

    private function verifySignature($request_id, $signature, $wallet) {
        // Verify cryptographic signature
        return true; // Simplified for now
    }

    private function requestSubscriptionApproval($wallet, $payload) {
        return ['url' => "{$this->app_url}/approve-subscription?id={$payload['subscription_id']}"];
    }
}
