<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = "payments";

    protected $fillable = [
        'transaction_subtotal',
        'order',
        'vendor_pay',
        'sms_cost',
        'transaction_id',
        'customer_name',
        'transaction_phone',
        'transaction_amount',
        'transaction_date',
        'transaction_type',
        'callback_url',
        'callback_status',
        'merchant_request_id',
        'checkout_request_id',
        'status',
        'vat',
        'type',
        'ref'
    ];
}
