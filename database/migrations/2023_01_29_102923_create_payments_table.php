<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->double('transaction_subtotal');
            $table->string('order');
            $table->string('vendor_pay');
            $table->double('sms_cost');
            $table->integer('transaction_id');
            $table->string('customer_name');
            $table->string('transaction_phone');
            $table->double('transaction_amount');
            $table->dateTime('transaction_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('transaction_type');
            $table->string('callback_url');
            $table->string('callback_status');
            $table->string('merchant_request_id');
            $table->string('checkout_request_id');
            $table->string('status');
            $table->string('vat');
            $table->string('type');
            $table->string('ref');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
