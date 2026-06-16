<?php

/**
 * DGLab LLM Multi-Factor Categorization System
 *
 * Defines the 10-dimensional classification system for LLM providers.
 *
 * @package DGLab\Config
 */

return [
    /**
     * Dimension 1: Cost Structure
     * Tiers based on cost per 1K tokens
     */
    'cost_tiers' => [
        'free' => [
            'max_cost' => 0,
            'description' => 'No monetary cost',
            'examples' => ['Local models', 'HuggingFace free tier'],
        ],
        'freemium' => [
            'max_cost' => 0.001,
            'description' => 'Generous free tier available',
            'examples' => ['OpenRouter free models', 'Groq free tier'],
        ],
        'budget' => [
            'max_cost' => 0.005,
            'description' => 'Under $0.005 per 1K tokens',
            'examples' => ['DeepSeek', 'Mistral Small'],
        ],
        'standard' => [
            'max_cost' => 0.02,
            'description' => 'Market rate pricing',
            'examples' => ['GPT-4o-mini', 'Claude Haiku'],
        ],
        'premium' => [
            'max_cost' => 0.10,
            'description' => 'High quality models',
            'examples' => ['GPT-4o', 'Claude Sonnet'],
        ],
        'enterprise' => [
            'max_cost' => INF,
            'description' => 'Custom enterprise pricing',
            'examples' => ['Azure OpenAI', 'AWS Bedrock'],
        ],
    ],

    /**
     * Dimension 2: Censorship Level
     * 0-5 scale for content filtering
     */
    'censorship_levels' => [
        0 => [
            'name' => 'none',
            'description' => 'No filtering, raw output',
            'user_control' => 'total',
            'examples' => ['Local uncensored models', 'Dolphin'],
        ],
        1 => [
            'name' => 'minimal',
            'description' => 'Illegal content only',
            'user_control' => 'high',
            'examples' => ['Mistral', 'Together AI'],
        ],
        2 => [
            'name' => 'moderate',
            'description' => 'Hate, violence filtered',
            'user_control' => 'medium',
            'examples' => ['Anthropic Claude', 'Google Gemini'],
        ],
        3 => [
            'name' => 'strict',
            'description' => 'Broad safety categories',
            'user_control' => 'low',
            'examples' => ['OpenAI GPT-4', 'Character.AI'],
        ],
        4 => [
            'name' => 'enterprise',
            'description' => 'Custom policy enforced',
            'user_control' => 'configured',
            'examples' => ['Azure OpenAI with content filters'],
        ],
        5 => [
            'name' => 'regulatory',
            'description' => 'Government mandated',
            'user_control' => 'none',
            'examples' => ['Chinese models (DeepSeek, Qwen)'],
        ],
    ],

    /**
     * Dimension 3: Speed Priority
     * Based on typical latency
     */
    'speed_tiers' => [
        'batch' => [
            'max_latency_ms' => 30000,
            'description' => 'Background processing OK',
            'use_cases' => ['Large document processing', 'Async workflows'],
        ],
        'standard' => [
            'max_latency_ms' => 5000,
            'description' => 'Interactive acceptable',
            'use_cases' => ['Web applications', 'API services'],
        ],
        'fast' => [
            'max_latency_ms' => 1000,
            'description' => 'Real-time feel',
            'use_cases' => ['Chat interfaces', 'Interactive editing'],
        ],
        'ultra' => [
            'max_latency_ms' => 200,
            'description' => 'Instant response',
            'use_cases' => ['Code completion', 'Real-time suggestions'],
        ],
    ],

    /**
     * Dimension 4: Context Length
     * Based on token capacity
     */
    'context_tiers' => [
        'short' => [
            'max_tokens' => 4096,
            'description' => 'Scenes/paragraphs',
            'use_cases' => ['Short-form content', 'Simple queries'],
        ],
        'medium' => [
            'max_tokens' => 32768,
            'description' => 'Chapters',
            'use_cases' => ['Document analysis', 'Long conversations'],
        ],
        'long' => [
            'max_tokens' => 131072,
            'description' => 'Short stories',
            'use_cases' => ['Book summaries', 'Code analysis'],
        ],
        'massive' => [
            'max_tokens' => 2000000,
            'description' => 'Full novels',
            'use_cases' => ['Entire codebases', 'Full book processing'],
        ],
    ],

    /**
     * Dimension 5: Specialization
     * Task-specific optimization
     */
    'specializations' => [
        'general' => [
            'description' => 'All-purpose',
            'strengths' => ['Versatility', 'Broad knowledge'],
        ],
        'creative' => [
            'description' => 'Fiction/storytelling',
            'strengths' => ['Narrative', 'Character development', 'Dialogue'],
        ],
        'technical' => [
            'description' => 'Code/documentation',
            'strengths' => ['Programming', 'Technical writing', 'Analysis'],
        ],
        'analytical' => [
            'description' => 'Analysis/reasoning',
            'strengths' => ['Logic', 'Problem solving', 'Data analysis'],
        ],
        'roleplay' => [
            'description' => 'Character interaction',
            'strengths' => ['Persona consistency', 'Dialogue', 'Scenarios'],
        ],
        'multilingual' => [
            'description' => 'Non-English optimized',
            'strengths' => ['Translation', 'Cross-lingual tasks'],
        ],
    ],

    /**
     * Dimension 6: Deployment Type
     * Where the model runs
     */
    'deployment_types' => [
        'saas' => [
            'description' => 'Vendor-hosted API',
            'pros' => ['No infrastructure', 'Always updated'],
            'cons' => ['Data privacy', 'Vendor lock-in'],
        ],
        'vpc' => [
            'description' => 'Private cloud deployment',
            'pros' => ['Data isolation', 'Custom security'],
            'cons' => ['Higher cost', 'Management overhead'],
        ],
        'onprem' => [
            'description' => 'Customer data center',
            'pros' => ['Full control', 'Regulatory compliance'],
            'cons' => ['Capital expense', 'Maintenance'],
        ],
        'local' => [
            'description' => 'User hardware',
            'pros' => ['Free', 'Complete privacy'],
            'cons' => ['Hardware requirements', 'Performance limits'],
        ],
        'hybrid' => [
            'description' => 'Mixed/composable',
            'pros' => ['Flexibility', 'Optimization'],
            'cons' => ['Complexity'],
        ],
    ],

    /**
     * Dimension 7: Geographic Compliance
     * Regulatory frameworks supported
     */
    'compliance_regions' => [
        'gdpr' => [
            'name' => 'GDPR',
            'description' => 'EU General Data Protection Regulation',
            'regions' => ['EU', 'EEA'],
        ],
        'ccpa' => [
            'name' => 'CCPA',
            'description' => 'California Consumer Privacy Act',
            'regions' => ['US-CA'],
        ],
        'pipeda' => [
            'name' => 'PIPEDA',
            'description' => 'Canadian privacy law',
            'regions' => ['CA'],
        ],
        'lgpd' => [
            'name' => 'LGPD',
            'description' => 'Brazilian privacy law',
            'regions' => ['BR'],
        ],
        'pdpa' => [
            'name' => 'PDPA',
            'description' => 'Singapore privacy law',
            'regions' => ['SG'],
        ],
        'cybersecurity_law' => [
            'name' => 'Cybersecurity Law',
            'description' => 'China cybersecurity regulations',
            'regions' => ['CN'],
        ],
        'sox' => [
            'name' => 'SOX',
            'description' => 'Sarbanes-Oxley financial compliance',
            'regions' => ['US'],
        ],
        'hipaa' => [
            'name' => 'HIPAA',
            'description' => 'US healthcare data protection',
            'regions' => ['US'],
        ],
        'fedramp' => [
            'name' => 'FedRAMP',
            'description' => 'US government cloud security',
            'regions' => ['US-GOV'],
        ],
        'iso27001' => [
            'name' => 'ISO 27001',
            'description' => 'Information security standard',
            'regions' => ['Global'],
        ],
        'soc2' => [
            'name' => 'SOC 2',
            'description' => 'Service Organization Control',
            'regions' => ['Global'],
        ],
    ],

    /**
     * Dimension 8: Model Architecture
     * Technical architecture type
     */
    'architectures' => [
        'transformer' => [
            'description' => 'Standard decoder-only transformer',
            'examples' => ['GPT-4', 'Llama', 'Mistral'],
        ],
        'moe' => [
            'description' => 'Mixture of Experts',
            'examples' => ['Mixtral', 'GPT-4 (rumored)'],
        ],
        'ssm' => [
            'description' => 'State Space Model',
            'examples' => ['Mamba', 'Jamba'],
        ],
        'hybrid' => [
            'description' => 'Mixed architectures',
            'examples' => ['Jamba (SSM + Transformer)'],
        ],
        'rnn' => [
            'description' => 'Recurrent (legacy)',
            'examples' => ['RWKV'],
        ],
        'diffusion_lm' => [
            'description' => 'Diffusion-based language model',
            'examples' => ['Experimental research models'],
        ],
    ],

    /**
     * Dimension 9: Accessibility
     * How to access the API
     */
    'accessibility' => [
        'api_key' => [
            'description' => 'Simple API key authentication',
            'complexity' => 'low',
        ],
        'oauth' => [
            'description' => 'OAuth 2.0 flow',
            'complexity' => 'medium',
        ],
        'saml' => [
            'description' => 'Enterprise SSO',
            'complexity' => 'high',
        ],
        'mtls' => [
            'description' => 'Mutual TLS authentication',
            'complexity' => 'high',
        ],
        'ip_whitelist' => [
            'description' => 'Network IP restriction',
            'complexity' => 'medium',
        ],
        'invite_only' => [
            'description' => 'Closed beta access',
            'complexity' => 'varies',
        ],
    ],

    /**
     * Dimension 10: Openness Level
     * Model/code availability
     */
    'openness' => [
        'proprietary' => [
            'description' => 'Closed weights, API only',
            'transparency' => 'low',
            'examples' => ['GPT-4', 'Claude'],
        ],
        'weights_available' => [
            'description' => 'Downloadable model weights',
            'transparency' => 'medium',
            'examples' => ['Llama', 'Mistral'],
        ],
        'open_source' => [
            'description' => 'Full source code available',
            'transparency' => 'high',
            'examples' => ['OLMo', 'OpenLLaMA'],
        ],
        'open_data' => [
            'description' => 'Training data published',
            'transparency' => 'very_high',
            'examples' => ['RedPajama', 'Dolma'],
        ],
        'reproducible' => [
            'description' => 'Training recipe shared',
            'transparency' => 'very_high',
            'examples' => ['AI2 OLMo'],
        ],
        'auditable' => [
            'description' => 'Third-party audits available',
            'transparency' => 'high',
            'examples' => ['Some enterprise providers'],
        ],
    ],

    /**
     * Provider Category Definitions
     * A-H taxonomy from blueprint
     */
    'provider_categories' => [
        'A' => [
            'name' => 'Enterprise Cloud',
            'tier' => 1,
            'description' => 'Paid, high reliability, strict/configurable censorship',
            'examples' => ['OpenAI', 'Anthropic', 'Google', 'Azure'],
        ],
        'B' => [
            'name' => 'Open Model Hosting',
            'tier' => 2,
            'description' => 'Freemium/paid, uncensored by default, API access',
            'examples' => ['Mistral', 'Together', 'Fireworks', 'Groq'],
        ],
        'C' => [
            'name' => 'Aggregator Platforms',
            'tier' => 2,
            'description' => 'Route to multiple backends, free/paid mix',
            'examples' => ['OpenRouter', 'Unify AI', 'AI Horde'],
        ],
        'D' => [
            'name' => 'Regional/Cloud Providers',
            'tier' => 2,
            'description' => 'Geo-specific, regulatory compliant',
            'examples' => ['DeepSeek', 'Qwen', 'Yi', 'HyperCLOVA'],
        ],
        'E' => [
            'name' => 'Local/Self-Hosted',
            'tier' => 3,
            'description' => 'Free (hardware cost only), full control',
            'examples' => ['Ollama', 'LM Studio', 'llama.cpp'],
        ],
        'F' => [
            'name' => 'Specialized/Creative',
            'tier' => 2,
            'description' => 'Domain-optimized, unique features',
            'examples' => ['NovelAI', 'SudoWrite', 'Inworld'],
        ],
        'G' => [
            'name' => 'Research/Education',
            'tier' => 3,
            'description' => 'Free, open, experimental',
            'examples' => ['HuggingFace', 'Google Colab', 'Kaggle'],
        ],
        'H' => [
            'name' => 'Custom/Enterprise Endpoints',
            'tier' => 'custom',
            'description' => 'User-defined, bring your own',
            'examples' => ['Private deployments', 'VPC endpoints'],
        ],
    ],
];
