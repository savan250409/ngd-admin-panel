<?php

use App\Support\Media;

if (!function_exists('media')) {
    /** Public, URL-encoded link to a stored media path (handles spaces/unicode in folders). */
    function media(?string $path): ?string
    {
        return Media::url($path);
    }
}
