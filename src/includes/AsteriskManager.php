<?php
/**
 * FlexPBX - Asterisk Manager Interface (AMI) Class
 * Handles connections and commands to Asterisk Manager Interface
 */

class AsteriskManager {
    private $host;
    private $port;
    private $username;
    private $secret;
    private $socket;
    private $connected = false;
    private $timeout = 5;

    public function __construct($host = 'localhost', $port = 5038, $username = 'admin', $secret = 'admin') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->secret = $secret;
    }

    /**
     * Connect to AMI
     */
    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Failed to connect to AMI: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read welcome message
        $this->readResponse();

        // Login
        $response = $this->sendCommand([
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->secret
        ]);

        if (isset($response['Response']) && $response['Response'] === 'Success') {
            $this->connected = true;
            return true;
        }

        throw new Exception("AMI Login failed");
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->connected && $this->socket) {
            $this->sendCommand(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Send a command to AMI
     */
    public function sendCommand($params) {
        if (!$this->connected) {
            throw new Exception("Not connected to AMI");
        }

        $message = "";
        foreach ($params as $key => $value) {
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
        $line = '';

        while (($line = fgets($this->socket)) !== false) {
            $line = trim($line);

            if ($line === '') {
                break;
            }

            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $response[trim($key)] = trim($value);
            }
        }

        return $response;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatus($queueName = null) {
        $params = ['Action' => 'QueueStatus'];
        if ($queueName) {
            $params['Queue'] = $queueName;
        }

        $response = $this->sendCommand($params);
        $queues = [];

        // Read all queue events
        while (true) {
            $event = $this->readResponse();
            if (empty($event) || (isset($event['Event']) && $event['Event'] === 'QueueStatusComplete')) {
                break;
            }

            if (isset($event['Event']) && $event['Event'] === 'QueueParams') {
                $queues[$event['Queue']] = [
                    'name' => $event['Queue'],
                    'max' => $event['Max'] ?? 0,
                    'strategy' => $event['Strategy'] ?? '',
                    'calls' => $event['Calls'] ?? 0,
                    'holdtime' => $event['Holdtime'] ?? 0,
                    'talktime' => $event['TalkTime'] ?? 0,
                    'completed' => $event['Completed'] ?? 0,
                    'abandoned' => $event['Abandoned'] ?? 0,
                    'service_level' => $event['ServiceLevel'] ?? 0,
                    'service_level_perf' => $event['ServicelevelPerf'] ?? 0
                ];
            }
        }

        return $queues;
    }

    /**
     * Get queue members
     */
    public function getQueueMembers($queueName) {
        $response = $this->sendCommand([
            'Action' => 'QueueStatus',
            'Queue' => $queueName
        ]);

        $members = [];

        while (true) {
            $event = $this->readResponse();
            if (empty($event) || (isset($event['Event']) && $event['Event'] === 'QueueStatusComplete')) {
                break;
            }

            if (isset($event['Event']) && $event['Event'] === 'QueueMember') {
                $members[] = [
                    'name' => $event['Name'] ?? '',
                    'location' => $event['Location'] ?? '',
                    'state_interface' => $event['StateInterface'] ?? '',
                    'membership' => $event['Membership'] ?? '',
                    'penalty' => $event['Penalty'] ?? 0,
                    'calls_taken' => $event['CallsTaken'] ?? 0,
                    'last_call' => $event['LastCall'] ?? 0,
                    'last_pause' => $event['LastPause'] ?? 0,
                    'in_call' => $event['InCall'] ?? 0,
                    'status' => $event['Status'] ?? 0,
                    'paused' => $event['Paused'] ?? 0,
                    'paused_reason' => $event['PausedReason'] ?? ''
                ];
            }
        }

        return $members;
    }

    /**
     * Add member to queue
     */
    public function addQueueMember($queue, $interface, $memberName = '', $penalty = 0) {
        return $this->sendCommand([
            'Action' => 'QueueAdd',
            'Queue' => $queue,
            'Interface' => $interface,
            'MemberName' => $memberName,
            'Penalty' => $penalty
        ]);
    }

    /**
     * Remove member from queue
     */
    public function removeQueueMember($queue, $interface) {
        return $this->sendCommand([
            'Action' => 'QueueRemove',
            'Queue' => $queue,
            'Interface' => $interface
        ]);
    }

    /**
     * Pause/Unpause queue member
     */
    public function pauseQueueMember($queue, $interface, $paused = true, $reason = '') {
        return $this->sendCommand([
            'Action' => 'QueuePause',
            'Queue' => $queue,
            'Interface' => $interface,
            'Paused' => $paused ? 'true' : 'false',
            'Reason' => $reason
        ]);
    }

    /**
     * Get active channels
     */
    public function getActiveChannels() {
        $response = $this->sendCommand(['Action' => 'CoreShowChannels']);

        $channels = [];

        while (true) {
            $event = $this->readResponse();
            if (empty($event) || (isset($event['Event']) && $event['Event'] === 'CoreShowChannelsComplete')) {
                break;
            }

            if (isset($event['Event']) && $event['Event'] === 'CoreShowChannel') {
                $channels[] = [
                    'channel' => $event['Channel'] ?? '',
                    'channel_state' => $event['ChannelState'] ?? '',
                    'channel_state_desc' => $event['ChannelStateDesc'] ?? '',
                    'caller_id_num' => $event['CallerIDNum'] ?? '',
                    'caller_id_name' => $event['CallerIDName'] ?? '',
                    'connected_line_num' => $event['ConnectedLineNum'] ?? '',
                    'connected_line_name' => $event['ConnectedLineName'] ?? '',
                    'context' => $event['Context'] ?? '',
                    'extension' => $event['Extension'] ?? '',
                    'priority' => $event['Priority'] ?? '',
                    'duration' => $event['Duration'] ?? 0,
                    'account_code' => $event['AccountCode'] ?? '',
                    'uniqueid' => $event['Uniqueid'] ?? ''
                ];
            }
        }

        return $channels;
    }

    /**
     * Spy on a channel (listen)
     */
    public function chanSpy($spyChannel, $targetChannel, $options = 'q') {
        return $this->sendCommand([
            'Action' => 'Originate',
            'Channel' => $spyChannel,
            'Application' => 'ChanSpy',
            'Data' => $targetChannel . ',' . $options,
            'CallerID' => 'Monitor'
        ]);
    }

    /**
     * Whisper to a channel
     */
    public function whisper($spyChannel, $targetChannel) {
        return $this->chanSpy($spyChannel, $targetChannel, 'qw');
    }

    /**
     * Barge into a call
     */
    public function barge($spyChannel, $targetChannel) {
        return $this->chanSpy($spyChannel, $targetChannel, 'qB');
    }

    /**
     * Hangup a channel
     */
    public function hangup($channel) {
        return $this->sendCommand([
            'Action' => 'Hangup',
            'Channel' => $channel
        ]);
    }

    /**
     * Get SIP peers (extensions)
     */
    public function getSIPPeers() {
        $response = $this->sendCommand(['Action' => 'SIPpeers']);

        $peers = [];

        while (true) {
            $event = $this->readResponse();
            if (empty($event) || (isset($event['Event']) && $event['Event'] === 'PeerlistComplete')) {
                break;
            }

            if (isset($event['Event']) && $event['Event'] === 'PeerEntry') {
                $peers[] = [
                    'object_name' => $event['ObjectName'] ?? '',
                    'chan_object_type' => $event['ChanObjectType'] ?? '',
                    'ip_address' => $event['IPaddress'] ?? '',
                    'ip_port' => $event['IPport'] ?? '',
                    'dynamic' => $event['Dynamic'] ?? '',
                    'forcerport' => $event['Forcerport'] ?? '',
                    'videosupport' => $event['VideoSupport'] ?? '',
                    'textsupport' => $event['TextSupport'] ?? '',
                    'acl' => $event['ACL'] ?? '',
                    'status' => $event['Status'] ?? '',
                    'realtime_device' => $event['RealtimeDevice'] ?? ''
                ];
            }
        }

        return $peers;
    }

    /**
     * Get PJSIP endpoints
     */
    public function getPJSIPEndpoints() {
        $response = $this->sendCommand(['Action' => 'PJSIPShowEndpoints']);

        $endpoints = [];

        while (true) {
            $event = $this->readResponse();
            if (empty($event) || (isset($event['Event']) && $event['Event'] === 'EndpointListComplete')) {
                break;
            }

            if (isset($event['Event']) && $event['Event'] === 'EndpointList') {
                $endpoints[] = [
                    'object_type' => $event['ObjectType'] ?? '',
                    'object_name' => $event['ObjectName'] ?? '',
                    'transport' => $event['Transport'] ?? '',
                    'aor' => $event['Aor'] ?? '',
                    'auths' => $event['Auths'] ?? '',
                    'contacts' => $event['Contacts'] ?? '',
                    'device_state' => $event['DeviceState'] ?? '',
                    'active_channels' => $event['ActiveChannels'] ?? 0
                ];
            }
        }

        return $endpoints;
    }

    /**
     * Execute CLI command
     */
    public function command($cmd) {
        return $this->sendCommand([
            'Action' => 'Command',
            'Command' => $cmd
        ]);
    }

    /**
     * Destructor - ensure disconnect
     */
    public function __destruct() {
        $this->disconnect();
    }
}
