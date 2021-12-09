<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        DB::table('users')->insert([
            'username' => 'toka',
            'first_name' => 'Tornike',
            'last_name' => 'Porchkhidze',
            'email' => 'tporchkhidze@silknet.com',
            'password' => \Illuminate\Support\Facades\Hash::make('kitrebi14'),
            'user_groups_id' => 0,
            'created_at' => \Illuminate\Support\Carbon::now(),
            'last_login' => \Carbon\Carbon::now()
        ]);
    }
}
