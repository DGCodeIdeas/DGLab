<?php
/**
 * Novel to Manga Script Converter - Tool View
 * 
 * Extended view for the NovelToManga tool with AI provider selection,
 * API key management, and advanced options.
 * 
 * @package DGLab\Views\Tools
 */

use DGLab\Tools\NovelToManga\NovelToManga;
use DGLab\Tools\NovelToManga\ApiKeyManager;

$isNovelToManga = $tool instanceof NovelToManga;
$userId = session_id() ?? 'guest_' . uniqid();

// Check for stored API keys
$hasOpenAiKey = $isNovelToManga && $tool->hasApiKey($userId, 'openai');
$hasClaudeKey = $isNovelToManga && $tool->hasApiKey($userId, 'claude');
$hasGeminiKey = $isNovelToManga && $tool->hasApiKey($userId, 'gemini');
?>
<!-- Tool Header -->
<section class="tool-header">
    <div class="container">
        <div class="tool-header-content">
            <div class="tool-header-icon">
                <i class="fas <?php echo $tool->getIcon(); ?>"></i>
            </div>
            <div class="tool-header-info">
                <span class="tool-header-category"><?php echo htmlspecialchars($tool->getCategory()); ?></span>
                <h1 class="tool-header-title"><?php echo htmlspecialchars($tool->getName()); ?></h1>
                <p class="tool-header-description"><?php echo htmlspecialchars($tool->getDescription()); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Tool Interface -->
