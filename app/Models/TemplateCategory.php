<?php

namespace App\Models;

class TemplateCategory extends BaseCategory
{
    protected $table = 'template_categories';

    protected static string $itemModel = Template::class;
}
