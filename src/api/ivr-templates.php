<?php
/**
 * FlexPBX IVR Templates API
 * Manages IVR menu templates with ability to modify and save as new templates
 *
 * Endpoints:
 * - GET  ?path=list                    - List all templates (system + custom)
 * - GET  ?path=get&id={id}             - Get template details
 * - POST ?path=create                  - Create new custom template
 * - POST ?path=clone&id={id}           - Clone existing template
 * - PUT  ?path=update&id={id}          - Update custom template
 * - DELETE ?path=delete&id={id}        - Delete custom template
 * - POST ?path=apply&id={id}&ivr={num} - Apply template to create/update IVR
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

class IVRTemplateManager {
    private $pdo;
    private $systemTemplatesFile;
    private $customTemplatesFile;

    public function __construct() {
        $config = include __DIR__ . '/../config/database.php';

        $this->pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $this->systemTemplatesFile = dirname(__DIR__, 2) . '/apps/flexpbx/config/ivr-templates.json';
        $this->customTemplatesFile = dirname(__DIR__) . '/data/custom-ivr-templates.json';

        // Ensure custom templates file exists
        if (!file_exists($this->customTemplatesFile)) {
            $dir = dirname($this->customTemplatesFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->customTemplatesFile, json_encode(['templates' => []], JSON_PRETTY_PRINT));
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';

        try {
            switch ($path) {
                case 'list':
                    return $this->listTemplates();

                case 'get':
                    $id = $_GET['id'] ?? '';
                    return $this->getTemplate($id);

                case 'create':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed');
                    }
                    $data = json_decode(file_get_contents('php://input'), true);
                    return $this->createTemplate($data);

                case 'clone':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed');
                    }
                    $sourceId = $_GET['id'] ?? '';
                    $data = json_decode(file_get_contents('php://input'), true);
                    return $this->cloneTemplate($sourceId, $data);

                case 'update':
                    if ($method !== 'PUT') {
                        throw new Exception('Method not allowed');
                    }
                    $id = $_GET['id'] ?? '';
                    $data = json_decode(file_get_contents('php://input'), true);
                    return $this->updateTemplate($id, $data);

                case 'delete':
                    if ($method !== 'DELETE') {
                        throw new Exception('Method not allowed');
                    }
                    $id = $_GET['id'] ?? '';
                    return $this->deleteTemplate($id);

                case 'apply':
                    if ($method !== 'POST') {
                        throw new Exception('Method not allowed');
                    }
                    $templateId = $_GET['id'] ?? '';
                    $ivrNumber = $_GET['ivr'] ?? '';
                    return $this->applyTemplate($templateId, $ivrNumber);

                default:
                    throw new Exception('Invalid path');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function listTemplates() {
        $templates = [];

        // Load system templates
        if (file_exists($this->systemTemplatesFile)) {
            $data = json_decode(file_get_contents($this->systemTemplatesFile), true);
            foreach ($data['templates'] as $template) {
                $template['type'] = 'system';
                $template['can_edit'] = false;
                $template['can_delete'] = false;
                $templates[] = $template;
            }
        }

        // Load custom templates
        if (file_exists($this->customTemplatesFile)) {
            $data = json_decode(file_get_contents($this->customTemplatesFile), true);
            foreach ($data['templates'] as $template) {
                $template['type'] = 'custom';
                $template['can_edit'] = true;
                $template['can_delete'] = true;
                $templates[] = $template;
            }
        }

        return $this->success(['templates' => $templates, 'total' => count($templates)]);
    }

    private function getTemplate($id) {
        // Check system templates first
        if (file_exists($this->systemTemplatesFile)) {
            $data = json_decode(file_get_contents($this->systemTemplatesFile), true);
            foreach ($data['templates'] as $template) {
                if ($template['id'] === $id) {
                    $template['type'] = 'system';
                    $template['can_edit'] = false;
                    $template['can_delete'] = false;
                    $template['feature_code_mappings'] = $data['feature_code_mappings'] ?? [];
                    $template['freepbx_prompts'] = $data['freepbx_prompts'] ?? [];
                    return $this->success($template);
                }
            }
        }

        // Check custom templates
        if (file_exists($this->customTemplatesFile)) {
            $data = json_decode(file_get_contents($this->customTemplatesFile), true);
            foreach ($data['templates'] as $template) {
                if ($template['id'] === $id) {
                    $template['type'] = 'custom';
                    $template['can_edit'] = true;
                    $template['can_delete'] = true;
                    return $this->success($template);
                }
            }
        }

        throw new Exception('Template not found');
    }

    private function createTemplate($data) {
        // Validate required fields
        if (empty($data['name'])) {
            throw new Exception('Template name is required');
        }

        // Generate unique ID
        $id = 'custom-' . time() . '-' . bin2hex(random_bytes(4));

        $template = [
            'id' => $id,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'custom',
            'description' => $data['description'] ?? '',
            'greeting_type' => $data['greeting_type'] ?? 'recording',
            'greeting_prompt' => $data['greeting_prompt'] ?? '',
            'timeout' => intval($data['timeout'] ?? 10),
            'invalid_retries' => intval($data['invalid_retries'] ?? 3),
            'direct_dial_enabled' => boolval($data['direct_dial_enabled'] ?? true),
            'options' => $data['options'] ?? [],
            'timeout_destination' => $data['timeout_destination'] ?? ['type' => 'operator', 'value' => ''],
            'invalid_destination' => $data['invalid_destination'] ?? ['type' => 'repeat', 'value' => ''],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Load existing custom templates
        $customData = json_decode(file_get_contents($this->customTemplatesFile), true);
        $customData['templates'][] = $template;

        // Save
        file_put_contents($this->customTemplatesFile, json_encode($customData, JSON_PRETTY_PRINT));

        return $this->success([
            'message' => 'Template created successfully',
            'template' => $template
        ]);
    }

    private function cloneTemplate($sourceId, $data) {
        // Get source template
        $sourceTemplate = $this->getTemplate($sourceId);

        if (!$sourceTemplate['success']) {
            throw new Exception('Source template not found');
        }

        $source = $sourceTemplate['data'];

        // Create new template based on source
        $newData = [
            'name' => $data['name'] ?? ($source['name'] . ' (Copy)'),
            'category' => $data['category'] ?? $source['category'],
            'description' => $data['description'] ?? $source['description'],
            'greeting_type' => $source['greeting_type'],
            'greeting_prompt' => $source['greeting_prompt'],
            'timeout' => $source['timeout'],
            'invalid_retries' => $source['invalid_retries'],
            'direct_dial_enabled' => $source['direct_dial_enabled'],
            'options' => $source['options'],
            'timeout_destination' => $source['timeout_destination'],
            'invalid_destination' => $source['invalid_destination']
        ];

        return $this->createTemplate($newData);
    }

    private function updateTemplate($id, $data) {
        // Can only update custom templates
        if (!str_starts_with($id, 'custom-')) {
            throw new Exception('Cannot modify system templates. Clone it to create a custom version.');
        }

        $customData = json_decode(file_get_contents($this->customTemplatesFile), true);
        $found = false;

        foreach ($customData['templates'] as &$template) {
            if ($template['id'] === $id) {
                $found = true;

                // Update fields
                $template['name'] = $data['name'] ?? $template['name'];
                $template['category'] = $data['category'] ?? $template['category'];
                $template['description'] = $data['description'] ?? $template['description'];
                $template['greeting_type'] = $data['greeting_type'] ?? $template['greeting_type'];
                $template['greeting_prompt'] = $data['greeting_prompt'] ?? $template['greeting_prompt'];
                $template['timeout'] = intval($data['timeout'] ?? $template['timeout']);
                $template['invalid_retries'] = intval($data['invalid_retries'] ?? $template['invalid_retries']);
                $template['direct_dial_enabled'] = boolval($data['direct_dial_enabled'] ?? $template['direct_dial_enabled']);
                $template['options'] = $data['options'] ?? $template['options'];
                $template['timeout_destination'] = $data['timeout_destination'] ?? $template['timeout_destination'];
                $template['invalid_destination'] = $data['invalid_destination'] ?? $template['invalid_destination'];
                $template['updated_at'] = date('Y-m-d H:i:s');

                break;
            }
        }

        if (!$found) {
            throw new Exception('Custom template not found');
        }

        // Save
        file_put_contents($this->customTemplatesFile, json_encode($customData, JSON_PRETTY_PRINT));

        return $this->success(['message' => 'Template updated successfully']);
    }

    private function deleteTemplate($id) {
        // Can only delete custom templates
        if (!str_starts_with($id, 'custom-')) {
            throw new Exception('Cannot delete system templates');
        }

        $customData = json_decode(file_get_contents($this->customTemplatesFile), true);
        $filtered = array_filter($customData['templates'], function($t) use ($id) {
            return $t['id'] !== $id;
        });

        $customData['templates'] = array_values($filtered);

        // Save
        file_put_contents($this->customTemplatesFile, json_encode($customData, JSON_PRETTY_PRINT));

        return $this->success(['message' => 'Template deleted successfully']);
    }

    private function applyTemplate($templateId, $ivrNumber) {
        // Get template
        $templateResult = $this->getTemplate($templateId);

        if (!$templateResult['success']) {
            throw new Exception('Template not found');
        }

        $template = $templateResult['data'];

        // Create or update IVR menu
        // First check if IVR exists
        $stmt = $this->pdo->prepare("SELECT id FROM ivr_menus WHERE ivr_number = ?");
        $stmt->execute([$ivrNumber]);
        $existingIVR = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingIVR) {
            // Update existing IVR
            $stmt = $this->pdo->prepare("
                UPDATE ivr_menus SET
                    ivr_name = ?,
                    description = ?,
                    greeting_type = ?,
                    greeting_file = ?,
                    timeout = ?,
                    invalid_retries = ?,
                    timeout_destination_type = ?,
                    timeout_destination_value = ?,
                    invalid_destination_type = ?,
                    invalid_destination_value = ?,
                    direct_dial_enabled = ?,
                    enabled = 1
                WHERE ivr_number = ?
            ");

            $stmt->execute([
                $template['name'],
                $template['description'],
                $template['greeting_type'],
                $template['greeting_prompt'] ?? '',
                $template['timeout'],
                $template['invalid_retries'],
                $template['timeout_destination']['type'],
                $template['timeout_destination']['value'] ?? '',
                $template['invalid_destination']['type'],
                $template['invalid_destination']['value'] ?? '',
                $template['direct_dial_enabled'] ? 1 : 0,
                $ivrNumber
            ]);

            $ivrId = $existingIVR['id'];

            // Delete existing options
            $stmt = $this->pdo->prepare("DELETE FROM ivr_options WHERE ivr_menu_id = ?");
            $stmt->execute([$ivrId]);

        } else {
            // Create new IVR
            $stmt = $this->pdo->prepare("
                INSERT INTO ivr_menus (
                    ivr_number, ivr_name, description, greeting_type, greeting_file,
                    timeout, invalid_retries, timeout_destination_type, timeout_destination_value,
                    invalid_destination_type, invalid_destination_value, direct_dial_enabled, enabled
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $ivrNumber,
                $template['name'],
                $template['description'],
                $template['greeting_type'],
                $template['greeting_prompt'] ?? '',
                $template['timeout'],
                $template['invalid_retries'],
                $template['timeout_destination']['type'],
                $template['timeout_destination']['value'] ?? '',
                $template['invalid_destination']['type'],
                $template['invalid_destination']['value'] ?? '',
                $template['direct_dial_enabled'] ? 1 : 0
            ]);

            $ivrId = $this->pdo->lastInsertId();
        }

        // Add menu options
        foreach ($template['options'] as $option) {
            $stmt = $this->pdo->prepare("
                INSERT INTO ivr_options (
                    ivr_menu_id, digit, option_description, destination_type, destination_value, enabled
                ) VALUES (?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $ivrId,
                $option['digit'],
                $option['description'],
                $option['destination_type'],
                $option['destination_value']
            ]);
        }

        return $this->success([
            'message' => 'Template applied successfully',
            'ivr_id' => $ivrId,
            'ivr_number' => $ivrNumber
        ]);
    }

    private function success($data) {
        return ['success' => true, 'data' => $data];
    }

    private function error($message) {
        return ['success' => false, 'error' => $message];
    }
}

// Execute
$manager = new IVRTemplateManager();
echo json_encode($manager->handleRequest());
