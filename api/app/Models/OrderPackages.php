<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format("Y-m-d  H:i:s");
    } 
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
