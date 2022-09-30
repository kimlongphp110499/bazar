<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('image')->nullable();
            $table->string('name')->nullable();
            $table->string('desc')->nullable();
            $table->timestamps();
        });
        Schema::create('packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_id');
            $table->string('image')->nullable();
            $table->string('name')->nullable();
            $table->string('desc')->nullable();
            $table->timestamps();
        });
        Schema::create('package_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('package_id');
            $table->string('desc')->nullable();
            $table->string('key')->nullable();
            $table->string('max_device')->nullable();
            $table->integer('price')->nullable();
            $table->string('exp_day_time')->nullable();
            $table->boolean('defaut_value')->default(0)->nullable();
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
        Schema::dropIfExists('packages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('package_details');
    }
}
