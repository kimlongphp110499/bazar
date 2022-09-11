<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_name')->nullable();
            $table->unsignedBigInteger('package_id');
            $table->integer('max_device')->default(0)->nullable();
            $table->datetime('expTime')->nullable();
            $table->integer('expDayTime')->nullable();
            $table->string('license_key')->nullable();
            $table->boolean('defaut_value')->default(0)->nullable();
            $table->boolean('status')->default(0)->nullable();
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
        Schema::dropIfExists('user_packages');
    }
}
