# DGLab PWA - Comprehensive Documentation

## Table of Contents

1. [Introduction](#introduction)
2. [Architecture Overview](#architecture-overview)
3. [Installation & Setup](#installation--setup)
4. [Core Framework](#core-framework)
5. [MVC Components](#mvc-components)
6. [Tool System](#tool-system)
7. [File Upload System](#file-upload-system)
8. [Asset Management](#asset-management)
9. [PWA Features](#pwa-features)
10. [API Reference](#api-reference)
11. [Creating Custom Tools](#creating-custom-tools)
12. [Security Considerations](#security-considerations)
13. [Performance Optimization](#performance-optimization)
14. [Troubleshooting](#troubleshooting)
15. [Deployment Guide](#deployment-guide)

---

## Introduction

DGLab PWA is a modern, extensible web application platform built on PHP 8+ with MySQL support. It provides a Progressive Web App (PWA) experience with offline capabilities, responsive design, and a powerful tool system for file processing and conversion.

### Key Features

- **MVC Architecture**: Clean separation of concerns with Model-View-Controller pattern
- **Extensible Tool System**: Easy-to-extend plugin architecture for adding new tools
- **Chunked File Uploads**: Handle large files efficiently with resumable uploads
- **PWA Capabilities**: Offline support, installable app, service worker caching
- **Asset Bundling**: Runtime SCSS compilation and JavaScript bundling without Node.js
- **Responsive Design**: Mobile-first approach with modern UI/UX
- **RESTful API**: Complete API for external integrations
- **EPUB 3 Support**: First-class EPUB e-book processing with validation

### Technology Stack

- **Backend**: PHP 8.0+, MySQL 5.7+/MariaDB 10.3+
- **Frontend**: jQuery 3.7, SCSS, Font Awesome 6
- **Architecture**: MVC with OOP Framework
- **Storage**: File system with chunked upload support
- **Caching**: Multi-level caching (view, asset, query)

---

## Architecture Overview

### Directory Structure

```
dglab-pwa/
├── app/                    # Application code
│   ├── Config/            # Configuration files
│   ├── Controllers/       # MVC Controllers
│   ├── Core/              # Core framework classes
│   ├── Models/            # MVC Models
│   ├── Tools/             # Tool implementations
│   │   ├── Interfaces/    # Tool interface definitions
│   │   └── EpubFontChanger/  # EPUB Font Changer tool
│   └── Views/             # View templates
│       ├── layouts/       # Layout templates
│       ├── partials/      # Partial templates
│       └── tools/         # Tool-specific views
├── assets/                # Static assets
│   ├── scss/             # SCSS stylesheets
│   ├── js/               # JavaScript files
│   └── fonts/            # Font files
├── config/               # Configuration
├── database/             # Database migrations
├── docs/                 # Documentation
├── public/               # Web root
│   ├── index.php         # Front controller
│   ├── .htaccess         # Apache rewrite rules
│   ├── manifest.json     # PWA manifest
│   └── sw.js             # Service worker
├── storage/              # Writable storage
│   ├── cache/            # Cached files
│   ├── chunks/           # Upload chunks
│   ├── exports/          # Processed file exports
│   ├── temp/             # Temporary files
│   └── uploads/          # Uploaded files
└── config.php            # Main configuration
```

### Request Lifecycle

1. **Request Entry**: All requests go through `public/index.php`
2. **Bootstrap**: Constants defined, autoloader registered, config loaded
3. **Routing**: Router parses URL and matches to controller/action
4. **Middleware**: Any registered middleware executes
5. **Controller**: Controller handles the request logic
6. **Model**: Models interact with database if needed
7. **View**: Views render the response
8. **Response**: Output sent to client

---

## Installation & Setup

### Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite
- 100MB+ disk space
- PHP Extensions: PDO, PDO_MySQL, Zip, GD, mbstring, json

### Step-by-Step Installation

#### 1. Upload Files

Upload all files to your web server. For InfinityFree:

1. Log in to your InfinityFree control panel
2. Go to File Manager or use FTP
3. Upload all files to the `htdocs` directory
4. Ensure `public/` contents are in the web root

#### 2. Configure Database

Create a MySQL database through your hosting control panel:

```sql
CREATE DATABASE dglab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3. Copy Configuration

```bash
cp config/config.example.php config/config.php
```

Edit `config/config.php` with your database credentials:

```php
'database' => [
    'enabled'  => true,
    'driver'   => 'mysql',
    'host'     => 'sqlXXX.epizy.com',  // InfinityFree host
    'port'     => 3306,
    'database' => 'epiz_XXX_dglab',     // Your database name
    'username' => 'epiz_XXX',           // Your username
    'password' => 'your_password',      // Your password
    'charset'  => 'utf8mb4',
],
```

#### 4. Set Permissions

Ensure these directories are writable (755 or 777):

```bash
chmod -R 755 storage/
chmod -R 755 cache/
```

#### 5. Configure .htaccess

The included `.htaccess` file should work for most setups. For InfinityFree, no changes are needed.

#### 6. Test Installation

Visit your domain. You should see the DGLab PWA homepage.

---

## Core Framework

### Router

The Router class handles URL routing and request dispatching.

#### Basic Routes

```php
// In app/Config/routes.php
$router->get('/', 'HomeController@index', 'home');
$router->post('/api/data', 'ApiController@store');
$router->get('/user/{id}', 'UserController@show');
```

#### Route Parameters

```php
// Required parameter
$router->get('/user/{id}', 'UserController@show');

// Optional parameter with type
$router->get('/post/{id:int}', 'PostController@show');

// Multiple parameters
$router->get('/category/{slug}/post/{id}', 'PostController@byCategory');
```

#### Route Groups

```php
$router->group(['prefix' => 'admin', 'middleware' => 'AuthMiddleware'], function($router) {
    $router->get('/dashboard', 'AdminController@dashboard');
    $router->get('/users', 'AdminController@users');
});
```

### Database

The Database class provides PDO wrapper with query building.

#### Basic Queries

```php
$db = Database::getInstance();

// Fetch all
$users = $db->fetchAll("SELECT * FROM users WHERE active = ?", [1]);

// Fetch one
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

// Insert
$userId = $db->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$db->update('users', ['name' => 'Jane Doe'], "id = ?", [$userId]);

// Delete
$db->delete('users', "id = ?", [$userId]);
```

#### Query Builder

```php
// Select
$users = $db->select('users', ['id', 'name', 'email'])
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Insert
$db->insertInto('users')
    ->values(['name' => 'John', 'email' => 'john@example.com'])
    ->insert();

// Update
$db->select('users')
    ->where('id', $id)
    ->set(['name' => 'Jane'])
    ->update();
```

#### Transactions

```php
$db->transaction(function($db) {
    $db->insert('orders', ['total' => 100]);
    $db->insert('order_items', ['order_id' => $db->lastInsertId()]);
});
```

---

## MVC Components

### Controllers

Controllers extend the base `Controller` class and handle HTTP requests.

```php
namespace DGLab\Controllers;

use DGLab\Core\Controller;

class MyController extends Controller
{
    public function index(): void
    {
        // Get input
        $name = $this->input('name', 'default');
        
        // Validate
        $errors = $this->validate(['email' => $email], [
            'email' => 'required|email'
        ]);
        
        // Render view
        $this->render('my/view', [
            'title' => 'My Page',
            'data' => $data
        ]);
        
        // Or return JSON
        $this->json(['success' => true]);
    }
}
```

### Views

Views use PHP templates with helper functions.

```php
<!-- app/Views/my/view.php -->
<div class="container">
    <h1><?php echo htmlspecialchars($title); ?></h1>
    
    <?php foreach ($items as $item): ?>
        <p><?php echo $this->e($item->name); ?></p>
    <?php endforeach; ?>
</div>
```

### Models

Models extend the base `Model` class for Active Record-style ORM.

```php
namespace DGLab\Models;

use DGLab\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password'];
    protected bool $timestamps = true;
    
    // Custom getter
    protected function getNameAttribute($value)
    {
        return ucfirst($value);
    }
    
    // Relationship
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

---

## Tool System

The Tool System is the heart of DGLab PWA. It provides a standardized way to add new file processing tools.

### Tool Interface

All tools must implement `ToolInterface`:

```php
namespace DGLab\Tools\Interfaces;

interface ToolInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getIcon(): string;
    public function getCategory(): string;
    public function getSupportedTypes(): array;
    public function getMaxFileSize(): int;
    public function supportsChunking(): bool;
    public function process(string $inputPath, array $options = []): array;
    public function validate(string $inputPath, array $options = []): array;
    public function getConfigSchema(): array;
    public function getDefaultConfig(): array;
    public function getProgress(string $jobId): array;
    public function cleanup(?string $jobId = null): void;
}
```

### Tool Registry

Tools are automatically discovered and registered:

```php
use DGLab\Tools\ToolRegistry;

$registry = ToolRegistry::getInstance();

// Get all tools
$tools = $registry->getAll();

// Get specific tool
$tool = $registry->get('epub-font-changer');

// Get by category
$ebookTools = $registry->getByCategory('E-Books');
```

---

## File Upload System

### Chunked Uploads

For files larger than 5MB, the system automatically uses chunked uploads:

```javascript
// Client-side
DGLab.FileUpload.handleFile(file);
DGLab.FileUpload.upload((progress) => {
    console.log('Upload: ' + progress + '%');
}).then((data) => {
    console.log('Upload complete:', data);
});
```

### Server-side Handling

```php
// Initialize upload
$uploader = new ChunkedUpload();
$result = $uploader->initialize($filename, $totalSize, $mimeType);

// Upload chunk
$progress = $uploader->uploadChunk($uploadId, $chunkIndex, $chunkData);

// Check progress
$progress = $uploader->getProgress($uploadId);
```

---

## Asset Management

### SCSS Compilation

The AssetBundler compiles SCSS at runtime:

```php
$bundler = new AssetBundler();
$cssUrl = $bundler->compileScss([
    'base/variables.scss',
    'components/buttons.scss',
    'app.scss'
], 'app.css');
```

### JavaScript Bundling

```php
$jsUrl = $bundler->bundleJs([
    'core/utils.js',
    'components/upload.js',
    'app.js'
], 'app.js');
```

### Caching

Compiled assets are cached and only recompiled when source files change.

---

## PWA Features

### Manifest

The PWA manifest is auto-generated from configuration:

```php
// In config/config.php
'pwa' => [
    'name' => 'DGLab PWA',
    'short_name' => 'DGLab',
    'theme_color' => '#4f46e5',
    'background_color' => '#ffffff',
    'display' => 'standalone',
    'icons' => [...]
]
```

### Service Worker

The service worker provides:
- Static asset caching
- Offline page support
- Cache-first strategy for assets
- Network-first for API calls

### Installation

Users can install DGLab PWA:
- Chrome: Click install icon in address bar
- Safari: Add to Home Screen
- Firefox: Install from menu

---

## API Reference

### Status Endpoint

```http
GET /api/v1/status
```

Response:
```json
{
    "success": true,
    "data": {
        "name": "DGLab PWA",
        "version": "1.0.0",
        "tools_count": 5
    }
}
```

### List Tools

```http
GET /api/v1/tools
```

### Tool Detail

```http
GET /api/v1/tools/{id}
```

### Process File

```http
POST /api/v1/process/{toolId}
Content-Type: multipart/form-data

file: <binary>
option1: value1
```

### Validate File

```http
POST /api/v1/validate/{toolId}
Content-Type: multipart/form-data

file: <binary>
```

---

## Creating Custom Tools

### Step 1: Create Tool Directory

```bash
mkdir app/Tools/MyTool
```

### Step 2: Implement Tool Class

```php
<?php
namespace DGLab\Tools\MyTool;

use DGLab\Tools\Interfaces\ToolInterface;

class MyTool implements ToolInterface
{
    public function getId(): string
    {
        return 'my-tool';
    }
    
    public function getName(): string
    {
        return 'My Tool';
    }
    
    public function getDescription(): string
    {
        return 'Description of my tool';
    }
    
    public function getIcon(): string
    {
        return 'fa-magic';
    }
    
    public function getCategory(): string
    {
        return 'Utilities';
    }
    
    public function getSupportedTypes(): array
    {
        return ['text/plain', '.txt'];
    }
    
    public function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB
    }
    
    public function supportsChunking(): bool
    {
        return true;
    }
    
    public function getConfigSchema(): array
    {
        return [
            'option1' => [
                'type' => 'string',
                'label' => 'Option 1',
                'required' => true,
                'default' => 'default value'
            ]
        ];
    }
    
    public function getDefaultConfig(): array
    {
        return ['option1' => 'default value'];
    }
    
    public function validate(string $inputPath, array $options = []): array
    {
        $errors = [];
        
        if (!file_exists($inputPath)) {
            $errors[] = 'File not found';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function process(string $inputPath, array $options = []): array
    {
        try {
            // Process file
            $outputPath = $this->doProcessing($inputPath, $options);
            
            return [
                'success' => true,
                'output_path' => $outputPath,
                'output_filename' => basename($outputPath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getProgress(string $jobId): array
    {
        return ['progress' => 100, 'status' => 'completed'];
    }
    
    public function cleanup(?string $jobId = null): void
    {
        // Clean up temporary files
    }
}
```

### Step 3: Tool Auto-Discovery

The tool will be automatically discovered and registered by the ToolRegistry.

---

## Security Considerations

### CSRF Protection

All forms include CSRF tokens:

```php
<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
```

Validate in controllers:

```php
$this->requireCsrfToken();
```

### Input Sanitization

All user input is automatically sanitized:

```php
$name = $this->input('name'); // Already escaped
```

### File Upload Security

- File type validation
- File size limits
- Secure filename generation
- Upload directory outside web root

### SQL Injection Prevention

All database queries use prepared statements:

```php
$db->query("SELECT * FROM users WHERE id = ?", [$id]);
```

---

## Performance Optimization

### Caching

Multiple caching layers:

1. **View Caching**: Compiled templates cached
2. **Asset Caching**: Compiled CSS/JS cached
3. **Query Caching**: Database query results cached

### Chunked Uploads

Large files are split into chunks to:
- Avoid PHP upload limits
- Enable resumable uploads
- Reduce memory usage

### Lazy Loading

Tools are loaded on-demand through the registry.

---

## Troubleshooting

### Common Issues

#### 404 Errors

Check `.htaccess` is present and mod_rewrite is enabled.

#### Database Connection Failed

Verify database credentials in `config/config.php`.

#### Uploads Not Working

Ensure `storage/uploads` and `storage/chunks` are writable.

#### CSS/JS Not Loading

Clear asset cache: Delete files in `storage/cache/assets/`.

### Debug Mode

Enable debug mode in development:

```php
'app' => [
    'debug' => true,
    'env' => 'development'
]
```

### Logs

Check PHP error logs for detailed error messages.

---

## Deployment Guide

### InfinityFree Deployment

1. **Sign Up**: Create account at infinityfree.net
2. **Create Site**: Set up a new hosting account
3. **Upload Files**: Use File Manager or FTP
4. **Create Database**: Via MySQL Databases section
5. **Configure**: Edit config.php with database details
6. **Test**: Visit your subdomain

### General Deployment

1. **Upload**: Copy all files to web server
2. **Configure**: Set up database and config.php
3. **Permissions**: Set 755 on storage directories
4. **Web Server**: Configure rewrite rules
5. **SSL**: Enable HTTPS for PWA features

### Post-Deployment

1. Clear all caches
2. Test all tools
3. Verify PWA installation works
4. Monitor error logs

---

## License

MIT License - See LICENSE file for details.

## Support

For support and questions:
- Documentation: /docs
- API Reference: /docs/api
- Issues: GitHub Issues

---

*DGLab PWA v1.0.0 - Built with modern web technologies*
