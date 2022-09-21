<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPackages extends Model
{
    use HasFactory;
    
    protected $table = 'order_packages';
    
    protected $fillable = [
        'user_id',
        'price',
        'package_id',
        'max_device',
        'exp_day_time',
        'expTime',
        'status',
    ];
    
}
