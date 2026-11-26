<?php

namespace App\Models;

use App\Support\Media;

/** Gallery of template entries (frame + thumbnail + count), one record per category. */
class Template extends PairGalleryItem
{
    protected static string $categoryModel = TemplateCategory::class;

    protected function apiPair(array $p): array
    {
        return [
            'frame_url'     => Media::url($this->subDir('frame') . '/' . $p['frame']),
            'thumbnail_url' => Media::url($this->subDir('thumbnail') . '/' . $p['thumbnail']),
            'count'         => (int) ($p['count'] ?? 1),
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'templates'   => $this->apiImages(),
        ];
    }
}
