# API Documentation

## GPT Trainer API Integration

The plugin integrates with the GPT Trainer API for AI-powered SORN management. This document details the API endpoints and their usage.

### Base URL

```
https://app.gpt-trainer.com/api/v1
```

### Authentication

All requests require an API token sent in the Authorization header:

```
Authorization: Bearer YOUR_API_TOKEN
```

### Endpoints

#### Data Sources

##### Create Data Source

```http
POST /data-sources
```

Creates a new data source for training.

**Parameters:**

```json
{
  "name": "string",
  "type": "file|url|qa",
  "tags": ["string"],
  "content": "string|object"
}
```

**Response:**

```json
{
  "uuid": "string",
  "name": "string",
  "type": "string",
  "tags": ["string"],
  "created_at": "string"
}
```

##### Get Data Source

```http
GET /data-sources/{uuid}
```

Retrieves a specific data source.

**Response:**

```json
{
  "uuid": "string",
  "name": "string",
  "type": "string",
  "tags": ["string"],
  "content": "string|object",
  "created_at": "string",
  "updated_at": "string"
}
```

#### Chatbots

##### Create Chatbot

```http
POST /chatbots
```

Creates a new chatbot instance.

**Parameters:**

```json
{
  "name": "string",
  "description": "string",
  "data_sources": ["uuid"],
  "settings": {
    "temperature": number,
    "max_tokens": number
  }
}
```

**Response:**

```json
{
  "uuid": "string",
  "name": "string",
  "description": "string",
  "status": "string",
  "created_at": "string"
}
```

### Error Handling

The API uses standard HTTP status codes and returns error details in the response:

```json
{
  "error": {
    "code": "string",
    "message": "string",
    "details": {}
  }
}
```

Common error codes:

- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `429`: Too Many Requests
- `500`: Internal Server Error

### Rate Limiting

The API implements rate limiting based on your subscription tier:

- Basic: 100 requests/minute
- Pro: 1000 requests/minute
- Enterprise: Custom limits

Rate limit headers:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1580000000
```

### Best Practices

1. **Error Handling**
   - Always check for error responses
   - Implement exponential backoff for retries
   - Log detailed error information

2. **Performance**
   - Cache responses when appropriate
   - Use compression for large payloads
   - Batch requests when possible

3. **Security**
   - Store API tokens securely
   - Use HTTPS for all requests
   - Validate all input data

### Code Examples

#### PHP

```php
use PiperPrivacySorn\Services\GptTrainerApi;

// Initialize API client
$api = new GptTrainerApi();

// Create data source
try {
    $result = $api->create_data_source([
        'name' => 'Example Source',
        'type' => 'file',
        'tags' => ['sorn', 'privacy']
    ]);
} catch (\Exception $e) {
    // Handle error
}

