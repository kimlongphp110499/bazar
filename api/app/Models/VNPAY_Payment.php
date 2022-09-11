<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VNPAY_Payment extends Model
{
    use HasFactory;
    protected $table = 'vnpay_payments';
    protected $fillable = [
        'p_user_id',
        'p_money',
        'p_node',
        'p_transaction_code',
        'p_vnp_response_code',
        'p_code_bank',
        'p_time',
        'p_code_vnpay',
        'p_transaction_id',
    ];
}
