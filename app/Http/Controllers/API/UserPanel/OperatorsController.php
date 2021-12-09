<?php

namespace App\Http\Controllers\API\UserPanel;

use App\Exceptions\ModelAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteOperator;
use App\Http\Requests\DeleteOperatorFromSip;
use App\Http\Requests\StoreOperator;
use App\Http\Requests\StoreOperatorToSip;
use App\Http\Requests\TransferOperatorToSip;
use App\Logging\Logger;
use App\Operator;
use App\Rules\OperatorExists;
use App\Sip;
use App\SipToOperatorHistory;
use App\Traits\SipOperatorTrait;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\UserTrait;

class OperatorsController extends Controller {

    use SipOperatorTrait, UserTrait;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    /**
     * this function adds operator or trainee based on given input values.
     *
     *
     * @param StoreOperator $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function addOperator(StoreOperator $request) {
        $this->authorize("createAndDelete", Operator::class);
        $inputArr = $request->input();
//        logger()->error($inputArr);
        DB::beginTransaction();
        $sip = null;
        if($request->has('sipNumber')) {
            $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        }
        if($inputArr['trainee'] == 1) {
            if(isset($inputArr['operatorCardNum']) === false) {
                return response()->json([
                    'სტაჟიორის პირადი ნომერი აუცილებელი ველია!'
                ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
            }
            $newOperator = $this->addOperatorWithID($inputArr, $inputArr['operatorCardNum']);
        } else if($inputArr['trainee'] == 0) {
            $operatorCardNum = $inputArr['operatorCardNum'] ?? null;
            // if operator card number is present it means, in previous request
            // on given first_name and last_name there were 2 employees, so user chose one and we dont need to get card number again.
            if($operatorCardNum == null) {
                $operatorData = $this->getPersonalIdFromHR($inputArr['firstName'], $inputArr['lastName']);
                if(empty($operatorData)) {
                    throw new \RuntimeException("ასეთი ოპერატორი HR Soft_ში არ არსებობს!");
                } else if(count($operatorData) > 1) {
                    return response()->json([
                        'employees' => $operatorData
                    ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
                }
                $operatorCardNum = $operatorData[0]['idCardNumber'];
            }
            $newOperator = $this->addOperatorWithID($inputArr, $operatorCardNum);
        }
        if(isset($newOperator) === false) {
            throw new \RuntimeException("Operator has not been created!");
        }
        $inputArr['operatorsID'] = $newOperator->id;
        if(isset($inputArr['userID'])) {
            $user = $this->linkOperatorToUser($inputArr);
        } else {
            $inputArr['templateID'] = 21; // MISAXEDIA ES
            list($user, $template) = $this->createUser($inputArr);
        }
        if($sip != null) {
            $this->createOperatorSipBridge($newOperator, $sip);
            $this->addOperatorSipLogEntry($newOperator, $sip);
        }
         $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s დაამატა %s - %s %s, სიპი - %s. მომხმარებელი - %s",
             $request->user('api')->username,
             ($inputArr['trainee'] == 0) ? "ოპერატორი" : "სტაჟიორი",
             $inputArr['firstName'], $inputArr['lastName'],
             $inputArr['sipNumber'] ?? "სიპის გარეშე",
             $user->username));
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ოპერატორის დამატება'));
        DB::commit();
        return response()->json([
            'ოპერატორი და მომხარებელი წარმატებით შეიქმნა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * this function adds trainee, it means
     * we dont need to check given name in HR Soft.
     * Creates trainee in database and returns it's object.
     *
     * @param array $inputArr
     * @return mixed
     */
    private function addOperatorWithoutID(array $inputArr) {
        $operator = Operator::where('first_name', $inputArr['firstName'])->where('last_name', $inputArr['lastName'])->first();
        if($operator != null) {
            throw new ModelAlreadyExists("ასეთი სტაჟიორი უკვე არსებობს ბაზაში!");
        }
        $newTrainee = Operator::create([
            'first_name' => $inputArr['firstName'],
            'last_name' => $inputArr['lastName'],
            'trainee' => 1,
            'created_at' => Carbon::now()
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'firstName' => $inputArr['firstName'],
            'lastName' => $inputArr['lastName'],
            'trainee' => 1,
        ]);
        return $newTrainee;
    }

    /**
     * creates real operator(not trainee)
     *
     * Creates operator record in database with his card number and returns object
     *
     * @param array $inputArr
     * @param string $operatorCardNum
     * @return mixed
     */
    private function addOperatorWithID(array $inputArr, string $operatorCardNum) {
        $trainee = Operator::where('first_name', $inputArr['firstName'])->where('last_name', $inputArr['lastName'])->where('trainee', 1)->first();
        if($trainee != null) {
            throw new ModelAlreadyExists('ასეთი სტაჟიორი უკვე არსებობს ბაზაში!');
        }
        $operator = Operator::where('personal_id', $operatorCardNum)->first();
        if($operator != null) {
            throw new ModelAlreadyExists("ოპერატორი უკვე არსებობს ბაზაში!");
        }
        $newOperator = Operator::create([
            'personal_id' => $operatorCardNum,
            'first_name' => $inputArr['firstName'],
            'last_name' => $inputArr['lastName'],
            'trainee' => $inputArr['trainee'],
            'created_at' => Carbon::now()
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'firstName' => $inputArr['firstName'],
            'lastName' => $inputArr['lastName'],
            'personalID' => $operatorCardNum,
            'trainee' => $inputArr['trainee']
        ]);
        return $newOperator;
    }

