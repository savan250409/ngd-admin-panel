<?php

namespace App\Models;

class AiVideoCategory extends BaseCategory
{
    protected $table = 'ai_video_categories';

    protected static string $itemModel = AiVideo::class;
}
