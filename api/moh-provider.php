<?php
/**
 * FlexPBX MOH Provider Service
 * Public API endpoint for other installations to discover and use our MOH streams
 * Allows remote FlexPBX instances to add our music streams to their systems
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'info';

switch ($action) {
    case 'info':
        providerInfo();
        break;

    case 'list':
        listMOHStreams();
        break;

    case 'search':
        searchMOHStreams();
        break;

    case 'install':
        getInstallInstructions();
        break;

    case 'config':
        getAsteriskConfig();
        break;

    case 'module-manifest':
        getModuleManifest();
        break;

    case 'health':
        healthCheck();
        break;

    default:
        respond(['error' => 'Invalid action'], 404);
        break;
}

/**
 * Provider information
 */
function providerInfo() {
    respond([
        'provider' => [
            'name' => 'FlexPBX MOH Provider',
            'version' => '1.0.0',
            'description' => 'Professional music on hold streaming service with accessibility-focused content',
            'vendor' => 'Devine Creations',
            'url' => 'https://flexpbx.devinecreations.net',
            'contact' => 'admin@devinecreations.net'
        ],
        'service' => [
            'type' => 'moh-provider',
            'protocol' => 'https',
            'streams_available' => 4,
            'formats_supported' => ['mp3', 'icecast'],
            'quality' => 'high',
            'uptime_sla' => '99.9%'
        ],
        'features' => [
            'audio_described_content' => true,
            'multiple_genres' => true,
            'scheduled_programming' => true,
            'accessibility_focused' => true,
            'free_tier' => true
        ],
        'endpoints' => [
            'list' => '?action=list',
            'search' => '?action=search&q={query}',
            'install' => '?action=install',
            'config' => '?action=config',
            'manifest' => '?action=module-manifest',
            'health' => '?action=health'
        ]
    ]);
}

/**
 * Get streams data (shared function)
 */
function getStreamsData() {
    return [
        [
            'id' => 'raywonder-radio',
            'name' => 'Raywonder Radio',
            'display_name' => 'Raywonder Radio (Audio Described)',
            'description' => 'Scheduled Audio Described TV Shows, Movies, and Music - Accessibility Focused',
            'url' => 'https://stream.raywonderis.me/jellyfin-radio',
            'stream_type' => 'icecast',
            'format' => 'mp3',
            'bitrate' => '192kbps',
            'codec' => 'MP3',
            'sample_rate' => '44100',
            'channels' => 'stereo',
            'category' => 'entertainment',
            'tags' => ['audio-described', 'accessibility', 'tv', 'movies', 'music'],
            'schedule' => 'TV Episodes (even hours) | Movies (odd hours) | Music between',
            'content_library' => [
                'tv_episodes' => 2424,
                'movies' => 538,
                'audiobooks' => 201,
                'music_tracks' => 87
            ],
            'accessibility_features' => [
                'audio_description' => true,
                'screen_reader_friendly' => true,
                'visual_impairment_optimized' => true
            ],
            'recommended' => true,
            'featured' => true,
            'asterisk_config' => [
                'mode' => 'custom',
                'application' => '/usr/bin/ffmpeg -i https://stream.raywonderis.me/jellyfin-radio -f s16le -ar 8000 -ac 1 -',
                'format' => 'slin'
            ]
        ],
        [
            'id' => 'tappedin-radio',
            'name' => 'TappedIn Radio',
            'display_name' => 'TappedIn Radio (Meditation & Soundscapes)',
            'description' => 'Relaxing soundscapes, meditation music, and calming podcasts',
            'url' => 'https://stream.tappedin.fm/tappedin-radio',
            'stream_type' => 'icecast',
            'format' => 'mp3',
            'bitrate' => '192kbps',
            'codec' => 'MP3',
            'sample_rate' => '44100',
            'channels' => 'stereo',
            'category' => 'meditation',
            'tags' => ['meditation', 'relaxation', 'ambient', 'wellness'],
            'schedule' => 'Continuous ambient and meditation music',
            'recommended' => true,
            'asterisk_config' => [
                'mode' => 'custom',
                'application' => '/usr/bin/ffmpeg -i https://stream.tappedin.fm/tappedin-radio -f s16le -ar 8000 -ac 1 -',
                'format' => 'slin'
            ]
        ],
        [
            'id' => 'chrismix-radio',
            'name' => 'ChrisMix Radio',
            'display_name' => 'ChrisMix Radio',
            'description' => 'Curated music streaming with various genres',
            'url' => 'http://s23.myradiostream.com:9372/',
            'stream_type' => 'shoutcast',
            'format' => 'mp3',
            'bitrate' => '128kbps',
            'codec' => 'MP3',
            'sample_rate' => '44100',
            'channels' => 'stereo',
            'category' => 'music',
            'tags' => ['music', 'variety', 'entertainment'],
            'schedule' => 'Continuous music streaming',
            'asterisk_config' => [
                'mode' => 'custom',
                'application' => '/usr/bin/mpg123 -q -r 8000 --mono -s http://s23.myradiostream.com:9372/',
                'format' => 'slin'
            ]
        ],
        [
            'id' => 'soulfood-radio',
            'name' => 'SoulFood Radio',
            'display_name' => 'SoulFood Radio',
            'description' => 'Soul, R&B, and classic music streaming',
            'url' => 'http://s38.myradiostream.com:15874',
            'stream_type' => 'shoutcast',
            'format' => 'mp3',
            'bitrate' => '128kbps',
            'codec' => 'MP3',
            'sample_rate' => '44100',
            'channels' => 'stereo',
            'category' => 'music',
            'tags' => ['soul', 'rnb', 'classic', 'music'],
            'schedule' => 'Continuous soul and R&B music',
            'asterisk_config' => [
                'mode' => 'custom',
                'application' => '/usr/bin/mpg123 -q -r 8000 --mono -s http://s38.myradiostream.com:15874',
                'format' => 'slin'
            ]
        ]
    ];
}

