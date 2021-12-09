<?php

namespace App\Http\Controllers\API\B2BMailSupport;

use App\B2BMailReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\B2BMailSupport\DeleteB2BMailReason;
use App\Http\Requests\B2BMailSupport\ReactivateB2BMailReason;
use App\Http\Requests\B2BMailSupport\StoreB2BMailReason;
use App\Logging\Logger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class B2BMailReasonsController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getB2BMailReasons(Request $request) {
        $reasonType = strtolower($request->input('reasonType'));
        if($reasonType == 'all') {
            return B2BMailReason::all();
        } else if($reasonType == 'active') {
            return B2BMailReason::where('isActive', 'YES')->get();
        } else if($reasonType == 'notactive') {
            return B2BMailReason::where('isActive', 'NO')->get();
        }
    }

    public function addB2BMailReason(StoreB2BMailReason $request) {
        $inputArr = $request->input();
        if(Gate::allows('addB2BMailReason') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        DB::beginTransaction();
        $newReason = B2BMailReason::create([
            'reason' => $inputArr['reason']
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.B2B Mail Reason-ის შექმნა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - შექმნა B2B Mail Reason, Reason - %s",
                                                         $request->user('api')->username, $newReason->reason));
        DB::commit();
        return response()->json([
            'message' => 'მიზეზი წარმატებით დაემატა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteB2BMailReason(DeleteB2BMailReason $request) {
        $inputArr = $request->input();
        if(Gate::allows('deleteB2BMailReason') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        DB::beginTransaction();
        $b2bReason = B2BMailReason::find($inputArr['reasonID']);
        $b2bReason->isActive = 'NO';
        $b2bReason->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.B2B Mail Reason-ის გაუქმება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - გააუქმა B2B Mail Reason, Reason - %s",
                                                         $request->user('api')->username, $b2bReason->reason));
        DB::commit();
        return response()->json([
            'message' => 'მიზეზი წარმატებით გაუქმდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function reactivateB2BMailReason(ReactivateB2BMailReason $request) {
        $inputArr = $request->input();
        if(Gate::allows('reactivateB2BMailReason') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        DB::beginTransaction();
        $b2bReason = B2BMailReason::find($inputArr['reasonID']);
        $b2bReason->isActive = 'YES';
        $b2bReason->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.B2B Mail Reason-ის აღდგენა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - აღადგინა B2B Mail Reason, Reason - %s",
                                                         $request->user('api')->username, $b2bReason->reason));
        DB::commit();
        return response()->json([
            'message' => 'მიზეზი წარმატებით გააქტიურდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
