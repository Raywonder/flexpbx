<?php
/**
 * FlexPBX MOH Submission API
 * Allows external users to submit streams and become MOH providers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('SUBMISSIONS_DIR', '/home/flexpbxuser/moh-submissions');
define('PROVIDERS_FILE', '/home/flexpbxuser/moh-providers.json');
define('SUBMISSIONS_FILE', '/home/flexpbxuser/moh-submissions.json');

// Ensure directories exist
if (!is_dir(SUBMISSIONS_DIR)) {
    mkdir(SUBMISSIONS_DIR, 0755, true);
}

$action = $_GET['action'] ?? 'info';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'info':
        getSubmissionInfo();
        break;

    case 'submit-stream':
        if ($method === 'POST') {
            submitStream();
        } else {
            respond(['error' => 'POST method required'], 405);
        }
        break;

    case 'submit-provider':
        if ($method === 'POST') {
            submitProvider();
        } else {
            respond(['error' => 'POST method required'], 405);
        }
        break;

    case 'list-community-streams':
        listCommunityStreams();
        break;

    case 'list-providers':
        listProviders();
        break;

    case 'search-community':
        searchCommunityStreams();
        break;

    case 'get-guidelines':
        getSubmissionGuidelines();
        break;

    case 'validate-stream':
        if ($method === 'POST') {
            validateStream();
        } else {
            respond(['error' => 'POST method required'], 405);
        }
        break;

    default:
        respond(['error' => 'Invalid action'], 404);
        break;
}

/**
 * Get submission information
 */
function getSubmissionInfo() {
    respond([
        'service' => [
            'name' => 'FlexPBX MOH Community Submissions',
            'version' => '1.0.0',
            'description' => 'Submit streams and become a MOH provider for the FlexPBX community'
        ],
        'submission_types' => [
            'stream' => 'Submit an individual music stream',
            'provider' => 'Register as a MOH provider server',
            'content' => 'Contribute audio content for existing streams'
        ],
        'guidelines' => [
            'content_must_be_legal' => true,
            'licensing_required' => true,
            'accessibility_encouraged' => true,
            'high_quality_preferred' => true,
            'family_friendly_recommended' => true
        ],
        'endpoints' => [
            'submit_stream' => '?action=submit-stream (POST)',
            'submit_provider' => '?action=submit-provider (POST)',
            'list_community' => '?action=list-community-streams',
            'list_providers' => '?action=list-providers',
            'search' => '?action=search-community&q={query}',
            'guidelines' => '?action=get-guidelines',
            'validate' => '?action=validate-stream (POST)'
        ],
        'moderation' => [
            'review_required' => true,
            'approval_time' => '24-72 hours',
            'quality_check' => true,
            'licensing_verification' => true
        ]
    ]);
}

/**
 * Submit a stream
 */
function submitStream() {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['stream_name', 'stream_url', 'description', 'category', 'submitter_name', 'submitter_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            respond(['error' => "Missing required field: $field"], 400);
            return;
        }
    }

    // Validate email
    if (!filter_var($data['submitter_email'], FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Invalid email address'], 400);
        return;
    }

    // Validate URL
    if (!filter_var($data['stream_url'], FILTER_VALIDATE_URL)) {
        respond(['error' => 'Invalid stream URL'], 400);
        return;
    }

    // Create submission
    $submission = [
        'id' => uniqid('stream_', true),
        'type' => 'stream',
        'submitted_at' => date('c'),
        'status' => 'pending',
        'stream_info' => [
            'name' => sanitize($data['stream_name']),
            'display_name' => sanitize($data['display_name'] ?? $data['stream_name']),
            'url' => $data['stream_url'],
            'description' => sanitize($data['description']),
            'category' => sanitize($data['category']),
            'tags' => $data['tags'] ?? [],
            'format' => $data['format'] ?? 'mp3',
            'bitrate' => $data['bitrate'] ?? 'unknown',
            'stream_type' => $data['stream_type'] ?? 'icecast',
            'accessibility_features' => $data['accessibility_features'] ?? [],
            'licensing' => $data['licensing'] ?? 'unknown',
            'content_rating' => $data['content_rating'] ?? 'general'
        ],
        'submitter' => [
            'name' => sanitize($data['submitter_name']),
            'email' => $data['submitter_email'],
            'organization' => sanitize($data['submitter_organization'] ?? ''),
            'website' => $data['submitter_website'] ?? '',
            'contact_allowed' => $data['contact_allowed'] ?? true
        ],
        'technical_info' => [
            'requires_ffmpeg' => $data['requires_ffmpeg'] ?? true,
            'requires_mpg123' => $data['requires_mpg123'] ?? false,
            'bandwidth_estimate' => $data['bandwidth_estimate'] ?? 'medium',
            'uptime_claim' => $data['uptime_claim'] ?? 'best-effort'
        ],
        'notes' => sanitize($data['notes'] ?? '')
    ];

    // Save submission
    $submissions = loadSubmissions();
    $submissions[] = $submission;
    saveSubmissions($submissions);

    // Send notification email (in production)
    // sendNotificationEmail($submission);

    respond([
        'success' => true,
        'message' => 'Stream submission received and pending review',
        'submission_id' => $submission['id'],
        'status' => 'pending',
        'expected_review_time' => '24-72 hours',
        'next_steps' => [
            'Our team will review your submission',
            'We will verify stream accessibility and quality',
            'We will check licensing compliance',
            'You will receive an email when approved or if we need more information'
        ]
    ]);
}

