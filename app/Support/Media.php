<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * File helpers for everything stored under public/upload.
 * Files are organised by category and keep their original name, e.g.
 * "upload/ai-image/Birthday/sunset.webp".
 */
class Media
{
    /** Turn a category (or any) name into a safe folder name, keeping it readable. */
    public static function folder(string $name): string
    {
        // Drop characters that are illegal in Windows/Unix paths; keep spaces, case, unicode.
        $name = preg_replace('~[\\\\/:*?"<>|\x00-\x1F]+~u', '-', trim($name));
        $name = trim(preg_replace('~\s+~u', ' ', (string) $name));

        return $name === '' ? 'uncategorized' : mb_substr($name, 0, 120);
    }

    /**
     * Store an upload under $dir (relative to upload/) and return ONLY the stored
     * filename — the database keeps the name, not the path. The original name is kept;
     * if the same name already exists in that folder a timestamp is appended.
     */
    public static function store(UploadedFile $file, string $dir, ?string $name = null): string
    {
        $rel = 'upload/' . trim($dir, '/');
        $abs = public_path($rel);
        if (!File::exists($abs)) {
            File::makeDirectory($abs, 0775, true, true);
        }

        $final = static::uniqueName($abs, $name ?? $file->getClientOriginalName(), $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $file->move($abs, $final);

        return $final;
    }

    /** Move a stored file from one public-relative path to another (used on category change). */
    public static function move(?string $fromRel, ?string $toRel): void
    {
        if (!$fromRel || !$toRel || $fromRel === $toRel) {
            return;
        }
        foreach ([$fromRel, $toRel] as $p) {
            if (str_contains($p, '..') || !str_starts_with($p, 'upload/')) {
                return;
            }
        }
        $from = public_path($fromRel);
        $to   = public_path($toRel);
        if (!File::isFile($from)) {
            return;
        }
        File::ensureDirectoryExists(dirname($to));
        File::move($from, $to);
    }

    /** Move a whole directory (public-relative). Merges into an existing target. */
    public static function moveDir(?string $fromRel, ?string $toRel): void
    {
        if (!$fromRel || !$toRel || $fromRel === $toRel) {
            return;
        }
        foreach ([$fromRel, $toRel] as $p) {
            if (str_contains($p, '..') || !str_starts_with($p, 'upload/')) {
                return;
            }
        }
        $from = public_path($fromRel);
        if (!File::isDirectory($from)) {
            return;
        }
        $to = public_path($toRel);
        File::ensureDirectoryExists($to);
        $fromN = rtrim(str_replace('\\', '/', $from), '/') . '/';
        foreach (File::allFiles($from) as $f) {
            $inside = ltrim(str_replace($fromN, '', str_replace('\\', '/', $f->getPathname())), '/');
            $dest   = $to . '/' . $inside;
            File::ensureDirectoryExists(dirname($dest));
            File::move($f->getPathname(), $dest);
        }
        File::deleteDirectory($from);
    }

    /** Sanitised, non-clashing filename inside $abs; a clash appends "_<timestamp>". */
    private static function uniqueName(string $abs, string $original, string $fallbackExt): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: $fallbackExt);

        // Keep letters/numbers/space/dot/_/-; turn anything else into a hyphen, collapse whitespace.
        $base = preg_replace('~[^\p{L}\p{N} ._-]+~u', '-', $base);
        $base = trim(preg_replace('~\s+~u', ' ', (string) $base)) ?: 'file';
        $base = mb_substr($base, 0, 80);

        $make = fn (string $b) => $ext ? "{$b}.{$ext}" : $b;

        if (!File::exists($abs . '/' . $make($base))) {
            return $make($base);
        }

        // Same name already in this category → append a timestamp (and a counter if needed).
        $stamped = $base . '_' . now()->format('YmdHis');
        $candidate = $stamped;
        $n = 1;
        while (File::exists($abs . '/' . $make($candidate))) {
            $candidate = $stamped . '_' . $n++;
        }

        return $make($candidate);
    }

    /** Save a base64 data URL (e.g. a canvas snapshot) and return its relative path. */
    public static function storeDataUrl(string $dataUrl, string $dir, string $prefix = 'thumb'): ?string
    {
        if (!preg_match('~^data:image/(png|jpe?g|webp);base64,(.+)$~', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) < 100) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $rel = 'upload/' . trim($dir, '/');
        $abs = public_path($rel);
        if (!File::exists($abs)) {
            File::makeDirectory($abs, 0775, true, true);
        }

        $name = $prefix . '-' . Str::random(10) . '.' . $ext;
        File::put($abs . '/' . $name, $bytes);

        return $name;
    }

    public static function delete(?string $path): void
    {
        if (!$path || str_contains($path, '..') || !str_starts_with($path, 'upload/')) {
            return;
        }
        $abs = public_path($path);
        if (File::isFile($abs)) {
            File::delete($abs);
        }
    }

    public static function deleteDir(?string $path): void
    {
        if (!$path || str_contains($path, '..') || !str_starts_with($path, 'upload/')) {
            return;
        }
        $abs = public_path($path);
        if (File::isDirectory($abs)) {
            File::deleteDirectory($abs);
        }
    }

    /** Absolute public URL with each path segment URL-encoded. */
    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

        return url($encoded);
    }
}
