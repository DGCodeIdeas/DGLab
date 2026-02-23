<?php
/**
 * Footer Partial
 * 
 * @package DGLab\Views\Partials
 */
?>
<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="footer-inner">
            <!-- Brand -->
            <div class="footer-brand">
                <a href="<?php echo $base_url; ?>/" class="footer-logo">
                    <i class="fas fa-cube"></i>
                    <span><?php echo APP_NAME; ?></span>
                </a>
                <p class="footer-description">
                    A collection of web tools for file processing and conversion. 
                    Built with modern web technologies.
                </p>
            </div>
            
            <!-- Links -->
            <div class="footer-links">
                <div class="footer-links-group">
                    <h4 class="footer-links-title">Quick Links</h4>
                    <ul class="footer-links-list">
                        <li><a href="<?php echo $base_url; ?>/">Home</a></li>
                        <li><a href="<?php echo $base_url; ?>/tools">Tools</a></li>
                        <li><a href="<?php echo $base_url; ?>/docs">Documentation</a></li>
                        <li><a href="<?php echo $base_url; ?>/about">About</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-group">
                    <h4 class="footer-links-title">Categories</h4>
                    <ul class="footer-links-list">
                        <li><a href="<?php echo $base_url; ?>/tools/category/E-Books">E-Books</a></li>
                        <li><a href="<?php echo $base_url; ?>/tools/category/Documents">Documents</a></li>
                        <li><a href="<?php echo $base_url; ?>/tools/category/Images">Images</a></li>
                        <li><a href="<?php echo $base_url; ?>/tools">All Categories</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-group">
                    <h4 class="footer-links-title">Resources</h4>
                    <ul class="footer-links-list">
                        <li><a href="<?php echo $base_url; ?>/docs/api">API Docs</a></li>
                        <li><a href="<?php echo $base_url; ?>/docs/development">Development</a></li>
                        <li><a href="<?php echo $base_url; ?>/manifest.json">Manifest</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Bottom Bar -->
        <div class="footer-bottom">
            <p class="footer-copyright">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
            </p>
            <p class="footer-version">
                Version <?php echo APP_VERSION; ?>
            </p>
        </div>
    </div>
</footer>