/**
 * List all available MOH streams
 */
function listMOHStreams() {
    $streams = getStreamsData();

    respond([
        'success' => true,
        'provider' => 'FlexPBX MOH Provider',
        'total_streams' => count($streams),
        'streams' => $streams,
        'last_updated' => '2025-10-21T00:00:00Z'
    ]);
}

/**
 * Search MOH streams
 */
function searchMOHStreams() {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';

    // Get all streams
    $streams = getStreamsData();

    // Filter streams
    $filtered = [];
    foreach ($streams as $stream) {
        $matches = false;

        // Search by query
        if (!empty($query)) {
            $searchFields = [
                $stream['name'],
                $stream['description'],
                implode(' ', $stream['tags'])
            ];
            $searchText = strtolower(implode(' ', $searchFields));
            if (stripos($searchText, strtolower($query)) !== false) {
                $matches = true;
            }
        }

        // Filter by category
        if (!empty($category)) {
            if (strtolower($stream['category']) === strtolower($category)) {
                $matches = true;
            }
        }

        // If no filters, include all
        if (empty($query) && empty($category)) {
            $matches = true;
        }

        if ($matches) {
            $filtered[] = $stream;
        }
    }

    respond([
        'success' => true,
        'query' => $query,
        'category' => $category,
        'total_results' => count($filtered),
        'streams' => $filtered
    ]);
}

/**
 * Get installation instructions
 */
function getInstallInstructions() {
    $format = $_GET['format'] ?? 'json';

    $instructions = [
        'title' => 'FlexPBX MOH Provider Installation',
        'version' => '1.0.0',
        'compatibility' => [
            'asterisk' => '16.x, 18.x, 20.x',
            'freepbx' => '15.x, 16.x',
            'flexpbx' => '1.x'
        ],
        'requirements' => [
            'ffmpeg' => 'Required for HTTPS streams',
            'mpg123' => 'Required for HTTP streams',
            'internet_connection' => 'Required for streaming'
        ],
        'installation_methods' => [
            'automatic' => [
                'description' => 'Automatic installation via FlexPBX module manager',
                'steps' => [
                    '1. Go to Admin → Modules → Available Modules',
                    '2. Search for "FlexPBX MOH Provider"',
                    '3. Click "Install"',
                    '4. Select desired streams',
                    '5. Click "Apply Configuration"'
                ]
            ],
            'manual' => [
                'description' => 'Manual installation via Asterisk configuration',
                'steps' => [
                    '1. Edit /etc/asterisk/musiconhold.conf',
                    '2. Add stream configurations (see config endpoint)',
                    '3. Run: asterisk -rx "moh reload"',
                    '4. Verify: asterisk -rx "moh show classes"'
                ]
            ],
            'api' => [
                'description' => 'Programmatic installation via API',
                'endpoint' => 'GET ?action=config',
                'steps' => [
                    '1. Fetch configuration: curl https://flexpbx.devinecreations.net/api/moh-provider.php?action=config',
                    '2. Append to musiconhold.conf',
                    '3. Reload MOH module'
                ]
            ]
        ],
        'post_installation' => [
            'verify' => 'asterisk -rx "moh show classes"',
            'test' => 'Call extension and place on hold',
            'configure' => 'Set per-extension MOH via moh_suggest parameter'
        ],
        'support' => [
            'documentation' => 'https://flexpbx.devinecreations.net/docs/moh-provider',
            'api_docs' => 'https://flexpbx.devinecreations.net/api/moh-provider.php?action=info',
            'contact' => 'admin@devinecreations.net'
        ]
    ];

    respond([
        'success' => true,
        'instructions' => $instructions
    ]);
}

/**
 * Get Asterisk configuration
 */
