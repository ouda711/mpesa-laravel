<?php

namespace App\Repositories;

use App\Http\Traits\SubmitRequestTrait;
use Carbon\Carbon;

class MpesaStkPush
{
    use SubmitRequestTrait;

    protected $consumer_key;
    protected $consumer_sercet;
    protected $passkey;
    protected $amount;
    protected $accountReference;
    protected $phone;
    protected $env;
    protected $short_code;
    protected $parent_short_code;
    protected $initiatorName;
    protected $initiatorPassword;
    protected  $datetime;

    public function __construct()
    {
        $TIMESTAMP = new \DateTime();
        $datetime = $TIMESTAMP->format('YmdHis');
        $this->short_code = 174379;
        $this->parent_short_code = 174379;
        $this->consumer_key = "OGGHJZX9UWoSmedelv5BCjDxOr3QnYmA";
        $this->consumer_sercet = "ePj9SPkRhEJRxg8V";
        $this->passkey = "MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjIwMzE5MTQxNTQz";
        $this->CallBackURL = "https://ouda.com";
        $this->env = "sandbox";
        $this->initiatorName = "testapi";
        $this->initiatorPassword = "Safaricom978!";
        $this->datetime =$datetime;
    }

    /** Lipa na M-PESA password **/
    public function getPassword(): string
    {
        $timestamp = Carbon::now()->format('YmdHms');
        return base64_encode($this->short_code. "" . $this->passkey ."". $timestamp);
    }

    public function lipaNaMpesa($amount,$phone,$accountReference)
    {
        $this->phone = $phone;
        $this->amount=$amount;
        $this->accountReference=$accountReference;

        $Password = $this->getPassword();

        $headers = ['Content-Type:application/json; charset=utf8'];

        $access_token_url = ($this->env  == "live") ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
        $initiate_url = ($this->env == "live") ? "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest" : "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";


        $curl = curl_init($access_token_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_USERPWD, $this->consumer_key.':'.$this->consumer_sercet);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $result = json_decode($result);
        $access_token = $result->access_token;
        curl_close($curl);


        # header for stk push
        $stkheader = ['Content-Type:application/json','Authorization:Bearer '.$access_token];
        # initiating the transaction
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $initiate_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $stkheader); //setting custom header
        $TIMESTAMP = new \DateTime();

        $curl_post_data = array(
            'BusinessShortCode' => 174379,
            'Password' => "MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjIwMzE5MTQxNTQz",
            'Timestamp' =>"20220319141543",
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $this->amount,
            'PartyA' => $phone,
            'PartyB' => 174379,
            'PhoneNumber' => $phone,
            'CallBackURL' => "https://2286-102-140-242-1.ngrok.io/api/mpesa/mpesastk/callback",
            'AccountReference' => "Octagon Payment Server",
            'TransactionDesc' => "Payment of X"
        );

        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        $responseData = curl_exec($curl);

        return $responseData;
    }

    public function status($transaction)
    {
        $access_token = ($this->env == "live") ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_sercet);

        $ch = curl_init($access_token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);


        $token = $result->{'access_token'} ?? "N/A";
        $endpoint = ($this->env == "live") ? "https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query" : "https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query";

        $lipa_time = Carbon::rawParse('now')->format('YmdHms');
        $password = base64_encode($this->short_code . $this->passkey . $this->datetime);
        $headers = array('Content-Type:application/json', 'Authorization:Bearer '.$token);
        $post_string =
            '{
              "BusinessShortCode": '.$this->short_code.',
              "Password": "MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjIwNDEwMjI0NjIw",
              "Timestamp": "20220410224620",
              "CheckoutRequestID": "'.$transaction.'"
            }
            ';
        $result = $this->submitRequest($endpoint, $post_string, $headers, 'POST');
        $result = json_decode($result);
        return $result;
    }
}
