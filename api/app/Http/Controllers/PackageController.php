<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PackageD;
use App\Models\Service;
use App\Models\PackageDetail;


class PackageController extends Controller
{
    public function service(){
        $result = Service::get();
        return ['result' => $result];
    }
    public function package_detail($id){
        $result = PackageDetail::where('package_id',$id)->get();
        return ['result' => $result];
    }

    public function service_find($id){
        $result = Service::find($id);
        $package_list = PackageD::where('service_id',$result->id)->get();
        $package_first = PackageD::where('service_id',$result->id)->first();
        $first_package_detail = PackageDetail::where('package_id',$package_first->id)->get();
        return compact('result','package_list','first_package_detail');
    }
}
