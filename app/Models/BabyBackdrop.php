<?php

namespace App\Models;

/** One record per category, holding that category's ordered list of image filenames. */
class BabyBackdrop extends GalleryItem
{
    protected static string $categoryModel = BabyBackdropCategory::class;
}
