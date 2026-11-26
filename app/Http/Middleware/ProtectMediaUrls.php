<?php

namespace App\Http\Middleware;

use App\Support\SecureMedia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rewrites every ".../upload/<path>.<media-ext>" URL in an admin HTML/JSON
 * response into an opaque, expiring "/secure-media?token=..." URL. The real file
 * paths therefore never reach the DOM (Inspect), View Source or the Network tab.
 */
class ProtectMediaUrls
{
    /**
     * $mode === 'api' protects the PUBLIC API the mobile apps call: it emits long-lived
     * "/media?token=..." URLs (a gate the app/CDN can hold) instead of the admin's short
     * "/secure-media?token=..." URLs. Default (null) is the admin panel.
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $response = $next($request);

        $api     = $mode === 'api';
        $enabled = $api ? config('admin.api_media_security') : config('admin.media_security');

        if (!$enabled
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type');
        if (!str_contains($type, 'text/html') && !str_contains($type, 'application/json')) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent($this->rewrite($content, $api));
        }

        return $response;
    }

    private function rewrite(string $content, bool $api = false): string
    {
        $ext      = implode('|', config('admin.media_ext'));
        $longExt  = config('admin.long_lived_ext');
        // The public API uses one long-lived TTL for every type; the admin panel uses
        // short (image) / long (playable/download) TTLs.
        $shortTtl = $api ? (int) config('admin.api_ttl') : (int) config('admin.short_ttl');
        $longTtl  = $api ? (int) config('admin.api_ttl') : (int) config('admin.long_ttl');
        $route    = $api ? 'media' : 'secure-media';

        // "/upload/..." with optional JSON-escaped slashes, ending at a quote, bracket, whitespace, "?" or "\".
        $pattern = '~(?<sep>\\\\?/)upload\\\\?/(?<path>[^"\'<>()\s]+?\.(?<ext>' . $ext . '))(?=["\'<>()\s\\\\?]|$)~i';

        return preg_replace_callback($pattern, function ($m) use ($longExt, $shortTtl, $longTtl, $route) {
            $escaped = str_contains($m['sep'], '\\');
            $rel     = 'upload/' . ltrim(rawurldecode(str_replace('\\/', '/', $m['path'])), '/');
            if (str_contains($rel, '..')) {
                return $m[0];
            }
            $ttl = in_array(strtolower($m['ext']), $longExt, true) ? $longTtl : $shortTtl;

            return ($escaped ? '\\/' : '/') . $route . '?token=' . SecureMedia::makeToken($rel, $ttl);
        }, $content) ?? $content;
    }
}
