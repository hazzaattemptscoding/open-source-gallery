# REST API v1 Documentation

Open Source Gallery provides a REST API for programmatic access to photo data and statistics.

## Authentication

All API requests require an API key. You can create API keys in the admin panel at `/admin/settings?category=api`.

Pass your API key in one of two ways:

```bash
# Query parameter
GET /api/v1/photos?api_key=YOUR_KEY

# Authorization header
Authorization: Bearer YOUR_KEY
```

## Rate Limiting

API keys have configurable rate limits (default: 1000 requests/hour). Rate limit information is returned in response headers.

## Endpoints

### List Photos

```
GET /api/v1/photos
```

**Parameters:**
- `page` (optional): Page number, default 1
- `per_page` (optional): Results per page, default 50 (max 250)

**Response:**
```json
{
  "success": true,
  "page": 1,
  "per_page": 50,
  "count": 25,
  "data": [
    {
      "id": 123,
      "public_token": "abc123xyz",
      "original_filename": "motorsport-hero.jpg",
      "price_pence": 2500,
      "width": 4000,
      "height": 2667,
      "view_count": 145,
      "created_at": "2024-01-15T10:30:00Z",
      "camera_make": "Canon",
      "camera_model": "EOS R5"
    }
  ]
}
```

### Get Photo Details

```
GET /api/v1/photos/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "public_token": "abc123xyz",
    "original_filename": "motorsport-hero.jpg",
    "price_pence": 2500,
    "width": 4000,
    "height": 2667,
    "view_count": 145,
    "created_at": "2024-01-15T10:30:00Z",
    "camera_make": "Canon",
    "camera_model": "EOS R5",
    "focal_length": "85mm",
    "aperture": "f/2.8",
    "shutter_speed": "1/2000",
    "iso": 400,
    "taken_at": "2024-01-15T10:15:00Z"
  }
}
```

## Error Responses

```json
{
  "error": "Invalid API key or insufficient permissions"
}
```

Common error codes:
- `401 Unauthorized` - Missing or invalid API key
- `403 Forbidden` - API key lacks required permissions
- `404 Not Found` - Resource not found
- `429 Too Many Requests` - Rate limit exceeded

## Example Usage

### JavaScript/Fetch

```javascript
const apiKey = 'your_api_key_here';
const response = await fetch('/api/v1/photos?page=1&per_page=20', {
  headers: {
    'Authorization': `Bearer ${apiKey}`
  }
});
const data = await response.json();
console.log(data.data);
```

### Python

```python
import requests

api_key = 'your_api_key_here'
headers = {'Authorization': f'Bearer {api_key}'}
response = requests.get('/api/v1/photos', headers=headers)
photos = response.json()['data']
```

### CURL

```bash
curl -H "Authorization: Bearer your_api_key_here" \
  https://gallery.example.com/api/v1/photos?page=1
```

## Permissions

API keys can have granular permissions:
- `read:photos` - List and view photo details
- `read:orders` - View order information
- `write:photos` - Modify photo data (future)

## Rate Limiting Best Practices

- Cache responses when possible
- Use pagination to fetch large datasets
- Implement exponential backoff for retries
- Monitor your usage in the admin panel

## Webhook Support (Future)

Webhooks for events like order placement, photo uploads, and payment confirmations are planned for a future release.
