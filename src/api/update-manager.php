<?php
/**
 * FlexPBX Update Manager
 * Handles client updates and version management on server
 */

header('Content-Type: application/json');

class ServerUpdateManager {
    private $config;
    private $updatePath;

    public function __construct() {
        $this->config = include 'config.php';
        $this->updatePath = $this->config['update_server']['download_path'] ?? '/var/www/flexpbx/updates/';
    }

    public function handleRequest() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        switch (end($pathParts)) {
            case 'check':
                return $this->checkForUpdates();
            case 'download':
                return $this->downloadUpdate();
            case 'upload':
                return $this->uploadClientUpdate();
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Unknown endpoint']);
        }
    }

    private function checkForUpdates() {
        $input = json_decode(file_get_contents('php://input'), true);
        $currentVersion = $input['currentVersion'] ?? '1.0.0';
        $platform = $input['platform'] ?? 'unknown';

        // Check available updates
        $latestVersion = $this->getLatestVersion($platform);

        if (version_compare($currentVersion, $latestVersion, '<')) {
            echo json_encode([
                'success' => true,
                'updateAvailable' => true,
                'latestVersion' => $latestVersion,
                'downloadUrl' => "/api/updates/download/{$platform}",
                'size' => $this->getUpdateSize($platform),
                'critical' => false,
                'releaseNotes' => $this->getReleaseNotes($latestVersion)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'updateAvailable' => false,
                'currentVersion' => $currentVersion
            ]);
        }
    }

    private function downloadUpdate() {
        $platform = $_GET['platform'] ?? 'unknown';
        $version = $_GET['version'] ?? $this->getLatestVersion($platform);

        $updateFile = $this->findUpdateFile($platform, $version);
        if (!$updateFile || !file_exists($updateFile)) {
            http_response_code(404);
            echo json_encode(['error' => 'Update file not found']);
            return;
        }

        // Stream file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($updateFile) . '"');
        header('Content-Length: ' . filesize($updateFile));
        readfile($updateFile);
    }

    private function getLatestVersion($platform) {
        return '1.1.0'; // This would read from version manifest
    }

    private function getUpdateSize($platform) {
        $updateFile = $this->findUpdateFile($platform, $this->getLatestVersion($platform));
        return $updateFile ? filesize($updateFile) : 0;
    }

    private function findUpdateFile($platform, $version) {
        $patterns = [
            'darwin' => "FlexPBX-{$version}.dmg",
            'win32' => "FlexPBX-{$version}-win.zip",
            'linux' => "FlexPBX-{$version}.AppImage"
        ];

        $filename = $patterns[$platform] ?? null;
        return $filename ? $this->updatePath . $filename : null;
    }

    private function getReleaseNotes($version) {
        return "Enhanced multi-server support, auto-updates, and fallback capabilities";
    }
}

$manager = new ServerUpdateManager();
$manager->handleRequest();
?>
