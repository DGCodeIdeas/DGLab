# Adding New Services to DGLab

This guide explains how to create and register new services in the DGLab platform.

## Overview

Services in DGLab are self-contained processing units that:
- Accept input through a defined schema
- Process data according to service logic
- Return results in a consistent format
- Support chunked uploads for large files

## Quick Start

1. Create a new service class implementing `ServiceInterface`
2. Register the service in `config/services.php`
3. Create frontend templates (optional)

## Service Interface

All services must implement `DGLab\Services\Contracts\ServiceInterface`:

```php
<?php
namespace DGLab\Services\MyService;

use DGLab\Services\BaseService;

class MyService extends BaseService
{
    public function getId(): string
    {
        return 'my-service';
    }
    
    public function getName(): string
    {
        return 'My Service';
    }
    
    public function getDescription(): string
    {
        return 'Description of what my service does';
    }
    
    public function getIcon(): string
    {
        return 'fa-icon-name'; // Font Awesome icon class
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'Input file',
                ],
                'option' => [
                    'type' => 'string',
                    'enum' => ['value1', 'value2'],
                    'default' => 'value1',
                ],
            ],
            'required' => ['file'],
        ];
    }
    
    public function validate(array $input): array
    {
        return $this->validateAgainstSchema($input, $this->getInputSchema());
    }
    
    public function process(array $input, ?callable $progressCallback = null): array
    {
        // Your processing logic here
        
        return [
            'success' => true,
            'download_url' => '/api/download/filename.ext',
            'filename' => 'output.ext',
            'file_size' => 12345,
        ];
    }
    
    public function supportsChunking(): bool
    {
        return true;
    }
    
    public function estimateTime(array $input): int
    {
        // Return estimated processing time in seconds
        return 30;
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
}
```

## Chunked Upload Support

For services that handle large files, implement `ChunkedServiceInterface`:

```php
<?php
namespace DGLab\Services\MyService;

use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ChunkedServiceInterface;

class MyService extends BaseService implements ChunkedServiceInterface
{
    // ... other methods ...
    
    public function initializeChunkedProcess(array $metadata): array
    {
        $session = UploadChunk::createSession(
            $this->getId(),
            $metadata['filename'],
            $metadata['file_size'],
            $this->getChunkSize(),
            $metadata
        );
        
        return [
            'session_id' => $session->session_id,
            'chunk_size' => $this->getChunkSize(),
            'total_chunks' => $session->total_chunks,
            'expires_at' => $session->expires_at,
        ];
    }
    
    public function processChunk(string $sessionId, int $chunkIndex, string $chunkData): array
    {
        $session = UploadChunk::findBySessionId($sessionId);
        
        // Save chunk to temporary location
        $chunkPath = $this->saveChunk($sessionId, $chunkIndex, $chunkData);
        
        // Record chunk in session
        $session->recordChunk($chunkIndex, $chunkPath);
        
        return [
            'success' => true,
            'progress' => $session->getProgress(),
            'received_chunks' => $session->received_chunks,
            'total_chunks' => $session->total_chunks,
        ];
    }
    
    public function finalizeChunkedProcess(string $sessionId): array
    {
        $session = UploadChunk::findBySessionId($sessionId);
        
        // Reassemble chunks
        $tempFile = $this->reassembleChunks($session);
        
        // Process the file
        $result = $this->process([
            'file' => $tempFile,
            // ... other options from session metadata
        ]);
        
        // Cleanup
        $session->cleanupChunks();
        $session->markExpired();
        
        return $result;
    }
    
    public function getChunkSize(): int
    {
        return 1024 * 1024; // 1MB
    }
}
```

## Registration

Add your service to `config/services.php`:

```php
<?php
return [
    'services' => [
        'epub-font-changer' => \DGLab\Services\EpubFontChanger\EpubFontChanger::class,
        'my-service' => \DGLab\Services\MyService\MyService::class,
    ],
    
    'my-service' => [
        'max_file_size' => 52428800, // 50MB
        'allowed_extensions' => ['txt', 'pdf'],
        // Service-specific config
    ],
];
```

## Frontend Templates

Create a service-specific view at `resources/views/services/my-service.php`:

