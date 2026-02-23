<?php
/**
 * DGLab PWA - Claude Service
 * 
 * Anthropic Claude API implementation for novel to manga conversion.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * ClaudeService Class
 * 
 * Anthropic Claude API service for manga script generation.
 */
class ClaudeService implements AiServiceInterface
{
    /**
     * @var string API_BASE Anthropic API base URL
     */
    private const API_BASE = 'https://api.anthropic.com/v1';
    
    /**
     * @var string API_VERSION API version
     */
    private const API_VERSION = '2023-06-01';
    
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
     * @var array $modelPricing Pricing per 1K tokens
     */
    private array $modelPricing = [
        'claude-3-opus'   => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku'  => ['input' => 0.00025, 'output' => 0.00125],
    ];

    /**
     * Constructor
     * 
     * @param string $model Model name
     * @param string|null $apiKey API key
     */
    public function __construct(string $model = 'claude-3-sonnet', ?string $apiKey = null)
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
            throw new \Exception('Claude API key is required');
        }
        
        // Build prompt
        $prompt = $this->buildPrompt($chunk, $config);
        
        // Make API request
        $response = $this->makeRequest('/messages', [
            'model' => $this->model,
            'max_tokens' => 4000,
            'system' => $this->getSystemPrompt($config),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
        
        if ($response === null) {
            throw new \Exception('Failed to get response from Claude: ' . $this->lastError);
        }
        
        // Parse response
        $content = $response['content'][0]['text'] ?? '';
        
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
        return 'Anthropic Claude';
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
        $pricing = $this->modelPricing[$this->model] ?? $this->modelPricing['claude-3-sonnet'];
        
        // Estimate 70% input, 30% output
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
        
        $prompt = "Convert the following novel excerpt into a manga script format.\n\n";
        
        if (!empty($context)) {
            $prompt .= "Context: {$context}\n\n";
        }
        
        if (!empty($previousEnding)) {
            $prompt .= "Previous scene ended with: \"{$previousEnding}\"\n\n";
        }
        
        $contentMode = $config['content_mode'] ?? 'censored';
        if ($contentMode === 'censored') {
            $prompt .= "Note: Keep content appropriate for general audiences.\n\n";
        }
        
        $prompt .= "NOVEL TEXT:\n";
        $prompt .= $content;
        $prompt .= "\n\nConvert this to manga script format with panels, descriptions, and dialogue.";
        
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
        $dialogueStyle = $config['dialogue_style'] ?? 'standard';
        $language = $config['language'] ?? 'auto';
        
        $prompt = "You are a professional manga scriptwriter specializing in converting novels to visual storytelling formats.\n\n";
        $prompt .= "Your task is to transform prose into manga panels with:\n";
        $prompt .= "- Clear visual descriptions for each panel\n";
        $prompt .= "- Natural character dialogue\n";
        $prompt .= "- Emotional cues and expressions\n";
        $prompt .= "- Proper pacing and scene transitions\n";
        
        if ($includeDescriptions) {
            $prompt .= "- Detailed background and setting descriptions\n";
        }
        
        if ($language !== 'auto') {
            $prompt .= "\nOutput the manga script in the requested language.\n";
        }
        
        $prompt .= "\nFormat:\n";
        $prompt .= "SCENE: [Title]\n";
        $prompt .= "Description: [Setting description]\n\n";
        $prompt .= "PANEL 1:\n";
        $prompt .= "[Visual: What the artist should draw]\n";
        $prompt .= "Character: \"Dialogue\" (emotion cue)\n\n";
        $prompt .= "PANEL 2:\n";
        $prompt .= "...";
        
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
        
        $ch = curl_init($url);
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . self::API_VERSION,
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
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
