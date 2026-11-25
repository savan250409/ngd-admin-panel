<?php

namespace App\Models;

class VideoTemplateCategory extends BaseCategory
{
    protected $table = 'video_template_categories';

    protected static string $itemModel = VideoTemplate::class;
}