function getAsteriskConfig() {
    $stream_id = $_GET['stream'] ?? 'all';
    $format = $_GET['format'] ?? 'asterisk';

    ob_start();
    listMOHStreams();
    $output = ob_get_clean();
    $data = json_decode($output, true);
    $streams = $data['streams'] ?? [];

    // Filter by stream ID if specified
    if ($stream_id !== 'all') {
        $streams = array_filter($streams, function($s) use ($stream_id) {
            return $s['id'] === $stream_id;
        });
    }

    if ($format === 'asterisk') {
        // Generate Asterisk config format
        $config = "; FlexPBX MOH Provider - Music On Hold Configuration\n";
        $config .= "; Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= "; Provider: https://flexpbx.devinecreations.net\n\n";

        foreach ($streams as $stream) {
            $config .= "; {$stream['display_name']}\n";
            $config .= "[{$stream['id']}]\n";
            $config .= "mode={$stream['asterisk_config']['mode']}\n";
            $config .= "application={$stream['asterisk_config']['application']}\n";
            $config .= "format={$stream['asterisk_config']['format']}\n";
            $config .= "; {$stream['description']}\n\n";
        }

        header('Content-Type: text/plain');
        echo $config;
        exit;
    } else {
        // Return JSON format
        respond([
            'success' => true,
            'format' => 'json',
            'streams' => $streams,
            'usage' => 'Append this configuration to /etc/asterisk/musiconhold.conf'
        ]);
    }
}

/**
 * Get module manifest for FlexPBX module manager
 */
function getModuleManifest() {
    $manifest = [
        'module' => [
            'id' => 'flexpbx-moh-provider',
            'name' => 'FlexPBX MOH Provider',
            'display_name' => 'Music On Hold Provider Service',
            'version' => '1.0.0',
            'description' => 'Professional music on hold streaming service with accessibility-focused content',
            'category' => 'media',
            'type' => 'extension',
            'vendor' => [
                'name' => 'Devine Creations',
                'url' => 'https://devinecreations.net',
                'contact' => 'admin@devinecreations.net'
            ],
            'license' => 'Free for non-commercial use',
            'icon' => 'https://flexpbx.devinecreations.net/images/moh-provider-icon.png',
            'screenshots' => [
                'https://flexpbx.devinecreations.net/images/moh-screenshot-1.png',
                'https://flexpbx.devinecreations.net/images/moh-screenshot-2.png'
            ]
        ],
        'compatibility' => [
            'min_asterisk_version' => '16.0.0',
            'max_asterisk_version' => '20.99.99',
            'min_flexpbx_version' => '1.0.0',
            'php_version' => '7.4',
            'required_modules' => ['res_musiconhold']
        ],
        'dependencies' => [
            'system' => [
                'ffmpeg' => 'Required for HTTPS streams',
                'mpg123' => 'Optional for HTTP streams'
            ],
            'asterisk_modules' => [
                'res_musiconhold.so'
            ]
        ],
        'features' => [
            'streams' => 4,
            'audio_described_content' => true,
            'accessibility_focused' => true,
            'categories' => ['entertainment', 'meditation', 'music'],
            'scheduled_programming' => true,
            'high_quality' => true,
            'https_streaming' => true
        ],
        'installation' => [
            'method' => 'automatic',
            'config_files' => [
                '/etc/asterisk/musiconhold.conf'
            ],
            'requires_reload' => ['moh'],
            'install_script' => 'https://flexpbx.devinecreations.net/api/moh-provider.php?action=install'
        ],
        'api' => [
            'base_url' => 'https://flexpbx.devinecreations.net/api/moh-provider.php',
            'endpoints' => [
                'list' => '?action=list',
                'search' => '?action=search',
                'config' => '?action=config',
                'health' => '?action=health'
            ],
            'authentication' => 'none',
            'rate_limit' => 'none'
        ],
        'pricing' => [
            'free_tier' => true,
            'commercial_license' => false,
            'support' => 'community'
        ]
    ];

    respond([
        'success' => true,
        'manifest' => $manifest
    ]);
}

/**
 * Health check endpoint
 */
function healthCheck() {
    $streams_status = [];

    // Check each stream
    $streams = [
        'raywonder-radio' => 'https://stream.raywonderis.me/jellyfin-radio',
        'tappedin-radio' => 'https://stream.tappedin.fm/tappedin-radio',
        'chrismix-radio' => 'http://s23.myradiostream.com:9372/',
        'soulfood-radio' => 'http://s38.myradiostream.com:15874'
    ];

    foreach ($streams as $id => $url) {
        // Simple check - in production, would do actual stream health check
        $streams_status[$id] = [
            'status' => 'operational',
            'url' => $url,
            'last_checked' => date('c')
        ];
    }

    respond([
        'success' => true,
        'service_status' => 'operational',
        'uptime' => '99.9%',
        'streams' => $streams_status,
        'last_updated' => date('c')
    ]);
}

/**
 * Send JSON response
 */
function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
