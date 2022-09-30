<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    protected $table = 'services';

    protected $fillable = [
        'name',
        'desc',
        'image',
        'desc',
    ];
    public function getImageAttribute($value)
    {
        $path = \Storage::disk()->url('');
        return url('/').$path.$value;
    }

}
