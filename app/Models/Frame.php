<?php

namespace App\Models;

/** Gallery of frame+thumbnail pairs, one record per category. */
class Frame extends PairGalleryItem
{
    protected static string $categoryModel = FrameCategory::class;

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'frames'      => $this->apiImages(),
        ];
    }
}
