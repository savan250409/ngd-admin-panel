<?php

namespace App\Http\Controllers\Admin;

/** Frames: one record per category, many frame+thumbnail pairs, added several at once. */
class FrameController extends PairGalleryController
{
    protected string $module = 'frame';
    protected string $noun   = 'frame';
}
