<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;


class PackageD extends Model
{
    use HasFactory;
    protected $table = 'packages';

    protected $fillable = [
        'name',
        'desc',
        'image',
        'service_id',
    ];

    // public function getKeyAttribute($value)
    // {
    //     return json_decode($value);
    // }
   
    // public function getExpDayTimeAttribute($value)
    // {
    //     return json_decode($value);
    // } 
    // public function getMaxDeviceAttribute($value)
    // {
    //     return json_decode($value);
    // }
}
