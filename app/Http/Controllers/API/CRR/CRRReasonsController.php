<?php

namespace App\Http\Controllers\API\CRR;

use App\CRRReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRR\DeleteReason;
use App\Http\Requests\CRR\EditReason;
use App\Http\Requests\CRR\ReactivateReason;
use App\Http\Requests\CRR\StoreReason;
use App\Logging\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CRRReasonsController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getCRRReasons() {
        return CRRReason::all();
    }

    public function addReason(StoreReason $request) {
        $inputArr = $request->input();
        if(strtolower($inputArr['isUnwanted']) == "true") {
            $unwanted = "YES";
        } else {
            $unwanted = "NO";
        }
        if(Gate::allows('createCRRReason') === false) {
            return response()->json([
                'message' =>   'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        };
        DB::beginTransaction();
        CRRReason::create([
            'reason' => $inputArr['reason'],
            'skill' => $inputArr['skill'],
            'isunwanted' => $unwanted
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR-ის მიზეზის შექმნა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s შექმნა CRR-ის მიზეზი - %s",
                                                         $request->user('api')->username, $inputArr['reason']));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function reactivateReason(ReactivateReason $request) {
        $inputArr = $request->input();
        if(Gate::allows('reactivateCRRReason') === false) {
            return response()->json([
                                        'message' =>   'unauthorized'
                                    ], config('errorCodes.HTTP_UNAUTHORIZED'));
        };
        $reason = CRRReason::find($inputArr['id']);
        DB::beginTransaction();
        $reason->isactive = "YES";
        $reason->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR-ის მიზეზის აღდგენა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s აღადგინა მიზეზი: %s",
            $request->user('api')->username, $reason->reason));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function editReason(EditReason $request) {
        $inputArr = $request->input();
        if(strtolower($inputArr['isUnwanted']) == "true") {
            $unwanted = "YES";
        } else {
            $unwanted = "NO";
        }
        if(Gate::allows('editCRRReason') === false) {
            return response()->json([
                'message' =>   'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        };
        $reason = CRRReason::find($inputArr['id']);
        $currReason = $reason->reason;
        $currType = $reason->isunwanted;
        DB::beginTransaction();
        $reason->reason = $inputArr['newReason'];
        $reason->isunwanted = $unwanted;
        $reason->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR-ის მიზეზის განახლება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s განაახლა CRR-ის მიზეზი - ძველი: %s, ახალი: %s, ძველი ტიპი: %s, ახალი ტიპი: %s",
                                                         $request->user('api')->username, $currReason, $inputArr['newReason'],
                                                         ($currType == "YES") ? "არასასურველი" : "სასურველი", ($unwanted == "YES") ? "არასასურველი" : "სასურველი"));
        DB::commit();
        return response()->json([
            'message' => 'მიზეზი წარმატებით განახლდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteReason(DeleteReason $request) {
        $inputArr = $request->input();
        if(Gate::allows('deleteCRRReason') === false) {
            return response()->json([
                                        'message' =>   'unauthorized'
                                    ], config('errorCodes.HTTP_UNAUTHORIZED'));
        };
        DB::beginTransaction();
        $reason = CRRReason::find($inputArr['id']);
        $reason->isactive = "NO";
        $reason->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR-ის მიზეზის წაშლა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s გააუქმა CRR-ის მიზეზი - %s",
                                                         $request->user('api')->username, $reason->reason));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
