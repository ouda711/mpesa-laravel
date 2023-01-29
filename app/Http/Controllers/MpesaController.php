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

    public function lipaNaMpesaPassword(): string
    {
        $timestamp = \Illuminate\Support\Carbon::rawParse('now')->format('YmdHms');
        $passkey = env('MPESA_PASSKEY');
        $businessShortCode = env('MPESA_SHORTCODE');
        $mpesaPassword = base64_encode($businessShortCode.$passkey.$timestamp);

        return $mpesaPassword;

    }

    public function getAccessToken()
    {
        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $credentials = env('MPESA_CONSUMER_KEY').':'.env('MPESA_CONSUMER_SECRET');

        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            array(

                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_USERPWD => $credentials,
            )
        );
        $response = json_decode(curl_exec($curl));
        curl_close($curl);

        return $response->access_token;

    }

    public function registerURLS(){

        $body = array(
            'ShortCode' => env('MPESA_SHORTCODE'),
            'ResponseType' => 'Completed',
            'ConfirmationURL' => env('MPESA_TEST_URL').'/api/mobile-money/confirmation',
            'ValidationURL' => env('MPESA_TEST_URL').'/api/mobile-money/validation',
        );

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl'
            : 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));

        return curl_exec($curl);

    }

    public function simulateTransaction(Request $request){
        $body = array(
            'CommandID' => 'CustomerPayBillOnline',
            'Amount' => $request->amount,
            'Msisdn' => env('MPESA_TEST_MSISDN'),
            'BillRefNumber' => $request->account,
            'ShortCode' => env('MPESA_SHORTCODE'),

        );

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate'
            : 'https://api.safaricom.co.ke/mpesa/c2b/v1/simulate';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));

        $response = $response = curl_exec($curl);
        return $response;

    }

    public function phoneValidator($phone)
    {
        // Some validations for the phone number to format it to the required format
        $phone = (str_starts_with($phone, "+")) ? str_replace("+", "", $phone) : $phone;
        $phone = (str_starts_with($phone, "0")) ? preg_replace("/^0/", "254", $phone) : $phone;
        return (str_starts_with($phone, "7")) ? "254{$phone}" : $phone;
    }

    /**
     * @throws \Exception
     */
    public function stkPush(Request $request)
    {
        $accountReference='Transaction#'.Str::random(10);

        $amount= $request->get('amount');
        $phone=$this->formatPhone($request->get('phone_number'));
        $order_id = $request->get('order_id');
        $token = $request->get('token');
        $customer_name = $request->get('customer_name');
        $type = strtolower($request->get('type'));

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $curl_post_data = array(
            'BusinessShortCode' => 174379,
            'Password' => "MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjIwMzE5MTQxNTQz",
            'Timestamp' =>"20220319141543",
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => 174379,
            'PhoneNumber' => $phone,
            'CallBackURL' => "https://839f-41-90-185-176.ngrok.io/api/mobile-money/stk/callbackurl",
            'AccountReference' => "TEST Payment Server",
            'TransactionDesc' => "Payment of X"

        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $response = curl_exec($curl);

        if($response != FALSE)
        {
            $invalid = json_decode($response);

            if(property_exists($invalid,'errorCode')){
                $resultCode = "mpesaError";
                $resultDesc = $invalid->errorMessage;
                $MerchantRequestID = 0;
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
                    'callback_url' => " https://4cd9-41-90-185-176.ngrok.io/api/v1/stk/callback",
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
                    'callback_url' => " https://4cd9-41-90-185-176.ngrok.io/api/v1/stk/callback",
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
        else{
            $resultCode = "error";
            $resultDesc = "network error";
            $payment = Payment::where('user_token',$token)->first();
            $payment->status = "failed";
            $payment->save();
        }
        return response()->json([
            "resultCode"=>$resultCode,
            "resultDesc"=>$resultDesc,
        ]);
    }

    public function b2cRequest(Request $request)
    {
        $amount = $request->amount;
        $phone =  $request->phone;
        $remarks = $request->remarks;
        $occasion = $request->occasion;
        $formatedPhone = substr($phone, 1);//721223344
        $code = "254";
        $phoneNumber = $code.$formatedPhone;//254721223344

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest'
            : 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';

        $curl_post_data = array(
            'InitiatorName' => env('MPESA_B2C_INITIATOR'),
            'SecurityCredential' => env('MPESA_B2C_PASSWORD'),
            'CommandID' => 'BusinessPayment',
            'Amount' => $amount,
            'PartyA' => env('MPESA_SHORTCODE'),
            'PartyB' => $phoneNumber,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => env('MPESA_TEST_URL') . '/api/mobile-money/b2ctimeout',
            'ResultURL' => env('MPESA_TEST_URL') . '/api/mobile-money/b2c/result',
            'Occasion' => $occasion
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $response = curl_exec($curl);
        return $response;

    }

    public function transactionStatus(Request $request)
    {
        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query'
            : 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query';


        $curl_post_data =  array(
            'Initiator' => env('MPESA_B2C_INITIATOR'),
            'SecurityCredential' => env('MPESA_B2C_PASSWORD'),
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $request->transactionid,
            'PartyA' => env('MPESA_SHORTCODE'),
            'IdentifierType' => '4',
            'ResultURL' => env('MPESA_TEST_URL').'/api/mobile-money/transaction/response',
            'QueueTimeOutURL' => env('MPESA_TEST_URL').'/api/mobile-money/transaction-status/timeout_url',
            'Remarks' => 'CheckTransaction',
            'Occasion' => 'VerifyTransaction'
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $response = curl_exec($curl);
        return $response;

    }


    public function responseUrl(Request $request)
    {
        $response = json_decode($request->getContent());

        $payment = Payment::where('checkout_request_id', $response->Body->stkCallback->CheckoutRequestID)->first();
        $invoice = Invoice::where('order_id', $payment->order)->first();


        $phone = $payment->transaction_phone;

        $resultCode = $response->Body->stkCallback->ResultCode;

        if($resultCode != null){
            if ($resultCode == "1032"){
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
            elseif ($resultCode == "1019"){
                $payment->callback_status = "expired";
                $payment->status = "expired";
                $payment->save();

                if(!empty($invoice)){
                    $invoice->status = "expired";
                    $invoice->update();

                    $parameters = [
                        'message'   => 'This transaction is expired. Please try again.', // the actual message
                        'sender_id' => 'TEST', // please always maintain capital letters. possible value: TEST, IPM, MOBIKEZA
                        'recipient' => $phone, // always begin with country code. Let us know any country you need us to enable.
                        'type'      => 'plain', // possible value: plain, mms, voice, whatsapp, default plain
                    ];
                }

                return response()->json([
                    'success'=>false,
                    'message' => 'Transaction expired'
                ]);
            }
            elseif ($resultCode == "1"){
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
            }
            elseif ($resultCode == "8"){
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
            }
            elseif ($resultCode == "17"){
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
            }
            elseif ($resultCode == "26"){
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
            }
            elseif ($resultCode == "0"){
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
        }
    }

    public function reverseTransaction(Request $request){

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/reversal/v1/request'
            : 'https://api.safaricom.co.ke/mpesa/reversal/v1/request';

        $curl_post_data = array(
            'Initiator' => env('MPESA_B2C_INITIATOR'),
            'SecurityCredential' => env('MPESA_B2C_PASSWORD'),
            'CommandID' => 'TransactionReversal',
            'TransactionID' => $request->transactionid,
            'Amount' => $request->amount,
            'ReceiverParty' => env('MPESA_SHORTCODE'),
            'RecieverIdentifierType' => '11',
            'ResultURL' => env('MPESA_TEST_URL') . '/api/reversal/result',
            'QueueTimeOutURL' => env('MPESA_TEST_URL') . '/api/reversal/timeout_url',
            'Remarks' => 'ReversalRequest',
            'Occasion' => 'ErroneousPayment'
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $response = curl_exec($curl);
        return $response;

    }

    public function checkBalance(Request $request){

        $url = env('MPESA_ENV') == 0
            ? 'https://sandbox.safaricom.co.ke/mpesa/accountbalance/v1/query'
            : 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query';

        $curl_post_data = array(
            'Initiator' => env('MPESA_B2C_INITIATOR'),
            'SecurityCredential' => env('MPESA_B2C_PASSWORD'),
            'CommandID' => 'AccountBalance',
            'IdentifierType' => '4',
            'PartyA' => env('MPESA_SHORTCODE'),
            'ResultURL' => env('MPESA_TEST_URL') . '/api/mobile-money/balance/result',
            'QueueTimeOutURL' => env('MPESA_TEST_URL') . '/api/balance/timeout_url',
            'Remarks' => 'BalanceCheck',

        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer '.$this->getAccessToken()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));

        $response = curl_exec($curl);
        return $response;

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
