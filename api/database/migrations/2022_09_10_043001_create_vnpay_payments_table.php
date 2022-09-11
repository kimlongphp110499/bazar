<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVnpayPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vnpay_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('p_user_id')->nullable();
            $table->integer('p_money')->nullable();
            $table->string('p_node')->nullable();
            $table->string('p_transaction_code')->nullable();
            $table->string('p_vnp_response_code')->nullable();
            $table->string('p_code_bank')->nullable();
            $table->datetime('p_time')->nullable();
            $table->double('p_code_vnpay')->nullable();
            $table->integer('p_transaction_id');
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
        Schema::dropIfExists('vnpay_payments');
    }
}
