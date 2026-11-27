<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileApp extends Model
{
    protected $table = 'apps';

    protected $fillable = ['app_name', 'package_name', 'platform', 'status'];

    protected $casts = ['status' => 'boolean'];
}