<section class="tool-interface">
    <div class="container">
        <div class="tool-interface-grid">
            <!-- Upload Section -->
            <div class="tool-panel tool-upload-panel">
                <h2 class="panel-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Upload Novel (EPUB)
                </h2>
                
                <div class="upload-zone" id="upload-zone">
                    <input type="file" id="file-input" class="file-input" 
                           accept="<?php echo implode(',', $tool->getSupportedTypes()); ?>"
                           data-max-size="<?php echo $tool->getMaxFileSize(); ?>">
                    <div class="upload-zone-content">
                        <i class="fas fa-book upload-zone-icon"></i>
                        <p class="upload-zone-text">
                            <strong>Click to upload</strong> or drag and drop
                        </p>
                        <p class="upload-zone-hint">
                            EPUB files only (Max <?php echo number_format($tool->getMaxFileSize() / 1024 / 1024, 0); ?> MB)
                        </p>
                    </div>
                </div>
                
                <!-- Upload Progress -->
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="progress-fill"></div>
                    </div>
                    <p class="progress-text" id="progress-text">0%</p>
                </div>
                
                <!-- File Info -->
                <div class="file-info" id="file-info" style="display: none;">
                    <div class="file-info-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="file-info-details">
                        <p class="file-info-name" id="file-name"></p>
                        <p class="file-info-size" id="file-size"></p>
                    </div>
                    <button type="button" class="file-info-remove" id="remove-file" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- AI Processing Info -->
                <div class="ai-info-card" id="ai-info">
                    <h4><i class="fas fa-robot"></i> AI Processing</h4>
                    <p>Your novel will be processed using AI to convert it into a structured manga script format.</p>
                    <ul class="ai-features">
                        <li><i class="fas fa-check"></i> Contextual chunking for optimal results</li>
                        <li><i class="fas fa-check"></i> Character dialogue extraction</li>
                        <li><i class="fas fa-check"></i> Scene descriptions and panel layouts</li>
                        <li><i class="fas fa-check"></i> Preserves chapter structure</li>
                    </ul>
                </div>
            </div>
            
            <!-- Options Section -->
            <div class="tool-panel tool-options-panel">
                <h2 class="panel-title">
                    <i class="fas fa-cog"></i>
                    Conversion Options
                </h2>
                
                <form id="tool-options-form" class="tool-options-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="upload_id" id="upload-id">
                    
                    <!-- AI Provider Selection -->
                    <div class="form-group">
                        <label for="ai_provider" class="form-label">
                            <i class="fas fa-brain"></i> AI Provider
                        </label>
                        <p class="form-help">Select the AI service for processing your novel</p>
                        <select name="ai_provider" id="ai_provider" class="form-select" required>
                            <option value="openai" selected>
                                OpenAI GPT (Free tier available)
                            </option>
                            <option value="claude">
                                Anthropic Claude (Requires API key)
                            </option>
                            <option value="gemini">
                                Google Gemini (Requires API key)
                            </option>
                        </select>
                        <div class="provider-badge" id="provider-badge">
                            <span class="badge badge-free">Free Tier Available</span>
                        </div>
                    </div>
                    
                    <!-- AI Model Selection -->
                    <div class="form-group">
                        <label for="ai_model" class="form-label">
                            <i class="fas fa-microchip"></i> AI Model
                        </label>
                        <p class="form-help">Choose the model based on quality and speed needs</p>
                        <select name="ai_model" id="ai_model" class="form-select" required>
                            <optgroup label="OpenAI Models" data-provider="openai">
                                <option value="gpt-4o-mini" selected>GPT-4o Mini (Fast, Free)</option>
                                <option value="gpt-4o">GPT-4o (Best Quality)</option>
                            </optgroup>
                            <optgroup label="Claude Models" data-provider="claude">
                                <option value="claude-3-opus">Claude 3 Opus (Best Quality)</option>
                                <option value="claude-3-sonnet">Claude 3 Sonnet (Balanced)</option>
                                <option value="claude-3-haiku">Claude 3 Haiku (Fast)</option>
                            </optgroup>
                            <optgroup label="Gemini Models" data-provider="gemini">
                                <option value="gemini-1.5-pro">Gemini 1.5 Pro (Best Quality)</option>
                                <option value="gemini-1.5-flash">Gemini 1.5 Flash (Fast)</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <!-- Content Mode -->
                    <div class="form-group">
                        <label for="content_mode" class="form-label">
                            <i class="fas fa-shield-alt"></i> Content Mode
                        </label>
                        <p class="form-help">Select content filtering level for the output</p>
                        <select name="content_mode" id="content_mode" class="form-select" required>
                            <option value="censored" selected>Censored (Safe Content Only)</option>
                            <option value="uncensored">Uncensored (Mature Content Allowed)</option>
                        </select>
                        <div class="content-warning" id="content-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Uncensored mode may produce mature content. Use responsibly.</span>
                        </div>
                    </div>
                    
                    <!-- Custom API Key Section -->
                    <div class="form-group api-key-section">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Custom API Key
                        </label>
                        <p class="form-help">Use your own API key for dedicated processing</p>
                        
                        <label class="form-toggle">
                            <input type="checkbox" name="use_custom_key" id="use_custom_key" value="1">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label">Use my own API key</span>
                        </label>
                        
                        <div class="custom-key-input" id="custom-key-container" style="display: none;">
                            <div class="input-group">
                                <input type="password" 
                                       name="custom_api_key" 
                                       id="custom_api_key" 
                                       class="form-input"
                                       placeholder="Enter your API key"
                                       autocomplete="off">
                                <button type="button" class="btn btn-icon" id="toggle-key-visibility" title="Show/Hide">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="api-key-actions">
                                <button type="button" class="btn btn-sm btn-primary" id="save-key-btn">
                                    <i class="fas fa-save"></i> Save Key
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="delete-key-btn" style="display: none;">
                                    <i class="fas fa-trash"></i> Remove Saved Key
                                </button>
                            </div>
                            <p class="key-status" id="key-status"></p>
                            <p class="form-help text-muted">
                                <i class="fas fa-lock"></i> Your key is encrypted and stored securely.
                            </p>
                        </div>
                        
                        <!-- Stored Keys Info -->
                        <div class="stored-keys-info" id="stored-keys-info">
                            <?php if ($hasOpenAiKey || $hasClaudeKey || $hasGeminiKey): ?>
                                <p class="stored-keys-label">
                                    <i class="fas fa-check-circle text-success"></i> 
                                    You have saved API keys for:
                                </p>
                                <ul class="stored-keys-list">
                                    <?php if ($hasOpenAiKey): ?><li>OpenAI</li><?php endif; ?>
                                    <?php if ($hasClaudeKey): ?><li>Claude</li><?php endif; ?>
                                    <?php if ($hasGeminiKey): ?><li>Gemini</li><?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Chunk Size -->
                    <div class="form-group">
                        <label for="chunk_size" class="form-label">
                            <i class="fas fa-cut"></i> Chunk Size
                        </label>
                        <p class="form-help">Text chunk size for AI processing (larger = better context)</p>
                        <select name="chunk_size" id="chunk_size" class="form-select" required>
                            <option value="2000">2,000 tokens (Fastest)</option>
                            <option value="4000" selected>4,000 tokens (Balanced)</option>
                            <option value="8000">8,000 tokens (Best Context)</option>
                        </select>
                    </div>
                    
                    <!-- Dialogue Style -->
                    <div class="form-group">
                        <label for="dialogue_style" class="form-label">
                            <i class="fas fa-comments"></i> Dialogue Style
                        </label>
                        <p class="form-help">Format for character dialogue in the manga script</p>
                        <select name="dialogue_style" id="dialogue_style" class="form-select" required>
                            <option value="standard" selected>Standard Manga Format</option>
                            <option value="dramatic">Dramatic/Emphasis</option>
                            <option value="minimal">Minimal/Simple</option>
                        </select>
                    </div>
                    
                    <!-- Output Format -->
                    <div class="form-group">
                        <label for="output_format" class="form-label">
                            <i class="fas fa-file-export"></i> Output Format
                        </label>
                        <p class="form-help">Format of the generated manga script</p>
                        <select name="output_format" id="output_format" class="form-select" required>
                            <option value="epub" selected>EPUB E-book</option>
                            <option value="script">Screenplay Format</option>
                            <option value="detailed">Detailed Storyboard</option>
                        </select>
                    </div>
                    
                    <!-- Toggle Options -->
                    <div class="form-group toggle-options">
                        <label class="form-label">
                            <i class="fas fa-sliders-h"></i> Additional Options
                        </label>
                        
                        <div class="toggle-grid">
                            <label class="form-toggle">
                                <input type="checkbox" name="preserve_chapters" id="preserve_chapters" value="1" checked>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Preserve Chapter Structure</span>
                            </label>
                            
                            <label class="form-toggle">
                                <input type="checkbox" name="include_descriptions" id="include_descriptions" value="1" checked>
                                <span class="toggle-slider"></span>
                                <span class="toggle-label">Include Scene Descriptions</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Processing Info -->
                    <div class="processing-info" id="processing-info">
                        <div class="info-item">
                            <i class="fas fa-info-circle"></i>
                            <span>Processing time depends on novel length and AI model selected.</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>Estimated: 1-5 minutes for average novel length.</span>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="process-btn" disabled>
                        <i class="fas fa-magic"></i>
                        Convert to Manga Script
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Results Section -->
        <div class="tool-results" id="tool-results" style="display: none;">
            <div class="tool-panel">
                <h2 class="panel-title">
                    <i class="fas fa-check-circle"></i>
                    Conversion Complete
                </h2>
                
                <div class="result-content">
                    <div class="result-info">
                        <p class="result-filename" id="result-filename"></p>
                        <p class="result-details" id="result-details"></p>
                        <div class="result-stats" id="result-stats"></div>
                    </div>
                    
                    <div class="result-actions">
                        <a href="#" class="btn btn-primary" id="download-btn" download>
                            <i class="fas fa-download"></i>
                            Download Manga Script
                        </a>
                        <button type="button" class="btn btn-outline" id="process-another">
                            <i class="fas fa-redo"></i>
                            Convert Another
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progress Section -->
        <div class="tool-progress-panel" id="progress-panel" style="display: none;">
            <div class="tool-panel">
                <h2 class="panel-title">
                    <i class="fas fa-spinner fa-spin"></i>
                    Converting Novel...
                </h2>
                
                <div class="progress-display">
                    <div class="progress-bar progress-bar-large">
                        <div class="progress-bar-fill" id="conversion-progress-fill"></div>
                    </div>
                    <p class="progress-percentage" id="conversion-progress-text">0%</p>
                    <p class="progress-stage" id="progress-stage">Initializing...</p>
                </div>
                
                <div class="progress-steps" id="progress-steps">
                    <div class="step" data-step="extract">
                        <i class="fas fa-file-archive"></i>
                        <span>Extract EPUB</span>
                    </div>
                    <div class="step" data-step="parse">
                        <i class="fas fa-file-alt"></i>
                        <span>Parse Content</span>
                    </div>
                    <div class="step" data-step="chunk">
                        <i class="fas fa-cut"></i>
                        <span>Segment Text</span>
                    </div>
                    <div class="step" data-step="ai">
                        <i class="fas fa-robot"></i>
                        <span>AI Processing</span>
                    </div>
                    <div class="step" data-step="format">
                        <i class="fas fa-paint-brush"></i>
                        <span>Format Script</span>
                    </div>
                    <div class="step" data-step="create">
                        <i class="fas fa-book"></i>
                        <span>Create EPUB</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tool Info -->
