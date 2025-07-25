<?php

use Illuminate\Http\Request;

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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('/store/coupon', 'Api\HomeController@StoreCoupon');
Route::post('/lux/cozi/test', 'Api\HomeController@CoziTest');
Route::get('/lux/cozi/user/data', 'Api\HomeController@CoziUserData');
Route::get('/lux/cozi/send-message', 'Api\HomeController@SendWhatsAppMessage');