// Get chatbot
try {
    $chatbot = $api->get_chatbot($uuid);
} catch (\Exception $e) {
    // Handle error
}
```

### Testing

The API provides a test mode for development:

1. Use test token: `test_token`
2. All operations will use test data
3. No charges are incurred

### Webhooks

The API can send webhooks for asynchronous events:

1. Configure webhook URL in settings
2. Events will be signed with your webhook secret
3. Verify webhook signatures for security

### Support

For API support:

1. Check documentation
2. Contact API support team
3. Submit bug reports with detailed information

### Overview

The PiperPrivacy SORN Manager provides a comprehensive REST API for managing SORNs and integrating with external services. This document details all available endpoints and their usage.

### Authentication

All API requests require authentication using WordPress nonces and proper user capabilities.

```php
// Example authentication header
X-WP-Nonce: YOUR_NONCE_HERE
```

### Base URL

```
/wp-json/piper-privacy-sorn/v1
```

### Endpoints

### SORNs

#### List SORNs

```http
GET /sorns
```

Query Parameters:
- `page` (int): Page number
- `per_page` (int): Items per page (max 100)
- `status` (string): Filter by status (draft, published, archived)
- `agency` (string): Filter by agency
- `search` (string): Search term

Response:
```json
{
    "items": [
        {
            "id": 1,
            "title": "Example SORN",
            "agency": "EXAMPLE",
            "system_number": "EXAMPLE-001",
            "status": "published",
            "created_at": "2025-02-05T12:00:00Z",
            "updated_at": "2025-02-05T12:00:00Z"
        }
    ],
    "total": 100,
    "total_pages": 10
}
```

#### Get SORN

```http
GET /sorns/{id}
```

Response:
```json
{
    "id": 1,
    "title": "Example SORN",
    "agency": "EXAMPLE",
    "system_number": "EXAMPLE-001",
    "content": "...",
    "status": "published",
    "metadata": {
        "federal_register_number": "2025-12345",
        "publication_date": "2025-02-05",
        "comment_deadline": "2025-03-05"
    },
    "created_at": "2025-02-05T12:00:00Z",
    "updated_at": "2025-02-05T12:00:00Z"
}
```

#### Create SORN

```http
POST /sorns
```

Request Body:
```json
{
    "title": "New SORN",
    "agency": "EXAMPLE",
    "system_number": "EXAMPLE-002",
    "content": "...",
    "status": "draft"
}
```

#### Update SORN

```http
PUT /sorns/{id}
```

Request Body:
```json
{
    "title": "Updated SORN",
    "content": "...",
    "status": "published"
}
```

#### Delete SORN

```http
DELETE /sorns/{id}
```

#### Submit to Federal Register

```http
POST /sorns/{id}/submit
```

Response:
```json
{
    "success": true,
    "submission": {
        "id": "fr-12345",
        "status": "pending",
        "submitted_at": "2025-02-05T12:00:00Z"
    }
}
```

### Federal Register Integration

#### Check Submission Status

```http
GET /federal-register/submissions/{id}
```

Response:
```json
{
    "id": "fr-12345",
    "status": "published",
    "document_number": "2025-12345",
    "publication_date": "2025-02-05",
    "url": "https://www.federalregister.gov/d/2025-12345"
}
```

#### Search Federal Register

```http
GET /federal-register/search
```

Query Parameters:
- `q` (string): Search term
- `agency` (string): Filter by agency
- `date_range` (string): Filter by date range

Response:
```json
{
    "results": [
        {
            "document_number": "2025-12345",
            "title": "Example SORN",
            "agency": "EXAMPLE",
            "publication_date": "2025-02-05",
            "url": "https://www.federalregister.gov/d/2025-12345"
        }
    ],
    "total": 100
}
```

### AI Features

#### Generate Draft

```http
POST /ai/generate-draft
```

Request Body:
```json
{
    "system_name": "Example System",
    "system_purpose": "...",
    "data_elements": ["name", "email", "address"]
}
```

Response:
```json
{
    "draft": {
        "title": "Generated SORN Title",
        "content": "...",
        "sections": {
            "purpose": "...",
            "categories": "...",
            "routine_uses": "..."
        }
    }
}
```

#### Analyze SORN

```http
POST /ai/analyze
```

Request Body:
```json
{
    "content": "SORN content to analyze"
}
```

Response:
```json
{
    "score": 85,
    "suggestions": [
        {
            "section": "routine_uses",
            "issue": "Missing required disclosure statement",
            "suggestion": "Add standard disclosure statement..."
        }
    ],
    "compliance": {
        "privacy_act": true,
        "e_government_act": true,
        "fedramp": true
    }
}
```

### Statistics

#### Get System Stats

```http
GET /stats
```

Response:
```json
{
    "total_sorns": 100,
    "by_status": {
        "draft": 20,
        "published": 70,
        "archived": 10
    },
    "by_agency": {
        "EXAMPLE": 50,
        "OTHER": 50
    },
    "submissions": {
        "pending": 5,
        "published": 95
    }
}
```

### Error Handling

All API errors follow this format:

```json
{
    "code": "error_code",
    "message": "Human readable error message",
    "data": {
        "status": 400
    }
}
```

Common Error Codes:
- `invalid_request`: Malformed request
- `not_found`: Resource not found
- `unauthorized`: Authentication required
- `forbidden`: Insufficient permissions
- `validation_error`: Invalid input data

### Rate Limiting

- 1000 requests per hour per IP
- 5000 requests per day per API token

### Webhooks

Configure webhooks to receive real-time updates:

```http
POST /webhooks
```

Request Body:
```json
{
    "url": "https://your-server.com/webhook",
    "events": ["sorn.created", "sorn.published", "fr.submitted"],
    "secret": "your-webhook-secret"
}
```

### SDK & Examples

Visit our [GitHub repository](https://github.com/PiperPrivacy/sorn-manager) for SDK implementations and code examples in various languages.
