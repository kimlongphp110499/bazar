<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPackageD extends Model
{
    use HasFactory;
    protected $table = 'user_packages';

    protected $fillable = [
        'user_id',
        'user_name',
        'package_id',
        'max_device',
        'exp_day_time',
        'expTime',
        'license_key',
        'defaut_value',
        'status',
    ];
}
