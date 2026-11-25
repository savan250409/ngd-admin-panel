<?php

namespace App\Http\Controllers\Admin;

/**
 * Baby backdrops (ASM003 style): each category owns ONE record holding an ordered
 * list of backdrop images, added many at once, re-orderable.
 */
class BabyBackdropController extends GalleryController
{
    protected string $module = 'baby-backdrop';
    protected string $noun   = 'backdrop';
}
