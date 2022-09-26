<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderPackages;
use Carbon\Carbon;

class LeaderBoardController extends Controller
{
    public function lists(Request $request){
        if($request->week)
        {
            $current_week = Carbon::today()->week-2;
            $top1 = OrderPackages::selectRaw('week(order_packages.created_at), users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('week(order_packages.created_at) = '. $current_week)->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(1)->first();
            $top2 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('week(order_packages.created_at) ='.  $current_week)->whereNotIn('user_id',[$top1->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top3 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('week(order_packages.created_at) ='. $current_week)->whereNotIn('user_id',[$top1->user_id, $top2->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top10 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('week(order_packages.created_at) ='. $current_week)->whereNotIn('user_id',[$top1->user_id, $top2->user_id,$top3->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(10)->get();
        }
        if($request->month)
        {
            $top1 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('month(order_packages.created_at) ='. Carbon::today()->month)->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(1)->first();
            $top2 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('month(order_packages.created_at) ='. Carbon::today()->month)->whereNotIn('user_id',[$top1->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top3 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('month(order_packages.created_at) ='. Carbon::today()->month)->whereNotIn('user_id',[$top1->user_id, $top2->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top10 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')
            ->whereRaw('month(order_packages.created_at) ='. Carbon::today()->month)->whereNotIn('user_id',[$top1->user_id, $top2->user_id,$top3->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(10)->get();
        }
        else if(!$request->month && !$request->week) {
            $top1 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(1)->first();
            $top2 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top3 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id, $top2->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(2)->first();
            $top10 = OrderPackages::selectRaw('users.name, order_packages.user_id ,sum(price) as total')->fromRaw('users, order_packages')->whereRaw('users.id = order_packages.user_id')->whereNotIn('user_id',[$top1->user_id, $top2->user_id,$top3->user_id])->groupByRaw('order_packages.user_id')->orderBy('total','DESC')->limit(10)->get();
    
        }
     
        return ['top1' => $top1,
        'top2' => $top2,
        'top3' => $top3,
        'top10' => $top10,
    ];
    }
}
