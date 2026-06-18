# OpenBlogFlow WordPress Plugin

A REST API plugin for WordPress that lets you create blog posts and retrieve posts, categories, and tags as JSON — secured with a configurable Bearer token.

## Features

- **Bearer token authentication** — Define your own API secret in the WordPress admin
- **Create posts via API** — Send JSON payloads to publish or draft blog posts
- **Automatic media import** — Featured images and inline `<img>` tags are downloaded into the WordPress media library
- **Read endpoints** — List and fetch posts, categories, and tags as structured JSON
- **Built-in documentation** — Full API reference with request/response examples inside the admin panel

## Requirements

- WordPress 5.8+
- PHP 7.4+
- PHP `DOMDocument` extension (for inline image processing)

## Installation

1. Download or clone this repository.
2. Upload the `openblogflow-wp-plugin` folder to `/wp-content/plugins/` on your WordPress site.
3. Rename the folder to `openblogflow` (optional but recommended).
4. Activate **OpenBlogFlow** from the **Plugins** screen in WordPress admin.
5. Go to **OpenBlogFlow → Settings** in the left sidebar.
6. Generate or enter a Bearer token and click **Save Token**.

## Configuration

Navigate to **OpenBlogFlow** in the WordPress admin sidebar.

### Settings Tab

| Setting | Description |
|---------|-------------|
| API Bearer Token | Secret token sent in the `Authorization` header for every API request |

Use **Generate Secure Token** to create a cryptographically random 64-character hex token.

### API Documentation Tab

Switch to the **API Documentation** tab for a complete reference including:

- Base URL and authentication headers
- All endpoints with query/body parameters
- cURL examples
- Sample JSON responses
- Error codes

## API Overview

OpenBlogFlow uses the **native WordPress REST API**. All requests use the `index.php?rest_route=` URL format (works without pretty permalinks).

**REST API base URL:** `https://your-site.com/index.php?rest_route=/openblogflow/v1`

**Query parameters:** append with `&`, not a second `?`. Example: `…/posts&per_page=10&page=1`

This plugin does **not** use `admin-ajax.php` or wp-admin form endpoints for API traffic. The wp-admin settings page is only for configuring your Bearer token.

**Authentication:**

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

`Content-Type: application/json` is required only for `POST` requests with a JSON body.

### Endpoints

| Method | Full URL | Description |
|--------|----------|-------------|
| `GET` | `https://your-site.com/index.php?rest_route=/openblogflow/v1/posts` | List blog posts (paginated) |
| `GET` | `https://your-site.com/index.php?rest_route=/openblogflow/v1/posts/{id}` | Get a single post |
| `POST` | `https://your-site.com/index.php?rest_route=/openblogflow/v1/posts` | Create a new post |
| `GET` | `https://your-site.com/index.php?rest_route=/openblogflow/v1/categories` | List categories |
| `GET` | `https://your-site.com/index.php?rest_route=/openblogflow/v1/tags` | List tags |

## Quick Examples

### List Posts

```bash
curl -X GET "https://your-site.com/index.php?rest_route=/openblogflow/v1/posts&per_page=10&page=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Create a Post

```bash
curl -X POST "https://your-site.com/index.php?rest_route=/openblogflow/v1/posts" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Hello from API",
    "content": "<p>My first API post.</p><img src=\"https://example.com/image.jpg\" />",
    "status": "publish",
    "categories": ["News"],
    "tags": ["api"],
    "featured_image": "https://example.com/hero.jpg"
  }'
```

### Get Categories

```bash
curl -X GET "https://your-site.com/index.php?rest_route=/openblogflow/v1/categories" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Apache Authorization Header

If your server strips the `Authorization` header, add this to `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

## Image Handling

When creating a post:

1. **`featured_image`** — Accepts a remote URL or base64 data URI. The image is uploaded to the media library and set as the post thumbnail.
2. **`content` inline images** — Every external `<img src="...">` in the HTML content is downloaded, stored in the media library, and the `src` attribute is replaced with the local WordPress URL.
3. **Local images** — Images already hosted on your WordPress site are not re-imported.

## Project Structure

```
openblogflow/
├── openblogflow.php          # Main plugin bootstrap
├── uninstall.php             # Cleanup on plugin deletion
├── includes/
│   ├── class-auth.php        # Bearer token authentication
│   ├── class-api.php         # REST route registration
│   ├── class-post-handler.php # Post CRUD logic
│   ├── class-media-handler.php # Media library uploads
│   └── class-admin.php       # Admin settings & docs UI
├── admin/
│   └── css/
│       └── admin.css         # Admin panel styles
└── readme/
    └── README.md             # This file
```

## Error Codes

| HTTP | Code | Meaning |
|------|------|---------|
| 401 | `openblogflow_missing_token` | No Authorization header |
| 403 | `openblogflow_invalid_token` | Token mismatch |
| 503 | `openblogflow_token_not_configured` | Token not set in admin |
| 400 | `openblogflow_missing_title` | POST /posts without title |
| 400 | `openblogflow_missing_content` | POST /posts without content |
| 404 | `openblogflow_post_not_found` | Post ID does not exist |
| 422 | `openblogflow_featured_image_failed` | Post created but thumbnail failed |

## Security Notes

- Store your Bearer token securely; treat it like a password.
- Only users with `manage_options` capability can view or change the token in admin.
- Token comparison uses `hash_equals()` to prevent timing attacks.
- All API input is sanitized before being saved to the database.

## License

GPL-2.0-or-later

## Support

For issues and feature requests, open an issue in the project repository.
