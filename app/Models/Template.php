<?php

namespace App\Models;

class Template extends BaseItem
{
    protected static string $categoryModel = TemplateCategory::class;
    protected $casts = ['status' => 'boolean', 'count' => 'integer'];

    public function fileFields(): array
    {
        return ['frame' => 'frame', 'thumbnail' => 'thumbnail'];
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'category_id'   => $this->category_id,
            'frame_url'     => $this->fileUrl('frame'),
            'thumbnail_url' => $this->fileUrl('thumbnail'),
            'count'         => $this->count,
        ];
    }
}
