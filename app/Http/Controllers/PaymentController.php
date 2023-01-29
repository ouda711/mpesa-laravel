<?php

namespace App\Http\Controllers;

use App\Http\Traits\SubmitRequestTrait;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Token;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    use SubmitRequestTrait;
    protected  $key;
    protected  $secret;
    protected  $token;
    protected  $password;
    protected  $passkey;
    protected  $merchant_id;
    protected  $datetime;

    public function __construct()
    {
        $TIMESTAMP = new \DateTime();
        $datetime = $TIMESTAMP->format('YmdHis');
        $this->key = config('configuration.mpesa.key');
        $this->secret = config('configuration.mpesa.secret');
        $this->token = Token::where('type','mpesa')->orderby('created_at', 'desc')->first();
        $this->passkey = 'acac3975ea7b99bcd59262c186daee0a60587c643b279192a57b25b8e6b26a47';
        $this->merchant_id = 174379;
        $this->datetime =$datetime;
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

    /**
     * @throws \Exception
     */
    public function mpesaPayApi(Request $request)
    {
        $token = $request->get('token');
        $amount= $request->get('amount');
        $phone=$this->formatPhone($request->get('phone_number'));
        $order_id = $request->get('order_id');
        $customer_name = $request->get('customer_name');
        $type = strtolower($request->get('type'));

        $refNO = bin2hex(random_bytes(10));
        $customer = $request->get('customer_name');
        $subtotal = $request->get('subtotal');
        $amount = $request->get('amount');
        $type = strtolower($request->get('type'));
        $order_id = $request->get('order_id');
        $phone= "254".substr($request->get('phone'),-9);
        $resultCode = "default";
        $password = base64_encode($this->merchant_id . $this->passkey . $this->datetime);
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $headers = array('Content-Type:application/json', 'Authorization:Bearer ' . $this->token->token);

        $post_string =
            '{
            "BusinessShortCode":"174379",
            "Password":"MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjIwMzE5MTQxNTQz",
            "Timestamp":"20220319141543",
            "TransactionType":"CustomerPayBillOnline",
            "Amount":"' . $amount . '",
            "PartyA":"254746764503",
            "PartyB":"174379",
            "PhoneNumber":"254746764503",
            "CallBackURL":"https://4cd9-41-90-185-176.ngrok.io/api/v1/stk/callback",
            "AccountReference":"ref",
            "TransactionDesc":" desc"
            }
            ';

        $result = $this->submitRequest($url, $post_string, $headers, 'POST');

        if($result != FALSE) {
            $result = json_decode($result);
            if(property_exists($result,'errorCode'))
            {
                $resultCode = "mpesaError";
                $resultDesc = $result->errorMessage;
                $MerchantRequestID = 0;
            } else {
                $resultDesc = $result->ResponseDescription;
                $resultCode = $result->ResponseCode;
                $MerchantRequestID = $result->CheckoutRequestID;
                if ($resultCode == 0) {
                    $resultCode = "success";
                    $code = $this->generateCode();

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
                        'merchant_request_id' => $MerchantRequestID,
                        'checkout_request_id' => $MerchantRequestID,
                        'callback_url' => "https://4cd9-41-90-185-176.ngrok.io/api/v1/stk/callback",
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
                        'checkout_request_id' => $MerchantRequestID,
                        'callback_url' => "https://4cd9-41-90-185-176.ngrok.io/api/v1/stk/callback",
                        'amount' => $amount,
                        'status' => 'pending',
                        'invoice_no' => '#inv-'.bin2hex(random_bytes(6)),
                    ]);
                }
                elseif($resultCode == "7")
                {
                    $payment = Payment::where('user_token',$token)->first();
                    $payment->status = "failed";
                    $payment->save();
                }
            }
        }else
        {
            $resultCode = "error";
            $resultDesc = "network error";
            $payment = Payment::where('user_token',$token)->first();
            $payment->status = "failed";
            $payment->save();
        }
        return response()->json([
            "resultCode"=>$resultCode,
            "CheckoutRequestID"=>$MerchantRequestID,
            "resultDesc"=>$resultDesc,
        ]);
    }
}
