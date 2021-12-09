<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNumbersToOperatorsHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('numbers_to_operators_history', function (Blueprint $table) {
            $table->increments('id');
            $table->string('personal_id', 45);
            $table->integer('sip');
            $table->datetime('paired_at');
            $table->datetime('removed_at');
            $table->index(['personal_id', 'paired_at']);
            $table->index(['sip', 'paired_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('numbers_to_operators_history');
    }
}
