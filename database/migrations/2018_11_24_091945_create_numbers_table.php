<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sips', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sip')->unsigned();
            $table->string('password', 255);
            $table->smallInteger('state');
            $table->integer('operators_id');
            $table->json('queues');
            $table->unique('sip');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('numbers');
    }
}
