<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\MpesaResponsesController;

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

Route::get('/v1/mpesa/c2b',[\App\Http\Controllers\MpesaController::class,'c2b_register_url']);
Route::post('/v1/stk/push', [\App\Http\Controllers\PaymentController::class,'mpesaPayApi']);
Route::post('/v1/stk/callback', [\App\Http\Controllers\MpesaController::class,'stkCallback']);
Route::post('stk/confirm',[\App\Http\Controllers\MpesaController::class,'c2b_confirm_url']);
Route::post('stk/validate',[\App\Http\Controllers\MpesaController::class,'c2b_validate_url']);
Route::get('stk/validate',[\App\Http\Controllers\MpesaController::class,'c2b_validate_url']);
Route::get('stk/confirm',[\App\Http\Controllers\MpesaController::class,'c2b_confirm_url']);
Route::get('/v1/transaction-status/{transactionCode}', [\App\Http\Controllers\MpesaController::class, 'checkTransactionStatus']);
Route::get('/v1/check-transaction/{checkoutId}', [\App\Http\Controllers\MpesaController::class, 'check']);


Route::post('/mobile-money/confirmation', [MpesaResponsesController::class, 'confirmation']);
Route::post('/mobile-money/validation', [MpesaResponsesController::class, 'validation']);
Route::post('/mobile-money/b2c/result', [MpesaResponsesController::class, 'b2cResult']);
Route::post('/mobile-money/transaction/response', [MpesaResponsesController::class, 'transactionResponse']);
Route::post('/mobile-money/stkresult', [MpesaResponsesController::class, 'stkLog']);
Route::post('/mobile-money/reversal/result', [MPESAController::class, 'reversalResponse']);
Route::post('/mobile-money/balance/result', [MPESAController::class, 'balanceResponse']);


Route::post('/mobile-money/get-token', [MpesaController::class, 'getAccessToken']);
Route::post('/mobile-money/register-url', [MpesaController::class, 'registerURLS']);
Route::post('/mobile-money/simulate', [MpesaController::class, 'simulateTransaction']);
Route::get('/mobile-money/password', [MpesaController::class, 'lipaNaMpesaPassword']);
Route::post('/mobile-money/stk/push', [MpesaController::class, 'stkPush']);
Route::post('/mobile-money/stk/callbackurl', [MpesaController::class, 'responseUrl']);
Route::post('/mobile-money/b2c', [MpesaController::class, 'b2cRequest']);
Route::post('/mobile-money/transaction-status', [MpesaController::class, 'transactionStatus']);
Route::post('/mobile-money/reversal', [MpesaController::class, 'reverseTransaction']);
Route::post('/mobile-money/balance', [MpesaController::class, 'checkBalance']);