<section class="tool-info">
    <div class="container">
        <div class="tool-info-grid">
            <div class="tool-info-card">
                <h3 class="tool-info-title">
                    <i class="fas fa-info-circle"></i>
                    Supported Formats
                </h3>
                <ul class="tool-info-list">
                    <?php foreach ($tool->getSupportedTypes() as $type): ?>
                        <li><?php echo htmlspecialchars($type); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="tool-info-card">
                <h3 class="tool-info-title">
                    <i class="fas fa-hdd"></i>
                    File Limits
                </h3>
                <p>Maximum file size: <strong><?php echo number_format($tool->getMaxFileSize() / 1024 / 1024, 0); ?> MB</strong></p>
                <?php if ($tool->supportsChunking()): ?>
                    <p><i class="fas fa-check text-success"></i> Supports large file uploads</p>
                <?php endif; ?>
            </div>
            
            <div class="tool-info-card">
                <h3 class="tool-info-title">
                    <i class="fas fa-shield-alt"></i>
                    Privacy & Security
                </h3>
                <p>Your files and API keys are processed securely.</p>
                <ul class="tool-info-list">
                    <li>Files deleted after processing</li>
                    <li>API keys encrypted with AES-256</li>
                    <li>No data retained on servers</li>
                </ul>
            </div>
            
            <div class="tool-info-card">
                <h3 class="tool-info-title">
                    <i class="fas fa-robot"></i>
                    AI Providers
                </h3>
                <ul class="tool-info-list">
                    <li><strong>OpenAI GPT:</strong> Free tier available</li>
                    <li><strong>Anthropic Claude:</strong> Requires API key</li>
                    <li><strong>Google Gemini:</strong> Requires API key</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
