<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/10/2019
 * Time: 6:23 PM
 */

namespace App\Http\Controllers\API;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Lcobucci\JWT\Parser;
use App\User;

class AuthenticationController extends Controller {

    public function login(Request $request) {
        /* @var User $user*/
        $user = User::where('username', $request->input('username'))->first();
        if(!$user) {
            return "Invalid Credentials";
        }
        $token = $user->createToken('silknet_call_centre Password Grant Client')->token;
        return $token;
    }

    public function logout(Request $request) {

        $request->user()->token()->revoke();
    }

}