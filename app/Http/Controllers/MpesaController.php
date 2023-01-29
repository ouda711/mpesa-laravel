<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Repositories\MpesaStkPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class MpesaController extends BaseController
{
    /**
     * @throws \Exception
     */
    public function stkPushRequest(Request $request)
    {
        $accountReference='Transaction#'.Str::random(10);

        $amount= $request->get('amount');
        $phone=$this->formatPhone($request->get('phone_number'));
        $order_id = $request->get('order_id');
        $token = $request->get('token');
        $customer_name = $request->get('customer_name');
        $type = strtolower($request->get('type'));

        $mpesa=new MpesaStkPush();
        $stk=$mpesa->lipaNaMpesa($amount,$phone,$accountReference);
        $invalid=json_decode($stk);

        if(@$invalid->errorCode){
            Session::flash('mpesa-error', 'Invalid phone number!');
            Session::flash('alert-class', 'alert-danger');

            return response()->json([
                $invalid
            ]);
        }

        if($invalid->ResponseCode === "0"){
            $refNO = bin2hex(random_bytes(10));
            Payment::create([
                'ref' => "P001-".$refNO,
                'transaction_amount' => $amount,
                'vendor_pay' => 0,
                'sms_cost' => 0,
                'transaction_subtotal' => 1,
                'order' => $order_id,
                'transaction_id' => '1',
                'customer_name' => $customer_name,
                'transaction_phone' => $phone,
                'transaction_type' => 'payment',
                'merchant_request_id' => $invalid->MerchantRequestID,
                'checkout_request_id' => $invalid->CheckoutRequestID,
                'callback_url' => "https://2286-102-140-242-1.ngrok.io/api/mpesa/mpesastk/callback",
                'consignment'=> "0",
                'callback_status' => 'pending',
                'status' => 'pending',
                'vat' => 0,
                'type' => $type,
            ]);

            Invoice::create([
                'order_id' => $order_id,
                'token' => $token,
                'subtotal'=>$amount,
                'customer_name'=>$customer_name,
                'checkout_request_id' => $invalid->CheckoutRequestID,
                'callback_url' => "https://2286-102-140-242-1.ngrok.io/api/mpesa/mpesastk/callback",
                'amount' => $amount,
                'status' => 'pending',
                'invoice_no' => '#inv-'.bin2hex(random_bytes(6)),
            ]);

            return response()->json([
                "transaction_reference" => $accountReference,
                "MerchantRequestID"=>$invalid->MerchantRequestID,
                "CheckoutRequestID" => $invalid->CheckoutRequestID,
                "ResponseCode" => $invalid->ResponseCode,
                "ResponseDescription" => $invalid->ResponseDescription,
                "CustomerMessage" => $invalid->CustomerMessage
            ]);
        }
        return response()->json([
            "ResponseCode" => "error",
            "ResponseDescription" => "Network error",
        ]);
    }


    public function checkTransactionStatus($transactionCode){

        $mpesa=new MpesaStkpush();
        $status=$mpesa->status($transactionCode);

        $payment = Payment::where('checkout_request_id', $transactionCode)->first();
        $invoice = Invoice::where('order_id', $payment->order)->first();

        $phone = $payment->transaction_phone;

        if(property_exists($status,'ResultCode'))
        {
            if ($status->ResultCode == "1032"){
                $payment->callback_status = "cancelled";
                $payment->status = "cancelled";
                $payment->save();

                if(!empty($invoice)){
                    $invoice->status = "cancelled";
                    $invoice->update();
                }

                $parameters = [
                    'message'   => 'Transaction was cancelled by the user', // the actual message
                    'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                    'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                    'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                ];

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction cancelled by user'
                ]);
            }
            elseif ($status->ResultCode == "1019"){
                $payment->callback_status = "expired";
                $payment->status = "expired";
                $payment->save();

                if(!empty($invoice)) {
                    $invoice->status = "expired";
                    $invoice->update();

                    $parameters = [
                        'message' => 'This transaction is expired. Please try again.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type' => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }
                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction expired'
                ]);
            }
            elseif ($status->ResultCode == "1"){
                $payment->callback_status = "failed";
                $payment->status = "failed";
                $payment->save();
                if(!empty($invoice)){
                    $invoice->status = "failed";
                    $invoice->update();

                    $parameters = [
                        'message'   => 'Transaction failed. Sorry, you do not have sufficient funds to complete this transaction, please recharge your account and try again.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction failed due to insufficient funds'
                ]);
            }elseif ($status->ResultCode == "8"){
                $payment->callback_status = "failed";
                $payment->status = "failed";
                $payment->save();
                if(!empty($invoice)){
                    $invoice->status = "failed";
                    $invoice->update();

                    $parameters = [
                        'message'   => 'This transaction was rejected as the amount would exceed your maximum balance.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction failed as the amount would exceed maximum balance'
                ]);
            }elseif ($status->ResultCode == "17"){
                $payment->callback_status = "failed";
                $payment->status = "failed";
                $payment->save();
                if(!empty($invoice)){
                    $invoice->status = "failed";
                    $invoice->update();

                    $parameters = [
                        'message'   => 'Transaction failed. An internal error occurred.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction failed. An internal error occurred.'
                ]);
            }elseif ($status->ResultCode == "26"){
                $payment->callback_status = "failed";
                $payment->status = "failed";
                $payment->save();
                if(!empty($invoice)){
                    $invoice->status = "failed";
                    $invoice->update();

                    $parameters = [
                        'message'   => 'Transaction failed. Traffic blocking condition in place.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction failed. Traffic blocking condition in place.'
                ]);
            }elseif ($status->ResultCode == "0"){
                $status = "success";
                $headers = array('Content-Type:application/json');

                if($payment->callback_status != 'complete')
                {
                    $payment->callback_status = "complete";
                    $payment->status = "complete";
                    $payment->save();

                    if(!empty($invoice)){
                        $invoice->status = "complete";
                        $invoice->update();

                        $parameters = [
                            'message'   => 'Success. The payment you made to TEST was successful. Thank you', // the actual message
                            'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                            'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                            'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                        ];
                    }
                }
            }
            return response()->json([
                "success" => true,
                "ResponseCode" => $status->ResponseCode,
                "ResponseDescription" => $status->ResponseDescription,
                "MerchantRequestID" => $status->MerchantRequestID,
                "CheckoutRequestID" => $status->CheckoutRequestID,
                "ResultCode" => $status->ResultCode,
                "ResultDesc" => $status->ResultDesc,
            ]);
        }


        if(property_exists($status,'errorCode'))
        {
            return response()->json([
                "success" => false,
                "errorCode"=>$status->errorCode,
                "errorMessage"=>$status->errorMessage
            ]);
        }
    }

    public function formatPhone($phone)
    {
        $phone = 'hfhsgdgs' . $phone;
        $phone = str_replace('hfhsgdgs0', '', $phone);
        $phone = str_replace('hfhsgdgs', '', $phone);
        $phone = str_replace('+', '', $phone);
        if (strlen($phone) == 9) {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}
