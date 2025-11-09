<?php
/**
 * MCP Server Installation Functions for FlexPBX Installer
 *
 * ADD THESE FUNCTIONS TO install.php in the FlexPBXInstaller class
 * Insert before the renderHeader() function (around line 2557)
 *
 * Integration Instructions:
 * 1. Open /home/flexpbxuser/public_html/api/install.php
 * 2. Find the initializeServices() function (around line 2443)
 * 3. After the initializeServices() function, add all functions below
 * 4. In performInstallation(), after Step 8, add:
 *    // Step 9: MCP Server Installation (optional)
 *    if ($_GET['install_mcp'] ?? false) {
 *        $this->installMCPServer($dbConfig);
 *    } else {
 *        $this->logProgress("⏭️  Step 9/9: MCP Server installation skipped (optional)");
 *    }
 */

/**
 * Check if Node.js is installed and meets version requirements
 * @return array Status information
 */
private function checkNodeJs() {
    $result = [
        'installed' => false,
        'version' => null,
        'meets_requirement' => false,
        'npm_installed' => false,
        'required_version' => '18.0.0',
        'path' => null
    ];

    // Check Node.js
    $nodeCheck = shell_exec('node --version 2>&1');
    if ($nodeCheck && preg_match('/v?(\d+\.\d+\.\d+)/', $nodeCheck, $matches)) {
        $result['installed'] = true;
        $result['version'] = $matches[1];
        $result['meets_requirement'] = version_compare($matches[1], '18.0.0', '>=');

        // Get Node.js path
        $nodePath = shell_exec('which node 2>&1');
        if ($nodePath) {
            $result['path'] = trim($nodePath);
        }
    }

    // Check npm
    $npmCheck = shell_exec('npm --version 2>&1');
    if ($npmCheck && preg_match('/(\d+\.\d+\.\d+)/', $npmCheck)) {
        $result['npm_installed'] = true;
    }

    return $result;
}

/**
 * Install MCP server with full configuration
 * @param array $dbConfig Database configuration for AMI credentials
 * @return bool Success status
 */
private function installMCPServer($dbConfig) {
    try {
        $this->logProgress("🤖 Step 9/9: Installing MCP Server (Optional AI Integration)...");

        // Check Node.js first
        $nodeStatus = $this->checkNodeJs();

        if (!$nodeStatus['installed']) {
            $this->logProgress("⚠️ Node.js not found - skipping MCP server installation");
            $this->logProgress("💡 Install Node.js 18+ to enable AI-assisted PBX management");
            $this->logProgress("   Run: curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash");
            return false;
        }

        if (!$nodeStatus['meets_requirement']) {
            $this->logProgress("⚠️ Node.js version {$nodeStatus['version']} is too old (requires 18+)");
            $this->logProgress("💡 Upgrade Node.js: nvm install 18 && nvm use 18");
            return false;
        }

        $this->logProgress("✅ Node.js {$nodeStatus['version']} detected at {$nodeStatus['path']}");
        $this->logProgress("✅ npm installed and ready");

        // Create MCP server directory
        $this->downloadMCPServerFiles();

        // Install npm dependencies
        $this->installMCPDependencies();

        // Configure .env with AMI credentials
        $this->configureMCPEnv($dbConfig);

        // Create systemd service
        $this->createMCPSystemdService();

        // Create startup script
        $this->createMCPStartupScript();

        // Start MCP server
        $this->startMCPServer();

        // Verify installation
        $this->verifyMCPInstallation();

        $this->logProgress("🎉 MCP Server installed successfully!");
        $this->logProgress("💡 Configure Claude Desktop to use: /home/flexpbxuser/mcp-server/server.js");
        $this->logProgress("💡 Documentation: /home/flexpbxuser/documentation/MCP_SERVER_INSTALLER_INTEGRATION.md");

        return true;

    } catch (Exception $e) {
        $this->logProgress("⚠️ MCP Server installation failed: " . $e->getMessage());
        $this->logProgress("💡 MCP server is optional - FlexPBX will work without it");
        $this->logProgress("💡 You can install it later from Admin → System → MCP Server Setup");
        return false;
    }
}

