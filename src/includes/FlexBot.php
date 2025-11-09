<?php
/**
 * FlexBot - AI Assistant for FlexPBX
 * Manages checklists, formats notes, and provides system guidance
 *
 * Version: 1.0
 * Compatible with: FlexPBX v1.2+
 */

require_once __DIR__ . '/OllamaClient.php';

class FlexBot {

    private $ollama;
    private $pdo;
    private $config;
    private $systemPrompt;

    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'ollama_url' => 'http://localhost:11434',
            'model' => 'llama3.2:latest',
            'auto_format_notes' => true,
            'training_enabled' => true
        ], $config);

        $this->ollama = new OllamaClient(
            $this->config['ollama_url'],
            $this->config['model']
        );

        $this->systemPrompt = $this->loadSystemPrompt();
    }

    /**
     * Load system prompt with FlexPBX context
     */
    private function loadSystemPrompt() {
        return "You are FlexBot, an AI assistant for FlexPBX VoIP systems. Your role is to:

1. Help users manage checklists and tasks
2. Format notes to be clear and readable
3. Answer FlexPBX configuration questions
4. Provide troubleshooting guidance
5. Suggest best practices

You have access to the user's FlexPBX system including:
- Checklist items and completion status
- System configuration
- User roles and permissions
- Module status

Always be helpful, concise, and technically accurate. Format your responses in markdown when appropriate.";
    }

    /**
     * Process natural language command
     */
    public function processCommand($userInput, $userId = null, $userRole = 'user') {
        // Build context from system state
        $context = $this->buildContext($userId, $userRole);

        // Generate response
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt],
            ['role' => 'system', 'content' => "Current context:\n" . json_encode($context, JSON_PRETTY_PRINT)],
            ['role' => 'user', 'content' => $userInput]
        ];

        $response = $this->ollama->chat($messages);

        if ($response['success']) {
            // Log conversation
            $this->logConversation($userId, $userInput, $response['message']['content']);

            // Extract and execute any actions
            $actions = $this->extractActions($response['message']['content']);
            if (!empty($actions)) {
                $this->executeActions($actions, $userId);
            }

            return [
                'success' => true,
                'response' => $response['message']['content'],
                'actions_executed' => count($actions)
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to process command'
        ];
    }

    /**
     * Format and enhance a note
     */
    public function formatNote($noteContent, $noteType = 'info') {
        $prompt = "Format and enhance the following note for a FlexPBX checklist. Make it:
- Clear and concise
- Well-structured with proper HTML formatting
- Include relevant tips or warnings
- Use <strong>, <em>, <ul>, <li>, <code> tags appropriately
- Keep it under 200 words

Note type: {$noteType}
Original content:
{$noteContent}

Return only the formatted HTML content, no explanations.";

        $response = $this->ollama->generate($prompt, $this->systemPrompt);

        if ($response['success']) {
            return [
                'success' => true,
                'formatted_content' => $response['response']
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to format note'
        ];
    }

    /**
     * Suggest next checklist action
     */
    public function suggestNextAction($checklistType = 'system_setup', $userId = null) {
        // Get incomplete items
        $stmt = $this->pdo->prepare("
            SELECT ci.*, ct.type_name
            FROM checklist_items ci
            LEFT JOIN checklist_types ct ON ci.checklist_type_id = ct.id
            WHERE ct.type_key = ?
            AND ci.is_completed = 0
            ORDER BY ci.priority DESC, ci.check_order ASC
            LIMIT 5
        ");
        $stmt->execute([$checklistType]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'success' => true,
                'message' => "All tasks in {$checklistType} are complete!",
                'items' => []
            ];
        }

        $prompt = "Based on these incomplete checklist items, suggest which one to complete next and why:

" . json_encode($items, JSON_PRETTY_PRINT) . "

Provide:
1. Which item to do next (by check_key)
2. Brief explanation why
3. Quick tips for completion

Format as JSON: {\"suggested_key\": \"...\", \"reason\": \"...\", \"tips\": [\"...\", \"...\"]}";

        $response = $this->ollama->generate($prompt, $this->systemPrompt);

        if ($response['success']) {
            // Try to parse JSON from response
            $suggestion = json_decode($response['response'], true);

            return [
                'success' => true,
                'suggestion' => $suggestion ?? ['raw_response' => $response['response']],
                'incomplete_items' => $items
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to generate suggestion'
        ];
    }

    /**
     * Answer a FlexPBX question
     */
    public function answerQuestion($question, $userId = null) {
        // Get relevant training data
        $trainingContext = $this->getRelevantTraining($question);

        $prompt = "User question: {$question}

Relevant documentation:
{$trainingContext}

Provide a clear, helpful answer specific to FlexPBX.";

        $response = $this->ollama->generate($prompt, $this->systemPrompt);

        if ($response['success']) {
            $this->logConversation($userId, $question, $response['response']);

            return [
                'success' => true,
                'answer' => $response['response']
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to generate answer'
        ];
    }

    /**
     * Update checklist notes automatically
     */
    public function updateChecklistNotes($typeKey = 'system_setup') {
        $stmt = $this->pdo->prepare("
            SELECT cn.*
            FROM checklist_notes cn
            LEFT JOIN checklist_types ct ON cn.checklist_type_id = ct.id
            WHERE ct.type_key = ?
            AND cn.note_content IS NOT NULL
        ");
        $stmt->execute([$typeKey]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        foreach ($notes as $note) {
            $formatted = $this->formatNote($note['note_content'], $note['note_type']);

            if ($formatted['success']) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE checklist_notes
                    SET note_content = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$formatted['formatted_content'], $note['id']]);
                $updated++;
            }
        }

        return [
            'success' => true,
            'notes_updated' => $updated,
            'total_notes' => count($notes)
        ];
    }

    /**
     * Build context from current system state
     */
    private function buildContext($userId, $userRole) {
        $context = [
            'user_role' => $userRole,
            'incomplete_tasks' => []
        ];

        // Get user's incomplete tasks
        if ($userId) {
            $stmt = $this->pdo->prepare("
                SELECT check_key, check_name, priority
                FROM checklist_items
                WHERE (assigned_to_user_id = ? OR assigned_to_role = ?)
                AND is_completed = 0
                LIMIT 10
            ");
            $stmt->execute([$userId, $userRole]);
            $context['incomplete_tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $context;
    }

    /**
     * Extract actions from AI response
     */
    private function extractActions($response) {
        $actions = [];

        // Look for action markers like [ACTION:complete:check_key]
        if (preg_match_all('/\[ACTION:(\w+):([^\]]+)\]/', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $actions[] = [
                    'type' => $match[1],
                    'target' => $match[2]
                ];
            }
        }

        return $actions;
    }

    /**
     * Execute actions extracted from AI response
     */
    private function executeActions($actions, $userId) {
        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'complete':
                    $this->completeChecklistItem($action['target'], $userId);
                    break;
                case 'assign':
                    // Handle assignment
                    break;
            }
        }
    }

    /**
     * Complete a checklist item
     */
    private function completeChecklistItem($checkKey, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE checklist_items
            SET is_completed = 1,
                completed_at = NOW(),
                completed_by = 'flexbot',
                completion_note = CONCAT('Completed by FlexBot for user ', ?)
            WHERE check_key = ?
        ");
        $stmt->execute([$userId ?? 'system', $checkKey]);
    }

    /**
     * Log conversation for training
     */
    private function logConversation($userId, $userInput, $botResponse) {
        if (!$this->config['training_enabled']) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO flexbot_conversations (user_id, user_input, bot_response, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $userInput, $botResponse]);
    }

    /**
     * Get relevant training data for a question
     */
    private function getRelevantTraining($question) {
        // Simple keyword matching for now
        // TODO: Implement semantic search with embeddings
        $stmt = $this->pdo->prepare("
            SELECT content
            FROM flexbot_training_data
            WHERE content LIKE ?
            LIMIT 5
        ");
        $stmt->execute(['%' . $question . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return implode("\n\n---\n\n", $results);
    }

    /**
     * Check if Ollama is available
     */
    public function isAvailable() {
        return $this->ollama->isAvailable();
    }

    /**
     * Get system status
     */
    public function getStatus() {
        return [
            'ollama_available' => $this->ollama->isAvailable(),
            'current_model' => $this->config['model'],
            'auto_format_enabled' => $this->config['auto_format_notes'],
            'training_enabled' => $this->config['training_enabled']
        ];
    }
}
