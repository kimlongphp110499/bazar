<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageD extends Model
{
    use HasFactory;
    protected $table = 'packages';

    protected $fillable = [
        'name',
        'desc',
        'image',
        'key',
        'max_device',
        'expDayTime',
        'defaut_value',
    ];
}
