<?php

return [
    // Seeded admin account (php artisan db:seed)
    'name'     => env('ADMIN_NAME', 'Super Admin'),
    'email'    => env('ADMIN_EMAIL', 'admin@ngd.test'),
    'password' => env('ADMIN_PASSWORD'),

    /*
     | Hide real upload paths in the admin panel. When on, every /upload/... media
     | URL in admin pages becomes an expiring /secure-media?token=... URL, so
     | Inspect and the Network tab never show the real file location.
     | Set ADMIN_MEDIA_SECURITY=false in .env to switch it off.
     */
    'media_security' => env('ADMIN_MEDIA_SECURITY', true),

    /*
     | Protect media in the PUBLIC API the mobile apps call. When on, the API hands
     | the app opaque "/media?token=..." URLs instead of real "/upload/..." paths, and
     | (with the deny rule in public/upload/.htaccess under Apache) the raw path 403s.
     | The token is a long-lived GATE, not a short expiry, so the app can show images
     | long after the call and a CDN can still cache them.
     | To remove this feature: set ADMIN_API_MEDIA_SECURITY=false AND delete the
     | "block direct access" block from public/upload/.htaccess.
     */
    'api_media_security' => env('ADMIN_API_MEDIA_SECURITY', true),

    // Token lifetime (seconds) for the public API media gate — long (default 1 year),
    // because the app may render an image long after the API response was fetched/cached.
    'api_ttl' => (int) env('ADMIN_API_TTL', 31536000),

    // Refuse /media URLs that are OPENED IN A BROWSER TAB. A browser navigation sends
    // "Sec-Fetch-Mode: navigate"; a native app's image loader sends no Sec-Fetch header,
    // so the app still works while a pasted URL shows "Forbidden". (Heuristic: a crafted
    // request without the header can still fetch — airtight needs an app key/attestation.)
    'api_block_browser' => env('ADMIN_API_BLOCK_BROWSER', true),

    // Token lifetime (seconds) for images. Long enough that a "See more" popup
    // opened a while after the page loaded still resolves its image tokens.
    'short_ttl' => 1800,

    // Token lifetime (seconds) for click-to-play / download media.
    'long_ttl' => 3600,

    'long_lived_ext' => ['mp4', 'mov', 'webm', 'zip'],

    'media_ext' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'mp4', 'mov', 'webm', 'zip'],
];
