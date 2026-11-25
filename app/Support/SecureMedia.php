<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a public-relative media path + expiry into an opaque URL-safe token
 * and resolves it back. The admin panel never sends real "upload/..." paths to
 * the browser, so Inspect / View Source / the Network tab only ever see
 * "/secure-media?token=..." URLs that expire.
 */
class SecureMedia
{
    public static function makeToken(string $relPath, int $ttl): string
    {
        $raw = Crypt::encryptString(json_encode([
            'p' => ltrim(str_replace('\\', '/', $relPath), '/'),
            'e' => time() + max(1, $ttl),
        ]));

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Absolute file path for a valid token, or null when missing/expired/tampered/outside upload/. */
    public static function resolve(?string $token): ?string
    {
        if (!$token) {
            return null;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data) || empty($data['p']) || empty($data['e']) || time() > (int) $data['e']) {
            return null;
        }

        $rel = ltrim($data['p'], '/');
        if (str_contains($rel, '..') || !str_starts_with($rel, 'upload/')) {
            return null;
        }

        $abs  = realpath(public_path($rel));
        $base = realpath(public_path('upload'));
        if ($abs === false || $base === false) {
            return null;
        }

        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        if (!str_starts_with(str_replace('\\', '/', $abs), $base) || !is_file($abs)) {
            return null;
        }

        return $abs;
    }
}
