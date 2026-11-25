# NGD Admin Panel (Laravel 11)

Admin panel for managing app content (frames, templates, dynamic frames, baby backdrops,
video templates, AI images and AI videos) and serving it to mobile apps through
bearer-token protected APIs. The modules follow the `photo_frame` reference panel; the UI is new.

## Setup

```bash
composer install
cp .env.example .env        # set DB_* and ADMIN_EMAIL / ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed  # creates the admin user, a "Demo App" and the API token
php artisan serve
```

Sign in at `/login` with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`.
Run the test suite with `php artisan test` (uses in-memory SQLite).

## Modules

Every category has a **name, an image and one or more apps** (pick several at once on the
category form). The same category can be served to multiple apps; each app's API only ever
returns the categories it is assigned to. Every list page has search, filters
(app, category, status), per-page and pagination (loaded over AJAX).

| Module         | Item fields                                                                 |
|----------------|-----------------------------------------------------------------------------|
| Apps           | app name, package name                                                      |
| Frame          | **gallery**: one record per category holding many frames; each frame is a **frame image + thumbnail** pair (add several at once, reorder). API returns `frame_url` + `thumbnail_url` per frame |
| Template       | frame image, thumbnail, count (photo slots)                                 |
| Dynamic Frame  | aspect ratio, zip (JSON layout extracted + colours normalised), thumbnail, paid |
| Baby Backdrop  | one image set per category (multi-upload, drag to reorder); category has orientation |
| Video Template | title, zip (Lottie `data.json`/`data.html` + `template.json`), thumbnail, premium |
| AI Image       | image, prompt                                                               |
| AI Video       | video, prompt, thumbnail generated in the browser with `<canvas>` (no libraries) |

Modules are defined once in `app/Support/ModuleRegistry.php`; routes, sidebar,
dashboard, API and API List screen are all generated from it.

**Images are WebP-only.** Every image field (category images, frames, thumbnails,
backdrops, AI images) accepts `.webp` only — the file picker shows only webp, a warning
is displayed, non-webp selections are rejected in the browser, and the server validates
`mimes:webp`. AI-video thumbnails are generated as webp in the browser with `<canvas>`.

**Lightweight lists.** List images use `loading="lazy"` and are only redrawn to
`<canvas>` once they scroll into view, so long, image-heavy pages stay fast; everything
is paginated (10/page by default) and search/filter/pagination load over AJAX.

## API

All endpoints are `POST`, need `Authorization: Bearer <token>`, and only return
active data of the app whose `package_name` is sent.

| Module         | Categories                               | Items by category (`package_name`, `category_id`)   |
|----------------|------------------------------------------|-----------------------------------------------------|
| Frame          | `/api/v1/frame/getCategories`            | `/api/v1/frame/getFramesByCategory`                 |
| Template       | `/api/v1/template/getCategories`         | `/api/v1/template/getTemplatesByCategory`           |
| Dynamic Frame  | `/api/v1/dynamic-frame/getCategories`    | `/api/v1/dynamic-frame/getDynamicFramesByCategory`  |
| Baby Backdrop  | `/api/v1/baby-backdrop/getCategories`    | `/api/v1/baby-backdrop/getBackdropsByCategory`      |
| Video Template | `/api/v1/video-template/getCategories`   | `/api/v1/video-template/getVideoTemplatesByCategory`|
| AI Image       | `/api/v1/ai-image/getCategories`         | `/api/v1/ai-image/getAiImagesByCategory`            |
| AI Video       | `/api/v1/ai-video/getCategories`         | `/api/v1/ai-video/getAiVideosByCategory`            |

The token is shown (and can be regenerated) on the **API List** screen, which also has
sample responses, cURL snippets and a Postman collection download.

## Hidden media URLs (Inspect / Network tab)

With `ADMIN_MEDIA_SECURITY=true` (default) every `/upload/...` URL in admin pages is
rewritten to an encrypted, expiring `/secure-media?token=...` URL that only works for a
signed-in admin, images are redrawn onto `<canvas>`, and video downloads are disabled.
Video-template previews are served through `/video-template/templates/{id}/player/...`.
API responses (for the apps) still return normal public URLs.

## Storage layout

Files are organised by **category name** and keep their **original filename**:

```
public/upload/{module}/{Category Name}/{original-file.ext}          # single-file items (AI image, backdrops) + category cover
public/upload/{module}/{Category Name}/{field}/{original-file.ext}  # multi-file items (frame/thumbnail, zip/thumbnail, video/thumbnail)
public/upload/video-template/{Category Name}/extracted/{id}/...     # unpacked Lottie template
```

**The database stores only the filename** (e.g. `rose.webp`), never the full path. The
path is rebuilt from the module + the item's category name by `BaseItem::fileRelPath()` /
`BaseCategory::imageRelPath()`. Because of this:

- Renaming a category moves its whole folder; moving an item to another category moves
  its files; deleting an item or category deletes its files.
- Uploading a file whose name already exists **in the same category** keeps the original
  and appends a timestamp (`rose_20261125u.webp`) — nothing is overwritten.

In the admin UI these paths are served through the expiring `/secure-media` token (the
`media()` helper and the models URL-encode them). Scripts are blocked under `upload/` by
`.htaccess`.
