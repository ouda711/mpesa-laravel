<?php

namespace App\Http\Traits;

trait SubmitRequestTrait
{
    function submitRequest($url, $post_string, $headers, $type)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if($type == "POST") {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        }

        return curl_exec($ch);
    }

    public function generateCode($codeLength = 5): int
    {
        $min = pow(10, $codeLength);
        $max = $min * 10 - 1;
        return mt_rand($min, $max);
    }

}
