# API & Headless Specification

## Vision
To provide a first-class Headless experience where content can be consumed by any frontend (Web, Mobile, IoT) via a standardized, secure RESTful API.

## Core Endpoints

### 1. Content Delivery (Public/Authenticated)
- `GET /api/v1/content/{content_type_slug}`: List entries (filterable, paginated).
- `GET /api/v1/content/{content_type_slug}/{entry_slug_or_id}`: Retrieve a single entry.
- **Parameters**:
    - `locale`: Request content in a specific language.
    - `include`: Sideload related entries (e.g., `include=author,tags`).
    - `tenant`: The tenant identifier (if not provided via header/domain).

### 2. Management API (Authenticated/Studio)
- `POST /api/v1/manage/entries`: Create a new entry.
- `PUT /api/v1/manage/entries/{id}`: Update an entry (creates a new version).
- `POST /api/v1/manage/entries/{id}/publish`: Trigger workflow transition.
- `GET /api/v1/manage/schemas`: Retrieve content type definitions.

### 3. Media & Assets
- `GET /api/v1/media`: List media library assets.
- `POST /api/v1/media/upload`: Upload new asset (proxies to `DownloadService`).

## Data Formatting
All responses follow a standardized JSON structure:
```json
{
  "data": {
    "id": "123",
    "type": "article",
    "attributes": {
      "title": "My Awesome Post",
      "body": "<p>...</p>",
      "published_at": "2023-10-27T10:00:00Z"
    },
    "relationships": {
      "author": { "data": { "id": "456", "type": "user" } }
    },
    "meta": {
      "version": 5,
      "locale": "en"
    }
  },
  "included": [
    { "id": "456", "type": "user", "attributes": { "name": "Jules" } }
  ]
}
```

## Security & Performance
- **API Tokens**: Support for scoped API keys (Read-only vs. Read/Write).
- **Caching**: Integration with the `Cache` service for fast content delivery (ETags and Cache-Control headers).
- **Rate Limiting**: Enforced per-tenant to prevent abuse.
