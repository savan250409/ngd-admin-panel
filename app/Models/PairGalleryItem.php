<?php

namespace App\Models;

use App\Support\Media;

/**
 * A "pair gallery" keeps ONE record per category holding many entries, where each entry
 * is a PAIR of files — a frame image + a thumbnail (plus optional extra fields such as a
 * count). Stored as:
 *   images = [ {"frame":"rose.webp","thumbnail":"rose-t.webp", ...extra}, ... ]
 * under upload/{module}/{Category}/frame/... and upload/{module}/{Category}/thumbnail/...
 * Used by Frame and Template.
 */
abstract class PairGalleryItem extends GalleryItem
{
    /** Folder for one sub-kind ("frame" | "thumbnail"). */
    public function subDir(string $sub, ?string $categoryFolder = null): string
    {
        return 'upload/' . $this->moduleKey() . '/' . ($categoryFolder ?? $this->categoryFolder()) . '/' . $sub;
    }

    /** @return array<int,array> each entry has at least frame + thumbnail */
    public function pairs(): array
    {
        return array_values(array_filter($this->images ?? [], fn ($p) => is_array($p) && !empty($p['frame'])));
    }

    /** Thumbnail URLs — the admin list preview and "See more" popup. */
    public function imageUrls(): array
    {
        return array_map(fn ($p) => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']), $this->pairs());
    }

    /** Every file (frame + thumbnail) for deletion/move. */
    public function imageRelPaths(?string $categoryFolder = null): array
    {
        $paths = [];
        foreach ($this->pairs() as $p) {
            $paths[] = $this->subDir('frame', $categoryFolder) . '/' . $p['frame'];
            $paths[] = $this->subDir('thumbnail', $categoryFolder) . '/' . $p['thumbnail'];
        }

        return $paths;
    }

    /** API output: frame_url + thumbnail_url (+ extras) per entry. */
    public function apiImages(): array
    {
        return array_map(fn ($p) => $this->apiPair($p), $this->pairs());
    }

    /** One entry's API shape; subclasses add extra fields (e.g. count). */
    protected function apiPair(array $p): array
    {
        return [
            'frame_url'     => Media::url($this->subDir('frame') . '/' . $p['frame']),
            'thumbnail_url' => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']),
        ];
    }

    /** Popup shows both the frame and the thumbnail (+ filename + count) per entry. */
    public function galleryPopup(): array
    {
        return array_map(fn ($p) => [
            'frame'     => Media::url($this->subDir('frame') . '/' . $p['frame']),
            'thumbnail' => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']),
            'name'      => $p['frame'],
            'count'     => $p['count'] ?? null,
        ], $this->pairs());
    }

    /** Frame image URL for a saved entry (used by the editor's "click to replace" boxes). */
    public function frameUrls(): array
    {
        return array_map(fn ($p) => Media::url($this->subDir('frame') . '/' . $p['frame']), $this->pairs());
    }
}
