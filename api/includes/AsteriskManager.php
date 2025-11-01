<?php
/**
 * Asterisk Manager Interface (AMI) Class
 * Handles communication with Asterisk Manager Interface
 * Used for call origination, status checking, and other Asterisk operations
 */

class AsteriskManager {
    private $socket;
    private $host = 'localhost';
    private $port = 5038;
    private $username = 'admin';
    private $secret = 'flexpbx_ami_secret';
    private $connected = false;
    private $timeout = 5;

    /**
     * Constructor - optionally set custom credentials
     */
    public function __construct($config = []) {
        if (isset($config['host'])) $this->host = $config['host'];
        if (isset($config['port'])) $this->port = $config['port'];
        if (isset($config['username'])) $this->username = $config['username'];
        if (isset($config['secret'])) $this->secret = $config['secret'];
        if (isset($config['timeout'])) $this->timeout = $config['timeout'];
    }

    /**
     * Connect to Asterisk Manager Interface
     */
    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            error_log("AMI Connection failed: $errstr ($errno)");
            return false;
        }

        // Read welcome message
        $welcome = fgets($this->socket, 1024);

        // Login
        $response = $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->secret
        ]);

        if (isset($response['Response']) && $response['Response'] === 'Success') {
            $this->connected = true;
            return true;
        }

        error_log("AMI Login failed: " . print_r($response, true));
        return false;
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->connected && $this->socket) {
            $this->sendAction(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Send action to AMI and get response
     */
    private function sendAction($params) {
        if (!$this->socket) {
            return ['Response' => 'Error', 'Message' => 'Not connected'];
        }

        // Build action string
        $action = '';
        foreach ($params as $key => $value) {
            $action .= "$key: $value\r\n";
        }
        $action .= "\r\n";

        // Send action
        fputs($this->socket, $action);

        // Read response
        $response = [];
        $line = '';

        while ($line !== "\r\n") {
            $line = fgets($this->socket, 1024);
            if ($line === false) break;

            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $response[$key] = $value;
            }
        }

        return $response;
    }

    /**
     * Originate a call
     *
     * @param array $params Origination parameters
     * @return array Response from AMI
     */
    public function originate($params) {
        $action = [
            'Action' => 'Originate',
            'Async' => 'true'
        ];

        // Required parameters
        if (isset($params['Channel'])) $action['Channel'] = $params['Channel'];
        if (isset($params['Exten'])) $action['Exten'] = $params['Exten'];
        if (isset($params['Context'])) $action['Context'] = $params['Context'];
        if (isset($params['Priority'])) $action['Priority'] = $params['Priority'];

        // Optional parameters
        if (isset($params['CallerID'])) $action['CallerID'] = $params['CallerID'];
        if (isset($params['Timeout'])) $action['Timeout'] = $params['Timeout'];
        if (isset($params['Variable'])) $action['Variable'] = $params['Variable'];
        if (isset($params['Account'])) $action['Account'] = $params['Account'];
        if (isset($params['Application'])) $action['Application'] = $params['Application'];
        if (isset($params['Data'])) $action['Data'] = $params['Data'];

        return $this->sendAction($action);
    }

    /**
     * Get PJSIP endpoint status
     */
    public function getPjsipEndpointStatus($endpoint) {
        return $this->sendAction([
            'Action' => 'PJSIPShowEndpoint',
            'Endpoint' => $endpoint
        ]);
    }

    /**
     * Get PJSIP registration status
     */
    public function getPjsipRegistrations() {
        return $this->sendAction([
            'Action' => 'PJSIPShowRegistrationsOutbound'
        ]);
    }

    /**
     * Get channel status
     */
    public function getChannelStatus($channel = null) {
        $action = ['Action' => 'Status'];
        if ($channel) {
            $action['Channel'] = $channel;
        }
        return $this->sendAction($action);
    }

    /**
     * Get active channels
     */
    public function getActiveChannels() {
        return $this->sendAction([
            'Action' => 'CoreShowChannels'
        ]);
    }

    /**
     * Hangup a channel
     */
    public function hangup($channel, $cause = 16) {
        return $this->sendAction([
            'Action' => 'Hangup',
            'Channel' => $channel,
            'Cause' => $cause
        ]);
    }

    /**
     * Send DTMF digits
     */
    public function sendDTMF($channel, $digit) {
        return $this->sendAction([
            'Action' => 'PlayDTMF',
            'Channel' => $channel,
            'Digit' => $digit
        ]);
    }

    /**
     * Monitor call recording
     */
    public function monitor($channel, $file, $format = 'wav', $mix = true) {
        return $this->sendAction([
            'Action' => 'Monitor',
            'Channel' => $channel,
            'File' => $file,
            'Format' => $format,
            'Mix' => $mix ? 'true' : 'false'
        ]);
    }

    /**
     * Stop monitoring
     */
    public function stopMonitor($channel) {
        return $this->sendAction([
            'Action' => 'StopMonitor',
            'Channel' => $channel
        ]);
    }

    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Ping AMI to check connection
     */
    public function ping() {
        $response = $this->sendAction(['Action' => 'Ping']);
        return isset($response['Response']) && $response['Response'] === 'Success';
    }

    /**
     * Reload Asterisk module
     */
    public function reload($module = '') {
        $action = ['Action' => 'Reload'];
        if ($module) {
            $action['Module'] = $module;
        }
        return $this->sendAction($action);
    }

    /**
     * Get list of extensions
     */
    public function getExtensions($context) {
        return $this->sendAction([
            'Action' => 'ShowDialPlan',
            'Context' => $context
        ]);
    }

    /**
     * Destructor - ensure cleanup
     */
    public function __destruct() {
        $this->disconnect();
    }
}
?>
