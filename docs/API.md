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
