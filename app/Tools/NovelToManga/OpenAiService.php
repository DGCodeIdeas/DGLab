<?php
/**
 * DGLab PWA - OpenAI Service
 * 
 * OpenAI API implementation for novel to manga conversion.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * OpenAiService Class
 * 
 * OpenAI API service for manga script generation.
 */
class OpenAiService implements AiServiceInterface
{
    /**
     * @var string API_BASE OpenAI API base URL
     */
    private const API_BASE = 'https://api.openai.com/v1';
    
    /**
     * @var string $model Model name
     */
    private string $model;
    
    /**
     * @var string|null $apiKey API key
     */
    private ?string $apiKey;
    
    /**
     * @var string|null $lastError Last error message
     */
    private ?string $lastError = null;
    
    /**
     * @var array $modelPricing Pricing per 1K tokens
     */
    private array $modelPricing = [
        'gpt-4o'        => ['input' => 0.005, 'output' => 0.015],
        'gpt-4o-mini'   => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4-turbo'   => ['input' => 0.01, 'output' => 0.03],
    ];

    /**
     * Constructor
     * 
     * @param string $model Model name
     * @param string|null $apiKey API key (null for free tier)
     */
    public function __construct(string $model = 'gpt-4o-mini', ?string $apiKey = null)
    {
        $this->model = $model;
        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToMangaScript(array $chunk, array $config): array
    {
        $this->lastError = null;
        
        // Build prompt
        $prompt = $this->buildPrompt($chunk, $config);
        
        // Make API request
        $response = $this->makeRequest('/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt($config)],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ]);
        
        if ($response === null) {
            throw new \Exception('Failed to get response from OpenAI: ' . $this->lastError);
        }
        
        // Parse response
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        return $this->parseResponse($content);
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        // Free tier doesn't require API key
        if ($this->apiKey === null) {
            return true;
        }
        
        // Test with a simple request
        $response = $this->makeRequest('/models', [], 'GET');
        return $response !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'OpenAI';
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
        $pricing = $this->modelPricing[$this->model] ?? $this->modelPricing['gpt-4o-mini'];
        
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
        
        // Add context if available
        if (!empty($context)) {
            $prompt .= "Context: {$context}\n\n";
        }
        
        // Add previous ending for continuity
        if (!empty($previousEnding)) {
            $prompt .= "Previous scene ended with: \"{$previousEnding}\"\n\n";
        }
        
        // Add content mode instruction
        $contentMode = $config['content_mode'] ?? 'censored';
        if ($contentMode === 'censored') {
            $prompt .= "Note: Keep content appropriate for general audiences. Avoid explicit descriptions.\n\n";
        }
        
        // Add formatting instructions
        $prompt .= "Format each panel with:\n";
        $prompt .= "- Panel description (visual scene)\n";
        $prompt .= "- Character dialogue (Character: \"dialogue\")\n";
        $prompt .= "- Include emotional cues in parentheses\n\n";
        
        // Add the actual content
        $prompt .= "NOVEL TEXT:\n";
        $prompt .= $content;
        $prompt .= "\n\nConvert this to manga script format.";
        
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
        
        $prompt = "You are a professional manga scriptwriter. Your task is to convert novel text into manga script format.\n\n";
        $prompt .= "Rules:\n";
        $prompt .= "1. Break the story into visual panels\n";
        $prompt .= "2. Describe what should be shown in each panel\n";
        $prompt .= "3. Write character dialogue in a natural, engaging way\n";
        $prompt .= "4. Include emotional cues and expressions\n";
        
        if ($includeDescriptions) {
            $prompt .= "5. Provide detailed scene descriptions\n";
        }
        
        switch ($dialogueStyle) {
            case 'dramatic':
                $prompt .= "6. Use dramatic, emphatic dialogue style\n";
                break;
            case 'minimal':
                $prompt .= "6. Keep dialogue concise and minimal\n";
                break;
            default:
                $prompt .= "6. Use natural, conversational dialogue\n";
        }
        
        if ($language !== 'auto') {
            $langNames = [
                'en' => 'English',
                'ja' => 'Japanese',
                'ko' => 'Korean',
                'zh' => 'Chinese',
                'es' => 'Spanish',
                'fr' => 'French',
                'de' => 'German',
            ];
            $prompt .= "\nOutput the script in " . ($langNames[$language] ?? $language) . ".\n";
        }
        
        $prompt .= "\nOutput format:\n";
        $prompt .= "SCENE: [Scene Title]\n";
        $prompt .= "Description: [Scene description]\n\n";
        $prompt .= "PANEL 1:\n";
        $prompt .= "[Visual description]\n";
        $prompt .= "Character: \"Dialogue line\" (emotion)\n";
        $prompt .= "Character: \"Another line\"\n\n";
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
        // Structure the response for the formatter
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
        
        // Split by SCENE markers
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
        ];
        
        // Add authorization if API key provided
        if ($this->apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
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
