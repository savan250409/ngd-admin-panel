<?php

namespace App\Http\Controllers\Admin;

/** Templates: one record per category, many template entries (frame + thumbnail + count). */
class TemplateController extends PairGalleryController
{
    protected string $module   = 'template';
    protected string $noun     = 'template';
    protected bool $hasCount = true;
}
