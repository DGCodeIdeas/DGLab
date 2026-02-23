<?php
/**
 * Documentation Page View
 * 
 * @package DGLab\Views\Home
 */
?>
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="page-title">Documentation</h1>
        <p class="page-description">
            Learn how to use our platform and integrate with our API.
        </p>
    </div>
</section>

<!-- Documentation Content -->
<section class="docs-content">
    <div class="container">
        <div class="docs-grid">
            <!-- Sidebar Navigation -->
            <aside class="docs-sidebar">
                <nav class="docs-nav">
                    <h3>Getting Started</h3>
                    <ul>
                        <li><a href="#introduction">Introduction</a></li>
                        <li><a href="#quickstart">Quick Start</a></li>
                        <li><a href="#installation">Installation</a></li>
                    </ul>
                    
                    <h3>Using Tools</h3>
                    <ul>
                        <li><a href="#uploading">Uploading Files</a></li>
                        <li><a href="#processing">Processing Files</a></li>
                        <li><a href="#downloading">Downloading Results</a></li>
                    </ul>
                    
                    <h3>API Reference</h3>
                    <ul>
                        <li><a href="#api-overview">API Overview</a></li>
                        <li><a href="#authentication">Authentication</a></li>
                        <li><a href="#endpoints">Endpoints</a></li>
                    </ul>
                    
                    <h3>Development</h3>
                    <ul>
                        <li><a href="#architecture">Architecture</a></li>
                        <li><a href="#creating-tools">Creating Tools</a></li>
                        <li><a href="#contributing">Contributing</a></li>
                    </ul>
                </nav>
            </aside>
            
            <!-- Main Content -->
            <main class="docs-main">
                <article id="introduction">
                    <h2>Introduction</h2>
                    <p>
                        <?php echo APP_NAME; ?> is a web-based platform for file processing and conversion. 
                        It provides a collection of tools that work directly in your browser, 
                        with no software installation required.
                    </p>
                    <p>
                        Our platform is built as a Progressive Web App (PWA), which means you can 
                        install it on your device and use it offline. It works on desktop computers, 
                        tablets, and mobile devices.
                    </p>
                </article>
                
                <article id="quickstart">
                    <h2>Quick Start</h2>
                    <ol class="steps">
                        <li>
                            <strong>Choose a Tool</strong>
                            <p>Browse our <a href="/tools">tools collection</a> and select the one you need.</p>
                        </li>
                        <li>
                            <strong>Upload Your File</strong>
                            <p>Click the upload area or drag and drop your file. We support files up to 100MB.</p>
                        </li>
                        <li>
                            <strong>Configure Options</strong>
                            <p>Select your desired settings and options for the processing.</p>
                        </li>
                        <li>
                            <strong>Process & Download</strong>
                            <p>Click "Process File" and download your result when complete.</p>
                        </li>
                    </ol>
                </article>
                
                <article id="installation">
                    <h2>Installation</h2>
                    <p>
                        To install <?php echo APP_NAME; ?> as a Progressive Web App:
                    </p>
                    <div class="install-methods">
                        <div class="install-method">
                            <h4><i class="fab fa-chrome"></i> Chrome / Edge</h4>
                            <p>Click the install icon (➕) in the address bar, then click "Install".</p>
                        </div>
                        <div class="install-method">
                            <h4><i class="fab fa-safari"></i> Safari (iOS)</h4>
                            <p>Tap Share → "Add to Home Screen" → "Add".</p>
                        </div>
                        <div class="install-method">
                            <h4><i class="fab fa-firefox"></i> Firefox</h4>
                            <p>Click the menu (☰) → "Install".</p>
                        </div>
                    </div>
                </article>
                
                <article id="api-overview">
                    <h2>API Overview</h2>
                    <p>
                        Our REST API allows you to integrate our tools into your own applications. 
                        All API endpoints return JSON responses.
                    </p>
                    
                    <h3>Base URL</h3>
                    <code class="code-block"><?php echo $base_url; ?>/api/v1</code>
                    
                    <h3>Response Format</h3>
                    <pre class="code-block"><code>{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}</code></pre>
                </article>
                
                <article id="endpoints">
                    <h2>API Endpoints</h2>
                    
                    <div class="endpoint">
                        <h4><span class="method get">GET</span> /status</h4>
                        <p>Get platform status and information.</p>
                    </div>
                    
                    <div class="endpoint">
                        <h4><span class="method get">GET</span> /tools</h4>
                        <p>List all available tools.</p>
                    </div>
                    
                    <div class="endpoint">
                        <h4><span class="method get">GET</span> /tools/{id}</h4>
                        <p>Get detailed information about a specific tool.</p>
                    </div>
                    
                    <div class="endpoint">
                        <h4><span class="method post">POST</span> /process/{toolId}</h4>
                        <p>Process a file with the specified tool.</p>
                        <p><strong>Parameters:</strong></p>
                        <ul>
                            <li><code>file</code> - The file to process (required)</li>
                            <li>Tool-specific options</li>
                        </ul>
                    </div>
                </article>
                
                <article id="creating-tools">
                    <h2>Creating Custom Tools</h2>
                    <p>
                        You can extend <?php echo APP_NAME; ?> by creating custom tools. Tools must implement 
                        the <code>ToolInterface</code> and follow our conventions.
                    </p>
                    
                    <h3>Basic Tool Structure</h3>
                    <pre class="code-block"><code>namespace DGLab\Tools\MyTool;