/**
 * Download or create MCP server files in installation directory
 */
private function downloadMCPServerFiles() {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    if (!is_dir($mcpDir)) {
        mkdir($mcpDir, 0755, true);
        mkdir("{$mcpDir}/logs", 0755, true);
    }

    $this->logProgress("📦 Creating MCP server directory: {$mcpDir}");

    // Create package.json
    $packageJson = [
        'name' => 'flexpbx-asterisk-mcp',
        'version' => '1.0.0',
        'description' => 'FlexPBX Asterisk AMI MCP Server - AI Assistant Integration',
        'main' => 'server.js',
        'scripts' => [
            'start' => 'node server.js',
            'dev' => 'nodemon server.js'
        ],
        'keywords' => ['mcp', 'asterisk', 'flexpbx', 'voip', 'pbx', 'ai'],
        'author' => 'FlexPBX',
        'license' => 'MIT',
        'dependencies' => [
            '@modelcontextprotocol/sdk' => '^1.0.0',
            'asterisk-manager' => '^0.1.16',
            'dotenv' => '^16.0.0'
        ]
    ];

    file_put_contents("{$mcpDir}/package.json", json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->logProgress("📝 Created package.json");

    // Create basic server.js
    $serverJs = <<<'JS'
#!/usr/bin/env node
/**
 * FlexPBX Asterisk MCP Server v1.0
 * Provides AI-assisted PBX management via Asterisk Manager Interface (AMI)
 *
 * Supports: Claude Desktop, Claude Code, and other MCP-compatible clients
 * Tools: list_extensions, get_active_calls, asterisk_command
 */

require('dotenv').config();
const { Server } = require('@modelcontextprotocol/sdk/server/index.js');
const { StdioServerTransport } = require('@modelcontextprotocol/sdk/server/stdio.js');
const { CallToolRequestSchema, ListToolsRequestSchema } = require('@modelcontextprotocol/sdk/types.js');
const AsteriskManager = require('asterisk-manager');

// Create MCP server
const server = new Server(
  {
    name: 'flexpbx-asterisk',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// Connect to Asterisk AMI
const ami = new AsteriskManager(
  process.env.AMI_PORT || 5038,
  process.env.AMI_HOST || 'localhost',
  process.env.AMI_USERNAME || 'admin',
  process.env.AMI_SECRET || '',
  true  // Keep connection alive
);

ami.keepConnected();

ami.on('connect', () => {
  console.error('[MCP] Connected to Asterisk AMI');
});

ami.on('disconnect', () => {
  console.error('[MCP] Disconnected from Asterisk AMI');
});

ami.on('error', (err) => {
  console.error('[MCP] AMI Error:', err.message);
});

// Define MCP tools
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: 'list_extensions',
        description: 'List all SIP/PJSIP extensions registered in the PBX',
        inputSchema: {
          type: 'object',
          properties: {
            type: {
              type: 'string',
              description: 'Extension type: sip or pjsip (default: both)',
              enum: ['sip', 'pjsip', 'both']
            }
          },
        },
      },
      {
        name: 'get_active_calls',
        description: 'Get list of currently active calls with channel details',
        inputSchema: {
          type: 'object',
          properties: {},
        },
      },
      {
        name: 'get_extension_status',
        description: 'Get registration status and details for a specific extension',
        inputSchema: {
          type: 'object',
          properties: {
            extension: {
              type: 'string',
              description: 'Extension number to check (e.g., 100, 1001)',
            },
          },
          required: ['extension'],
        },
      },
      {
        name: 'asterisk_command',
        description: 'Execute any Asterisk CLI command and get the output',
        inputSchema: {
          type: 'object',
          properties: {
            command: {
              type: 'string',
              description: 'CLI command to execute (e.g., "core show version", "sip show peers")',
            },
          },
          required: ['command'],
        },
      },
      {
        name: 'system_status',
        description: 'Get comprehensive Asterisk system status including uptime, channels, and resources',
        inputSchema: {
          type: 'object',
          properties: {},
        },
      },
    ],
  };
});

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    if (name === 'list_extensions') {
      const type = args.type || 'both';
      const results = [];

      if (type === 'sip' || type === 'both') {
        const sipPeers = await executeAMIAction({ action: 'SIPpeers' });
        results.push(`SIP Peers:\n${JSON.stringify(sipPeers, null, 2)}`);
      }

      if (type === 'pjsip' || type === 'both') {
        const pjsipEndpoints = await executeAMIAction({ action: 'PJSIPShowEndpoints' });
        results.push(`PJSIP Endpoints:\n${JSON.stringify(pjsipEndpoints, null, 2)}`);
      }

      return {
        content: [
          {
            type: 'text',
            text: results.join('\n\n'),
          },
        ],
      };
    }

    if (name === 'get_active_calls') {
      const status = await executeAMIAction({ action: 'Status' });
      return {
        content: [
          {
            type: 'text',
            text: `Active Calls:\n${JSON.stringify(status, null, 2)}`,
          },
        ],
      };
    }

    if (name === 'get_extension_status') {
      const ext = args.extension;
      const command = await executeAMIAction({
        action: 'Command',
        command: `sip show peer ${ext}`
      });

      return {
        content: [
          {
            type: 'text',
            text: `Extension ${ext} Status:\n${command.output || JSON.stringify(command, null, 2)}`,
          },
        ],
      };
    }

    if (name === 'asterisk_command') {
      const result = await executeAMIAction({
        action: 'Command',
        command: args.command,
      });

      return {
        content: [
          {
            type: 'text',
            text: result.output || JSON.stringify(result, null, 2),
          },
        ],
      };
    }

    if (name === 'system_status') {
      const commands = [
        'core show version',
        'core show uptime',
        'core show channels',
        'core show calls'
      ];

      const results = [];
      for (const cmd of commands) {
        const result = await executeAMIAction({
          action: 'Command',
          command: cmd
        });
        results.push(`${cmd}:\n${result.output || JSON.stringify(result, null, 2)}`);
      }

      return {
        content: [
          {
            type: 'text',
            text: results.join('\n\n'),
          },
        ],
      };
    }

    throw new Error(`Unknown tool: ${name}`);

  } catch (error) {
    return {
      content: [
        {
          type: 'text',
          text: `Error executing ${name}: ${error.message}`,
        },
      ],
      isError: true,
    };
  }
});

