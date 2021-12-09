<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 30);
            $table->string('email', 255);
            $table->string('first_name', 45);
            $table->string('last_name', 45);
            $table->string('password');
            $table->dateTime('created_at');
            $table->dateTime('last_login');
            $table->integer('user_groups_id');
            $table->rememberToken();
            $table->unique('username');
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
