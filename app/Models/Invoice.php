<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';
    protected $fillable = [
        'order_id',
        'token',
        'subtotal',
        'customer_name',
        'callback_url',
        'amount',
        'status',
        'invoice_no'
    ];
}
