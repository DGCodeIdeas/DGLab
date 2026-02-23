<?php
/**
 * Tools Listing Page View
 * 
 * @package DGLab\Views\Tools
 */
?>
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="page-title">All Tools</h1>
        <p class="page-description">
            Browse our collection of web-based tools for file processing and conversion.
        </p>
    </div>
</section>

<!-- Tools by Category -->
<section class="tools-listing">
    <div class="container">
        <?php foreach ($categories as $category => $catTools): ?>
            <div class="category-section">
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                
                <div class="tools-grid">
                    <?php foreach ($catTools as $id => $tool): ?>
                        <a href="<?php echo $base_url; ?>/tool/<?php echo $id; ?>" class="tool-card">
                            <div class="tool-card-icon">
                                <i class="fas <?php echo $tool->getIcon(); ?>"></i>
                            </div>
                            <h3 class="tool-card-title"><?php echo htmlspecialchars($tool->getName()); ?></h3>
                            <p class="tool-card-description">
                                <?php echo htmlspecialchars($tool->getDescription()); ?>
                            </p>
                            <div class="tool-card-meta">
                                <span class="tool-card-type">
                                    <?php echo implode(', ', array_slice($tool->getSupportedTypes(), 0, 2)); ?>
                                </span>
                                <?php if ($tool->supportsChunking()): ?>
                                    <span class="tool-card-badge" title="Supports large files">
                                        <i class="fas fa-bolt"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
.page-header {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    color: #fff;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1rem;
}

.page-description {
    font-size: 1.125rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
}

.tools-listing {
    padding: 4rem 0;
}

.category-section {
    margin-bottom: 4rem;
}

.category-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: var(--color-gray-800);
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-gray-200);
}

.tool-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 1rem;
}

.tool-card-type {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.tool-card-badge {
    color: var(--color-warning);
    font-size: 0.875rem;
}
</style>
