<?php
/**
 * Personal Remote Storage Handler
 * For users running Devine Creations Web Server on their Windows/Mac
 *
 * This allows FlexPBX users to store backups on their own computer
 * if they have the Devine Creations Web Server app installed.
 *
 * @package FlexPBX
 */

class PersonalRemoteStorage {

    private $config;
    private $license_key;

    public function __construct($config = []) {
        $this->config = $config;
        $this->license_key = $config['license_key'] ?? '';
    }

    /**
     * Verify connection to self-hosted Mac server
     */
    public function verifyConnection() {
        $endpoint = $this->getEndpoint('/api/backup/ping');

        $response = $this->makeRequest('GET', $endpoint);

        return [
            'connected' => $response['success'] ?? false,
            'server_version' => $response['version'] ?? 'unknown',
            'storage_available' => $response['storage_available'] ?? 0,
            'storage_available_formatted' => $this->formatBytes($response['storage_available'] ?? 0),
            'license_valid' => $response['license_valid'] ?? false
        ];
    }

    /**
     * Upload backup to self-hosted server
     */
    public function uploadBackup($archive_path, $manifest) {
        if (!file_exists($archive_path)) {
            return ['status' => 'error', 'message' => 'Backup file not found'];
        }

        // Verify license first
        if (!$this->verifyLicense()) {
            return ['status' => 'error', 'message' => 'Invalid or expired license'];
        }

        $endpoint = $this->getEndpoint('/api/backup/upload');

        // Use chunked upload for large files
        $file_size = filesize($archive_path);

        if ($file_size > 50 * 1024 * 1024) { // > 50MB
            return $this->uploadChunked($archive_path, $manifest);
        }

        // Regular upload for smaller files
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($archive_path),
            'manifest' => json_encode($manifest),
            'format' => $manifest['format'] ?? 'flxx'
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-License-Key: ' . $this->license_key,
            'X-Device-ID: ' . $this->getDeviceId()
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            return [
                'status' => 'success',
                'backup_id' => $data['backup_id'] ?? null,
                'stored_path' => $data['path'] ?? null
            ];
        }

        return [
            'status' => 'error',
            'http_code' => $http_code,
            'message' => 'Upload failed'
        ];
    }

    /**
     * Chunked upload for large files
     */
    private function uploadChunked($archive_path, $manifest) {
        $chunk_size = 10 * 1024 * 1024; // 10MB chunks
        $file_size = filesize($archive_path);
        $total_chunks = ceil($file_size / $chunk_size);

        $upload_id = bin2hex(random_bytes(16));
        $handle = fopen($archive_path, 'rb');

        $chunk_num = 0;
        while (!feof($handle)) {
            $chunk_data = fread($handle, $chunk_size);
            $chunk_num++;

            $endpoint = $this->getEndpoint('/api/backup/upload-chunk');

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'upload_id' => $upload_id,
                'chunk_num' => $chunk_num,
                'total_chunks' => $total_chunks,
                'chunk_data' => base64_encode($chunk_data),
                'manifest' => $chunk_num === $total_chunks ? json_encode($manifest) : ''
            ]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-License-Key: ' . $this->license_key,
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                fclose($handle);
                return ['status' => 'error', 'message' => "Chunk {$chunk_num} failed"];
            }
        }

        fclose($handle);

        // Finalize upload
        return $this->finalizeChunkedUpload($upload_id, $manifest);
    }

    /**
     * Finalize chunked upload
     */
    private function finalizeChunkedUpload($upload_id, $manifest) {
        $endpoint = $this->getEndpoint('/api/backup/finalize');

        $response = $this->makeRequest('POST', $endpoint, [
            'upload_id' => $upload_id,
            'manifest' => json_encode($manifest)
        ]);

        return $response;
    }

    /**
     * List backups stored on self-hosted server
     */
    public function listBackups($format = null) {
        $endpoint = $this->getEndpoint('/api/backup/list');

        $params = [];
        if ($format) {
            $params['format'] = $format;
        }

        $response = $this->makeRequest('GET', $endpoint, $params);

        return $response['backups'] ?? [];
    }

    /**
     * Download backup from self-hosted server
     */
    public function downloadBackup($backup_id, $local_path) {
        $endpoint = $this->getEndpoint('/api/backup/download/' . $backup_id);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-License-Key: ' . $this->license_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);

        $fp = fopen($local_path, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($http_code === 200 && file_exists($local_path)) {
            return ['status' => 'success', 'path' => $local_path];
        }

        unlink($local_path);
        return ['status' => 'error', 'message' => 'Download failed'];
    }

    /**
     * Delete backup from self-hosted server
     */
    public function deleteBackup($backup_id) {
        $endpoint = $this->getEndpoint('/api/backup/delete/' . $backup_id);

        $response = $this->makeRequest('DELETE', $endpoint);

        return $response;
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats() {
        $endpoint = $this->getEndpoint('/api/backup/stats');

        $response = $this->makeRequest('GET', $endpoint);

        return [
            'total' => $response['total'] ?? 0,
            'used' => $response['used'] ?? 0,
            'available' => $response['available'] ?? 0,
            'backup_count' => $response['backup_count'] ?? 0,
            'flx_count' => $response['flx_count'] ?? 0,
            'flxx_count' => $response['flxx_count'] ?? 0
        ];
    }

    /**
     * Verify license is valid
     */
    private function verifyLicense() {
        if (empty($this->license_key)) {
            return false;
        }

        $endpoint = $this->getEndpoint('/api/license/verify');

        $response = $this->makeRequest('POST', $endpoint, [
            'license_key' => $this->license_key,
            'feature' => 'backup_storage'
        ]);

        return $response['valid'] ?? false;
    }

    /**
     * Build endpoint URL
     */
    private function getEndpoint($path) {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3780;
        $scheme = $this->config['use_ssl'] ?? false ? 'https' : 'http';

        return "{$scheme}://{$host}:{$port}{$path}";
    }

    /**
     * Make HTTP request
     */
    private function makeRequest($method, $url, $data = []) {
        $ch = curl_init();

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-License-Key: ' . $this->license_key,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true) ?? [];
        $result['success'] = $http_code >= 200 && $http_code < 300;

        return $result;
    }

    /**
     * Get unique device identifier
     */
    private function getDeviceId() {
        $device_file = '/var/lib/flexpbx/.device_id';

        if (file_exists($device_file)) {
            return trim(file_get_contents($device_file));
        }

        // Generate new device ID
        $device_id = 'FPX-' . strtoupper(bin2hex(random_bytes(8)));

        $dir = dirname($device_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($device_file, $device_id);

        return $device_id;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}

/**
 * Configuration example:
 *
 * $selfHosted = new SelfHostedBackupStorage([
 *     'host' => '192.168.1.100',     // Mac IP on local network
 *     'port' => 3780,                 // Devine Creations Web Server port
 *     'use_ssl' => false,             // true if using HTTPS
 *     'license_key' => 'YOUR_LICENSE_KEY'
 * ]);
 *
 * // Verify connection
 * $status = $selfHosted->verifyConnection();
 *
 * // Upload backup
 * $result = $selfHosted->uploadBackup('/var/backups/flexpbx/flxx/backup.flxx', $manifest);
 *
 * // List backups
 * $backups = $selfHosted->listBackups();
 */
