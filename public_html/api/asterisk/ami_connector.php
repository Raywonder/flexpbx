<?php
/**
 * FlexPBX - Asterisk AMI Connector
 * Provides AMI (Asterisk Manager Interface) connectivity for FlexPBX
 *
 * File: /home/flexpbxuser/public_html/api/asterisk/ami_connector.php
 */

class AsteriskAMI {
    private $socket = null;
    private $host = '127.0.0.1';
    private $port = 5038;
    private $username = 'flexpbx_web';
    private $secret = 'FlexPBX_Web_2024!';
    private $connected = false;

    public function __construct($config = []) {
        if (isset($config['host'])) $this->host = $config['host'];
        if (isset($config['port'])) $this->port = $config['port'];
        if (isset($config['username'])) $this->username = $config['username'];
        if (isset($config['secret'])) $this->secret = $config['secret'];
    }

    /**
     * Connect to Asterisk AMI
     */
    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (!$this->socket) {
            error_log("AMI Connection Failed: $errstr ($errno)");
            return false;
        }

        // Read greeting
        $this->readResponse();

        // Login
        $response = $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->secret
        ]);

        if ($response && isset($response['Response']) && $response['Response'] == 'Success') {
            $this->connected = true;
            return true;
        }

        return false;
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->socket && $this->connected) {
            $this->sendAction(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Send action to AMI
     */
    public function sendAction($action) {
        if (!$this->socket || !$this->connected) {
            return false;
        }

        $message = "";
        foreach ($action as $key => $value) {
            $message .= "$key: $value\r\n";
        }
        $message .= "\r\n";

        fwrite($this->socket, $message);
        return $this->readResponse();
    }

    /**
     * Read response from AMI
     */
    private function readResponse() {
        $response = [];
        $buffer = "";

        while (!feof($this->socket)) {
            $line = fgets($this->socket, 4096);
            $buffer .= $line;

            if (trim($line) == "") {
                break;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $response[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $response;
    }

    /**
     * Originate a call
     */
    public function originate($channel, $extension, $context = 'from-internal', $callerid = '') {
        return $this->sendAction([
            'Action' => 'Originate',
            'Channel' => $channel,
            'Exten' => $extension,
            'Context' => $context,
            'Priority' => 1,
            'CallerID' => $callerid,
            'Timeout' => 30000
        ]);
    }

    /**
     * Get SIP peers
     */
    public function getSIPPeers() {
        return $this->sendAction(['Action' => 'SIPpeers']);
    }

    /**
     * Get active channels
     */
    public function getChannels() {
        return $this->sendAction(['Action' => 'CoreShowChannels']);
    }

    /**
     * Get system status
     */
    public function getStatus() {
        return $this->sendAction(['Action' => 'CoreStatus']);
    }

    /**
     * Reload Asterisk modules
     */
    public function reload($module = '') {
        $action = ['Action' => 'Reload'];
        if ($module) {
            $action['Module'] = $module;
        }
        return $this->sendAction($action);
    }

    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Destructor - ensure disconnect
     */
    public function __destruct() {
        $this->disconnect();
    }
}

// Usage example:
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');

    $ami = new AsteriskAMI();

    if ($ami->connect()) {
        $status = $ami->getStatus();
        echo json_encode([
            'success' => true,
            'message' => 'Connected to Asterisk AMI',
            'status' => $status
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to connect to Asterisk AMI'
        ], JSON_PRETTY_PRINT);
    }
}
