<?php
/**
 * FlexBot - Ollama Client Integration
 * Handles communication with local Ollama AI service
 *
 * Version: 1.0
 * Compatible with: FlexPBX v1.2+
 */

class OllamaClient {

    private $baseUrl;
    private $model;
    private $timeout;

    public function __construct($baseUrl = 'http://localhost:11434', $model = 'llama3.2:latest') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->timeout = 300; // 5 minutes for long responses
    }

    /**
     * Generate completion using Ollama
     */
    public function generate($prompt, $system = null, $options = []) {
        $url = $this->baseUrl . '/api/generate';

        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => array_merge([
                'temperature' => 0.7,
                'top_p' => 0.9,
                'num_ctx' => 8192
            ], $options)
        ];

        if ($system) {
            $data['system'] = $system;
        }

        $response = $this->makeRequest($url, $data);

        if ($response && isset($response['response'])) {
            return [
                'success' => true,
                'response' => $response['response'],
                'model' => $response['model'] ?? $this->model,
                'eval_count' => $response['eval_count'] ?? 0,
                'eval_duration' => $response['eval_duration'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to generate response'
        ];
    }

    /**
     * Chat completion with conversation history
     */
    public function chat($messages, $options = []) {
        $url = $this->baseUrl . '/api/chat';

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => array_merge([
                'temperature' => 0.7,
                'top_p' => 0.9,
                'num_ctx' => 8192
            ], $options)
        ];

        $response = $this->makeRequest($url, $data);

        if ($response && isset($response['message'])) {
            return [
                'success' => true,
                'message' => $response['message'],
                'model' => $response['model'] ?? $this->model,
                'eval_count' => $response['eval_count'] ?? 0
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to generate chat response'
        ];
    }

    /**
     * List available models
     */
    public function listModels() {
        $url = $this->baseUrl . '/api/tags';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'models' => $data['models'] ?? []
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to list models'
        ];
    }

    /**
     * Pull a model from Ollama library
     */
    public function pullModel($modelName) {
        $url = $this->baseUrl . '/api/pull';

        $data = [
            'model' => $modelName,
            'stream' => false
        ];

        $response = $this->makeRequest($url, $data, 600); // 10 minutes for model download

        return [
            'success' => $response !== false,
            'message' => $response['status'] ?? 'Model pull initiated'
        ];
    }

    /**
     * Check if Ollama service is available
     */
    public function isAvailable() {
        $url = $this->baseUrl . '/api/tags';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Generate embeddings for text
     */
    public function embeddings($text) {
        $url = $this->baseUrl . '/api/embeddings';

        $data = [
            'model' => $this->model,
            'prompt' => $text
        ];

        $response = $this->makeRequest($url, $data);

        if ($response && isset($response['embedding'])) {
            return [
                'success' => true,
                'embedding' => $response['embedding']
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to generate embeddings'
        ];
    }

    /**
     * Make HTTP request to Ollama API
     */
    private function makeRequest($url, $data, $timeout = null) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ?? $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Ollama API Error: " . $error);
            return false;
        }

        if ($httpCode !== 200) {
            error_log("Ollama API returned HTTP " . $httpCode);
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Set model to use
     */
    public function setModel($model) {
        $this->model = $model;
    }

    /**
     * Get current model
     */
    public function getModel() {
        return $this->model;
    }
}
