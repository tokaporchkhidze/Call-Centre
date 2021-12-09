<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/9/2019
 * Time: 4:29 PM
 */

namespace App\Http\Controllers\API;



use App\Common\MailHandler;
use App\Exceptions\ModelAlreadyExists;
use App\Http\Requests\ChangePassword;
use App\Http\Requests\PasswordReset\CheckToken;
use App\Http\Requests\PasswordReset\InitiatePasswordReset;
use App\Http\Requests\PasswordReset\ResetPassword;
use App\Http\Requests\StoreUser;
use App\Operator;
use App\Queue;
use App\Task;
use App\Template;
use App\UserToTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Logging\Logger;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Traits\UserTrait;
use App\Traits\SipOperatorTrait;

use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller {

    use UserTrait, SipOperatorTrait;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * Route: post('/addUser', 'UsersController@addUser')->middleware('checkApiHeader');
     * @param StoreUser $request
     * @throws
     * @return Response
     */
    public function addUser(StoreUser $request) {
        $inputArr = $request->input();
        $this->authorize('create', [User::class, Template::where("id", $inputArr['templateID'])->first()->priority]);
        DB::beginTransaction();
        list($newUser, $template) = $this->createUser($inputArr);
        $this->logger->addLogInfo(__METHOD__, [
            'userName' => $inputArr['userName'],
            'email' => $inputArr['email'],
            'templateID' => $inputArr['templateID'],
            'operatorsID' => $inputArr['operatorsID'] ?? 0
        ]);
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s დაამატა ახალი მომხმარებელი: %s %s[%s], %s_ის უფლებებით",
                $request->user('api')->username,
                $newUser->first_name, $newUser->last_name, $newUser->username,
                $template->display_name));
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.მომხმარებლის დამატება'));
        DB::commit();
        return response()->json([
            'მომხმარებელი წარმატებით დაემატა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function editUser(Request $request) {
        die();
        $inputArr = $request->input();
        $this->authorize('create', [User::class, Template::where("id", $inputArr['templateID'])->first()->priority]);
//        DB::beginTransaction();
        $user = User::getUserWithTemplateById($inputArr['userID']);
        $userModel = User::find($inputArr['userID']);
        $userModel->username = $inputArr['userName'];
        if(isset($inputArr['password'])) $userModel->password = Hash::make($inputArr['password']);
        $userModel->email = $inputArr['email'];
        $userModel->first_name = $inputArr['firstName'];
        $userModel->last_name = $inputArr['lastName'];
//        DB::commit();
    }

    /**
     * delete user and its relation with template
     * Route: post('/deleteUser', 'UsersController@deleteUser')->middleware('checkApiHeader');
     * @param Request $request
     * @throws
     */
    public function deleteUser(Request $request) {
        $inputArr = $request->input();
        $this->authorize('delete', [User::class, Template::getTemplateByUserId($inputArr['userID'])['priority']]);
        DB::beginTransaction();
        $userArr = $this->removeUser($inputArr);
        if(intval($userArr['operators_id']) !== 0) {
            $inputArr['operatorID'] = $userArr['operators_id'];
            list($sip, $operator) = $this->removeOperator($inputArr);
        }
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა %s წაშალა მომხარებელი: %s %s[%s]. ოპერატორი - %s, სიპი - %s",
                $request->user('api')->username,
                $userArr['first_name'], $userArr['last_name'], $userArr['username'],
                (isset($operator) ? sprintf("%s %s", $operator->first_name, $operator->last_name) : ""),
                (isset($sip) ? sprintf("%s", $sip->sip) : "")));
        $this->logger->addLogInfo(__METHOD__, [
            'userInfo' => $userArr
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.მომხმარებლის წაშლა'));
        DB::commit();
        return response()->json([
            'მომხმარებელი წარმატებით წაიშალა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function changePassword(ChangePassword $request) {
        $inputArr = $request->input();
        $user = User::find($inputArr['userID']);
        if(Hash::check($inputArr['currentPass'], $user->password) === false) {
            throw new \RuntimeException('Incorrect Password');
        }
        DB::beginTransaction();
        $user->password = Hash::make($inputArr['password']);
        $user->save();
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s პაროლი განაახლა!", $request->user('api')->username));
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.პაროლის განახლება'));
        DB::commit();
        return response()->json([
            'პაროლი წარმატებით განახლდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * @param Request $request
     * @return string
     */
    public function getCurrentUser(Request $request) {
        $user = $request->user();
        $user->tasks = Task::getTasksByUserId($user->id);
        $user->queues = Queue::getQueuesByUserId($user->id);
        $user->template = Template::getTemplateByUserId($user->id);
        $user->operator = Operator::getOperatorWithSip($user->operators_id);
//        $this->logger->addLogInfo(__METHOD__, [
//            'message' => 'get user info for current user'
//        ]);
//        $this->logger->addLogInfo("displayInfo", sprintf("მოხმარებელმა - %s მოითხოვა საკუთარი ინფორმაცია", $user->username));
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getCurrentUser'));
        if($user === null) {
            return "unAuthenticated";
        } else {
            return $user;
        }
    }

    /**
     * @return User[]|\Illuminate\Database\Eloquent\Collection
     * @throws
     */
    public function getUsersWithTemplates() {
        $this->authorize('view', User::class);
        $userWithTemplate = User::getUsersWithTemplates();
//        $this->logger->addLogInfo(__METHOD__, [
//            'userWithTemplate' => $userWithTemplate
//        ]);
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s მოითხოვა ყველა იუზერი თავიანთი შაბლონებით", request()->user('api')->username));
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getUsersWithTemplates'));
        return $userWithTemplate;
    }

    public function getUsersCountByTemplateId(Request $request) {
        $templateID = $request->input('templateId');
        $userToTemplateCount = UserToTemplate::where('templates_id', $templateID)->count();
        $templateName = Template::where('id', $templateID)->first()->display_name;
//        $this->logger->addLogInfo(__METHOD__, [
//            'userToTemplateCount' => $userToTemplateCount
//        ]);
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getUsersCountByTemplate'));
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s მოითხოვა %s შაბლონზე მიბმული იუზერების რაოდენობა",
//            $request->user('api')->username, $templateName));
        return $userToTemplateCount;
    }

    /**
     * revoke access token from current user
     *
     * @param Request $request
     */
    public function logout(Request $request) {
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სისტემიდან გასვლა'));
        $this->logger->addLogInfo("displayInfo", "მომხმარებელი - %s გავიდა სისტემიდან");
        $request->user()->token()->revoke();
    }

    public function logoutFromAllDevices(Request $request) {
        $request->validate([
            'userID' => ['required', 'integer']
        ]);
        $inputArr = $request->input();
        DB::beginTransaction();
        $rowsCount = User::deleteAllTokensByUserID($inputArr['userID']);
        $user = User::where('id', $inputArr['userID'])->first();
        $this->logger->addLogInfo("API", config("logging.mongo_mapping.Logout user from all devices"));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - სისტემიდან გაიყვანა %s", $request->user('api')->username, $user->username));
        DB::commit();
        return response()->json([
            'deviceCount' => $rowsCount
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function initiatePasswordReset(InitiatePasswordReset $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        if(isset($inputArr['userName'])) {
            $user = User::where('username', $inputArr['userName'])->first();
        } else {
            $user = User::where('email', $inputArr['email'])->first();
        }
        if(isset($user) === false) {
            return response()->json([
                'არასწორი მეილი ან მომხმარებლის სახელი!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $passResetToken = Hash::make(Str::random(10));
        $user->pass_reset_token = $passResetToken;
        $user->save();
        $mailhandler = new MailHandler();
        $mailhandler->configureSMTP();
        $mailhandler->addRecipients([$user->email]);
        $mailhandler->addContent('new.livege.net password reset', sprintf("პაროლის დასარესეტებლად გადადით ლინკზე: %s", sprintf(config('app.pass_reset_url'), urlencode($passResetToken))), "UTF-8", "base64");
        $mailhandler->sendMail();
        $this->logger->addLogInfo("API", config("logging.mongo_mapping.პაროლის აღდგენის ინიცირება"));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - პაროლის აღდგენა დაიწყო", $user->username));
        return response()->json([
            'message' => 'თქვენს მეილზე გამოიგზავნა ინსტრუქცია!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function checkPasswordResetToken(CheckToken $request) {
        $inputArr = $request->input();
        $decodedToken = urldecode($inputArr['passResetToken']);
        $user = User::where('pass_reset_token', $decodedToken)->first();
        if(isset($user) === false) {
            throw new \RuntimeException('არასწორი მისამართი პაროლის აღსადგენად!');
        }
        return response()->json([
            'id' => $user->id
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function resetPassword(ResetPassword $request) {
        $inputArr = $request->input();
        $user = User::find($inputArr['userID']);
        if(isset($user->pass_reset_token) === false) {
            throw new \RuntimeException("მოცემული მომხმარებლისთვის პაროლის აღდგენის პროცედურა არ არის დაწყებული!");
        }
        $user->pass_reset_token = null;
        $user->password = Hash::make($inputArr['password']);
        DB::beginTransaction();
        $user->save();
        $rowsCount = User::deleteAllTokensByUserID($inputArr['userID']);
        $this->logger->addLogInfo("API", config("logging.mongo_mapping.პაროლის აღდგენის დასრულება"));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - პაროლი აღადგინა", $user->username));
        DB::commit();
        return response()->json([
            'message' => 'პაროლი წარმატებით განახლდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
