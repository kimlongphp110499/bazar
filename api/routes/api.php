<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\OrderPackageController;
use App\Http\Controllers\LeaderBoardController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/list-shop', [PackageController::class, 'service']);
Route::get('/detail-shop/{id}', [PackageController::class, 'service_find']);
Route::get('/package-detail/{id}', [PackageController::class, 'package_detail']);
Route::get('/list-leader-board', [LeaderBoardController::class, 'lists']);
Route::post('/package/create', [PackageController::class, 'package_create']);
Route::post('/package/properties/{id}', [PackageController::class, 'package_properties']);
Route::group(
    ['middleware' => ['auth:sanctum']],
    function () {
    Route::get('/list-order-package', [OrderPackageController::class, 'list']);
    Route::post('/package/checkout', [OrderPackageController::class, 'checkout']);


    }
);