/**
 * Submit provider registration
 */
function submitProvider() {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['provider_name', 'api_endpoint', 'contact_name', 'contact_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            respond(['error' => "Missing required field: $field"], 400);
            return;
        }
    }

    // Validate email
    if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Invalid email address'], 400);
        return;
    }

    // Validate API endpoint
    if (!filter_var($data['api_endpoint'], FILTER_VALIDATE_URL)) {
        respond(['error' => 'Invalid API endpoint URL'], 400);
        return;
    }

    // Create provider registration
    $provider = [
        'id' => uniqid('provider_', true),
        'type' => 'provider',
        'submitted_at' => date('c'),
        'status' => 'pending',
        'provider_info' => [
            'name' => sanitize($data['provider_name']),
            'display_name' => sanitize($data['display_name'] ?? $data['provider_name']),
            'description' => sanitize($data['description'] ?? ''),
            'api_endpoint' => $data['api_endpoint'],
            'website' => $data['website'] ?? '',
            'country' => sanitize($data['country'] ?? ''),
            'region' => sanitize($data['region'] ?? '')
        ],
        'capabilities' => [
            'streams_offered' => (int)($data['streams_offered'] ?? 1),
            'supports_search' => $data['supports_search'] ?? false,
            'supports_manifest' => $data['supports_manifest'] ?? false,
            'supports_health_check' => $data['supports_health_check'] ?? false,
            'https_streaming' => $data['https_streaming'] ?? false,
            'api_version' => $data['api_version'] ?? '1.0'
        ],
        'contact' => [
            'name' => sanitize($data['contact_name']),
            'email' => $data['contact_email'],
            'organization' => sanitize($data['organization'] ?? ''),
            'phone' => sanitize($data['contact_phone'] ?? ''),
            'public_contact' => $data['public_contact'] ?? false
        ],
        'infrastructure' => [
            'hosting_type' => sanitize($data['hosting_type'] ?? 'unknown'),
            'uptime_sla' => $data['uptime_sla'] ?? 'best-effort',
            'bandwidth_capacity' => sanitize($data['bandwidth_capacity'] ?? 'unknown'),
            'geographic_regions' => $data['geographic_regions'] ?? [],
            'cdn_enabled' => $data['cdn_enabled'] ?? false
        ],
        'terms' => [
            'free_tier' => $data['free_tier'] ?? true,
            'commercial_terms' => sanitize($data['commercial_terms'] ?? 'free'),
            'usage_limits' => sanitize($data['usage_limits'] ?? 'none'),
            'attribution_required' => $data['attribution_required'] ?? false
        ],
        'notes' => sanitize($data['notes'] ?? '')
    ];

    // Save provider registration
    $providers = loadProviders();
    $providers[] = $provider;
    saveProviders($providers);

    respond([
        'success' => true,
        'message' => 'Provider registration received and pending review',
        'provider_id' => $provider['id'],
        'status' => 'pending',
        'expected_review_time' => '24-72 hours',
        'verification_steps' => [
            'API endpoint verification',
            'Stream quality testing',
            'Uptime monitoring setup',
            'Security audit',
            'Documentation review'
        ],
        'next_steps' => [
            'Our team will verify your API endpoint',
            'We will test stream quality and accessibility',
            'We will review your terms and SLA',
            'You will receive onboarding documentation upon approval'
        ]
    ]);
}

