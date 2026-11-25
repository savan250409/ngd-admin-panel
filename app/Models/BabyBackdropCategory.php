<?php

namespace App\Models;

class BabyBackdropCategory extends BaseCategory
{
    protected $table = 'baby_backdrop_categories';

    protected static string $itemModel = BabyBackdrop::class;
}