// Tool-specific configuration
window.toolConfig = {
    id: '<?php echo $tool->getId(); ?>',
    supportsChunking: <?php echo $tool->supportsChunking() ? 'true' : 'false'; ?>,
    maxFileSize: <?php echo $tool->getMaxFileSize(); ?>,
    acceptedTypes: <?php echo json_encode($tool->getSupportedTypes()); ?>,
    apiUrl: '<?php echo $base_url; ?>/tool/<?php echo $tool->getId(); ?>/process',
    apiKeyUrl: '<?php echo $base_url; ?>/api/v1/novel-to-manga/apikey',
    hasStoredKeys: {
        openai: <?php echo $hasOpenAiKey ? 'true' : 'false'; ?>,
        claude: <?php echo $hasClaudeKey ? 'true' : 'false'; ?>,
        gemini: <?php echo $hasGeminiKey ? 'true' : 'false'; ?>
    }
};

// Model availability by provider
const modelsByProvider = {
    openai: ['gpt-4o-mini', 'gpt-4o'],
    claude: ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
    gemini: ['gemini-1.5-pro', 'gemini-1.5-flash']
};

// Provider info
const providerInfo = {
    openai: { badge: '<span class="badge badge-free">Free Tier Available</span>', hasFree: true },
    claude: { badge: '<span class="badge badge-key">API Key Required</span>', hasFree: false },
    gemini: { badge: '<span class="badge badge-key">API Key Required</span>', hasFree: false }
};

