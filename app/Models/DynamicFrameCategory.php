<?php

namespace App\Models;

class DynamicFrameCategory extends BaseCategory
{
    protected $table = 'dynamic_frame_categories';

    protected static string $itemModel = DynamicFrame::class;
}
