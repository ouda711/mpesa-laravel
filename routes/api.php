<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/v1/stk/push', [\App\Http\Controllers\MpesaController::class,'stkPushRequest']);
Route::get('/v1/transaction-status/{transactionCode}', [\App\Http\Controllers\MpesaController::class, 'checkTransactionStatus']);
Route::get('/v1/check-transaction/{checkoutId}', [\App\Http\Controllers\MpesaController::class, 'check']);