/**
 * List community-submitted streams (approved only)
 */
function listCommunityStreams() {
    $submissions = loadSubmissions();

    // Filter approved streams only
    $approved = array_filter($submissions, function($s) {
        return $s['type'] === 'stream' && $s['status'] === 'approved';
    });

    $streams = array_map(function($s) {
        return [
            'id' => $s['stream_info']['name'],
            'name' => $s['stream_info']['name'],
            'display_name' => $s['stream_info']['display_name'],
            'description' => $s['stream_info']['description'],
            'url' => $s['stream_info']['url'],
            'category' => $s['stream_info']['category'],
            'tags' => $s['stream_info']['tags'],
            'format' => $s['stream_info']['format'],
            'bitrate' => $s['stream_info']['bitrate'],
            'contributed_by' => $s['submitter']['name'],
            'approved_at' => $s['approved_at'] ?? null,
            'community_contributed' => true
        ];
    }, $approved);

    respond([
        'success' => true,
        'total_community_streams' => count($streams),
        'streams' => array_values($streams),
        'note' => 'These streams are community-contributed and verified'
    ]);
}

/**
 * List registered providers
 */
function listProviders() {
    $providers = loadProviders();

    // Filter approved providers only
    $approved = array_filter($providers, function($p) {
        return $p['type'] === 'provider' && $p['status'] === 'approved';
    });

    $provider_list = array_map(function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['provider_info']['name'],
            'display_name' => $p['provider_info']['display_name'],
            'description' => $p['provider_info']['description'],
            'api_endpoint' => $p['provider_info']['api_endpoint'],
            'website' => $p['provider_info']['website'],
            'country' => $p['provider_info']['country'],
            'streams_offered' => $p['capabilities']['streams_offered'],
            'https_streaming' => $p['capabilities']['https_streaming'],
            'free_tier' => $p['terms']['free_tier'],
            'uptime_sla' => $p['infrastructure']['uptime_sla'],
            'approved_at' => $p['approved_at'] ?? null
        ];
    }, $approved);

    respond([
        'success' => true,
        'total_providers' => count($provider_list),
        'providers' => array_values($provider_list),
        'note' => 'Federated network of MOH providers'
    ]);
}

/**
 * Search community streams
 */
function searchCommunityStreams() {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';

    ob_start();
    listCommunityStreams();
    $output = ob_get_clean();
    $data = json_decode($output, true);
    $streams = $data['streams'] ?? [];

    $filtered = array_filter($streams, function($stream) use ($query, $category) {
        if (!empty($query)) {
            $searchText = strtolower(implode(' ', [
                $stream['name'],
                $stream['description'],
                implode(' ', $stream['tags'])
            ]));
            if (stripos($searchText, strtolower($query)) === false) {
                return false;
            }
        }

        if (!empty($category) && strtolower($stream['category']) !== strtolower($category)) {
            return false;
        }

        return true;
    });

    respond([
        'success' => true,
        'query' => $query,
        'category' => $category,
        'total_results' => count($filtered),
        'streams' => array_values($filtered)
    ]);
}

/**
 * Get submission guidelines
 */
