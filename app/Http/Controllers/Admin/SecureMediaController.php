<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecureMedia;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Streams an upload addressed only by its encrypted, expiring token (admin only). */
class SecureMediaController extends Controller
{
    private const MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mov' => 'video/quicktime', 'zip' => 'application/zip',
    ];

    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless(config('admin.media_security'), 404);

        $path = SecureMedia::resolve($request->query('token'));
        abort_unless($path, 403, 'Invalid or expired media token.');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // response()->file handles HTTP Range requests, so videos can still seek.
        return response()->file($path, [
            'Content-Type'           => self::MIME[$ext] ?? 'application/octet-stream',
            'Cache-Control'          => 'private, no-store, max-age=0',
            'Content-Disposition'    => $ext === 'zip' ? 'attachment; filename="' . basename($path) . '"' : 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
