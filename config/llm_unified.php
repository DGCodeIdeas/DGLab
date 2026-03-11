<?php

/**
 * DGLab Unified LLM Configuration
 *
 * Complete configuration for 40+ providers across 8 categories.
 *
 * @package DGLab\Config
 */

return [
    'version' => '2.0.0',
    'last_updated' => '2024-01-15',

    /**
     * All providers with full categorization
     */
    'providers' => [
        // =========================================
        // CATEGORY A: ENTERPRISE CLOUD (Tier 1)
        // =========================================

        'openai' => [
            'category' => 'A',
            'tier' => 1,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\OpenAiProvider::class,
            'display_name' => 'OpenAI',
            'api_base' => 'https://api.openai.com/v1',
            'auth_type' => 'bearer',
            'env_key' => 'OPENAI_API_KEY',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'configurable',
            'deployment_type' => 'saas',
            'compliance' => ['soc2', 'gdpr', 'ccpa', 'hipaa_baa_available'],
            'regions' => ['us', 'eu'],
            'data_retention_days' => 30,
            'training_opt_out' => true,
        ],

        'anthropic' => [
            'category' => 'A',
            'tier' => 1,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\AnthropicProvider::class,
            'display_name' => 'Anthropic',
            'api_base' => 'https://api.anthropic.com',
            'auth_type' => 'x-api-key',
            'env_key' => 'ANTHROPIC_API_KEY',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'censored',
            'deployment_type' => 'saas',
            'compliance' => ['soc2', 'gdpr', 'ccpa', 'iso27001'],
            'regions' => ['us'],
            'data_retention_days' => 28,
            'training_opt_out' => true,
        ],

        'google_gemini' => [
            'category' => 'A',
            'tier' => 1,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\GoogleProvider::class,
            'display_name' => 'Google Gemini',
            'api_base' => 'https://generativelanguage.googleapis.com/v1beta',
            'auth_type' => 'api_key',
            'env_key' => 'GOOGLE_API_KEY',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'configurable',
            'deployment_type' => 'saas',
            'compliance' => ['soc2', 'gdpr', 'fedramp', 'iso27001'],
            'regions' => ['global'],
            'data_retention_days' => 'varies_by_tier',
            'training_opt_out' => 'configurable',
        ],

        'azure_openai' => [
            'category' => 'A',
            'tier' => 1,
            'display_name' => 'Azure OpenAI',
            'api_base' => 'configurable',
            'auth_type' => 'azure_ad',
            'env_key' => 'AZURE_OPENAI_API_KEY',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'enterprise',
            'deployment_type' => 'vpc',
            'compliance' => ['soc2', 'gdpr', 'hipaa', 'fedramp', 'iso27001'],
            'regions' => ['regional'],
            'enterprise_features' => true,
        ],

        'aws_bedrock' => [
            'category' => 'A',
            'tier' => 1,
            'display_name' => 'AWS Bedrock',
            'api_base' => 'https://bedrock-runtime.{region}.amazonaws.com',
            'auth_type' => 'aws_sig_v4',
            'env_key' => 'AWS_ACCESS_KEY_ID',
            'censorship_default' => 'configurable',
            'deployment_type' => 'vpc',
            'compliance' => ['soc2', 'hipaa', 'fedramp', 'iso27001'],
            'regions' => ['regional'],
        ],

        'cohere' => [
            'category' => 'A',
            'tier' => 1,
            'display_name' => 'Cohere',
            'api_base' => 'https://api.cohere.ai/v1',
            'auth_type' => 'bearer',
            'env_key' => 'COHERE_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'moderate',
            'deployment_type' => 'saas',
            'specializations' => ['multilingual', 'rag'],
        ],

        'ai21' => [
            'category' => 'A',
            'tier' => 1,
            'display_name' => 'AI21 Labs',
            'api_base' => 'https://api.ai21.com/studio/v1',
            'auth_type' => 'bearer',
            'env_key' => 'AI21_API_KEY',
            'censorship_default' => 'moderate',
            'deployment_type' => 'saas',
            'specializations' => ['long_context'],
        ],

        // =========================================
        // CATEGORY B: OPEN MODEL HOSTING (Tier 2)
        // =========================================

        'mistral' => [
            'category' => 'B',
            'tier' => 2,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\MistralProvider::class,
            'display_name' => 'Mistral AI',
            'api_base' => 'https://api.mistral.ai/v1',
            'auth_type' => 'bearer',
            'env_key' => 'MISTRAL_API_KEY',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'minimal',
            'deployment_type' => 'saas',
            'compliance' => ['gdpr', 'iso27001'],
            'regions' => ['eu'],
        ],

        'together' => [
            'category' => 'B',
            'tier' => 2,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\TogetherProvider::class,
            'display_name' => 'Together AI',
            'api_base' => 'https://api.together.xyz/v1',
            'auth_type' => 'bearer',
            'env_key' => 'TOGETHER_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'uncensored',
            'deployment_type' => 'saas',
            'free_tier' => ['credits' => 0.18],
        ],

        'fireworks' => [
            'category' => 'B',
            'tier' => 2,
            'display_name' => 'Fireworks AI',
            'api_base' => 'https://api.fireworks.ai/inference/v1',
            'auth_type' => 'bearer',
            'env_key' => 'FIREWORKS_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'uncensored',
            'deployment_type' => 'saas',
            'free_tier' => ['tokens_per_month' => 1000000],
            'specializations' => ['speed'],
        ],

        'groq' => [
            'category' => 'B',
            'tier' => 2,
            'display_name' => 'Groq',
            'api_base' => 'https://api.groq.com/openai/v1',
            'auth_type' => 'bearer',
            'env_key' => 'GROQ_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'minimal',
            'deployment_type' => 'saas',
            'specializations' => ['ultra_fast'],
            'free_tier' => ['limited' => true],
        ],

        'perplexity' => [
            'category' => 'B',
            'tier' => 2,
            'display_name' => 'Perplexity',
            'api_base' => 'https://api.perplexity.ai',
            'auth_type' => 'bearer',
            'env_key' => 'PERPLEXITY_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'moderate',
            'deployment_type' => 'saas',
            'specializations' => ['search_integrated'],
        ],

        'replicate' => [
            'category' => 'B',
            'tier' => 2,
            'display_name' => 'Replicate',
            'api_base' => 'https://api.replicate.com/v1',
            'auth_type' => 'bearer',
            'env_key' => 'REPLICATE_API_TOKEN',
            'censorship_default' => 'varies',
            'deployment_type' => 'saas',
            'cost_model' => 'pay_per_use',
        ],

        // =========================================
        // CATEGORY C: AGGREGATOR PLATFORMS (Tier 2)
        // =========================================

        'openrouter' => [
            'category' => 'C',
            'tier' => 2,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\OpenRouterProvider::class,
            'display_name' => 'OpenRouter',
            'api_base' => 'https://openrouter.ai/api/v1',
            'auth_type' => 'bearer',
            'env_key' => 'OPENROUTER_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'varies',
            'deployment_type' => 'saas',
            'routing_features' => [
                'provider_preference' => true,
                'price_optimization' => true,
                'latency_optimization' => true,
                'fallthrough_routing' => true,
            ],
            'free_tier' => ['models' => 20, 'limits' => 'variable'],
        ],

        'unify_ai' => [
            'category' => 'C',
            'tier' => 2,
            'display_name' => 'Unify AI',
            'api_base' => 'https://api.unify.ai/v0',
            'auth_type' => 'bearer',
            'env_key' => 'UNIFY_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'varies',
            'specializations' => ['latency_optimization'],
        ],

        'ai_horde' => [
            'category' => 'C',
            'tier' => 3,
            'display_name' => 'AI Horde',
            'api_base' => 'https://aihorde.net/api/v2',
            'auth_type' => 'api_key',
            'env_key' => 'AIHORDE_API_KEY',
            'censorship_default' => 'varies',
            'deployment_type' => 'decentralized',
            'cost_model' => 'community_credits',
        ],

        // =========================================
        // CATEGORY D: REGIONAL PROVIDERS (Tier 2-3)
        // =========================================

        'deepseek' => [
            'category' => 'D',
            'tier' => 2,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\DeepSeekProvider::class,
            'display_name' => 'DeepSeek',
            'region' => 'CN',
            'api_base' => 'https://api.deepseek.com',
            'auth_type' => 'bearer',
            'env_key' => 'DEEPSEEK_API_KEY',
            'supports_streaming' => true,
            'censorship_default' => 'regulatory',
            'deployment_type' => 'saas',
            'cost_advantage' => '90% cheaper than US equivalents',
            'compliance_note' => 'Subject to PRC cybersecurity law',
        ],

        'qwen' => [
            'category' => 'D',
            'tier' => 2,
            'display_name' => 'Qwen (Alibaba)',
            'region' => 'CN',
            'api_base' => 'https://dashscope.aliyuncs.com/api/v1',
            'auth_type' => 'bearer',
            'env_key' => 'QWEN_API_KEY',
            'censorship_default' => 'regulatory',
            'cost_advantage' => '80% cheaper',
            'multilingual_strength' => ['zh', 'en', 'ja', 'ko', 'ar', 'es', 'fr'],
        ],

        'yi' => [
            'category' => 'D',
            'tier' => 2,
            'display_name' => 'Yi (01.AI)',
            'region' => 'CN',
            'api_base' => 'https://api.lingyiwanwu.com/v1',
            'auth_type' => 'bearer',
            'env_key' => 'YI_API_KEY',
            'censorship_default' => 'moderate',
            'cost_advantage' => '70% cheaper',
        ],

        'baichuan' => [
            'category' => 'D',
            'tier' => 2,
            'display_name' => 'Baichuan',
            'region' => 'CN',
            'censorship_default' => 'regulatory',
            'cost_advantage' => '75% cheaper',
        ],

        'zhipu' => [
            'category' => 'D',
            'tier' => 2,
            'display_name' => 'Zhipu AI (ChatGLM)',
            'region' => 'CN',
            'censorship_default' => 'regulatory',
            'cost_advantage' => '70% cheaper',
        ],

        'moonshot' => [
            'category' => 'D',
            'tier' => 2,
            'display_name' => 'Moonshot AI (Kimi)',
            'region' => 'CN',
            'censorship_default' => 'moderate',
            'specializations' => ['long_context'],
        ],

        // =========================================
        // CATEGORY E: LOCAL/SELF-HOSTED (Tier 3)
        // =========================================

        'ollama' => [
            'category' => 'E',
            'tier' => 3,
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\OllamaProvider::class,
            'display_name' => 'Ollama',
            'api_base' => 'http://localhost:11434',
            'auth_type' => 'none',
            'supports_streaming' => true,
            'supports_json_mode' => true,
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'cost_model' => 'hardware_only',
            'setup_difficulty' => 'easy',
            'setup_guide' => '/docs/llm/setup/ollama',
        ],

        'lmstudio' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'LM Studio',
            'api_base' => 'http://localhost:1234/v1',
            'auth_type' => 'none',
            'supports_streaming' => true,
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'cost_model' => 'hardware_only',
            'gui_based' => true,
            'platforms' => ['mac', 'windows', 'linux'],
        ],

        'llamacpp' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'llama.cpp',
            'api_base' => 'http://localhost:8080',
            'auth_type' => 'none',
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'cost_model' => 'hardware_only',
            'setup_difficulty' => 'moderate',
        ],

        'koboldcpp' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'KoboldCPP',
            'api_base' => 'http://localhost:5001',
            'auth_type' => 'none',
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'specializations' => ['creative', 'story'],
        ],

        'textgen_webui' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'Text Generation WebUI',
            'api_base' => 'http://localhost:5000',
            'auth_type' => 'none',
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'specializations' => ['extensions'],
        ],

        'vllm' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'vLLM',
            'api_base' => 'http://localhost:8000',
            'auth_type' => 'none',
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'specializations' => ['throughput'],
        ],

        'localai' => [
            'category' => 'E',
            'tier' => 3,
            'display_name' => 'LocalAI',
            'api_base' => 'http://localhost:8080',
            'auth_type' => 'none',
            'censorship_default' => 'uncensored',
            'deployment_type' => 'local',
            'openai_compatible' => true,
        ],

        // =========================================
        // CATEGORY F: SPECIALIZED/CREATIVE (Tier 2-3)
        // =========================================

        'novelai' => [
            'category' => 'F',
            'tier' => 2,
            'display_name' => 'NovelAI',
            'api_base' => 'https://api.novelai.net',
            'auth_type' => 'bearer',
            'env_key' => 'NOVELAI_API_KEY',
            'censorship_default' => 'configurable',
            'deployment_type' => 'saas',
            'specializations' => ['creative', 'nsfw_allowed'],
            'cost_model' => 'subscription',
            'features' => ['lorebooks', 'storytuning', 'modules'],
        ],

        'sudowrite' => [
            'category' => 'F',
            'tier' => 2,
            'display_name' => 'SudoWrite',
            'censorship_default' => 'minimal',
            'specializations' => ['fiction', 'authors'],
            'cost_model' => 'subscription',
        ],

        'inworld' => [
            'category' => 'F',
            'tier' => 2,
            'display_name' => 'Inworld AI',
            'censorship_default' => 'configurable',
            'specializations' => ['npcs', 'games'],
            'deployment_type' => 'saas',
        ],

        // =========================================
        // CATEGORY G: RESEARCH/EDUCATION (Tier 3)
        // =========================================

        'huggingface_inference' => [
            'category' => 'G',
            'tier' => 3,
            'display_name' => 'Hugging Face Inference',
            'api_base' => 'https://api-inference.huggingface.co',
            'auth_type' => 'bearer',
            'env_key' => 'HUGGINGFACE_API_KEY',
            'censorship_default' => 'varies',
            'deployment_type' => 'saas',
            'free_tier' => ['requests_per_hour' => 1000, 'queue' => true],
        ],

        // =========================================
        // CATEGORY H: CUSTOM ENDPOINTS
        // =========================================

        'custom' => [
            'category' => 'H',
            'tier' => 'custom',
            'class' => \DGLab\Services\NovelToMangaScript\AI\Providers\CustomProvider::class,
            'display_name' => 'Custom Endpoint',
            'configurable' => true,
            'fields' => [
                'api_base',
                'auth_type',
                'request_format',
                'model_mapping',
                'response_extraction',
                'custom_headers',
            ],
        ],
    ],

    /**
     * Routing defaults
     */
    'routing' => [
        'default_mode' => 'censored',
        'fallback_enabled' => true,
        'max_fallbacks' => 3,
        'circuit_breaker_threshold' => 5,
        'circuit_breaker_timeout' => 300,
        'cost_optimization' => true,
        'latency_optimization' => false,
    ],

    /**
     * Safety settings
     */
    'safety' => [
        'uncensored_requires_acknowledgment' => true,
        'acknowledgment_text' => 'I understand that uncensored mode may generate unfiltered content including mature, violent, or controversial material. I accept full legal and ethical responsibility for how this content is used, including compliance with local laws and platform terms of service.',
        'acknowledgment_version' => '2024.01',
        'audit_uncensored_full_content' => true,
        'auto_flag_for_review' => ['illegal_content_indicators' => true],
    ],

    /**
     * Documentation features
     */
    'documentation' => [
        'provider_directory_enabled' => true,
        'comparison_tool_enabled' => true,
        'cost_calculator_enabled' => true,
        'selector_quiz_enabled' => true,
        'status_page_enabled' => true,
        'api_reference_enabled' => true,
        'setup_guides_enabled' => true,
    ],
];
