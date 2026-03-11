<?php

/**
 * DGLab Novel to Manga Script Service
 *
 * Converts novels/stories to manga scripts using multi-AI provider system.
 *
 * @package DGLab\Services\NovelToMangaScript
 */

namespace DGLab\Services\NovelToMangaScript;

use DGLab\Core\Application;
use DGLab\Core\Exceptions\ValidationException;
use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ChunkedServiceInterface;
use DGLab\Services\NovelToMangaScript\AI\ProviderRepository;
use DGLab\Services\NovelToMangaScript\AI\RoutingEngine;
use DGLab\Services\NovelToMangaScript\AI\LLMProviderException;

/**
 * Class NovelToMangaScript
 *
 * Main service for novel to manga script conversion.
 */
class NovelToMangaScript extends BaseService implements ChunkedServiceInterface
{
    /**
     * Service ID
     */
    private const SERVICE_ID = 'novel-to-manga-script';

    /**
     * Service name
     */
    private const SERVICE_NAME = 'Novel to Manga Script';

    /**
     * Service description
     */
    private const SERVICE_DESCRIPTION = 'Convert novels and stories into detailed manga scripts with panel descriptions, dialogue, and visual directions using AI. Supports 40+ AI providers with intelligent routing.';

    /**
     * Service icon
     */
    private const SERVICE_ICON = 'bi bi-file-text';

    /**
     * Provider repository
     */
    private ?ProviderRepository $providerRepo = null;

