<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PackageD;

class PackageController extends Controller
{
    public function list(){
        $resul = Package::get();
        return ['result'=>$result];
    }
}
