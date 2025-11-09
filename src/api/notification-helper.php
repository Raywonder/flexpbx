<?php
/**
 * FlexPBX Notification Helper Class
 * Easy-to-use helper for sending notifications from anywhere in your code
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

class NotificationHelper {

    private $api_url;

    public function __construct($api_url = null) {
        if ($api_url === null) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->api_url = $protocol . '://' . $host . '/api/notifications-manager.php';
        } else {
            $this->api_url = $api_url;
        }
    }

    /**
     * Send a notification
     *
     * @param string $type Notification type (system, call, voicemail, sms, alert, message, task, announcement)
     * @param string $title Notification title
     * @param array $options Additional options
     * @return array Response from API
     */
    public function send($type, $title, $options = []) {
        $notification = [
            'notification_type' => $type,
            'title' => $title,
            'message' => $options['message'] ?? null,
            'icon' => $options['icon'] ?? null,
            'link_url' => $options['link_url'] ?? null,
            'priority' => $options['priority'] ?? 'normal',
            'target_user_id' => $options['target_user_id'] ?? null,
            'target_role' => $options['target_role'] ?? null,
            'target_group' => $options['target_group'] ?? null,
            'is_scheduled' => $options['is_scheduled'] ?? false,
            'scheduled_for' => $options['scheduled_for'] ?? null,
            'metadata' => $options['metadata'] ?? []
        ];

        return $this->apiCall('send', $notification, 'POST');
    }

    /**
     * Send a system notification
     */
    public function sendSystem($title, $message = null, $options = []) {
        return $this->send('system', $title, array_merge(['message' => $message], $options));
    }

    /**
     * Send a call notification
     */
    public function sendCallNotification($title, $message = null, $target_user = null) {
        return $this->send('call', $title, [
            'message' => $message,
            'target_user_id' => $target_user,
            'icon' => 'phone',
            'priority' => 'normal'
        ]);
    }

    /**
     * Send a missed call notification
     */
    public function sendMissedCall($extension, $caller_id, $timestamp = null) {
        $time = $timestamp ?? date('g:i A');
        return $this->send('call', 'Missed Call', [
            'message' => "You missed a call from {$caller_id} at {$time}",
            'target_user_id' => $extension,
            'icon' => 'phone-missed',
            'priority' => 'normal',
            'link_url' => '/user-portal/call-history.php'
        ]);
    }

    /**
     * Send a voicemail notification
     */
    public function sendVoicemail($extension, $caller_id, $voicemail_id = null) {
        $link = $voicemail_id ? "/user-portal/voicemail.php?id={$voicemail_id}" : '/user-portal/voicemail.php';

        return $this->send('voicemail', 'New Voicemail', [
            'message' => "You have a new voicemail from {$caller_id}",
            'target_user_id' => $extension,
            'icon' => 'voicemail',
            'priority' => 'high',
            'link_url' => $link
        ]);
    }

    /**
     * Send an SMS notification
     */
    public function sendSMSNotification($extension, $sender, $message_preview) {
        return $this->send('sms', 'New SMS Message', [
            'message' => "From {$sender}: " . substr($message_preview, 0, 50),
            'target_user_id' => $extension,
            'icon' => 'message',
            'priority' => 'normal',
            'link_url' => '/user-portal/sms-inbox.php'
        ]);
    }

    /**
     * Send an alert notification
     */
    public function sendAlert($title, $message, $priority = 'urgent', $target = null) {
        return $this->send('alert', $title, [
            'message' => $message,
            'priority' => $priority,
            'target_user_id' => $target,
            'icon' => 'alert-triangle'
        ]);
    }

    /**
     * Send an announcement to all users
     */
    public function sendAnnouncement($title, $message, $priority = 'normal') {
        return $this->send('announcement', $title, [
            'message' => $message,
            'priority' => $priority,
            'icon' => 'megaphone'
        ]);
    }

    /**
     * Send a task notification
     */
    public function sendTask($title, $message, $target_user, $link = null) {
        return $this->send('task', $title, [
            'message' => $message,
            'target_user_id' => $target_user,
            'icon' => 'clipboard',
            'priority' => 'normal',
            'link_url' => $link
        ]);
    }

    /**
     * Send a message notification (chat/Mattermost)
     */
    public function sendMessageNotification($title, $message, $target_user, $link = null) {
        return $this->send('message', $title, [
            'message' => $message,
            'target_user_id' => $target_user,
            'icon' => 'message-circle',
            'priority' => 'normal',
            'link_url' => $link
        ]);
    }

    /**
     * Send to a specific role
     */
    public function sendToRole($type, $title, $role, $options = []) {
        $options['target_role'] = $role;
        return $this->send($type, $title, $options);
    }

    /**
     * Send to a group
     */
    public function sendToGroup($type, $title, $group, $options = []) {
        $options['target_group'] = $group;
        return $this->send($type, $title, $options);
    }

    /**
     * Send to all users
     */
    public function sendToAll($type, $title, $options = []) {
        return $this->send($type, $title, $options);
    }

    /**
     * Schedule a notification
     */
    public function schedule($type, $title, $scheduled_time, $options = []) {
        $options['is_scheduled'] = true;
        $options['scheduled_for'] = $scheduled_time;
        return $this->send($type, $title, $options);
    }

    /**
     * Make API call
     */
    private function apiCall($action, $data = [], $method = 'GET') {
        $url = $this->api_url . '?action=' . $action;

        $ch = curl_init($url);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return json_decode($response, true) ?: ['success' => false, 'error' => 'Invalid response'];
    }
}

// Example usage:
/*

// Create instance
$notifier = new NotificationHelper();

// Send missed call notification
$notifier->sendMissedCall('2000', '555-1234', '10:30 AM');

// Send voicemail notification
$notifier->sendVoicemail('2000', '555-1234', 'vm_12345');

// Send SMS notification
$notifier->sendSMSNotification('2000', '555-1234', 'Hello, this is a test message');

// Send system announcement to all users
$notifier->sendAnnouncement('Scheduled Maintenance', 'System will be down tonight from 11 PM to 2 AM', 'high');

// Send alert to admins
$notifier->sendToRole('alert', 'High CPU Usage', 'admin', [
    'message' => 'Server CPU usage is at 95%',
    'priority' => 'urgent'
]);

// Send task to specific user
$notifier->sendTask('Review Customer Request', 'Please review request #12345', '2000', '/tasks/12345');

// Schedule a notification
$notifier->schedule('system', 'Reminder', '2025-11-10 14:00:00', [
    'message' => 'Meeting in 1 hour',
    'target_user_id' => '2000'
]);

*/
?>