    /**
     * Gets information for Silknet employee by first and last names
     * from HR Soft web service
     *
     * @param $firstName
     * @param $lastName
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getPersonalIdFromHR($firstName, $lastName) {
        $htppHandler = new Client();
        $res = $htppHandler->request('GET',
            sprintf('http://hr.silknet.com/webresources/employee/list?firstName=%s&lastName=%s', $firstName, $lastName), ['http_errors' => true, 'connect_timeout' => 3]);
        $responseArr = json_decode($res->getBody(), true);
        $this->logger->addLogInfo(__METHOD__, [
            'responseFromHR' => $responseArr
        ]);
        return $responseArr;
    }

    /**
     * function deletes operator, and all its connections from database
     *
     * @param DeleteOperator $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteOperator(DeleteOperator $request) {
        $this->authorize("createAndDelete", Operator::class);
        $inputArr = $request->input();
        DB::beginTransaction();
        // if operator currently occupies sip, delete this connection.
        list($sip, $operator) = $this->removeOperator($inputArr);
        if(strtolower($inputArr['needRemove']) == "true") {
            $userArr = $this->removeUser($inputArr);
        } else {
            $userArr = $this->unlinkOperatorFromUser($inputArr);
        }
        $this->logger->addLogInfo(__METHOD__, [
            'operator' => $operator->toArray(),
            'sip' => ($sip != null) ? $sip->toArray() : null,
            'message' => 'Operator has been deleted'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ოპერატორის წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s წაშალა %s - %s %s, სიპი - %s. მომხმარებელი - %s",
                $request->user('api')->username,
                ($operator->trainee == 0) ? "ოპერატორი" : "სტაჟიორი",
                $operator->first_name, $operator->last_name,
                ($sip != null) ? $sip->sip : "არ იყო გაწერილი",
                $userArr['username']));
        DB::commit();
        return response()->json([
            'ოპერატორი და მომხმარებელი წარმატებით წაიშალა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * based on input values, function retrieves
     * all opeators or one specified by card number with
     * its corresponding sips.
     *
     * @param Request $request
     * @return array|mixed
     */
    public function getOperatorsWithSips(Request $request) {
        if($request->has('operatorID')) {
            $request->validate([
                'operatorID' => [new OperatorExists()]
            ]);
            $operatorsWithSips = Operator::getOperatorWithSip($request->input('operatorID'));
        } else {
            $operatorsWithSips = Operator::getOperatorsWithSips();
        }
//        $this->logger->addLogInfo(__METHOD__, [
//            'getOperators' => 'all',
//            'message' => 'get operators with sips'
//        ]);
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getOperatorsWithSips'));
//        $this->logger->addLogInfo("displayInfo",
//            sprintf("მომხმარებელმა - %s, მოითხოვა ინფორმაცია ოპერატორებზე თავიანთი სიპებით.", $request->user('api')->username));
        return $operatorsWithSips ?? [];
    }

    public function getOperators(Request $request) {
        return Operator::all();
    }

    /**
     * Function pairs operator to sip.
     *
     * @param StoreOperatorToSip $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function addOperatorToSip(StoreOperatorToSip $request) {
        $this->authorize("createAndDelete", Operator::class);
        $inputArr = $request->input();
        $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        $operator = Operator::find($inputArr['operatorID']);
        DB::beginTransaction();
        $this->createOperatorSipBridge($operator, $sip);
        $this->addOperatorSipLogEntry($operator,  $sip);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ოპერატორის სიპზე გაწერა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, სიპზე - %s, მიამაგრა ოპერატორი - %s %s.",
                $request->user('api')->username, $sip->sip, $operator->first_name, $operator->last_name));
        DB::commit();
        return response()->json([
            'message' => sprintf("ოპერატორი გაიწერა სიპზე: %s", $inputArr['sipNumber'])
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * Delete operator to sip connection.
     *
     * @param DeleteOperatorFromSip $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function removeOperatorFromSip(DeleteOperatorFromSip $request) {
        $this->authorize("createAndDelete", Operator::class);
        $inputArr = $request->input();
        $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        $operator = Operator::find($inputArr['operatorID']);
        DB::beginTransaction();
        $this->deleteOperatorSipBridge($operator, $sip);
        $this->updateOperatorSipLogEntry($operator, $sip);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ოპერატორის სიპიდან მოშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, მოხსნა ოპერატორი - %s %s, სიპიდან - %s",
                $request->user('api')->username,
                $operator->first_name,
                $operator->last_name,
                $sip->sip));
        DB::commit();
        return response()->json([
            'ოპერატორი წარმატებით მოიხსნა სიპიდან!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * Function transfers operator from one sip to another.
     *
     * @param TransferOperatorToSip $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function transferOperatorToSip(TransferOperatorToSip $request) {
        $this->authorize("createAndDelete", Operator::class);
        $inputArr = $request->input();
        $operator = Operator::find($inputArr['operatorID']);
        $currSip = Sip::where('sip', $inputArr['currentSip'])->first();
        $newSip = Sip::where('sip', $inputArr['newSip'])->first();
        if($currSip->operators_id != $operator->id) {
            throw new \RuntimeException("ოპერატორის და სიპის კომბინაცია არ არის სწორი!");
        }
        DB::beginTransaction();
        $this->deleteOperatorSipBridge($operator, $currSip);
        $this->createOperatorSipBridge($operator, $newSip);
        $this->updateOperatorSipLogEntry($operator, $currSip);
        $this->addOperatorSipLogEntry($operator, $newSip);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ოპერატორის სიპიდან გადასმა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, გადასვა ოპერატორი - %s %s, სიპიდან - %s, სიპზე - %s.",
                $request->user('api')->username,
                $operator->first_name,
                $operator->last_name,
                $currSip->sip,
                $newSip->sip));
        DB::commit();
        return response()->json([
            'ოპერატორი წარმატებით დატრანსფერდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
