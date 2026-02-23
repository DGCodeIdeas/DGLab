<?php
/**
 * Home Page View
 * 
 * @package DGLab\Views\Home
 */
?>
<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                Powerful Web Tools for
                <span class="text-gradient">File Processing</span>
            </h1>
            <p class="hero-description">
                Transform, convert, and optimize your files with our collection of 
                browser-based tools. No installation required - works on any device.
            </p>
            <div class="hero-actions">
                <a href="<?php echo $base_url; ?>/tools" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket"></i>
                    Get Started
                </a>
                <a href="<?php echo $base_url; ?>/docs" class="btn btn-outline btn-lg">
                    <i class="fas fa-book"></i>
                    Documentation
                </a>
            </div>
        </div>
        
        <!-- Hero Visual -->
        <div class="hero-visual">
            <div class="hero-cards">
                <div class="hero-card hero-card-1">
                    <i class="fas fa-file-alt"></i>
                    <span>EPUB</span>
                </div>
                <div class="hero-card hero-card-2">
                    <i class="fas fa-font"></i>
                    <span>Fonts</span>
                </div>
                <div class="hero-card hero-card-3">
                    <i class="fas fa-magic"></i>
                    <span>Convert</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <div class="container">
        <h2 class="section-title">Why Choose <?php echo APP_NAME; ?>?</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="feature-title">Fast Processing</h3>
                <p class="feature-description">
                    Optimized algorithms and chunked uploads ensure your files are 
                    processed quickly and efficiently.
                </p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-title">Privacy First</h3>
                <p class="feature-description">
                    Your files are processed securely and automatically deleted after 
                    processing. We never store your data.
                </p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title">Works Everywhere</h3>
                <p class="feature-description">
                    Progressive Web App works on desktop, tablet, and mobile devices. 
                    Install for offline access.
                </p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-code"></i>
                </div>
                <h3 class="feature-title">Developer Friendly</h3>
                <p class="feature-description">
                    RESTful API for integrating tools into your own applications. 
                    Comprehensive documentation included.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Tools Section -->
<section class="tools-preview">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Available Tools</h2>
            <a href="<?php echo $base_url; ?>/tools" class="btn btn-outline">
                View All
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="tools-grid">
            <?php foreach ($featured as $id => $tool): ?>
                <a href="<?php echo $base_url; ?>/tool/<?php echo $id; ?>" class="tool-card">
                    <div class="tool-card-icon">
                        <i class="fas <?php echo $tool->getIcon(); ?>"></i>
                    </div>
                    <h3 class="tool-card-title"><?php echo htmlspecialchars($tool->getName()); ?></h3>
                    <p class="tool-card-description">
                        <?php echo htmlspecialchars($tool->getDescription()); ?>
                    </p>
                    <span class="tool-card-category">
                        <?php echo htmlspecialchars($tool->getCategory()); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta">
    <div class="container">
        <div class="cta-content">
            <h2 class="cta-title">Ready to Get Started?</h2>
            <p class="cta-description">
                Explore our collection of tools and start processing your files today.
            </p>
            <a href="<?php echo $base_url; ?>/tools" class="btn btn-primary btn-lg">
                <i class="fas fa-tools"></i>
                Browse All Tools
            </a>
        </div>
    </div>
</section>
