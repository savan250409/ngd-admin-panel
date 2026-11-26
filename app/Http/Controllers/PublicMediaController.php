<?php

namespace App\Http\Controllers;

use App\Support\SecureMedia;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public media gate for the mobile APIs. An image is addressed ONLY by its opaque
 * SecureMedia token (never its real "upload/..." path), so a full URL copied into a
 * browser cannot be resolved without a valid token, while the genuine app — which
 * received the "/media?token=..." URL from the API — loads it like any image URL.
 *
 * No admin session is required here (the encrypted token is the credential), which is
 * what lets the app's image loader fetch it. Responses are cacheable so a CDN
 * (e.g. Cloudflare) in front can still cache the bytes.
 */
class PublicMediaController extends Controller
{
    private const MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mov' => 'video/quicktime', 'zip' => 'application/zip',
    ];

    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(config('admin.api_media_security'), 404);

        // Block opening the URL directly in a browser tab. A browser navigation sends
        // "Sec-Fetch-Mode: navigate"; a native app image loader sends no Sec-Fetch header,
        // so the genuine app is unaffected.
        if (config('admin.api_block_browser')
            && strtolower((string) $request->header('Sec-Fetch-Mode')) === 'navigate') {
            abort(403, 'This media URL cannot be opened directly in a browser.');
        }

        $path = SecureMedia::resolve($request->query('token'));
        abort_unless($path, 403, 'Invalid or expired media token.');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // response()->file handles HTTP Range requests, so videos can still seek.
        return response()->file($path, [
            'Content-Type'           => self::MIME[$ext] ?? 'application/octet-stream',
            // Cacheable: the app and any CDN in front may keep the bytes.
            'Cache-Control'          => 'public, max-age=86400',
            'Content-Disposition'    => $ext === 'zip' ? 'attachment; filename="' . basename($path) . '"' : 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