    /**
     * Routing engine
     */
    private ?RoutingEngine $routingEngine = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->providerRepo = new ProviderRepository($this->config);
        $this->routingEngine = new RoutingEngine($this->providerRepo, $this->config);
    }

    /**
     * Get service ID
     */
    public function getId(): string
    {
        return self::SERVICE_ID;
    }

    /**
     * Get service name
     */
    public function getName(): string
    {
        return self::SERVICE_NAME;
    }

    /**
     * Get service description
     */
    public function getDescription(): string
    {
        return self::SERVICE_DESCRIPTION;
    }

    /**
     * Get service icon
     */
    public function getIcon(): string
    {
        return self::SERVICE_ICON;
    }

    /**
     * Get input schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // Source text input
                'source_text' => [
                    'type' => 'string',
                    'description' => 'Novel/story text to convert',
                    'minLength' => 100,
                    'maxLength' => 500000,
                ],
                'source_file' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'Text file (.txt, .md) to convert',
                ],

                // Conversion settings
                'style' => [
                    'type' => 'string',
                    'enum' => ['shonen', 'seinen', 'shojo', 'josei', 'kodomo', 'manhwa', 'manhua', 'webtoon', 'comic'],
                    'default' => 'seinen',
                    'description' => 'Target manga style',
                ],
                'panels_per_page' => [
                    'type' => 'integer',
                    'minimum' => 3,
                    'maximum' => 12,
                    'default' => 6,
                    'description' => 'Average panels per page',
                ],
                'detail_level' => [
                    'type' => 'string',
                    'enum' => ['minimal', 'standard', 'detailed', 'maximum'],
                    'default' => 'standard',
                    'description' => 'Level of visual description detail',
                ],

                // AI settings
                'mode' => [
                    'type' => 'string',
                    'enum' => ['censored', 'uncensored', 'auto'],
                    'default' => 'censored',
                    'description' => 'Content filtering mode',
                ],
                'provider' => [
                    'type' => 'string',
                    'description' => 'Specific provider to use (optional)',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Specific model to use (optional)',
                ],
                'temperature' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 2,
                    'default' => 0.7,
                    'description' => 'AI creativity level',
                ],

                // Quality settings
                'speed_priority' => [
                    'type' => 'string',
                    'enum' => ['batch', 'standard', 'fast', 'ultra'],
                    'default' => 'standard',
                    'description' => 'Speed vs quality tradeoff',
                ],
                'budget' => [
                    'type' => 'string',
                    'enum' => ['tight', 'moderate', 'unlimited'],
                    'default' => 'moderate',
                    'description' => 'Budget preference for API costs',
                ],

                // Output settings
                'output_format' => [
                    'type' => 'string',
                    'enum' => ['json', 'markdown', 'html', 'pdf'],
                    'default' => 'json',
                    'description' => 'Output format',
                ],
                'include_image_prompts' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Include AI image generation prompts',
                ],

                // Custom AI endpoint
                'custom_ai_endpoint' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => [
                            'type' => 'boolean',
                            'default' => false,
                        ],
                        'display_name' => [
                            'type' => 'string',
                        ],
                        'api_base' => [
                            'type' => 'string',
                            'format' => 'uri',
                        ],
                        'api_key' => [
                            'type' => 'string',
                        ],
                        'request_format' => [
                            'type' => 'string',
                            'enum' => ['openai', 'anthropic', 'google', 'custom'],
                            'default' => 'openai',
                        ],
                    ],
                ],

                // Acknowledgment for uncensored mode
                'uncensored_acknowledgment' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'User acknowledges uncensored mode terms',
                ],
            ],
            'required' => [],
            'oneOf' => [
                ['required' => ['source_text']],
                ['required' => ['source_file']],
            ],
        ];
    }

    /**
     * Validate input
     */
    public function validate(array $input): array
    {
        // Check source
        if (empty($input['source_text']) && empty($input['source_file'])) {
            throw new ValidationException([
                'source' => 'Either source_text or source_file is required',
            ]);
        }

        // Validate uncensored acknowledgment
        if (($input['mode'] ?? 'censored') === 'uncensored') {
            if (!($input['uncensored_acknowledgment'] ?? false)) {
                throw new ValidationException([
                    'uncensored_acknowledgment' => 'You must acknowledge the terms to use uncensored mode',
                ]);
            }
        }

        // Validate custom endpoint if enabled
        if ($input['custom_ai_endpoint']['enabled'] ?? false) {
            $endpoint = $input['custom_ai_endpoint'];

            if (empty($endpoint['api_base'])) {
                throw new ValidationException([
                    'custom_ai_endpoint.api_base' => 'API base URL is required for custom endpoints',
                ]);
            }

            if (!filter_var($endpoint['api_base'], FILTER_VALIDATE_URL)) {
                throw new ValidationException([
                    'custom_ai_endpoint.api_base' => 'Invalid URL format',
                ]);
            }
        }

        return $this->validateAgainstSchema($input, $this->getInputSchema());
    }

    /**
     * Process the service request
     */
    public function process(array $input, ?callable $progressCallback = null): array
    {
        $this->reportProgress($progressCallback, 0, 'Starting manga script conversion');

        // Get source text
        $sourceText = $this->getSourceText($input);
        $this->reportProgress($progressCallback, 5, 'Source text loaded');

        // Setup user context for routing
        $this->routingEngine->setUserContext([
            'budget' => $input['budget'] ?? 'moderate',
            'preferred_providers' => $input['preferred_providers'] ?? [],
            'blocked_providers' => $input['blocked_providers'] ?? [],
        ]);

        // Setup custom provider if configured
        if ($input['custom_ai_endpoint']['enabled'] ?? false) {
            $this->setupCustomProvider($input['custom_ai_endpoint']);
        }

        // Parse source into scenes
        $this->reportProgress($progressCallback, 10, 'Analyzing source structure');
        $scenes = $this->parseSourceIntoScenes($sourceText);
        $this->reportProgress($progressCallback, 15, 'Identified ' . count($scenes) . ' scenes');

        // Process each scene
        $mangaScript = [
            'metadata' => [
                'title' => $this->extractTitle($sourceText),
                'style' => $input['style'] ?? 'seinen',
                'panels_per_page' => $input['panels_per_page'] ?? 6,
                'detail_level' => $input['detail_level'] ?? 'standard',
                'generated_at' => date('Y-m-d H:i:s'),
                'provider_used' => null,
                'model_used' => null,
                'total_cost' => 0,
            ],
            'chapters' => [],
            'image_generation_intent' => null,
        ];

        $totalScenes = count($scenes);
        $processedScenes = 0;
        $totalCost = 0;

        foreach ($scenes as $sceneIndex => $scene) {
            $progressPercent = 15 + (int)(($sceneIndex / $totalScenes) * 75);
            $this->reportProgress($progressCallback, $progressPercent, "Processing scene " . ($sceneIndex + 1) . " of {$totalScenes}");

            try {
                $sceneResult = $this->processScene($scene, $input);

                $mangaScript['chapters'][] = $sceneResult['chapter'];
                $totalCost += $sceneResult['cost'];

                // Update metadata with provider info
                if (!$mangaScript['metadata']['provider_used']) {
                    $mangaScript['metadata']['provider_used'] = $sceneResult['provider'];
                    $mangaScript['metadata']['model_used'] = $sceneResult['model'];
                }

                $processedScenes++;
            } catch (LLMProviderException $e) {
                // Record failure and try fallback
                $this->routingEngine->recordFailure($e->provider);

                if ($e->shouldFallback()) {
                    // Retry with fallback provider
                    $sceneResult = $this->processSceneWithFallback($scene, $input, $e->provider);
                    $mangaScript['chapters'][] = $sceneResult['chapter'];
                    $totalCost += $sceneResult['cost'];
                    $processedScenes++;
                } else {
                    throw $e;
                }
            }
        }

        $mangaScript['metadata']['total_cost'] = $totalCost;
        $mangaScript['metadata']['scenes_processed'] = $processedScenes;

        // Add image generation intent package
        if ($input['include_image_prompts'] ?? true) {
            $mangaScript['image_generation_intent'] = $this->buildImageGenerationIntent($mangaScript);
        }

        $this->reportProgress($progressCallback, 95, 'Formatting output');

        // Format output
        $output = $this->formatOutput($mangaScript, $input['output_format'] ?? 'json');

        $this->reportProgress($progressCallback, 100, 'Complete');

        return [
            'success' => true,
            'manga_script' => $mangaScript,
            'output' => $output,
            'statistics' => [
                'scenes_processed' => $processedScenes,
                'total_panels' => $this->countPanels($mangaScript),
                'estimated_pages' => ceil($this->countPanels($mangaScript) / ($input['panels_per_page'] ?? 6)),
                'total_cost_usd' => round($totalCost, 4),
                'provider' => $mangaScript['metadata']['provider_used'],
                'model' => $mangaScript['metadata']['model_used'],
            ],
        ];
    }

    /**
     * Get source text from input
     */
    private function getSourceText(array $input): string
    {
        if (!empty($input['source_text'])) {
            return $input['source_text'];
        }

        if (!empty($input['source_file'])) {
            $filePath = $input['source_file'];

            if (!file_exists($filePath)) {
                throw new \RuntimeException('Source file not found');
            }

            return file_get_contents($filePath);
        }

        throw new ValidationException(['source' => 'No source text provided']);
    }

    /**
     * Parse source text into scenes
     */
    private function parseSourceIntoScenes(string $text): array
    {
        $scenes = [];

        // Split by chapter markers, double newlines, or scene breaks
        $patterns = [
            '/\n\s*Chapter\s+\d+[:\s]*/i',
            '/\n\s*\*\s*\*\s*\*\s*\n/',
            '/\n\s*---+\s*\n/',
            '/\n{3,}/',
        ];

        $parts = preg_split('/(' . implode('|', $patterns) . ')/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentScene = '';
        $sceneNumber = 0;

        foreach ($parts as $part) {
            $trimmed = trim($part);

            if (empty($trimmed)) {
                continue;
            }

            // Check if this is a delimiter
            if (preg_match('/^(Chapter|---|\*\s*\*\s*\*)/i', $trimmed)) {
                if (!empty($currentScene)) {
                    $scenes[] = [
                        'number' => ++$sceneNumber,
                        'content' => trim($currentScene),
                        'word_count' => str_word_count($currentScene),
                    ];
                    $currentScene = '';
                }
                continue;
            }

            $currentScene .= $trimmed . "\n\n";
        }

        // Add final scene
        if (!empty(trim($currentScene))) {
            $scenes[] = [
                'number' => ++$sceneNumber,
                'content' => trim($currentScene),
                'word_count' => str_word_count($currentScene),
            ];
        }

        // If no scenes detected, treat entire text as one scene
        if (empty($scenes)) {
            $scenes[] = [
                'number' => 1,
                'content' => trim($text),
                'word_count' => str_word_count($text),
            ];
        }

        return $scenes;
    }

    /**
     * Process a single scene
     */
    private function processScene(array $scene, array $input): array
    {
        // Select provider
        $ranked = $this->routingEngine->selectProvider([
            'mode' => $input['mode'] ?? 'censored',
            'estimated_tokens' => $scene['word_count'] * 2, // Rough estimate
            'speed_requirement' => $input['speed_priority'] ?? 'standard',
            'context_length' => $scene['word_count'] + 2000,
            'task_type' => 'manga_script',
            'privacy_level' => $input['privacy_level'] ?? 'standard',
        ]);

        if (!$ranked->primary) {
            throw new \RuntimeException('No suitable provider found');
        }

        $providerId = $input['provider'] ?? $ranked->primary;
        $provider = $this->providerRepo->get($providerId);

        // Set mode
        $provider->setMode($input['mode'] ?? 'censored');

        // Select model
        $modelId = $input['model'] ?? $this->routingEngine->selectModel($providerId, [
            'context_length' => $scene['word_count'] + 2000,
            'speed_requirement' => $input['speed_priority'] ?? 'standard',
            'task_type' => 'manga_script',
        ]);

        // Build prompt
        $messages = $this->buildScenePrompt($scene, $input);

        // Execute
        $response = $provider->chat($modelId, $messages, [
            'temperature' => $input['temperature'] ?? 0.7,
            'max_tokens' => 4096,
            'json_mode' => true,
        ]);

        // Record success
        $this->routingEngine->recordSuccess($providerId);

        // Parse response
        $chapter = $this->parseScriptResponse($response->content, $scene);

        return [
            'chapter' => $chapter,
            'cost' => $response->costUsd ?? 0,
            'provider' => $providerId,
            'model' => $modelId,
        ];
    }

    /**
     * Process scene with fallback provider
     */
    private function processSceneWithFallback(array $scene, array $input, string $failedProvider): array
    {
        $ranked = $this->routingEngine->selectProvider([
            'mode' => $input['mode'] ?? 'censored',
            'estimated_tokens' => $scene['word_count'] * 2,
            'speed_requirement' => $input['speed_priority'] ?? 'standard',
            'context_length' => $scene['word_count'] + 2000,
            'task_type' => 'manga_script',
        ]);

        // Find alternative that's not the failed provider
        $fallbackId = null;
        foreach ($ranked->getAllRanked() as $providerId) {
            if ($providerId !== $failedProvider) {
                $fallbackId = $providerId;
                break;
            }
        }

        if (!$fallbackId) {
            throw new \RuntimeException('No fallback provider available');
        }

        $input['provider'] = $fallbackId;
        return $this->processScene($scene, $input);
    }

    /**
     * Build prompt for scene conversion
     */
    private function buildScenePrompt(array $scene, array $input): array
    {
        $style = $input['style'] ?? 'seinen';
        $detailLevel = $input['detail_level'] ?? 'standard';
        $panelsPerPage = $input['panels_per_page'] ?? 6;

        $systemPrompt = <<<PROMPT
You are an expert manga script writer. Convert the given novel text into a detailed manga script.

Output Format (JSON):
{
  "chapter_title": "string",
  "pages": [
    {
      "page_number": 1,
      "panels": [
        {
          "panel_number": 1,
          "layout": "full|half|third|quarter|splash",
          "visual_description": "Detailed description of what to draw",
          "camera_angle": "close-up|medium|wide|bird's eye|worm's eye",
          "mood": "string",
          "dialogue": [
            {
              "speaker": "character name",
              "text": "dialogue text",
              "bubble_type": "speech|thought|narration|sfx"
            }
          ],
          "sfx": ["sound effects"],
          "motion_lines": "description if needed",
          "image_prompt": "AI image generation prompt for this panel"
        }
      ]
    }
  ]
}

Style Guidelines for {$style}:
- Panel pacing: Aim for {$panelsPerPage} panels per page average
- Detail level: {$detailLevel}
- Include visual storytelling techniques appropriate for the style
- Preserve important dialogue and internal monologue
- Describe character expressions and body language
- Note any important visual symbolism

Always output valid JSON.
PROMPT;

        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => "Convert this scene to manga script:\n\n" . $scene['content'],
            ],
        ];
    }

    /**
     * Parse script response from AI
     */
    private function parseScriptResponse(string $content, array $scene): array
    {
        // Try to parse JSON
        $json = json_decode($content, true);

        if ($json) {
            return array_merge($json, [
                'source_scene_number' => $scene['number'],
            ]);
        }

        // Fallback: extract structured data
        return [
            'chapter_title' => 'Scene ' . $scene['number'],
            'pages' => [
                [
                    'page_number' => 1,
                    'panels' => [
                        [
                            'panel_number' => 1,
                            'layout' => 'full',
                            'visual_description' => $content,
                            'dialogue' => [],
                        ],
                    ],
                ],
            ],
            'source_scene_number' => $scene['number'],
            'raw_output' => $content,
        ];
    }

    /**
     * Build image generation intent package
     */
    private function buildImageGenerationIntent(array $mangaScript): array
    {
        $panels = [];

        foreach ($mangaScript['chapters'] as $chapter) {
            foreach ($chapter['pages'] ?? [] as $page) {
                foreach ($page['panels'] ?? [] as $panel) {
                    if (!empty($panel['image_prompt'])) {
                        $panels[] = [
                            'panel_id' => "ch{$chapter['source_scene_number']}_p{$page['page_number']}_pn{$panel['panel_number']}",
                            'prompt' => $panel['image_prompt'],
                            'visual_description' => $panel['visual_description'] ?? '',
                            'style' => $mangaScript['metadata']['style'],
                        ];
                    }
                }
            }
        }

        return [
            'script_ref' => uniqid('manga_'),
            'panels' => $panels,
            'style_preferences' => [
                'manga_style' => $mangaScript['metadata']['style'],
                'detail_level' => $mangaScript['metadata']['detail_level'],
            ],
            'image_generation_requested' => false, // Future flag
        ];
    }

    /**
     * Format output in requested format
     */
    private function formatOutput(array $mangaScript, string $format): string
    {
        return match ($format) {
            'json' => json_encode($mangaScript, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'markdown' => $this->formatAsMarkdown($mangaScript),
            'html' => $this->formatAsHtml($mangaScript),
            default => json_encode($mangaScript, JSON_PRETTY_PRINT)
        };
    }

    /**
     * Format as Markdown
     */
    private function formatAsMarkdown(array $script): string
    {
        $md = "# {$script['metadata']['title']}\n\n";
        $md .= "*Style: {$script['metadata']['style']}*\n\n";

        foreach ($script['chapters'] as $chapter) {
            $md .= "## {$chapter['chapter_title']}\n\n";

            foreach ($chapter['pages'] ?? [] as $page) {
                $md .= "### Page {$page['page_number']}\n\n";

                foreach ($page['panels'] ?? [] as $panel) {
                    $md .= "**Panel {$panel['panel_number']}** ({$panel['layout']})\n\n";
                    $md .= "> {$panel['visual_description']}\n\n";

                    foreach ($panel['dialogue'] ?? [] as $dialogue) {
                        $bubble = $dialogue['bubble_type'] ?? 'speech';
                        $md .= "- **{$dialogue['speaker']}** ({$bubble}): {$dialogue['text']}\n";
                    }

                    $md .= "\n";
                }
            }
        }

        return $md;
    }

    /**
     * Format as HTML
     */
    private function formatAsHtml(array $script): string
    {
        $html = "<!DOCTYPE html><html><head><title>{$script['metadata']['title']}</title>";
        $html .= "<style>
            body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .chapter { margin-bottom: 30px; }
            .page { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 8px; }
            .panel { background: white; padding: 10px; margin: 10px 0; border-left: 4px solid #333; }
            .visual { font-style: italic; color: #666; }
            .dialogue { margin: 5px 0; }
            .speaker { font-weight: bold; }
        </style></head><body>";

        $html .= "<h1>{$script['metadata']['title']}</h1>";

        foreach ($script['chapters'] as $chapter) {
            $html .= "<div class='chapter'><h2>{$chapter['chapter_title']}</h2>";

            foreach ($chapter['pages'] ?? [] as $page) {
                $html .= "<div class='page'><h3>Page {$page['page_number']}</h3>";

                foreach ($page['panels'] ?? [] as $panel) {
                    $html .= "<div class='panel'>";
                    $html .= "<strong>Panel {$panel['panel_number']}</strong> ({$panel['layout']})<br>";
                    $html .= "<p class='visual'>{$panel['visual_description']}</p>";

                    foreach ($panel['dialogue'] ?? [] as $dialogue) {
                        $html .= "<p class='dialogue'><span class='speaker'>{$dialogue['speaker']}:</span> {$dialogue['text']}</p>";
                    }

                    $html .= "</div>";
                }

                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= "</body></html>";
        return $html;
    }

    /**
     * Extract title from source text
     */
    private function extractTitle(string $text): string
    {
        // Try to find a title at the start
        if (preg_match('/^#\s*(.+)$/m', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^Title:\s*(.+)$/im', $text, $matches)) {
            return trim($matches[1]);
        }

        // Use first line if short enough
        $firstLine = strtok($text, "\n");
        if (strlen($firstLine) < 100) {
            return trim($firstLine);
        }

        return 'Untitled Manga Script';
    }

    /**
     * Count total panels
     */
    private function countPanels(array $script): int
    {
        $count = 0;
        foreach ($script['chapters'] as $chapter) {
            foreach ($chapter['pages'] ?? [] as $page) {
                $count += count($page['panels'] ?? []);
            }
        }
        return $count;
    }

    /**
     * Setup custom provider
     */
    private function setupCustomProvider(array $endpointConfig): void
    {
        $this->providerRepo->register('custom_user', [
            'class' => AI\Providers\CustomProvider::class,
            'category' => 'H',
            'tier' => 3,
            'display_name' => $endpointConfig['display_name'] ?? 'Custom Endpoint',
            'censorship_default' => 'configurable',
            'api_base' => $endpointConfig['api_base'],
            'api_key' => $endpointConfig['api_key'] ?? '',
            'request_format' => $endpointConfig['request_format'] ?? 'openai',
        ]);
    }

    /**
     * Check if chunked upload is supported
     */
    public function supportsChunking(): bool
    {
        return true;
    }

    /**
     * Estimate processing time
     */
    public function estimateTime(array $input): int
    {
        $wordCount = 0;

        if (!empty($input['source_text'])) {
            $wordCount = str_word_count($input['source_text']);
        } elseif (!empty($input['file_size'])) {
            $wordCount = $input['file_size'] / 5; // Rough estimate
        }

        // Estimate: 100 words = ~5 seconds
        return (int) max(10, ($wordCount / 100) * 5);
    }

    /**
     * Get service configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Initialize chunked process
     */
    public function initializeChunkedProcess(array $metadata): array
    {
        $sessionId = uniqid('manga_', true);

        return [
            'session_id' => $sessionId,
            'chunk_size' => $this->getChunkSize(),
            'status_url' => '/api/chunk/status/' . $sessionId,
        ];
    }

    /**
     * Process a chunk
     */
    public function processChunk(string $sessionId, int $chunkIndex, string $chunkData): array
    {
        // For text processing, chunks are concatenated
        $chunkDir = Application::getInstance()->getBasePath() . '/storage/uploads/chunks/' . $sessionId;

        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        $chunkPath = $chunkDir . '/chunk_' . $chunkIndex;
        file_put_contents($chunkPath, $chunkData);

        return [
            'success' => true,
            'chunk_index' => $chunkIndex,
        ];
    }

    /**
     * Finalize chunked process
     */
    public function finalizeChunkedProcess(string $sessionId): array
    {
        $chunkDir = Application::getInstance()->getBasePath() . '/storage/uploads/chunks/' . $sessionId;

        // Reassemble chunks
        $text = '';
        $chunkIndex = 0;

        while (file_exists($chunkDir . '/chunk_' . $chunkIndex)) {
            $text .= file_get_contents($chunkDir . '/chunk_' . $chunkIndex);
            $chunkIndex++;
        }

        // Process
        $result = $this->process(['source_text' => $text]);

        // Cleanup
        array_map('unlink', glob($chunkDir . '/chunk_*'));
        rmdir($chunkDir);

        return $result;
    }

    /**
     * Cancel chunked process
     */
    public function cancelChunkedProcess(string $sessionId): bool
    {
        $chunkDir = Application::getInstance()->getBasePath() . '/storage/uploads/chunks/' . $sessionId;

        if (is_dir($chunkDir)) {
            array_map('unlink', glob($chunkDir . '/chunk_*'));
            rmdir($chunkDir);
        }

        return true;
    }

    /**
     * Get chunked status
     */
    public function getChunkedStatus(string $sessionId): array
    {
        $chunkDir = Application::getInstance()->getBasePath() . '/storage/uploads/chunks/' . $sessionId;

        $chunks = glob($chunkDir . '/chunk_*');

        return [
            'status' => 'in_progress',
            'received_chunks' => count($chunks),
        ];
    }

    /**
     * Get chunk size
     */
    public function getChunkSize(): int
    {
        return 1048576; // 1MB
    }

    /**
     * Get max file size
     */
    public function getMaxFileSize(): int
    {
        return 10485760; // 10MB for text files
    }

    /**
     * Check if chunk is valid
     */
    public function isChunkValid(string $sessionId, int $chunkIndex, string $chunkData): bool
    {
        return strlen($chunkData) <= $this->getChunkSize() * 1.1;
    }

    /**
     * Get provider metadata for UI
     */
    public function getProviderMetadata(): array
    {
        return $this->providerRepo->getProviderMetadata();
    }
}
