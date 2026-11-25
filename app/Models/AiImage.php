<?php

namespace App\Models;

class AiImage extends BaseItem
{
    protected static string $categoryModel = AiImageCategory::class;
    protected $casts = ['status' => 'boolean'];

    public function fileFields(): array
    {
        // Stored directly in the category folder: upload/ai-image/{Category}/{file}
        return ['image' => ''];
    }

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'image_url'   => $this->fileUrl('image'),
            'prompt'      => $this->prompt,
        ];
    }
}