```php
<?php $this->section('content') ?>

<section class="py-5">
    <div class="container">
        <h1><?= htmlspecialchars($service->getName()) ?></h1>
        
        <form id="service-form" action="/api/services/my-service/process" method="POST" enctype="multipart/form-data">
            <?= $this->csrfField() ?>
            
            <!-- File upload -->
            <div class="mb-3">
                <label for="file" class="form-label">Upload File</label>
                <input type="file" class="form-control" id="file" name="file" required>
            </div>
            
            <!-- Service options -->
            <div class="mb-3">
                <label for="option" class="form-label">Option</label>
                <select class="form-select" id="option" name="option">
                    <option value="value1">Option 1</option>
                    <option value="value2">Option 2</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Process</button>
        </form>
    </div>
</section>

<?php $this->endSection() ?>
```

## Progress Callbacks

Report progress during processing:

```php
public function process(array $input, ?callable $progressCallback = null): array
{
    // Report 0%
    $this->reportProgress($progressCallback, 0, 'Starting...');
    
    // Step 1
    $this->doStep1();
    $this->reportProgress($progressCallback, 25, 'Step 1 complete');
    
    // Step 2
    $this->doStep2();
    $this->reportProgress($progressCallback, 50, 'Step 2 complete');
    
    // Step 3
    $this->doStep3();
    $this->reportProgress($progressCallback, 75, 'Step 3 complete');
    
    // Finalize
    $this->reportProgress($progressCallback, 100, 'Complete');
    
    return ['success' => true, ...];
}
```

## Temporary Files

Use the base class helpers for temp files:

```php
// Create temp file
$tempFile = $this->createTempFile('prefix', 'ext');

// Create temp directory
$tempDir = $this->createTempDir('prefix');

// Files are automatically cleaned up on destruct
```

## Validation

Use the built-in schema validation:

```php
public function validate(array $input): array
{
    $rules = [
        'file' => 'required|file',
        'option' => 'required|in:value1,value2',
    ];
    
    $validator = new Validator($input);
    return $validator->validate($rules);
}
```

## Logging

Log service activity:

```php
$this->log('info', 'Processing started', [
    'service' => $this->getId(),
    'input_size' => strlen($input['file']),
]);
```

## Error Handling

Throw exceptions for errors:

```php
if (!file_exists($input['file'])) {
    throw new \RuntimeException('Input file not found');
}

if (!$this->validateFileType($input['file'])) {
    throw new ValidationException(['file' => 'Invalid file type']);
}
```

## Testing

Create tests for your service:

```php
<?php
namespace DGLab\Tests\Unit\Services;

use DGLab\Services\MyService\MyService;
use DGLab\Tests\TestCase;

class MyServiceTest extends TestCase
{
    public function testServiceMetadata(): void
    {
        $service = new MyService();
        
        $this->assertEquals('my-service', $service->getId());
        $this->assertEquals('My Service', $service->getName());
        $this->assertNotEmpty($service->getDescription());
    }
    
    public function testValidation(): void
    {
        $service = new MyService();
        
        $this->expectException(ValidationException::class);
        $service->validate([]);
    }
    
    public function testProcess(): void
    {
        $service = new MyService();
        
        // Create test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');
        
        $result = $service->process([
            'file' => $tempFile,
            'option' => 'value1',
        ]);
        
        $this->assertTrue($result['success']);
        
        unlink($tempFile);
    }
}
```

## Best Practices

1. **Keep services focused**: Each service should do one thing well
2. **Validate early**: Validate input before processing
3. **Report progress**: Use progress callbacks for long operations
4. **Clean up**: Always clean up temporary files
5. **Log activity**: Log important events for debugging
6. **Handle errors gracefully**: Provide meaningful error messages
7. **Support chunked uploads**: For services handling large files
8. **Document**: Add clear descriptions and examples

## Example: Complete Service

See `app/Services/EpubFontChanger/` for a complete example service implementation.

## Troubleshooting

### Service not appearing
- Check service is registered in `config/services.php`
- Verify class name and namespace are correct
- Check for PHP syntax errors

### Validation failing
- Ensure input schema matches expected input
- Check required fields are present
- Verify data types are correct

### Processing errors
- Check temporary directory is writable
- Verify file permissions
- Review logs for detailed error messages
