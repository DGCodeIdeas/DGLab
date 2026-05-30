# DGLab API Documentation

## Overview

The DGLab API provides RESTful endpoints for accessing services programmatically. All API responses are in JSON format.

**Base URL:** `/api`

## Authentication

Currently, the API does not require authentication for public endpoints. Rate limiting may be applied.

## Response Format

All responses follow a consistent format:

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

Error responses:

```json
{
  "success": false,
  "error": "Error code",
  "message": "Human-readable error message"
}
```

## Endpoints

### Service Discovery

#### List All Services

```http
GET /api/services
```

**Response:**

```json
{
  "success": true,
  "data": {
    "services": [
      {
        "id": "epub-font-changer",
        "name": "EPUB Font Changer",
        "description": "Change fonts in EPUB e-books",
        "icon": "fa-book",
        "supports_chunking": true
      }
    ]
  }
}
```

#### Get Service Details

```http
GET /api/services/{id}
```

**Parameters:**
- `id` (string, required): Service identifier

**Response:**

```json
{
  "success": true,
  "data": {
    "id": "epub-font-changer",
    "name": "EPUB Font Changer",
    "description": "Change fonts in EPUB e-books",
    "icon": "fa-book",
    "supports_chunking": true,
    "input_schema": { ... },
    "config": { ... },
    "metadata": { ... }
  }
}
```

### Service Processing

#### Process Service (Direct)

```http
POST /api/services/{id}/process
```

**Content-Type:** `multipart/form-data`

**Parameters:**
- `file` (file, required): Input file
- Additional parameters as defined by service schema

**Response:**

```json
{
  "success": true,
  "data": {
    "download_url": "/api/download/abc123.epub",
    "filename": "book-merriweather.epub",
    "file_size": 2456789,
    "metadata": { ... }
  }
}
```

#### Validate Input

```http
POST /api/services/{id}/validate
```

**Content-Type:** `application/json`

**Parameters:** Service-specific input parameters

**Response:**

```json
{
  "success": true,
  "data": {
    "valid": true
  }
}
```

Or on validation error:

```json
{
  "success": false,
  "error": "validation_error",
  "data": {
    "valid": false,
    "errors": {
      "file": ["The file field is required."]
    }
  }
}
```

### Chunked Upload

For large files, use the chunked upload endpoints.

#### Initialize Upload

```http
POST /api/chunk/init
```

**Content-Type:** `application/json`

**Parameters:**
- `service_id` (string, required): Target service
- `filename` (string, required): Original filename
- `file_size` (integer, required): Total file size in bytes
- `metadata` (object, optional): Service-specific metadata

**Response:**

```json
{
  "success": true,
  "data": {
    "session_id": "abc123def456",
    "chunk_size": 1048576,
    "total_chunks": 10,
    "expires_at": "2024-01-01T12:00:00Z",
    "status_url": "/api/chunk/status/abc123def456"
  }
}
```

#### Upload Chunk

```http
POST /api/chunk/upload
```

**Content-Type:** `multipart/form-data`

**Parameters:**
- `session_id` (string, required): Session ID from init
- `chunk_index` (integer, required): Chunk index (0-based)
- `chunk_data` (file/binary, required): Chunk data

**Response:**

```json
{
  "success": true,
  "data": {
    "progress": 50,
    "received_chunks": 5,
    "total_chunks": 10,
    "missing_chunks": [5, 6, 7, 8, 9]
  }
}
```

#### Finalize Upload

```http
POST /api/chunk/finalize
```

**Content-Type:** `application/json`

**Parameters:**
- `session_id` (string, required): Session ID

**Response:**

Same as direct process response.

#### Get Upload Status

```http
GET /api/chunk/status/{session_id}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "status": "active",
    "progress": 50,
    "received_chunks": 5,
    "total_chunks": 10,
    "missing_chunks": [5, 6, 7, 8, 9],
    "expires_at": "2024-01-01T12:00:00Z"
  }
}
```

#### Cancel Upload

```http
DELETE /api/chunk/cancel/{session_id}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "cancelled": true
  }
}
```

### Download

#### Download Processed File

```http
GET /api/download/{filename}
```

**Response:** File download (Content-Disposition: attachment)

## Error Codes

| Code | Description |
|------|-------------|
| `not_found` | Resource not found |
| `validation_error` | Input validation failed |
| `processing_error` | Service processing failed |
| `session_expired` | Upload session expired |
| `rate_limited` | Too many requests |
| `internal_error` | Internal server error |

## Rate Limiting

API requests are limited to:
- 60 requests per minute per IP
- 10 concurrent uploads per IP

Rate limit headers:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

## JavaScript Example

```javascript
// Direct upload
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('font', 'merriweather');

fetch('/api/services/epub-font-changer/process', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    window.location.href = result.data.download_url;
  }
});

// Chunked upload
async function uploadLargeFile(file) {
  // Initialize
  const init = await fetch('/api/chunk/init', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      service_id: 'epub-font-changer',
      filename: file.name,
      file_size: file.size
    })
  }).then(r => r.json());
  
  const { session_id, chunk_size, total_chunks } = init.data;
  
  // Upload chunks
  for (let i = 0; i < total_chunks; i++) {
    const chunk = file.slice(i * chunk_size, (i + 1) * chunk_size);
    const formData = new FormData();
    formData.append('session_id', session_id);
    formData.append('chunk_index', i);
    formData.append('chunk_data', chunk);
    
    await fetch('/api/chunk/upload', {
      method: 'POST',
      body: formData
    });
  }
  
  // Finalize
  const result = await fetch('/api/chunk/finalize', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ session_id })
  }).then(r => r.json());
  
  return result;
}
```

## PHP Example

```php
<?php
// Direct upload
$client = new GuzzleHttp\Client();

$response = $client->post('/api/services/epub-font-changer/process', [
    'multipart' => [
        ['name' => 'file', 'contents' => fopen('book.epub', 'r')],
        ['name' => 'font', 'contents' => 'merriweather'],
    ]
]);

$result = json_decode($response->getBody(), true);
```
