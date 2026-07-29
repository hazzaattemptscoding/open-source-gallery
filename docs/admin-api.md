# Admin System API

JSON API for programmatic access to system operations. All endpoints require admin authentication and return JSON responses.

## Authentication

All endpoints require admin authentication via session cookie (same as web admin panel). Make authenticated requests from your admin account or use API tokens if implemented.

## Endpoints

### Health Check

**Endpoint:** `POST /admin/api/health`

Check system health and resource status.

**Response:**
```json
{
  "health": {
    "database": {
      "status": "ok"
    },
    "storage_hires": {
      "status": "ok",
      "writable": true
    },
    "storage_derivatives": {
      "status": "ok",
      "writable": true
    },
    "storage_cache": {
      "status": "ok",
      "writable": true
    },
    "storage_zips": {
      "status": "ok",
      "writable": true
    },
    "jobs": {
      "pending": 42,
      "running": 1,
      "failed": 0,
      "completed": 1250
    },
    "cache": {
      "files": 156,
      "size_bytes": 47405824
    }
  }
}
```

### Job Queue Management

**Endpoint:** `POST /admin/api/jobs`

Manage background job queue.

**Actions:**

#### List jobs by status
```json
{
  "action": "list",
  "status": "pending",
  "limit": 50
}
```

Response:
```json
{
  "jobs": [
    {
      "id": 1,
      "type": "derivative",
      "status": "pending",
      "attempts": 0,
      "created_at": "2026-07-29 12:34:56",
      "run_after": "2026-07-29 12:35:00"
    }
  ]
}
```

#### Get job queue status
```json
{
  "action": "status"
}
```

Response:
```json
{
  "status": {
    "pending": 42,
    "running": 1,
    "failed": 0,
    "completed": 1250
  }
}
```

#### Retry a failed job
```json
{
  "action": "retry",
  "job_id": 123
}
```

Response:
```json
{
  "ok": true,
  "job_id": 123
}
```

#### Clear failed jobs
```json
{
  "action": "clear",
  "status": "failed"
}
```

Response:
```json
{
  "ok": true,
  "deleted": 5
}
```

### Cache Management

**Endpoint:** `POST /admin/api/cache`

Manage application caches.

**Actions:**

#### Cache statistics
```json
{
  "action": "stats"
}
```

Response:
```json
{
  "cache": {
    "files": 156,
    "size_bytes": 47405824
  },
  "zips": {
    "files": 89,
    "size_bytes": 1290534912
  }
}
```

#### Warm caches
```json
{
  "action": "warm"
}
```

Response:
```json
{
  "ok": true,
  "warmed": 5
}
```

#### Clear cache
```json
{
  "action": "clear",
  "pattern": "search_"
}
```

Response:
```json
{
  "ok": true,
  "cleared": 12
}
```

### Performance Metrics

**Endpoint:** `POST /admin/api/perf`

Retrieve performance metrics and analytics.

**Metrics:**

#### Summary
```json
{
  "metric": "summary"
}
```

Response:
```json
{
  "jobs": {
    "pending": 42,
    "running": 1,
    "failed": 0
  },
  "content": {
    "live_photos": 5234,
    "total_views": 127456
  },
  "sales": {
    "completed_orders": 892
  }
}
```

#### Top photos
```json
{
  "metric": "top-photos"
}
```

Response:
```json
{
  "photos": [
    {
      "id": 123,
      "original_filename": "photo_2024_01.jpg",
      "view_count": 4567,
      "event_title": "Race Day 2024",
      "times_sold": 23
    }
  ]
}
```

#### Slow queries
```json
{
  "metric": "slow-queries"
}
```

Response:
```json
{
  "note": "Enable slow query logging in MySQL config",
  "config": {
    "slow_query_log": 1,
    "long_query_time": 1
  }
}
```

### Batch Operations

**Endpoint:** `POST /admin/api/batch`

Execute batch operations.

**Operations:**

#### Export orders
```json
{
  "operation": "export-orders"
}
```

Response:
```json
{
  "ok": true,
  "count": 892,
  "orders": [
    {
      "id": 1,
      "customer_email": "user@example.com",
      "total_pence": 4999,
      "created_at": "2024-01-15 10:30:00",
      "item_count": 3
    }
  ]
}
```

#### Reprocess derivatives
```json
{
  "operation": "reprocess-derivatives",
  "limit": 100
}
```

Response:
```json
{
  "ok": true,
  "queued": 100
}
```

## Usage Examples

### Check system health
```bash
curl -X POST http://localhost/admin/api/health \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id"
```

### Get job queue status
```bash
curl -X POST http://localhost/admin/api/jobs \
  -H "Content-Type: application/json" \
  -d '{"action": "status"}' \
  -b "PHPSESSID=your_session_id"
```

### Retry failed job
```bash
curl -X POST http://localhost/admin/api/jobs \
  -H "Content-Type: application/json" \
  -d '{"action": "retry", "job_id": 123}' \
  -b "PHPSESSID=your_session_id"
```

### Warm caches
```bash
curl -X POST http://localhost/admin/api/cache \
  -H "Content-Type: application/json" \
  -d '{"action": "warm"}' \
  -b "PHPSESSID=your_session_id"
```

### Export orders
```bash
curl -X POST http://localhost/admin/api/batch \
  -H "Content-Type: application/json" \
  -d '{"operation": "export-orders"}' \
  -b "PHPSESSID=your_session_id" | jq '.orders' > orders.json
```

## Integration Examples

### Monitor job queue in external dashboard
```php
function check_gallery_jobs($admin_url, $session_id) {
    $ch = curl_init("$admin_url/admin/api/jobs");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'status']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$session_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    return json_decode($response, true);
}
```

### Auto-retry failed jobs
```php
function retry_failed_jobs($admin_url, $session_id) {
    $ch = curl_init("$admin_url/admin/api/jobs");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action': 'clear', 'status': 'failed']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$session_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    return json_decode($response, true);
}
```

### Alert on high job failure rate
```bash
#!/bin/bash
SESSION_ID="your_session_id"
ADMIN_URL="http://localhost"

JOBS=$(curl -s -X POST $ADMIN_URL/admin/api/jobs \
  -H "Content-Type: application/json" \
  -d '{"action": "status"}' \
  -b "PHPSESSID=$SESSION_ID")

FAILED=$(echo $JOBS | jq '.status.failed')

if [ "$FAILED" -gt 10 ]; then
  echo "Alert: $FAILED failed jobs in queue"
  # Send alert email, Slack message, etc.
fi
```

## Error Responses

All error responses return HTTP 400 with error message:

```json
{
  "error": "Invalid job_id"
}
```

## Security Considerations

- All endpoints require admin authentication
- Responses are JSON only
- No sensitive credentials in responses
- Rate limiting applied to prevent abuse
- Consider adding API key authentication for external integrations
