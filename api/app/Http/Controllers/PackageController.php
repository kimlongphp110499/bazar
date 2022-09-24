<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PackageD;

class PackageController extends Controller
{
    public function list(){
        $result = PackageD::get();
        return ['result' => $result];
    }

    public function detail($id){
        $result = PackageD::find($id);
        return ['result' => $result];
    }
}