$(document).ready(function() {
    // AI Provider change handler
    $('#ai_provider').on('change', function() {
        const provider = $(this).val();
        updateModelOptions(provider);
        updateProviderBadge(provider);
        updateApiKeyUI(provider);
    });
    
    // Content mode change handler
    $('#content_mode').on('change', function() {
        const mode = $(this).val();
        if (mode === 'uncensored') {
            $('#content-warning').slideDown();
        } else {
            $('#content-warning').slideUp();
        }
    });
    
    // Custom API key toggle
    $('#use_custom_key').on('change', function() {
        if ($(this).is(':checked')) {
            $('#custom-key-container').slideDown();
            checkStoredKey();
        } else {
            $('#custom-key-container').slideUp();
        }
    });
    
    // Toggle key visibility
    $('#toggle-key-visibility').on('click', function() {
        const input = $('#custom_api_key');
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Save API key
    $('#save-key-btn').on('click', function() {
        const provider = $('#ai_provider').val();
        const apiKey = $('#custom_api_key').val().trim();
        
        if (!apiKey) {
            showKeyStatus('Please enter an API key', 'error');
            return;
        }
        
        // Validate key format
        if (!validateKeyFormat(apiKey, provider)) {
            showKeyStatus('Invalid API key format for ' + provider, 'error');
            return;
        }
        
        // Save via AJAX
        $.ajax({
            url: window.toolConfig.apiKeyUrl,
            method: 'POST',
            data: {
                provider: provider,
                api_key: apiKey,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                if (response.success) {
                    showKeyStatus('API key saved successfully!', 'success');
                    window.toolConfig.hasStoredKeys[provider] = true;
                    updateStoredKeysUI();
                    $('#delete-key-btn').show();
                } else {
                    showKeyStatus(response.message || 'Failed to save key', 'error');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to save key';
                showKeyStatus(message, 'error');
            }
        });
    });
    
    // Delete API key
    $('#delete-key-btn').on('click', function() {
        const provider = $('#ai_provider').val();
        
        if (!confirm('Are you sure you want to remove your saved API key for ' + provider + '?')) {
            return;
        }
        
        $.ajax({
            url: window.toolConfig.apiKeyUrl,
            method: 'DELETE',
            data: {
                provider: provider,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                if (response.success) {
                    showKeyStatus('API key removed successfully!', 'success');
                    window.toolConfig.hasStoredKeys[provider] = false;
                    updateStoredKeysUI();
                    $('#delete-key-btn').hide();
                    $('#custom_api_key').val('');
                } else {
                    showKeyStatus(response.message || 'Failed to remove key', 'error');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to remove key';
                showKeyStatus(message, 'error');
            }
        });
    });
    
    // Update model options based on provider
    function updateModelOptions(provider) {
        const $modelSelect = $('#ai_model');
        const currentModel = $modelSelect.val();
        
        // Hide all optgroups
        $modelSelect.find('optgroup').hide();
        
        // Show optgroup for selected provider
        $modelSelect.find('optgroup[data-provider="' + provider + '"]').show();
        
        // Select first available model if current not available
        const availableModels = modelsByProvider[provider] || [];
        if (availableModels.indexOf(currentModel) === -1 && availableModels.length > 0) {
            $modelSelect.val(availableModels[0]);
        }
    }
    
    // Update provider badge
    function updateProviderBadge(provider) {
        const info = providerInfo[provider] || providerInfo.openai;
        $('#provider-badge').html(info.badge);
    }
    
    // Update API key UI based on provider
    function updateApiKeyUI(provider) {
        const hasStoredKey = window.toolConfig.hasStoredKeys[provider];
        
        if (hasStoredKey) {
            $('#delete-key-btn').show();
            showKeyStatus('You have a saved key for ' + provider, 'info');
        } else {
            $('#delete-key-btn').hide();
            $('#key-status').empty();
        }
        
        // Update toggle label
        const info = providerInfo[provider];
        if (info.hasFree) {
            $('.toggle-label').text('Use my own API key (optional)');
        } else {
            $('.toggle-label').text('Use my own API key (required)');
        }
    }
    
    // Check for stored key
    function checkStoredKey() {
        const provider = $('#ai_provider').val();
        const hasStoredKey = window.toolConfig.hasStoredKeys[provider];
        
        if (hasStoredKey) {
            $('#delete-key-btn').show();
            showKeyStatus('Using saved key for ' + provider, 'info');
        }
    }
    
    // Validate key format
    function validateKeyFormat(key, provider) {
        switch (provider) {
            case 'openai':
                return key.startsWith('sk-') && key.length > 20;
            case 'claude':
                return key.startsWith('sk-ant-') && key.length > 20;
            case 'gemini':
                return key.length > 20;
            default:
                return key.length > 10;
        }
    }
    
    // Show key status message
    function showKeyStatus(message, type) {
        const $status = $('#key-status');
        $status.removeClass('success error info').addClass(type).text(message);
        
        // Auto-clear after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $status.empty();
            }, 5000);
        }
    }
    
    // Update stored keys UI
    function updateStoredKeysUI() {
        const keys = [];
        if (window.toolConfig.hasStoredKeys.openai) keys.push('OpenAI');
        if (window.toolConfig.hasStoredKeys.claude) keys.push('Claude');
        if (window.toolConfig.hasStoredKeys.gemini) keys.push('Gemini');
        
        const $info = $('#stored-keys-info');
        if (keys.length > 0) {
            $info.html(
                '<p class="stored-keys-label">' +
                '<i class="fas fa-check-circle text-success"></i> ' +
                'You have saved API keys for:</p>' +
                '<ul class="stored-keys-list"><li>' + keys.join('</li><li>') + '</li></ul>'
            );
        } else {
            $info.empty();
        }
    }
    
    // Initialize
    updateModelOptions($('#ai_provider').val());
    updateProviderBadge($('#ai_provider').val());
    
    // Process form submission with progress tracking
    $('#tool-options-form').on('submit', function(e) {
        e.preventDefault();
        
        const uploadId = $('#upload-id').val();
        if (!uploadId) {
            alert('Please upload a file first');
            return;
        }
        
        // Show progress panel
        $('#progress-panel').show();
        $('#tool-results').hide();
        $('#process-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Converting...');
        
        // Submit form
        const formData = $(this).serialize();
        
        $.ajax({
            url: window.toolConfig.apiUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    updateProgressSteps('complete');
                    showResults(response.data);
                } else {
                    showError(response.message || 'Conversion failed');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Conversion failed';
                showError(message);
            }
        });
        
        // Start progress polling
        pollProgress();
    });
    
    // Poll for progress updates
    function pollProgress() {
        const jobId = $('#upload-id').val();
        if (!jobId) return;
        
        const checkProgress = function() {
            $.ajax({
                url: '<?php echo $base_url; ?>/tool/' + window.toolConfig.id + '/progress/' + jobId,
                method: 'GET',
                success: function(response) {
                    if (response.success && response.data) {
                        updateProgressDisplay(response.data);
                        
                        if (response.data.status === 'processing') {
                            setTimeout(checkProgress, 1000);
                        }
                    }
                }
            });
        };
        
        checkProgress();
    }
    
    // Update progress display
    function updateProgressDisplay(data) {
        const progress = data.progress || 0;
        $('#conversion-progress-fill').css('width', progress + '%');
        $('#conversion-progress-text').text(progress + '%');
        
        if (data.message) {
            $('#progress-stage').text(data.message);
        }
        
        // Update step indicators
        updateProgressSteps(data.stage);
    }
    
    // Update progress step indicators
    function updateProgressSteps(currentStage) {
        const steps = ['extract', 'parse', 'chunk', 'ai', 'format', 'create'];
        const stageIndex = steps.indexOf(currentStage);
        
        steps.forEach(function(step, index) {
            const $step = $('.step[data-step="' + step + '"]');
            $step.removeClass('active completed');
            
            if (index < stageIndex) {
                $step.addClass('completed');
            } else if (index === stageIndex) {
                $step.addClass('active');
            }
        });
        
        if (currentStage === 'complete') {
            $('.step').addClass('completed');
        }
    }
    
    // Show results
    function showResults(data) {
        $('#progress-panel').hide();
        $('#tool-results').show();
        $('#process-btn').prop('disabled', false).html('<i class="fas fa-magic"></i> Convert to Manga Script');
        
        $('#result-filename').text(data.output_filename);
        $('#result-details').text(
            'File size: ' + formatFileSize(data.file_size) + 
            ' | Processing time: ' + data.processing_time + 's'
        );
        
        if (data.chunks_processed) {
            $('#result-stats').html('<span><i class="fas fa-cut"></i> ' + 
                data.chunks_processed + ' text chunks processed</span>');
        }
        
        $('#download-btn').attr('href', data.download_url);
    }
    
    // Show error
    function showError(message) {
        $('#progress-panel').hide();
        $('#process-btn').prop('disabled', false).html('<i class="fas fa-magic"></i> Convert to Manga Script');
        alert('Error: ' + message);
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Process another button
    $('#process-another').on('click', function() {
        $('#tool-results').hide();
        $('#upload-zone').show();
        $('#file-info').hide();
        $('#upload-id').val('');
        $('#file-input').val('');
        $('#progress-fill').css('width', '0%');
        $('#progress-text').text('0%');
        $('.step').removeClass('active completed');
    });
});
</script>

<style>
/* Novel to Manga specific styles */
.ai-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 1.5rem;
}