use DGLab\Tools\Interfaces\ToolInterface;

class MyTool implements ToolInterface
{
    public function getId(): string
    {
        return 'my-tool';
    }
    
    public function process(string $inputPath, array $options = []): array
    {
        // Your processing logic
        return [
            'success' => true,
            'output_path' => $outputPath
        ];
    }
    
    // ... other required methods
}</code></pre>
                    
                    <p>
                        See the <a href="/docs/development">Development Guide</a> for complete documentation 
                        on creating custom tools.
                    </p>
                </article>
            </main>
        </div>
    </div>
</section>

<style>
.docs-content {
    padding: 4rem 0;
}

.docs-grid {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 4rem;
}

.docs-sidebar {
    position: sticky;
    top: 6rem;
    height: fit-content;
}

.docs-nav h3 {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-gray-500);
    margin: 1.5rem 0 0.75rem;
}

.docs-nav h3:first-child {
    margin-top: 0;
}

.docs-nav ul {
    list-style: none;
}

.docs-nav li {
    margin-bottom: 0.25rem;
}

.docs-nav a {
    display: block;
    padding: 0.5rem 0.75rem;
    color: var(--color-gray-600);
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.docs-nav a:hover {
    background: var(--color-gray-100);
    color: var(--color-primary);
}

.docs-main article {
    margin-bottom: 4rem;
}

.docs-main h2 {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--color-gray-900);
}

.docs-main h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 2rem 0 1rem;
    color: var(--color-gray-800);
}

.docs-main h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 1.5rem 0 0.75rem;
}

.docs-main p {
    color: var(--color-gray-600);
    line-height: 1.75;
    margin-bottom: 1rem;
}

.steps {
    list-style: none;
    counter-reset: step;
}

.steps li {
    position: relative;
    padding-left: 3rem;
    margin-bottom: 1.5rem;
}

.steps li::before {
    counter-increment: step;
    content: counter(step);
    position: absolute;
    left: 0;
    top: 0;
    width: 2rem;
    height: 2rem;
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.steps strong {
    display: block;
    font-size: 1.125rem;
    color: var(--color-gray-900);
    margin-bottom: 0.25rem;
}

.steps p {
    margin-bottom: 0;
}

.install-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.install-method {
    padding: 1.5rem;
    background: var(--color-gray-50);
    border-radius: 0.75rem;
}

.install-method h4 {
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.install-method p {
    margin: 0;
    font-size: 0.875rem;
}

.code-block {
    display: block;
    background: var(--color-gray-900);
    color: var(--color-gray-100);
    padding: 1.25rem;
    border-radius: 0.75rem;
    font-family: var(--font-family-mono);
    font-size: 0.875rem;
    overflow-x: auto;
    margin: 1rem 0;
}

.endpoint {
    padding: 1.5rem;
    background: var(--color-gray-50);
    border-radius: 0.75rem;
    margin-bottom: 1rem;
}

.endpoint h4 {
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.method {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.method.get {
    background: var(--color-success);
    color: white;
}

.method.post {
    background: var(--color-primary);
    color: white;
}

@media (max-width: 768px) {
    .docs-grid {
        grid-template-columns: 1fr;
    }
    
    .docs-sidebar {
        position: static;
        order: 2;
    }
}
</style>
