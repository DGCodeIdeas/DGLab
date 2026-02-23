<?php
/**
 * Tool Detail Page View
 * 
 * @package DGLab\Views\Tools
 */

use DGLab\Tools\EpubFontChanger\EpubFontChanger;

$isEpubTool = $tool instanceof EpubFontChanger;
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
                    Upload File
                </h2>
                
                <div class="upload-zone" id="upload-zone">
                    <input type="file" id="file-input" class="file-input" 
                           accept="<?php echo implode(',', $tool->getSupportedTypes()); ?>"
                           data-max-size="<?php echo $tool->getMaxFileSize(); ?>">
                    <div class="upload-zone-content">
                        <i class="fas fa-cloud-upload-alt upload-zone-icon"></i>
                        <p class="upload-zone-text">
                            <strong>Click to upload</strong> or drag and drop
                        </p>
                        <p class="upload-zone-hint">
                            <?php echo implode(', ', $tool->getSupportedTypes()); ?> 
                            (Max <?php echo number_format($tool->getMaxFileSize() / 1024 / 1024, 0); ?> MB)
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
                        <i class="fas fa-file"></i>
                    </div>
                    <div class="file-info-details">
                        <p class="file-info-name" id="file-name"></p>
                        <p class="file-info-size" id="file-size"></p>
                    </div>
                    <button type="button" class="file-info-remove" id="remove-file" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Options Section -->
            <div class="tool-panel tool-options-panel">
                <h2 class="panel-title">
                    <i class="fas fa-cog"></i>
                    Options
                </h2>
                
                <form id="tool-options-form" class="tool-options-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="upload_id" id="upload-id">
                    
                    <?php foreach ($tool->getConfigSchema() as $key => $config): ?>
                        <div class="form-group" data-conditional="<?php echo htmlspecialchars(json_encode($config['conditional'] ?? null)); ?>">
                            <label for="<?php echo $key; ?>" class="form-label">
                                <?php echo htmlspecialchars($config['label']); ?>
                                <?php if ($config['required'] ?? false): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if (isset($config['description'])): ?>
                                <p class="form-help"><?php echo htmlspecialchars($config['description']); ?></p>
                            <?php endif; ?>
                            
                            <?php switch ($config['type']):
                                case 'select': ?>
                                    <select name="<?php echo $key; ?>" id="<?php echo $key; ?>" 
                                            class="form-select"
                                            <?php echo ($config['required'] ?? false) ? 'required' : ''; ?>>
                                        <?php foreach ($config['options'] as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                    <?php echo ($config['default'] ?? '') === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php break; ?>
                                
                                <?php case 'boolean': ?>
                                    <label class="form-toggle">
                                        <input type="checkbox" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                               <?php echo ($config['default'] ?? false) ? 'checked' : ''; ?>
                                               value="1">
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php break; ?>
                                
                                <?php case 'number': ?>
                                    <input type="number" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                           class="form-input"
                                           value="<?php echo $config['default'] ?? ''; ?>"
                                           min="<?php echo $config['min'] ?? ''; ?>"
                                           max="<?php echo $config['max'] ?? ''; ?>"
                                           step="<?php echo $config['step'] ?? '1'; ?>"
                                           <?php echo ($config['required'] ?? false) ? 'required' : ''; ?>>
                                <?php break; ?>
                                
                                <?php case 'file': ?>
                                    <input type="file" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                           class="form-input"
                                           accept="<?php echo $config['accept'] ?? ''; ?>">
                                <?php break; ?>
                                
                                <?php default: ?>
                                    <input type="text" name="<?php echo $key; ?>" id="<?php echo $key; ?>"
                                           class="form-input"
                                           value="<?php echo htmlspecialchars($config['default'] ?? ''); ?>"
                                           <?php echo ($config['required'] ?? false) ? 'required' : ''; ?>>
                            <?php endswitch; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="process-btn" disabled>
                        <i class="fas fa-cog"></i>
                        Process File
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Results Section -->
        <div class="tool-results" id="tool-results" style="display: none;">
            <div class="tool-panel">
                <h2 class="panel-title">
                    <i class="fas fa-check-circle"></i>
                    Processing Complete
                </h2>
                
                <div class="result-content">
                    <div class="result-info">
                        <p class="result-filename" id="result-filename"></p>
                        <p class="result-details" id="result-details"></p>
                    </div>
                    
                    <div class="result-actions">
                        <a href="#" class="btn btn-primary" id="download-btn" download>
                            <i class="fas fa-download"></i>
                            Download
                        </a>
                        <button type="button" class="btn btn-outline" id="process-another">
                            <i class="fas fa-redo"></i>
                            Process Another
                        </button>
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
                    Privacy
                </h3>
                <p>Your files are processed securely and automatically deleted after processing.</p>
            </div>
        </div>
    </div>
</section>

<script>
// Tool-specific JavaScript
window.toolConfig = {
    id: '<?php echo $tool->getId(); ?>',
    supportsChunking: <?php echo $tool->supportsChunking() ? 'true' : 'false'; ?>,
    maxFileSize: <?php echo $tool->getMaxFileSize(); ?>,
    acceptedTypes: <?php echo json_encode($tool->getSupportedTypes()); ?>,
    apiUrl: '<?php echo $base_url; ?>/tool/<?php echo $tool->getId(); ?>/process'
};
</script>
