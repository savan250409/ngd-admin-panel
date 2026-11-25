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
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!config('admin.media_security')
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
            $response->setContent($this->rewrite($content));
        }

        return $response;
    }

    private function rewrite(string $content): string
    {
        $ext      = implode('|', config('admin.media_ext'));
        $longExt  = config('admin.long_lived_ext');
        $shortTtl = (int) config('admin.short_ttl');
        $longTtl  = (int) config('admin.long_ttl');

        // "/upload/..." with optional JSON-escaped slashes, ending at a quote, bracket, whitespace, "?" or "\".
        $pattern = '~(?<sep>\\\\?/)upload\\\\?/(?<path>[^"\'<>()\s]+?\.(?<ext>' . $ext . '))(?=["\'<>()\s\\\\?]|$)~i';

        return preg_replace_callback($pattern, function ($m) use ($longExt, $shortTtl, $longTtl) {
            $escaped = str_contains($m['sep'], '\\');
            $rel     = 'upload/' . ltrim(rawurldecode(str_replace('\\/', '/', $m['path'])), '/');
            if (str_contains($rel, '..')) {
                return $m[0];
            }
            $ttl = in_array(strtolower($m['ext']), $longExt, true) ? $longTtl : $shortTtl;

            return ($escaped ? '\\/' : '/') . 'secure-media?token=' . SecureMedia::makeToken($rel, $ttl);
        }, $content) ?? $content;
    }
}
