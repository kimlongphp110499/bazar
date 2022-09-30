<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderPackages;
use App\Models\VNPAY_Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserPackageD;
use App\Models\PackageD;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPackageController extends Controller
{
    public function list(){
        $result = OrderPackages::where('user_id',auth()->user()->id)->paginate(5);
        $payments = VNPAY_Payment::where('p_user_id',auth()->user()->id)->paginate(5);
        return ['result' => $result,'payments'=>$payments];
    }

    public function checkout(Request $request){
        try{
            DB::beginTransaction();
        $date = Carbon::now();
        $havePack = UserPackageD::where('user_id',auth()->user()->id)->where('package_id',$request->package_id)->first();
        if(!$havePack)
        {
            UserPackageD::create([
                'user_id' => auth()->user()->id,
                'user_name' => auth()->user()->id.$request->package_id,
                'package_id' => $request->package_id,
                'max_device' => $request->device,
                // 'defaut_value' => $packageDefault->defaut_value,
                'license_key' => Str::random(16),
                'expTime' =>  $date->addDays((int) $request->days),
                'exp_day_time' => $request->days,
            ]);
           
        }
        else{
            $havePack->update([
                'max_device' => $havePack->max_device + $request->device,
                'defaut_value' => 0,
                //'license_key' => Str::random(16),
                'expTime' =>  Carbon::parse($havePack->expTime)->addDays((int) $request->days),
                'exp_day_time' => $havePack->exp_day_time + $request->days,
            ]);
        }
       OrderPackages::create([
            'user_id'=> auth()->user()->id,
            'price' => $request->total,
            'package_id' => $request->package_id,
            'max_device' => $request->device,
            'exp_day_time' => $request->days,
            'status' => 1,
        ]);
        $wallet =  Wallet::where('customer_id', auth()->user()->id)->first();
        
        $wallet->update(['total_points'=> $wallet->total_points - $request->total,
        'available_points'=> $wallet->available_points - $request->total
        ]);
        DB::commit();
          return ['result'=>'done'];
    }
       catch(Exception $exception){
        DB::rollBack();
        Log::error('Message'.$exception->getMessage().'Line'.$exception->getLine());
  
    }
    return false;
    }
}
