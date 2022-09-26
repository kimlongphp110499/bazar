<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderPackages;

class LeaderBoardController extends Controller
{
    public function lists(Request $requestrequest){

        $top1 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(1)->first();
        $top2 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
        $top3 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id, $top2->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
        $top10 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id, $top2->user_id,$top3->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(10)->get();

        return ['top1' => $top1,
        'top2' => $top2,
        'top3' => $top3,
        'top10' => $top10,
    ];
    }
}
