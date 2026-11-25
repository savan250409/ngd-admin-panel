<?php

namespace App\Models;

class AiVideo extends BaseItem
{
    protected static string $categoryModel = AiVideoCategory::class;
    protected $casts = ['status' => 'boolean'];

    public function fileFields(): array
    {
        return ['video' => 'video', 'thumbnail' => 'thumbnail'];
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'category_id'   => $this->category_id,
            'video_url'     => $this->fileUrl('video'),
            'thumbnail_url' => $this->fileUrl('thumbnail'),
            'prompt'        => $this->prompt,
        ];
    }
}
