<?php
/**
 * DGLab PWA - Gemini Service
 * 
 * Google Gemini API implementation for novel to manga conversion.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * GeminiService Class
 * 
 * Google Gemini API service for manga script generation.
 */
class GeminiService implements AiServiceInterface
{
    /**
     * @var string API_BASE Gemini API base URL
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    
    /**
     * @var string $model Model name
     */
    private string $model;
    
    /**
     * @var string $apiKey API key
     */
    private string $apiKey;
    
    /**
     * @var string|null $lastError Last error message
     */
    private ?string $lastError = null;
    
    /**
     * @var array $modelPricing Pricing per 1K tokens (estimated)
     */
    private array $modelPricing = [
        'gemini-1.5-pro'   => ['input' => 0.0035, 'output' => 0.0105],
        'gemini-1.5-flash' => ['input' => 0.00035, 'output' => 0.00105],
    ];

    /**
     * Constructor
     * 
     * @param string $model Model name
     * @param string|null $apiKey API key
     */
    public function __construct(string $model = 'gemini-1.5-flash', ?string $apiKey = null)
    {
        $this->model = $model;
        $this->apiKey = $apiKey ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function convertToMangaScript(array $chunk, array $config): array
    {
        $this->lastError = null;
        
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is required');
        }
        
        // Build prompt
        $prompt = $this->buildPrompt($chunk, $config);
        
        // Make API request
        $response = $this->makeRequest('/models/' . $this->model . ':generateContent', [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->getSystemPrompt($config) . "\n\n" . $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4000,
            ],
        ]);
        
        if ($response === null) {
            throw new \Exception('Failed to get response from Gemini: ' . $this->lastError);
        }
        
        // Parse response
        $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return $this->parseResponse($content);
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }
        
        $response = $this->makeRequest('/models', [], 'GET');
        return $response !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Google Gemini';
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * {@inheritdoc}
     */
    public function estimateCost(int $tokenCount): float
    {
        $pricing = $this->modelPricing[$this->model] ?? $this->modelPricing['gemini-1.5-flash'];
        
        $inputTokens = (int) ($tokenCount * 0.7);
        $outputTokens = (int) ($tokenCount * 0.3);
        
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        
        return round($inputCost + $outputCost, 4);
    }

    /**
     * Build conversion prompt
     * 
     * @param array $chunk Text chunk
     * @param array $config Configuration
     * @return string Prompt
     */
    private function buildPrompt(array $chunk, array $config): string
    {
        $content = $chunk['content'] ?? '';
        $context = $chunk['context'] ?? '';
        $previousEnding = $chunk['previous_ending'] ?? '';
        
        $prompt = "Convert this novel excerpt to manga script format.\n\n";
        
        if (!empty($context)) {
            $prompt .= "Context: {$context}\n\n";
        }
        
        if (!empty($previousEnding)) {
            $prompt .= "Previous: \"{$previousEnding}\"\n\n";
        }
        
        $contentMode = $config['content_mode'] ?? 'censored';
        if ($contentMode === 'censored') {
            $prompt .= "Keep content appropriate for all audiences.\n\n";
        }
        
        $prompt .= "NOVEL TEXT:\n{$content}\n\n";
        $prompt .= "Convert to manga script with panels, descriptions, and dialogue.";
        
        return $prompt;
    }

    /**
     * Get system prompt
     * 
     * @param array $config Configuration
     * @return string System prompt
     */
    private function getSystemPrompt(array $config): string
    {
        $includeDescriptions = $config['include_descriptions'] ?? true;
        $language = $config['language'] ?? 'auto';
        
        $prompt = "You are an expert manga scriptwriter. Convert novel prose into manga format.\n\n";
        $prompt .= "Create:\n";
        $prompt .= "- Visual panel descriptions\n";
        $prompt .= "- Character dialogue with emotions\n";
        $prompt .= "- Scene pacing and transitions\n";
        
        if ($includeDescriptions) {
            $prompt .= "- Detailed setting descriptions\n";
        }
        
        $prompt .= "\nFormat:\n";
        $prompt .= "SCENE: [Title]\n";
        $prompt .= "Description: [Setting]\n\n";
        $prompt .= "PANEL 1:\n";
        $prompt .= "[Visual description]\n";
        $prompt .= "Character: \"Dialogue\" (emotion)\n";
        
        return $prompt;
    }

    /**
     * Parse API response
     * 
     * @param string $content Response content
     * @return array Parsed script
     */
    private function parseResponse(string $content): array
    {
        return [
            'content' => $content,
            'scenes'  => $this->extractScenes($content),
        ];
    }

    /**
     * Extract scenes from response
     * 
     * @param string $content Response content
     * @return array Extracted scenes
     */
    private function extractScenes(string $content): array
    {
        $scenes = [];
        $parts = preg_split('/SCENE[:\s]+/i', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($parts as $part) {
            $lines = explode("\n", trim($part));
            $title = trim($lines[0] ?? 'Scene');
            
            $scenes[] = [
                'title'   => $title,
                'content' => $part,
            ];
        }
        
        return $scenes;
    }

    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|null Response data or null on error
     */
    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST'): ?array
    {
        $url = self::API_BASE . $endpoint;
        
        // Add API key as query parameter for Gemini
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . $this->apiKey;
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $this->lastError = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $this->lastError = $errorData['error']['message'] ?? "HTTP error: {$httpCode}";
            return null;
        }
        
        return json_decode($response, true);
    }
}
