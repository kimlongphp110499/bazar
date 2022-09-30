<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;
    protected $table = 'wallets';

    public $guarded = [];
    protected $fillable = [
        'customer_id',
        'total_points',
        'available_points',
    ];
    /**
     * @return BelongsToMany
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
