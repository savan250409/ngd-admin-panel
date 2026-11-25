<?php

namespace App\Models;

class AiImageCategory extends BaseCategory
{
    protected $table = 'ai_image_categories';

    protected static string $itemModel = AiImage::class;
}
