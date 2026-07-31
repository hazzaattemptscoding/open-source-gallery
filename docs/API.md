# API reference

Every endpoint, parameter and response shape below was checked against the code
and against live responses from a running instance. Where something is missing
or behaves differently from what you would expect, it says so rather than
describing the intended behaviour.

For the admin system endpoints (health, jobs, cache, performance, batch
operations), see [admin-api.md](admin-api.md). Those are session-authenticated
browser endpoints, not integration surface.

## Contents

- [What exists today](#what-exists-today)
- [Authentication](#authentication)
- [Public REST v1](#public-rest-v1)
- [Public browsing endpoints](#public-browsing-endpoints)
- [Upload](#upload)
- [Errors](#errors)
- [Rate limits](#rate-limits)
- [Building a Lightroom plugin or other uploader](#building-a-lightroom-plugin-or-other-uploader)

## What exists today

Read this before designing against the API. Three gaps will shape what you can
build:

1. **There is no UI for creating API keys.** `create_api_key()` exists in
   `app/lib/api.php` and nothing calls it. Keys have to be inserted directly,
   see [Authentication](#authentication).
2. **The v1 API is read-only.** There is no write endpoint of any kind. Upload
   goes through the session-authenticated admin endpoints, which an external
   integration cannot currently use without driving a browser login.
3. **The v1 API is not rate limited.** The public browsing endpoints are; v1 is
   not, and the `api.rate_limit` setting in the settings registry is read by
   nothing.

## Authentication

### API keys (public REST v1)

Keys are 32 random bytes, hex encoded, stored as a SHA-256 hash in `api_keys`.
The plaintext key is shown once at creation and cannot be recovered afterwards.

Since no admin screen creates keys yet, generate one from the project root:

```php
php -r '
require "app/lib/api.php";
$pdo = new PDO("mysql:host=localhost;dbname=YOUR_DB", "user", "pass");
// 1 is the admin_users.id the key belongs to.
echo create_api_key($pdo, 1, "lightroom-plugin", ["read:photos"]), PHP_EOL;
'
```

Pass the key either way:

```bash
curl -H "Authorization: Bearer YOUR_KEY" https://gallery.example.com/api/v1/photos
curl "https://gallery.example.com/api/v1/photos?api_key=YOUR_KEY"
```

Prefer the header. Query strings end up in server access logs and browser
history.

**Permissions** are a JSON array on the key. `read:photos` is the only value any
endpoint currently checks. `*` grants everything. A key with the wrong
permissions gets `403`, not `401`.

Revoke with `revoke_api_key($pdo, $keyId)`, which sets `enabled = 0`; validation
only matches enabled keys.

Every v1 request is written to `api_logs` with the key id, endpoint, method,
status and response time.

### Sessions (admin endpoints)

Everything under `/admin/` authenticates with the PHP session cookie and expects
a CSRF token on writes. These are browser endpoints. There is no token-based
equivalent, so an external tool cannot call them without replaying a login,
which is not a supported integration path.

## Public REST v1

### List photos

```
GET /api/v1/photos
```

| Parameter | Default | Notes |
| --- | --- | --- |
| `page` | 1 | Not validated. Below 1 computes a negative `OFFSET`: SQLite silently treats that as 0 and returns the first page, MySQL rejects a negative offset. Send 1 or greater. |
| `per_page` | 50 | Capped at 250, not floored. `per_page=0` returns `{"count": 0, "data": []}` rather than an error. |

Returns live photos on published events, newest first. Photos on unpublished
events are never returned.

```json
{
  "success": true,
  "page": 1,
  "per_page": 2,
  "count": 2,
  "data": [
    {
      "id": 1,
      "public_token": "aed7e3ccbaf6",
      "original_filename": "photo-1.jpg",
      "price_pence": 500,
      "width": 1920,
      "height": 1080,
      "view_count": 92,
      "created_at": "2026-07-31 16:08:50",
      "camera_make": null,
      "camera_model": null
    }
  ]
}
```

`count` is the number of rows in this page, not the total number of photos.
There is no total count and no "next page" indicator: page until you get fewer
rows than `per_page`.

`price_pence` falls back to the event's `price_single_pence` when the photo has
no override.

`created_at` is a MySQL datetime string in the server's timezone, not ISO 8601.

### Get one photo

```
GET /api/v1/photos/{id}
```

```json
{
  "success": true,
  "data": {
    "id": 1,
    "public_token": "aed7e3ccbaf6",
    "original_filename": "photo-1.jpg",
    "price_pence": 500,
    "width": 1920,
    "height": 1080,
    "view_count": 92,
    "created_at": "2026-07-31 16:08:50",
    "camera_make": null,
    "camera_model": null
  }
}
```

This returns **exactly the same fields as the list endpoint**. It does not
return EXIF detail. `focal_length`, `aperture`, `shutter_speed`, `iso` and
`taken_at` are captured at upload and stored on the `photos` row, but
`api_get_photo()` does not select them, so there is currently no way to read
them over the API. An earlier version of this document described them as part
of the response; that was never true.

Returns `404` for an unknown id, a photo that is not `live`, or a photo whose
event is unpublished.

### Serving the image itself

The API returns `public_token`, not URLs. Derivatives are static files served
from `/media/d/{public_token}-{width}.jpg` at widths **400, 800 and 1600**.
No other width exists and none is generated on demand: a request for any other
size falls through to the 404 page.

Anything 800 and wider carries the watermark (the threshold is the
`watermark_min_width` setting, default 800). The unwatermarked original is only
ever delivered through a signed download link after purchase, and is not
reachable from the API.

## Public browsing endpoints

These need no key. They exist for the site's own front end, and are documented
because they are the only read surface with filtering.

### Search

```
GET /api/search?q=&page=&event=&kart=&class=
```

`q` shorter than two characters returns an empty result set rather than an
error, so `?kart=7` alone returns nothing: the filters narrow a query, they do
not replace one. Fixed page size of 20.

```json
{
  "success": true,
  "query": "photo",
  "results": {
    "photos": [
      {
        "id": 46,
        "public_token": "80f86004d433",
        "original_filename": "photo-22.jpg",
        "status": "live",
        "event_id": 2,
        "session_id": 4,
        "width": 1920,
        "height": 1080,
        "view_count": 119,
        "event_title": "Brands Hatch Club Day",
        "event_slug": "brands-hatch-club-day-2026",
        "price_single_pence": 600,
        "session_slug": "qualifying"
      }
    ],
    "total": 0,
    "pages": 0,
    "facets": []
  }
}
```

`date_from` and `date_to` are read by `search_photos()` but the API controller
never passes them through, so sending them has no effect.

Driver names are searchable in the admin only. They are deliberately absent from
every public response and from the facets; see `docs/architecture.md` and the
removal in commit `d750edf`. Do not add them back to a public surface.

### Photo grid fragment

```
GET /api/photos?event={slug}&session={slug}&kart=&class=
```

Returns an **HTML fragment**, not JSON: the grid items for the filter bar to
swap in. `event` is required. Unknown event or session gives `404`.

### View beacon

```
POST /api/photos/view
Content-Type: application/json

{"photo_id": 123}
```

Queues a background job to increment the view count and returns `{"ok": true}`
immediately. Limited to one request per IP per second, which is a beacon
throttle, not an API budget.

## Upload

Upload is a three-step chunked flow under `/admin/upload`. It is
**session-authenticated with a CSRF token**, so it is not usable from an
external integration as it stands. It is documented here because it is what a
Lightroom plugin would have to target once token auth exists.

All three steps post `multipart/form-data` including `csrf_token`. The token is
verified with `csrf_verify_reusable()` rather than the usual one-time check,
precisely because one upload makes many sequential requests under a single
token.

### 1. Init

```
POST /admin/upload/init
  session_id=<int>
  files[]=<json string per file: {"name": "...", "size": <bytes>}>
```

```json
{
  "batch_id": 12,
  "accepted": [
    {"file_id": 45, "name": "IMG_0001.jpg", "size": 8388608,
     "chunks_total": 4, "chunks_received": 0}
  ],
  "rejected": [
    {"name": "broken.jpg", "error": "File size is 0 or missing"}
  ]
}
```

**Resumption**: if a file with the same session, name and byte size is already
`uploading`, init returns that file's row with its real `chunks_received`.
Restart from that index instead of from zero. This is what makes an interrupted
upload resumable.

### 2. Chunk

```
POST /admin/upload/chunk
  file_id=<int>
  chunk_index=<0-based>
  chunk=<binary, 2 MB>
```

Chunk size is fixed at 2 MB (`CHUNK_SIZE` in `app/lib/upload.php`); the last
chunk is whatever remains. `chunks_total` is `ceil(size / 2 MB)`.

```json
{"file_id": 45, "chunk_index": 0, "chunks_received": 1, "chunks_total": 4}
```

`chunks_received` is a plain counter, not a record of which indexes arrived.
Re-sending an index you already sent increments it again, so the count can reach
`chunks_total` with a chunk still missing and pass finalize's check. Assembly
then fails on the absent chunk file and the upload errors with `500` rather than
producing a truncated image, but you get a failed upload instead of a clear
message. Send each index exactly once.

This also constrains resumption: because the count is assumed to be a contiguous
prefix, resume from `chunks_received` only works if you upload chunks in order.
Upload them sequentially.

### 3. Finalize

```
POST /admin/upload/finalize
  file_id=<int>
```

Assembles the chunks, validates the image, moves it into place and queues
derivative generation. Rejects with `400` if `chunks_received` does not equal
`chunks_total`.

### What finalize will reject

Validation runs on the assembled file (`validate_image_file()`):

- **JPEG and PNG only**, by sniffed MIME type. Not the file extension.
- The extension's implied type must match the sniffed type.
- Minimum **400×400**.
- Maximum **16384×16384**.

**RAW files are rejected.** This is the open blocker for a Lightroom plugin:
the plugin concept assumes RAW upload and the whitelist does not permit it.
Widening it is a deliberate security decision that has not been made (it is "M2"
in the backlog), so build against JPEG export for now and do not assume RAW
support is coming.

## Errors

Every JSON endpoint returns errors as `{"error": "message"}` with a matching
status. `/api/photos` is the exception: it returns plain text, since it serves
HTML.

| Status | When |
| --- | --- |
| `400` | Missing or malformed parameter |
| `401` | No API key supplied |
| `403` | Key is unknown, disabled, or lacks the permission. Also a failed CSRF check on admin endpoints. |
| `404` | Not found, or not publicly visible |
| `429` | Rate limited. Public browsing endpoints only. |
| `500` | Server-side failure, for example chunk assembly |

`401` means "you sent no key"; an invalid key is `403`, not `401`. Do not use
`401` alone to decide whether to prompt for credentials.

## Rate limits

Fixed window, counted per IP in the `rate_limits` table. A window's count resets
when the window rolls over; there is no sliding window and no `Retry-After`
header, so back off on your own schedule.

| Endpoint | Limit |
| --- | --- |
| `GET /api/search` | 30 per minute |
| `GET /api/photos` | 50 per minute |
| `POST /api/photos/view` | 1 per second |
| `GET /api/v1/photos*` | **none enforced** |

No endpoint returns rate limit headers. You cannot see remaining quota; you find
out by getting a `429`.

The `dev_mode: local` relaxation (`adjust_rate_limit_for_dev()`) applies only to
the login and TOTP buckets. The API limits above are the same in development as
in production.

## Building a Lightroom plugin or other uploader

Read this before starting. As of this document, an external uploader cannot
work end to end, and these are the reasons:

1. **No write API.** Upload is admin-session only, so a plugin has no supported
   way to authenticate.
2. **No RAW.** The MIME whitelist is JPEG and PNG. Export to JPEG.
3. **No key management UI.** Every integrator has to hand-insert a key.

If you are implementing the missing pieces rather than working around them, the
smallest useful change is accepting an API key with a `write:photos` permission
on the three upload endpoints as an alternative to the session check, keeping
CSRF for the browser path. The chunking protocol itself needs no changes: it
already resumes, which is the hard part for large files over a slow connection.

Talk to the maintainer before building on top of any of this. The endpoints
above are not versioned in a way that promises stability, apart from `/api/v1/`,
and even that has never had a compatibility guarantee written down.