.ai-info-card h4 {
    margin: 0 0 0.75rem 0;
    font-size: 1.1rem;
}

.ai-info-card p {
    margin: 0 0 1rem 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.ai-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ai-features li {
    padding: 0.35rem 0;
    font-size: 0.85rem;
}

.ai-features li i {
    margin-right: 0.5rem;
    color: #90EE90;
}

.provider-badge {
    margin-top: 0.5rem;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-free {
    background: #10b981;
    color: white;
}

.badge-key {
    background: #f59e0b;
    color: white;
}

.content-warning {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 8px;
    padding: 0.75rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #92400e;
    font-size: 0.85rem;
}

.api-key-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
}

.custom-key-input {
    margin-top: 1rem;
}

.input-group {
    display: flex;
    gap: 0.5rem;
}

.input-group .form-input {
    flex: 1;
}

.btn-icon {
    padding: 0.5rem 0.75rem;
    background: #e2e8f0;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.btn-icon:hover {
    background: #cbd5e1;
}

.api-key-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.key-status {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    min-height: 1.5rem;
}

.key-status.success {
    color: #10b981;
}

.key-status.error {
    color: #ef4444;
}

.key-status.info {
    color: #3b82f6;
}

.stored-keys-info {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.stored-keys-label {
    font-size: 0.85rem;
    margin: 0 0 0.5rem 0;
}

.stored-keys-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.stored-keys-list li {
    background: #e0e7ff;
    color: #4338ca;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.toggle-options .toggle-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

.processing-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #1e40af;
    margin-bottom: 0.5rem;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item i {
    margin-top: 0.1rem;
}

/* Progress Panel Styles */
.tool-progress-panel {
    margin-top: 2rem;
}

.progress-bar-large {
    height: 24px;
    border-radius: 12px;
}

.progress-bar-large .progress-bar-fill {
    border-radius: 12px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.progress-display {
    text-align: center;
    padding: 2rem;
}

.progress-percentage {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin: 1rem 0 0.5rem;
}

.progress-stage {
    color: #6b7280;
    font-size: 1rem;
    margin: 0;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    border-top: 1px solid #e5e7eb;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #9ca3af;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.step i {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border-radius: 50%;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.step.active {
    color: #667eea;
}

.step.active i {
    background: #667eea;
    color: white;
    animation: pulse 1.5s infinite;
}

.step.completed {
    color: #10b981;
}

.step.completed i {
    background: #10b981;
    color: white;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
    }
}

/* Result stats */
.result-stats {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e5e7eb;
}

.result-stats span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: #6b7280;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .progress-steps {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .step {
        flex: 0 0 calc(33.333% - 1rem);
    }
    
    .input-group {
        flex-direction: column;
    }
    
    .api-key-actions {
        flex-direction: column;
    }
}
</style>
