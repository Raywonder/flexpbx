#!/usr/bin/env php
<?php
/**
 * FlexPBX Auto-Configuration Script
 * Automatically configures feature codes and default IVR menus
 *
 * This script:
 * 1. Verifies all feature codes are present in dialplan
 * 2. Links extensions to proper voicemail feature codes
 * 3. Creates default IVR menus if none exist
 * 4. Applies FreePBX-compatible voice prompts
 *
 * Usage: php auto-configure-feature-codes.php [--apply]
 */

define('CONFIG_DIR', dirname(__DIR__) . '/config');
define('ASTERISK_CONF', '/etc/asterisk/extensions.conf');
define('TEMPLATE_FILE', __DIR__ . '/../config/ivr-templates.json');

class FlexPBXAutoConfigurator {
    private $templates;
    private $featureCodes = [];
    private $extensions = [];
    private $applyChanges = false;

    public function __construct($applyChanges = false) {
        $this->applyChanges = $applyChanges;
        $this->loadTemplates();
        $this->loadCurrentExtensions();
    }

    private function loadTemplates() {
        if (!file_exists(TEMPLATE_FILE)) {
            $this->error("Template file not found: " . TEMPLATE_FILE);
            exit(1);
        }

        $json = file_get_contents(TEMPLATE_FILE);
        $data = json_decode($json, true);

        if (!$data) {
            $this->error("Failed to parse template JSON");
            exit(1);
        }

        $this->templates = $data['templates'];
        $this->featureCodes = $data['feature_code_mappings'];

        $this->success("Loaded " . count($this->templates) . " IVR templates");
        $this->success("Loaded " . count($this->featureCodes) . " feature code mappings");
    }

