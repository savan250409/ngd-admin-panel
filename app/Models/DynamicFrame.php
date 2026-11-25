<?php

namespace App\Models;

class DynamicFrame extends BaseItem
{
    protected static string $categoryModel = DynamicFrameCategory::class;
    protected $casts = ['status' => 'boolean', 'is_paid' => 'boolean'];

    public const RATIOS = ['1:1', '2:1', '3:4', '4:5', '9:16', '16:9'];

    public function fileFields(): array
    {
        return ['zip' => 'zip', 'thumbnail' => 'thumbnail'];
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'category_id'   => $this->category_id,
            'aspect_ratio'  => $this->aspect_ratio,
            'is_paid'       => $this->is_paid,
            'thumbnail_url' => $this->fileUrl('thumbnail'),
            'zip_url'       => $this->fileUrl('zip'),
            'json'          => $this->json ? json_decode($this->json, true) : null,
        ];
    }
}
