<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/12/2019
 * Time: 10:31 AM
 */

namespace App\Traits;

use App\Exceptions\ModelAlreadyExists;
use App\Operator;
use App\Sip;
use App\Template;
use App\User;
use App\UserToTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\SipOperatorTrait;


trait UserTrait {



    private function createUser(array $inputArr) {
        if(User::checkUserName($inputArr['userName']) === false) {
            throw new ModelAlreadyExists('ასეთი მომხმარებელი უკვე არსებობს!');
        } else if(User::checkEmail($inputArr['email']) === false) {
            throw new ModelAlreadyExists('ასეთი ელ-ფოსტა უკვე არსებობს! ');
        }
        $template = Template::where('id', $inputArr['templateID'])->first();
        if($template == null) {
            throw new ModelNotFoundException("ასეთი შაბლონი არ არსებობს!");
        }
        $newUser = User::createUser($inputArr);
        UserToTemplate::createBridgeUserTemplate($newUser->id, $inputArr['templateID']);
        return [$newUser, $template];
    }

    private function removeUser(array $inputArr) {
        $user = User::where('id', $inputArr['userID'])->first();
        if($user == null) {
            throw new ModelAlreadyExists('ასეთი მომხმარებელი არ არსებობს!');
        }
        UserToTemplate::where('users_id', $inputArr['userID'])->delete();
        $userArr = $user->toArray();
        $user->delete();
        return $userArr;
    }

    private function removeOperator(array $inputArr) {
        $operator = Operator::find($inputArr['operatorID']);
        $sip = Sip::where('operators_id', $operator->id)->first();
        // if operator currently occupies sip, delete this connection.
        if($sip != null) {
            $this->deleteOperatorSipBridge($operator, $sip);
            $this->updateOperatorSipLogEntry($operator, $sip);
        }
        $operator->delete();
        return [$sip, $operator];
    }

    private function linkOperatorToUser(array $inputArr) {
        $user = User::where('id', $inputArr['userID'])->first();
        if($user == null) {
            throw new ModelAlreadyExists('ასეთი მომხმარებელი არ არსებობს!');
        }
        if(intval($user->operators_id) !== 0) {
            throw new ModelAlreadyExists('ასეთი მომხმარებელი უკვე დაკავებულია!');
        }
        $user->operators_id = $inputArr['operatorsID'];
        $user->save();
        return $user;
    }

    private function unlinkOperatorFromUser(array $inputArr) {
        $user = User::where('id', $inputArr['userID'])->first();
        if($user == null) {
            throw new ModelAlreadyExists('ასეთი მომხმარებელი არ არსებობს!');
        }
        if(intval($user->operators_id) === 0) {
            throw new ModelAlreadyExists('ასეთ მომხმარებელს ოპერატორი გაწერილი არ აქვს!');
        }
        $user->operators_id = 0;
        $user->save();
        return $user->toArray();
    }

}