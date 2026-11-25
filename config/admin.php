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

    // Token lifetime (seconds) for images. Long enough that a "See more" popup
    // opened a while after the page loaded still resolves its image tokens.
    'short_ttl' => 1800,

    // Token lifetime (seconds) for click-to-play / download media.
    'long_ttl' => 3600,

    'long_lived_ext' => ['mp4', 'mov', 'webm', 'zip'],

    'media_ext' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'mp4', 'mov', 'webm', 'zip'],
];
