<?php
/**
 * About Page View
 * 
 * @package DGLab\Views\Home
 */
?>
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="page-title">About <?php echo APP_NAME; ?></h1>
        <p class="page-description">
            Learn more about our mission, features, and the technology behind our platform.
        </p>
    </div>
</section>

<!-- About Content -->
<section class="about-content">
    <div class="container">
        <div class="about-grid">
            <div class="about-main">
                <h2>Our Mission</h2>
                <p>
                    <?php echo APP_NAME; ?> was created with a simple mission: to provide powerful, 
                    easy-to-use web tools for file processing and conversion. We believe that 
                    everyone should have access to professional-grade tools without needing 
                    technical expertise or expensive software.
                </p>
                
                <h2>What Makes Us Different</h2>
                <ul class="feature-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <strong>Privacy First</strong> - Your files are processed securely and 
                        automatically deleted after processing. We never store your data.
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <strong>Works Everywhere</strong> - Our Progressive Web App works on any 
                        device with a modern web browser. No installation required.
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <strong>Fast & Efficient</strong> - Optimized algorithms and chunked uploads 
                        ensure quick processing of even large files.
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <strong>Developer Friendly</strong> - Complete REST API for integrating our 
                        tools into your own applications.
                    </li>
                </ul>
                
                <h2>Technology Stack</h2>
                <p>
                    Our platform is built with modern, reliable technologies:
                </p>
                <div class="tech-stack">
                    <div class="tech-item">
                        <i class="fab fa-php"></i>
                        <span>PHP 8+</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-database"></i>
                        <span>MySQL</span>
                    </div>
                    <div class="tech-item">
                        <i class="fab fa-js"></i>
                        <span>jQuery</span>
                    </div>
                    <div class="tech-item">
                        <i class="fab fa-sass"></i>
                        <span>SCSS</span>
                    </div>
                </div>
            </div>
            
            <div class="about-sidebar">
                <div class="info-card">
                    <h3>Platform Stats</h3>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($tools ?? []); ?></span>
                        <span class="stat-label">Available Tools</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">100MB</span>
                        <span class="stat-label">Max File Size</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label">Availability</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>Supported Formats</h3>
                    <ul class="format-list">
                        <li><i class="fas fa-book"></i> EPUB E-books</li>
                        <li><i class="fas fa-file-pdf"></i> PDF Documents</li>
                        <li><i class="fas fa-image"></i> Images</li>
                        <li><i class="fas fa-file-alt"></i> Text Files</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.about-content {
    padding: 4rem 0;
}

.about-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 4rem;
}

.about-main h2 {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 2rem 0 1rem;
    color: var(--color-gray-900);
}

.about-main h2:first-child {
    margin-top: 0;
}

.about-main p {
    color: var(--color-gray-600);
    line-height: 1.75;
    margin-bottom: 1rem;
}

.feature-list {
    list-style: none;
    margin: 1.5rem 0;
}

.feature-list li {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: var(--color-gray-50);
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
}

.feature-list i {
    color: var(--color-success);
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.tech-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1.5rem;
}

.tech-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--color-gray-50);
    border-radius: 0.5rem;
    font-weight: 500;
}

.tech-item i {
    font-size: 1.25rem;
    color: var(--color-primary);
}

.about-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-card {
    background: var(--color-white);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
}

.info-card h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--color-gray-900);
}

.stat-item {
    display: flex;
    flex-direction: column;
    padding: 1rem 0;
    border-bottom: 1px solid var(--color-gray-200);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-primary);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--color-gray-500);
}

.format-list {
    list-style: none;
}

.format-list li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0;
    color: var(--color-gray-600);
}

.format-list i {
    color: var(--color-primary);
    width: 1.25rem;
}

@media (max-width: 768px) {
    .about-grid {
        grid-template-columns: 1fr;
    }
}
</style>
