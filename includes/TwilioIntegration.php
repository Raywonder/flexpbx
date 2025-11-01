<?php
/**
 * Twilio API Integration for FlexPBX
 * Full Twilio API integration including Voice, SMS, SIP Trunking, and more
 *
 * @version 2.0
 * @author FlexPBX System
 */

class TwilioIntegration {
    private $accountSid;
    private $authToken;
    private $twilioNumber;
    private $baseUrl = 'https://api.twilio.com/2010-04-01';
    private $config;
    private $logFile;

    /**
     * Constructor
     * @param array $config Configuration array with Twilio credentials
     */
    public function __construct($config = null) {
        $this->logFile = '/home/flexpbxuser/logs/twilio.log';

        if ($config) {
            $this->config = $config;
            $this->accountSid = $config['account_sid'] ?? null;
            $this->authToken = $config['auth_token'] ?? null;
            $this->twilioNumber = $config['twilio_number'] ?? null;
        } else {
            $this->loadConfig();
        }
    }

    /**
     * Load Twilio configuration from file
     */
    private function loadConfig() {
        $configFile = '/home/flexpbxuser/config/twilio_config.json';

        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->accountSid = $config['account_sid'] ?? null;
            $this->authToken = $config['auth_token'] ?? null;
            $this->twilioNumber = $config['twilio_number'] ?? null;
            $this->config = $config;
        }
    }

    /**
     * Save Twilio configuration
     */
    public function saveConfig($config) {
        $configFile = '/home/flexpbxuser/config/twilio_config.json';

        // Create config directory if doesn't exist
        $configDir = dirname($configFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $config['updated_at'] = date('Y-m-d H:i:s');

        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
            $this->config = $config;
            $this->accountSid = $config['account_sid'];
            $this->authToken = $config['auth_token'];
            $this->twilioNumber = $config['twilio_number'] ?? null;
            return true;
        }

        return false;
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO') {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Make API request to Twilio
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        if (!$this->accountSid || !$this->authToken) {
            throw new Exception('Twilio credentials not configured');
        }

        $url = $this->baseUrl . '/Accounts/' . $this->accountSid . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->accountSid . ':' . $this->authToken);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        } elseif ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("API Error: {$error}", 'ERROR');
            throw new Exception("Twilio API Error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['message'] ?? 'Unknown error';
            $this->log("API Error {$httpCode}: {$errorMsg}", 'ERROR');
            throw new Exception("Twilio API Error: {$errorMsg}");
        }

        return $result;
    }

    // ==================== VOICE CALLS ====================

    /**
     * Make an outbound call
     */
    public function makeCall($to, $from = null, $twimlUrl = null, $statusCallback = null) {
        $from = $from ?? $this->twilioNumber;

        if (!$from) {
            throw new Exception('From number not specified');
        }

        $data = [
            'To' => $to,
            'From' => $from,
            'Url' => $twimlUrl ?? $this->config['default_twiml_url'] ?? '',
        ];

        if ($statusCallback) {
            $data['StatusCallback'] = $statusCallback;
            $data['StatusCallbackEvent'] = ['initiated', 'ringing', 'answered', 'completed'];
        }

        $this->log("Making call from {$from} to {$to}");
        return $this->request('/Calls.json', 'POST', $data);
    }

    /**
     * Get call details
     */
    public function getCall($callSid) {
        return $this->request("/Calls/{$callSid}.json");
    }

    /**
     * List calls with filters
     */
    public function listCalls($filters = []) {
        return $this->request('/Calls.json', 'GET', $filters);
    }

    /**
     * Update call (modify in progress)
     */
    public function updateCall($callSid, $data) {
        return $this->request("/Calls/{$callSid}.json", 'POST', $data);
    }

    /**
     * Hangup call
     */
    public function hangupCall($callSid) {
        return $this->updateCall($callSid, ['Status' => 'completed']);
    }

    // ==================== SMS/MMS ====================

    /**
     * Send SMS message
     */
    public function sendSMS($to, $body, $from = null, $mediaUrl = null) {
        $from = $from ?? $this->twilioNumber;

        if (!$from) {
            throw new Exception('From number not specified');
        }

        $data = [
            'To' => $to,
            'From' => $from,
            'Body' => $body
        ];

        if ($mediaUrl) {
            $data['MediaUrl'] = is_array($mediaUrl) ? $mediaUrl : [$mediaUrl];
        }

        $this->log("Sending SMS from {$from} to {$to}");
        return $this->request('/Messages.json', 'POST', $data);
    }

    /**
     * Get message details
     */
    public function getMessage($messageSid) {
        return $this->request("/Messages/{$messageSid}.json");
    }

    /**
     * List messages with filters
     */
    public function listMessages($filters = []) {
        return $this->request('/Messages.json', 'GET', $filters);
    }

    // ==================== PHONE NUMBERS ====================

    /**
     * List available phone numbers
     */
    public function searchAvailableNumbers($countryCode = 'US', $filters = []) {
        $endpoint = "/AvailablePhoneNumbers/{$countryCode}/Local.json";
        return $this->request($endpoint, 'GET', $filters);
    }

    /**
     * Purchase phone number
     */
    public function purchaseNumber($phoneNumber, $config = []) {
        $data = array_merge([
            'PhoneNumber' => $phoneNumber
        ], $config);

        $this->log("Purchasing number: {$phoneNumber}");
        return $this->request('/IncomingPhoneNumbers.json', 'POST', $data);
    }

    /**
     * List owned phone numbers
     */
    public function listPhoneNumbers($filters = []) {
        return $this->request('/IncomingPhoneNumbers.json', 'GET', $filters);
    }

    /**
     * Update phone number configuration
     */
    public function updatePhoneNumber($numberSid, $config) {
        return $this->request("/IncomingPhoneNumbers/{$numberSid}.json", 'POST', $config);
    }

    /**
     * Release phone number
     */
    public function releasePhoneNumber($numberSid) {
        $this->log("Releasing number SID: {$numberSid}");
        return $this->request("/IncomingPhoneNumbers/{$numberSid}.json", 'DELETE');
    }

    // ==================== RECORDINGS ====================

    /**
     * Get recording
     */
    public function getRecording($recordingSid) {
        return $this->request("/Recordings/{$recordingSid}.json");
    }

    /**
     * List recordings
     */
    public function listRecordings($filters = []) {
        return $this->request('/Recordings.json', 'GET', $filters);
    }

    /**
     * Delete recording
     */
    public function deleteRecording($recordingSid) {
        return $this->request("/Recordings/{$recordingSid}.json", 'DELETE');
    }

    /**
     * Get recording URL
     */
    public function getRecordingUrl($recordingSid, $format = 'mp3') {
        return "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Recordings/{$recordingSid}.{$format}";
    }

    // ==================== SIP ====================

    /**
     * Create SIP domain
     */
    public function createSipDomain($domainName, $config = []) {
        $data = array_merge([
            'DomainName' => $domainName
        ], $config);

        return $this->request('/SIP/Domains.json', 'POST', $data);
    }

    /**
     * List SIP domains
     */
    public function listSipDomains() {
        return $this->request('/SIP/Domains.json');
    }

    /**
     * Create SIP credential list
     */
    public function createSipCredentialList($domainSid, $friendlyName) {
        $data = ['FriendlyName' => $friendlyName];
        return $this->request("/SIP/Domains/{$domainSid}/CredentialLists.json", 'POST', $data);
    }

    /**
     * Add SIP credential
     */
    public function addSipCredential($domainSid, $credentialListSid, $username, $password) {
        $data = [
            'Username' => $username,
            'Password' => $password
        ];
        return $this->request("/SIP/Domains/{$domainSid}/CredentialLists/{$credentialListSid}/Credentials.json", 'POST', $data);
    }

    // ==================== ACCOUNT INFO ====================

    /**
     * Get account information
     */
    public function getAccount() {
        return $this->request('.json');
    }

    /**
     * Get account balance
     */
    public function getBalance() {
        return $this->request('/Balance.json');
    }

    /**
     * List usage records
     */
    public function getUsage($category = 'all-time', $filters = []) {
        return $this->request("/Usage/Records/{$category}.json", 'GET', $filters);
    }

    // ==================== TWIML GENERATION ====================

    /**
     * Generate TwiML response
     */
    public function generateTwiML($actions) {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
        $twiml .= '<Response>';

        foreach ($actions as $action) {
            $verb = $action['verb'];
            $attributes = $action['attributes'] ?? [];
            $content = $action['content'] ?? '';

            $twiml .= '<' . $verb;
            foreach ($attributes as $key => $value) {
                $twiml .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
            $twiml .= '>';

            if ($content) {
                $twiml .= htmlspecialchars($content);
            }

            $twiml .= '</' . $verb . '>';
        }

        $twiml .= '</Response>';
        return $twiml;
    }

    // ==================== WEBHOOK VALIDATION ====================

    /**
     * Validate Twilio webhook signature
     */
    public function validateRequest($url, $postData, $signature) {
        if (!$this->authToken) {
            return false;
        }

        // Build the data string
        $data = $url;
        ksort($postData);
        foreach ($postData as $key => $value) {
            $data .= $key . $value;
        }

        // Hash data with auth token
        $expectedSignature = base64_encode(hash_hmac('sha1', $data, $this->authToken, true));

        return hash_equals($expectedSignature, $signature);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Format phone number to E.164
     */
    public function formatPhoneNumber($number, $defaultCountryCode = '+1') {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Add country code if not present
        if (strlen($number) === 10) {
            $number = $defaultCountryCode . $number;
        } elseif (strlen($number) === 11 && substr($number, 0, 1) === '1') {
            $number = '+' . $number;
        }

        return $number;
    }

    /**
     * Get call cost estimate
     */
    public function estimateCallCost($to, $from = null, $duration = 60) {
        // Simplified cost estimation
        // Actual costs vary by destination
        $perMinuteRate = 0.014; // $0.014/min for US/Canada
        $minutes = ceil($duration / 60);
        return $minutes * $perMinuteRate;
    }

    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $account = $this->getAccount();
            return [
                'success' => true,
                'account' => $account['friendly_name'] ?? 'Unknown',
                'status' => $account['status'] ?? 'unknown'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // ==================== E911 EMERGENCY ADDRESSES ====================

    /**
     * Create emergency address for E911
     */
    public function createEmergencyAddress($friendlyName, $street, $city, $region, $postalCode, $isoCountry = 'US') {
        $data = [
            'FriendlyName' => $friendlyName,
            'CustomerName' => $friendlyName,
            'Street' => $street,
            'City' => $city,
            'Region' => $region,
            'PostalCode' => $postalCode,
            'IsoCountry' => $isoCountry,
            'EmergencyEnabled' => true
        ];

        $this->log("Creating E911 address: {$friendlyName}");
        return $this->request('/Addresses.json', 'POST', $data);
    }

    /**
     * Get emergency address details
     */
    public function getEmergencyAddress($addressSid) {
        return $this->request("/Addresses/{$addressSid}.json");
    }

    /**
     * Update emergency address
     */
    public function updateEmergencyAddress($addressSid, $data) {
        return $this->request("/Addresses/{$addressSid}.json", 'POST', $data);
    }

    /**
     * Delete emergency address
     */
    public function deleteEmergencyAddress($addressSid) {
        $this->log("Deleting E911 address: {$addressSid}");
        return $this->request("/Addresses/{$addressSid}.json", 'DELETE');
    }

    /**
     * List emergency addresses
     */
    public function listEmergencyAddresses($filters = []) {
        return $this->request('/Addresses.json', 'GET', $filters);
    }

    /**
     * Assign emergency address to phone number
     */
    public function assignEmergencyAddress($phoneNumberSid, $addressSid) {
        $data = [
            'EmergencyAddressSid' => $addressSid
        ];

        $this->log("Assigning E911 address {$addressSid} to phone {$phoneNumberSid}");
        return $this->request("/IncomingPhoneNumbers/{$phoneNumberSid}.json", 'POST', $data);
    }
}
?>
