<?php

namespace App\Models;

use App\Support\Media;

class VideoTemplate extends BaseItem
{
    protected static string $categoryModel = VideoTemplateCategory::class;
    protected $casts = [
        'status'            => 'boolean',
        'is_paid'           => 'boolean',
        'total_image_count' => 'integer',
        'total_editable'    => 'integer',
    ];

    public function fileFields(): array
    {
        return ['zip' => 'zip', 'thumbnail' => 'thumbnail'];
    }

    public function videoUrl(): ?string
    {
        return $this->extract_dir && $this->video_html_path
            ? Media::url($this->extract_dir . '/' . $this->video_html_path)
            : null;
    }

    public function toApiArray(): array
    {
        return [
            'id'                => $this->id,
            'category_id'       => $this->category_id,
            'title'             => $this->title,
            'is_paid'           => $this->is_paid,
            'thumbnail_url'     => $this->fileUrl('thumbnail'),
            'zip_url'           => $this->fileUrl('zip'),
            'video_url'         => $this->videoUrl(),
            'width'             => $this->width,
            'height'            => $this->height,
            'total_image_count' => $this->total_image_count,
            'total_editable'    => $this->total_editable,
            'json'              => $this->json ? json_decode($this->json, true) : null,
        ];
    }
}