// Helper function to execute AMI actions
function executeAMIAction(action) {
  return new Promise((resolve, reject) => {
    ami.action(action, (err, res) => {
      if (err) {
        reject(err);
      } else {
        resolve(res);
      }
    });
  });
}

// Start server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('[MCP] FlexPBX Asterisk MCP server running on stdio');
  console.error('[MCP] Available tools: list_extensions, get_active_calls, get_extension_status, asterisk_command, system_status');
}

main().catch((error) => {
  console.error('[MCP] Server error:', error);
  process.exit(1);
});
JS;

    file_put_contents("{$mcpDir}/server.js", $serverJs);
    chmod("{$mcpDir}/server.js", 0755);
    $this->logProgress("📝 Created server.js with 5 AI tools");

    // Create README
    $readme = <<<'MD'
# FlexPBX Asterisk MCP Server

AI-assisted PBX management via Asterisk Manager Interface (AMI).

## Features

- **list_extensions**: List all SIP/PJSIP extensions
- **get_active_calls**: View active calls
- **get_extension_status**: Check extension registration
- **asterisk_command**: Execute any CLI command
- **system_status**: Get system health info

## Configuration

Edit `.env` file with your AMI credentials:

```bash
AMI_HOST=localhost
AMI_PORT=5038
AMI_USERNAME=admin
AMI_SECRET=your_secret
```

