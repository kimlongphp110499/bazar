<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderPackages;
use App\Models\VNPAY_Payment;

class OrderPackageController extends Controller
{
    public function list(){
        $result = OrderPackages::where('user_id',auth()->user()->id)->paginate(5);
        $payments = VNPAY_Payment::where('p_user_id',auth()->user()->id)->paginate(5);
        return ['result' => $result,'payments'=>$payments];
    }
}