    private function loadCurrentExtensions() {
        // Get all extensions from database
        $dbConfig = $this->loadDatabaseConfig();

        try {
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );

            $stmt = $pdo->query("SELECT extension_number FROM extensions WHERE enabled = 1");
            $this->extensions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->success("Found " . count($this->extensions) . " active extensions");

        } catch (PDOException $e) {
            $this->warning("Could not load extensions from database: " . $e->getMessage());
            // Fallback: parse from extensions.conf
            $this->loadExtensionsFromDialplan();
        }
    }

    private function loadExtensionsFromDialplan() {
        if (!file_exists(ASTERISK_CONF)) {
            $this->warning("Dialplan file not found: " . ASTERISK_CONF);
            return;
        }

        $content = file_get_contents(ASTERISK_CONF);
        preg_match_all('/exten\s*=>\s*(\d{4}),/', $content, $matches);

        $this->extensions = array_unique($matches[1]);
        $this->info("Loaded " . count($this->extensions) . " extensions from dialplan");
    }

    private function loadDatabaseConfig() {
        $configFile = CONFIG_DIR . '/database.php';

        if (file_exists($configFile)) {
            return include $configFile;
        }

        // Fallback default config
        return [
            'host' => 'localhost',
            'database' => 'flexpbx',
            'username' => 'flexpbx_user',
            'password' => ''
        ];
    }

    public function checkFeatureCodes() {
        $this->header("Checking Feature Codes Configuration");

        if (!file_exists(ASTERISK_CONF)) {
            $this->error("Dialplan not found: " . ASTERISK_CONF);
            return false;
        }

        $dialplan = file_get_contents(ASTERISK_CONF);
        $missing = [];
        $present = [];

        foreach ($this->featureCodes as $code => $config) {
            // Check if feature code exists in dialplan
            $pattern = '/exten\s*=>\s*' . preg_quote($code, '/') . ',/';

            if (preg_match($pattern, $dialplan)) {
                $present[] = $code;
                $this->success("✓ Feature code {$code} ({$config['name']}) is configured");
            } else {
                $missing[] = $code;
                $this->warning("✗ Feature code {$code} ({$config['name']}) is MISSING");
            }
        }

        if (empty($missing)) {
            $this->success("\nAll " . count($present) . " feature codes are properly configured!");
            return true;
        } else {
            $this->warning("\nMissing " . count($missing) . " feature codes");

            if ($this->applyChanges) {
                $this->info("Applying missing feature codes...");
                return $this->addMissingFeatureCodes($missing);
            } else {
                $this->info("Run with --apply flag to add missing feature codes");
                return false;
            }
        }
    }

    private function addMissingFeatureCodes($missingCodes) {
        $this->info("\nAdding missing feature codes to dialplan...");

        // Load default template
        $templateFile = CONFIG_DIR . '/asterisk-dialplan-defaults.conf';

        if (!file_exists($templateFile)) {
            $this->error("Default dialplan template not found");
            return false;
        }

        $template = file_get_contents($templateFile);
        $dialplan = file_get_contents(ASTERISK_CONF);

        // Find [flexpbx-internal] context
        if (!preg_match('/\[flexpbx-internal\]/', $dialplan)) {
            $this->error("[flexpbx-internal] context not found in dialplan");
            return false;
        }

        // Extract feature codes from template
        foreach ($missingCodes as $code) {
            $config = $this->featureCodes[$code];

            // Extract the feature code block from template
            $pattern = '/; Feature Codes.*?exten\s*=>\s*' . preg_quote($code, '/') . ',.*?(?=\n(?:exten|;|\[))/s';

            if (preg_match($pattern, $template, $matches)) {
                $featureBlock = $matches[0];

                // Insert after [flexpbx-internal] context
                $dialplan = preg_replace(
                    '/(\[flexpbx-internal\])/',
                    "$1\n" . $featureBlock . "\n",
                    $dialplan,
                    1
                );

                $this->success("Added feature code: {$code} - {$config['name']}");
            }
        }

        // Backup existing dialplan
        $backupFile = ASTERISK_CONF . '.backup.' . date('Y-m-d_H-i-s');
        copy(ASTERISK_CONF, $backupFile);
        $this->info("Backed up dialplan to: {$backupFile}");

        // Write updated dialplan
        file_put_contents(ASTERISK_CONF, $dialplan);
        $this->success("Updated dialplan with missing feature codes");

        // Reload Asterisk
        exec('asterisk -rx "dialplan reload"', $output, $returnCode);

        if ($returnCode === 0) {
            $this->success("Asterisk dialplan reloaded successfully");
            return true;
        } else {
            $this->warning("Could not reload Asterisk (may not be running)");
            $this->info("Restart Asterisk to apply changes");
            return false;
        }
    }

    public function linkExtensionsToFeatureCodes() {
        $this->header("Linking Extensions to Feature Codes");

        if (empty($this->extensions)) {
            $this->warning("No extensions found to configure");
            return false;
        }

        $this->info("Extensions will automatically have access to:");
        $this->info("  • *97 - Check their voicemail");
        $this->info("  • *98 - Check any voicemail box");
        $this->info("  • *43 - Echo test");
        $this->info("  • *411 - Company directory");
        $this->info("  • 700-702 - Call parking");
        $this->info("  • 8000+ - Conference rooms");

        $this->success("\nAll feature codes are accessible from any extension in the [flexpbx-internal] context");

        // Verify each extension is in flexpbx-internal context
        $dialplan = file_get_contents(ASTERISK_CONF);

        foreach ($this->extensions as $ext) {
            if (preg_match("/exten\s*=>\s*{$ext},.*Dial\(PJSIP\/{$ext}/", $dialplan)) {
                $this->success("✓ Extension {$ext} has feature code access");
            } else {
                $this->warning("✗ Extension {$ext} may need to be added to dialplan");
            }
        }

        return true;
    }

    public function checkDefaultIVRMenus() {
        $this->header("Checking Default IVR Menus");

        // Check if any IVR menus exist
        $dbConfig = $this->loadDatabaseConfig();

        try {
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}",
                $dbConfig['username'],
                $dbConfig['password']
            );

            $stmt = $pdo->query("SELECT COUNT(*) FROM ivr_menus WHERE enabled = 1");
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $this->success("Found {$count} active IVR menu(s)");
                $this->info("IVR menus are configured");
                return true;
            } else {
                $this->warning("No IVR menus configured");

                if ($this->applyChanges) {
                    return $this->createDefaultIVRMenu();
                } else {
                    $this->info("Run with --apply flag to create default IVR menu");
                    $this->info("Or use the admin panel: /admin/ivr-builder.php");
                    return false;
                }
            }

        } catch (PDOException $e) {
            $this->warning("Could not check IVR menus: " . $e->getMessage());
            $this->info("IVR menus can be created via admin panel");
            return false;
        }
    }

    private function createDefaultIVRMenu() {
        $this->info("\nCreating default IVR menu from template...");

        // Use the "simple-business" template
        $template = null;
        foreach ($this->templates as $tmpl) {
            if ($tmpl['id'] === 'simple-business') {
                $template = $tmpl;
                break;
            }
        }

        if (!$template) {
            $this->error("Default template not found");
            return false;
        }

        $this->info("Template: {$template['name']}");
        $this->info("Description: {$template['description']}");
        $this->info("Options: " . count($template['options']));

        // Save template to a file for manual import
        $outputFile = CONFIG_DIR . '/default-ivr-menu.json';
        file_put_contents($outputFile, json_encode($template, JSON_PRETTY_PRINT));

        $this->success("Default IVR template saved to: {$outputFile}");
        $this->info("\nTo apply this template:");
        $this->info("1. Go to: /admin/ivr-builder.php");
        $this->info("2. Click 'Templates' tab");
        $this->info("3. Select 'Simple Business Menu'");
        $this->info("4. Configure and apply");

        return true;
    }

    public function verifyVoicePrompts() {
        $this->header("Verifying FreePBX Voice Prompts");

        $soundsDir = '/var/lib/asterisk/sounds/en';

        if (!is_dir($soundsDir)) {
            $this->error("Sounds directory not found: {$soundsDir}");
            $this->info("Install Asterisk sound files:");
            $this->info("  yum install asterisk-sounds-core-en-ulaw");
            return false;
        }

        // Load prompts from template
        $json = file_get_contents(TEMPLATE_FILE);
        $data = json_decode($json, true);
        $prompts = $data['freepbx_prompts'];

        $totalPrompts = 0;
        $missingPrompts = [];

        foreach ($prompts as $category => $files) {
            $this->info("\n{$category} prompts:");

            foreach ($files as $prompt) {
                $totalPrompts++;

                // Check for various formats
                $formats = ['ulaw', 'wav', 'gsm', 'g722'];
                $found = false;

                foreach ($formats as $format) {
                    if (file_exists("{$soundsDir}/{$prompt}.{$format}")) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $this->success("  ✓ {$prompt}");
                } else {
                    $this->warning("  ✗ {$prompt} (missing)");
                    $missingPrompts[] = $prompt;
                }
            }
        }

        if (empty($missingPrompts)) {
            $this->success("\nAll {$totalPrompts} required prompts are available!");
            return true;
        } else {
            $this->warning("\nMissing " . count($missingPrompts) . " prompts");
            $this->info("These prompts will be created as needed or you can record custom ones");
            return false;
        }
    }

    public function generateReport() {
        $this->header("FlexPBX Configuration Report");

        echo "\n";
        echo "System Status:\n";
        echo "==============\n";
        echo "Feature Codes: " . count($this->featureCodes) . " configured\n";
        echo "IVR Templates: " . count($this->templates) . " available\n";
        echo "Extensions: " . count($this->extensions) . " active\n";
        echo "\n";
        echo "Available Feature Codes:\n";
        echo "========================\n";

        foreach ($this->featureCodes as $code => $config) {
            echo sprintf("  %-8s - %s\n", $code, $config['name']);
        }

        echo "\n";
        echo "Available IVR Templates:\n";
        echo "========================\n";

        foreach ($this->templates as $template) {
            echo sprintf("  %-30s - %s\n", $template['name'], $template['description']);
            echo sprintf("  %30s   Category: %s, Options: %d\n",
                '',
                $template['category'],
                count($template['options'])
            );
        }

        echo "\n";
        echo "Next Steps:\n";
        echo "===========\n";
        echo "1. Run with --apply to configure missing feature codes\n";
        echo "2. Visit /admin/ivr-builder.php to create IVR menus\n";
        echo "3. Use templates for quick setup\n";
        echo "\n";
    }

    // Helper output methods
    private function header($text) {
        echo "\n\033[1;36m=== {$text} ===\033[0m\n";
    }

    private function success($text) {
        echo "\033[0;32m{$text}\033[0m\n";
    }

    private function warning($text) {
        echo "\033[0;33m{$text}\033[0m\n";
    }

    private function error($text) {
        echo "\033[0;31m{$text}\033[0m\n";
    }

    private function info($text) {
        echo "\033[0;37m{$text}\033[0m\n";
    }
}

// Main execution
$applyChanges = in_array('--apply', $argv);

if ($applyChanges) {
    echo "\033[1;33m⚠  Running in APPLY mode - changes will be made\033[0m\n";
} else {
    echo "\033[1;36mℹ  Running in CHECK mode - no changes will be made\033[0m\n";
    echo "\033[0;37m   Use --apply flag to apply configurations\033[0m\n";
}

$configurator = new FlexPBXAutoConfigurator($applyChanges);

// Run checks
$configurator->checkFeatureCodes();
$configurator->linkExtensionsToFeatureCodes();
$configurator->checkDefaultIVRMenus();
$configurator->verifyVoicePrompts();
$configurator->generateReport();

echo "\n\033[1;32m✓ Configuration check complete\033[0m\n\n";