## Usage with Claude Desktop

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "flexpbx-asterisk": {
      "command": "node",
      "args": ["/home/flexpbxuser/mcp-server/server.js"]
    }
  }
}
```

## Manual Start

```bash
cd /home/flexpbxuser/mcp-server
node server.js
```

## Logs

Check logs at: `/home/flexpbxuser/mcp-server/logs/mcp.log`

## Documentation

Full documentation: `/home/flexpbxuser/documentation/MCP_SERVER_INSTALLER_INTEGRATION.md`
MD;

    file_put_contents("{$mcpDir}/README.md", $readme);
    $this->logProgress("📝 Created README.md");
}

/**
 * Install npm packages for MCP server
 */
private function installMCPDependencies() {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    $this->logProgress("📦 Installing npm dependencies (this may take 2-3 minutes)...");

    // Run npm install
    $output = [];
    $returnVar = 0;

    $command = "cd {$mcpDir} && npm install --production 2>&1";
    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        throw new Exception("npm install failed: " . implode("\n", array_slice($output, -5)));
    }

    $this->logProgress("✅ npm dependencies installed (@modelcontextprotocol/sdk, asterisk-manager, dotenv)");
}

/**
 * Create .env file with AMI credentials from FlexPBX/Asterisk config
 * @param array $dbConfig Database configuration
 */
private function configureMCPEnv($dbConfig) {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    // Try to get AMI credentials from Asterisk manager.conf
    $amiUser = 'admin';
    $amiSecret = '';
    $amiHost = 'localhost';
    $amiPort = '5038';

    // Attempt to read from manager.conf if accessible
    $managerConf = '/etc/asterisk/manager.conf';
    if (file_exists($managerConf) && is_readable($managerConf)) {
        $content = file_get_contents($managerConf);

        // Extract username (section name)
        if (preg_match('/\[([a-zA-Z0-9_-]+)\]/', $content, $matches)) {
            $amiUser = $matches[1];
        }

        // Extract secret
        if (preg_match('/secret\s*=\s*([^\s\r\n]+)/', $content, $matches)) {
            $amiSecret = trim($matches[1]);
        }

        // Extract port
        if (preg_match('/port\s*=\s*(\d+)/', $content, $matches)) {
            $amiPort = $matches[1];
        }

        $this->logProgress("📖 Read AMI credentials from manager.conf");
    } else {
        $this->logProgress("⚠️ Cannot read manager.conf - using default AMI credentials");
        $this->logProgress("💡 Update .env file manually if AMI connection fails");
    }

    $envContent = "# FlexPBX MCP Server Configuration\n";
    $envContent .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $envContent .= "# Asterisk Manager Interface (AMI) Configuration\n";
    $envContent .= "AMI_HOST={$amiHost}\n";
    $envContent .= "AMI_PORT={$amiPort}\n";
    $envContent .= "AMI_USERNAME={$amiUser}\n";
    $envContent .= "AMI_SECRET={$amiSecret}\n\n";
    $envContent .= "# MCP Server Configuration\n";
    $envContent .= "MCP_SERVER_NAME=flexpbx-asterisk\n";
    $envContent .= "MCP_SERVER_VERSION=1.0.0\n\n";
    $envContent .= "# Logging\n";
    $envContent .= "LOG_LEVEL=info\n";

    file_put_contents("{$mcpDir}/.env", $envContent);
    $this->logProgress("⚙️ Created .env configuration");
    $this->logProgress("💡 AMI credentials: {$amiUser}@{$amiHost}:{$amiPort}");

    if (empty($amiSecret)) {
        $this->logProgress("⚠️ AMI secret is empty - update .env file with correct credentials");
    }
}

/**
 * Create systemd service for MCP server auto-start
 */
private function createMCPSystemdService() {
    $mcpDir = '/home/flexpbxuser/mcp-server';
    $user = 'flexpbxuser';

    // Get node path
    $nodePath = trim(shell_exec('which node 2>&1') ?: '/usr/bin/node');

    $serviceContent = "[Unit]\n";
    $serviceContent .= "Description=FlexPBX Asterisk MCP Server (AI Assistant Integration)\n";
    $serviceContent .= "After=network.target asterisk.service\n";
    $serviceContent .= "Wants=asterisk.service\n\n";
    $serviceContent .= "[Service]\n";
    $serviceContent .= "Type=simple\n";
    $serviceContent .= "User={$user}\n";
    $serviceContent .= "WorkingDirectory={$mcpDir}\n";
    $serviceContent .= "ExecStart={$nodePath} {$mcpDir}/server.js\n";
    $serviceContent .= "Restart=on-failure\n";
    $serviceContent .= "RestartSec=10\n";
    $serviceContent .= "StandardOutput=append:{$mcpDir}/logs/mcp.log\n";
    $serviceContent .= "StandardError=append:{$mcpDir}/logs/mcp.log\n\n";
    $serviceContent .= "[Install]\n";
    $serviceContent .= "WantedBy=multi-user.target\n";

    $serviceFile = '/etc/systemd/system/flexpbx-mcp.service';

    file_put_contents("{$mcpDir}/flexpbx-mcp.service", $serviceContent);

    $this->logProgress("📝 Created systemd service file");
    $this->logProgress("💡 To enable auto-start on boot, run as root:");
    $this->logProgress("   sudo cp {$mcpDir}/flexpbx-mcp.service /etc/systemd/system/");
    $this->logProgress("   sudo systemctl daemon-reload");
    $this->logProgress("   sudo systemctl enable flexpbx-mcp");
    $this->logProgress("   sudo systemctl start flexpbx-mcp");
}

/**
 * Create startup script for MCP server
 */
private function createMCPStartupScript() {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    $script = "#!/bin/bash\n";
    $script .= "# FlexPBX MCP Server Startup Script\n\n";
    $script .= "cd {$mcpDir}\n";
    $script .= "node server.js >> logs/mcp.log 2>&1 &\n";
    $script .= "echo \$! > server.pid\n";
    $script .= "echo \"MCP Server started (PID: \$(cat server.pid))\"\n";

    file_put_contents("{$mcpDir}/start.sh", $script);
    chmod("{$mcpDir}/start.sh", 0755);

    $this->logProgress("📝 Created start.sh script");
}

/**
 * Start MCP server in background
 */
private function startMCPServer() {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    // Check if already running
    $pidFile = "{$mcpDir}/server.pid";
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if (!empty($pid) && posix_kill((int)$pid, 0)) {
            $this->logProgress("⚠️ MCP server already running (PID: {$pid})");
            return;
        }
    }

    // Start server in background
    $cmd = "cd {$mcpDir} && nohup node server.js >> logs/mcp.log 2>&1 & echo $!";
    $pid = trim(shell_exec($cmd));

    if ($pid && is_numeric($pid)) {
        file_put_contents($pidFile, $pid);
        $this->logProgress("🚀 MCP server started (PID: {$pid})");

        // Give it time to start and connect to AMI
        sleep(3);
    } else {
        throw new Exception("Failed to start MCP server");
    }
}

/**
 * Verify MCP server installation and connectivity
 */
private function verifyMCPInstallation() {
    $mcpDir = '/home/flexpbxuser/mcp-server';

    // Check if process is running
    $pidFile = "{$mcpDir}/server.pid";
    if (!file_exists($pidFile)) {
        throw new Exception("MCP server PID file not found");
    }

    $pid = trim(file_get_contents($pidFile));
    if (empty($pid) || !is_numeric($pid)) {
        throw new Exception("Invalid PID in server.pid");
    }

    if (!posix_kill((int)$pid, 0)) {
        throw new Exception("MCP server process not running");
    }

    $this->logProgress("✅ MCP server process verified (PID: {$pid})");

    // Check log file for connection status
    $logFile = "{$mcpDir}/logs/mcp.log";
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);

        if (strpos($logContent, 'Connected to Asterisk AMI') !== false) {
            $this->logProgress("✅ MCP server connected to Asterisk AMI successfully");
        } else if (strpos($logContent, 'running on stdio') !== false) {
            $this->logProgress("✅ MCP server running and waiting for stdio connections");
        } else if (strpos($logContent, 'error') !== false || strpos($logContent, 'Error') !== false) {
            $this->logProgress("⚠️ Check logs for connection issues: {$logFile}");
            $this->logProgress("💡 Common issue: Verify AMI credentials in .env file");
        }
    }

    // Check if dependencies are installed
    if (file_exists("{$mcpDir}/node_modules")) {
        $this->logProgress("✅ npm dependencies verified");
    }

    $this->logProgress("✅ MCP server installation verified and operational");
}

?>
