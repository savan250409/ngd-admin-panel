<?php

namespace App\Models;

use App\Support\Media;

/**
 * A "gallery" module keeps ONE record per category holding an ordered list of image
 * filenames (added many at once). Used by Frame and Baby Backdrop.
 */
abstract class GalleryItem extends BaseItem
{
    protected $casts = ['status' => 'boolean', 'images' => 'array'];

    /** Folder that holds this category's images. */
    public function imageDir(?string $categoryFolder = null): string
    {
        return 'upload/' . $this->moduleKey() . '/' . ($categoryFolder ?? $this->categoryFolder());
    }

    /** @return string[] public-relative path for each stored image name */
    public function imageRelPaths(?string $categoryFolder = null): array
    {
        $dir = $this->imageDir($categoryFolder);

        return array_map(fn ($n) => $dir . '/' . $n, $this->images ?? []);
    }

    public function imageUrls(): array
    {
        return array_values(array_map(fn ($p) => Media::url($p), $this->imageRelPaths()));
    }

    /** What the API returns for this record's items (flat list by default). */
    public function apiImages(): array
    {
        return $this->imageUrls();
    }

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'images'      => $this->imageUrls(),
        ];
    }
}
