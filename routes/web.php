<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/index', function () {
//    return \Illuminate\Support\Facades\Hash::make("zura1234");
//});

# manipulateBonusStats - middleware parsavs arrays da yvela operatoristvs missed calls aklebs konkretul ciprs(baiasvhlis motxovnat)
Route::get('/getBonusStats', 'Bonus\\BonusController@getBonusStats')->middleware('manipulateBonusStats');