function getSubmissionGuidelines() {
    respond([
        'success' => true,
        'guidelines' => [
            'content_requirements' => [
                'legal_compliance' => [
                    'description' => 'All content must be legally obtained and distributed',
                    'requirements' => [
                        'You must own the rights or have proper licensing',
                        'No copyrighted music without proper licenses',
                        'Creative Commons content must be properly attributed',
                        'Public domain content is welcome'
                    ]
                ],
                'quality_standards' => [
                    'description' => 'Streams must meet minimum quality standards',
                    'requirements' => [
                        'Minimum 64kbps bitrate (128kbps+ recommended)',
                        'Consistent audio levels (no sudden volume changes)',
                        'No extended silence or dead air',
                        'Clear audio without excessive noise'
                    ]
                ],
                'content_appropriateness' => [
                    'description' => 'Content should be suitable for business environments',
                    'requirements' => [
                        'No explicit language or adult content',
                        'Family-friendly content preferred',
                        'Professional and appropriate for all audiences',
                        'Cultural sensitivity required'
                    ]
                ]
            ],
            'technical_requirements' => [
                'streaming' => [
                    'supported_protocols' => ['HTTP', 'HTTPS', 'Icecast', 'Shoutcast'],
                    'supported_formats' => ['MP3', 'AAC', 'OGG'],
                    'recommended_bitrate' => '128-192kbps',
                    'uptime_expectation' => '95%+ recommended'
                ],
                'accessibility' => [
                    'audio_description' => 'Highly valued for video content',
                    'closed_captions' => 'Appreciated where applicable',
                    'clear_speech' => 'Important for instructional content',
                    'volume_normalization' => 'Required for consistent experience'
                ]
            ],
            'submission_process' => [
                'steps' => [
                    '1. Fill out submission form with stream details',
                    '2. Provide licensing/copyright information',
                    '3. Submit for review',
                    '4. Our team tests stream quality and accessibility',
                    '5. Approval notification sent via email (24-72 hours)',
                    '6. Stream added to community catalog'
                ],
                'review_criteria' => [
                    'Legal compliance verified',
                    'Stream accessibility tested',
                    'Audio quality assessed',
                    'Content appropriateness reviewed',
                    'Technical reliability checked'
                ]
            ],
            'becoming_a_provider' => [
                'description' => 'Host your own MOH provider server',
                'requirements' => [
                    'Public API endpoint (compatible with our spec)',
                    'Minimum 95% uptime',
                    'HTTPS support recommended',
                    'Health check endpoint required',
                    'At least 1 stream offered'
                ],
                'benefits' => [
                    'Listed in federated provider network',
                    'Discoverable by FlexPBX installations worldwide',
                    'API documentation and support provided',
                    'Community recognition',
                    'Optional revenue sharing for premium streams'
                ]
            ],
            'attribution' => [
                'required_for' => ['Creative Commons content', 'Third-party contributions'],
                'optional_for' => ['Original content', 'Public domain'],
                'format' => 'Provider name and URL in stream metadata'
            ]
        ]
    ]);
}

/**
 * Validate stream (test before submission)
 */
function validateStream() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['stream_url'])) {
        respond(['error' => 'stream_url required'], 400);
        return;
    }

    $url = $data['stream_url'];

    // Basic URL validation
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        respond([
            'success' => false,
            'valid' => false,
            'errors' => ['Invalid URL format']
        ]);
        return;
    }

    $validation = [
        'url_valid' => true,
        'protocol' => parse_url($url, PHP_URL_SCHEME),
        'host_reachable' => false,
        'https_supported' => false,
        'estimated_quality' => 'unknown',
        'recommendations' => []
    ];

    // Check if HTTPS
    if ($validation['protocol'] === 'https') {
        $validation['https_supported'] = true;
        $validation['recommendations'][] = 'Great! HTTPS streaming is preferred for security';
    } else {
        $validation['recommendations'][] = 'Consider using HTTPS for better security';
    }

    // In production, would actually test the stream
    // For now, provide recommendations based on URL
    $validation['recommendations'][] = 'Test your stream before submitting';
    $validation['recommendations'][] = 'Ensure consistent uptime';
    $validation['recommendations'][] = 'Check audio quality and levels';

    respond([
        'success' => true,
        'valid' => true,
        'validation' => $validation,
        'next_steps' => [
            'If validation passes, proceed with submission',
            'Include licensing information',
            'Provide detailed stream description'
        ]
    ]);
}

/**
 * Helper functions
 */
function loadSubmissions() {
    if (!file_exists(SUBMISSIONS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(SUBMISSIONS_FILE), true) ?? [];
}

function saveSubmissions($submissions) {
    file_put_contents(SUBMISSIONS_FILE, json_encode($submissions, JSON_PRETTY_PRINT));
    chmod(SUBMISSIONS_FILE, 0644);
}

function loadProviders() {
    if (!file_exists(PROVIDERS_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(PROVIDERS_FILE), true) ?? [];
}

function saveProviders($providers) {
    file_put_contents(PROVIDERS_FILE, json_encode($providers, JSON_PRETTY_PRINT));
    chmod(PROVIDERS_FILE, 0644);
}

function sanitize($text) {
    return htmlspecialchars(strip_tags(trim($text)), ENT_QUOTES, 'UTF-8');
}

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
