<?php
/**
 * Header Partial
 * 
 * @package DGLab\Views\Partials
 */
?>
<header class="header" role="banner">
    <div class="container">
        <div class="header-inner">
            <!-- Logo -->
            <a href="<?php echo $base_url; ?>/" class="logo" aria-label="<?php echo APP_NAME; ?> Home">
                <span class="logo-icon">
                    <i class="fas fa-cube"></i>
                </span>
                <span class="logo-text"><?php echo APP_NAME; ?></span>
            </a>
            
            <!-- Navigation -->
            <nav class="nav" role="navigation" aria-label="Main navigation">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>/" class="nav-link<?php echo $active_nav === 'home' ? ' active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>/tools" class="nav-link<?php echo $active_nav === 'tools' ? ' active' : ''; ?>">
                            <i class="fas fa-tools"></i>
                            <span>Tools</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>/docs" class="nav-link<?php echo $active_nav === 'docs' ? ' active' : ''; ?>">
                            <i class="fas fa-book"></i>
                            <span>Docs</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo $base_url; ?>/about" class="nav-link<?php echo $active_nav === 'about' ? ' active' : ''; ?>">
                            <i class="fas fa-info-circle"></i>
                            <span>About</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Mobile Menu Toggle -->
            <button class="menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-menu">
                <span class="menu-toggle-bar"></span>
                <span class="menu-toggle-bar"></span>
                <span class="menu-toggle-bar"></span>
            </button>
        </div>
    </div>
</header>
