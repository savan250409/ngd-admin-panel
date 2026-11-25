<?php

namespace App\Models;

class FrameCategory extends BaseCategory
{
    protected $table = 'frame_categories';

    protected static string $itemModel = Frame::class;
}
