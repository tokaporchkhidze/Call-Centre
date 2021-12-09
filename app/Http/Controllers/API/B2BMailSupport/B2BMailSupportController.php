<?php

namespace App\Http\Controllers\API\B2BMailSupport;

use App\B2BMailSupport;
use App\Http\Requests\B2BMailSupport\StoreB2BMail;
use App\Http\Requests\B2BMailSupport\UpdateB2BMail;
use App\Jobs\CrrExcelGenerator;
use App\Logging\Logger;
use App\Operator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class B2BMailSupportController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getB2BMailsByOperators(Request $request) {
        $inputArr = $request->input();
        $filePath = sprintf("%s/%s/%s", config('crrReporting.REPORTS_DIR'), 'B2BMailReport', sprintf("b2bMailReport_%s_%s.xlsx",
            date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report already exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $operatorIDs = Operator::getOperatorIDsByPersonalIDs($inputArr['personalIDs']);
        if(empty($operatorIDs)) {
            return response()->json([
                'ოპერატორი ვერ მოიძებნა!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $resultSet = B2BMailSupport::getB2BMailsByOperators($operatorIDs, $inputArr['startDate'], $inputArr['endDate']);
        $headerArr = ['დრო', 'მიზეზი', 'ელ-ფოსტა', 'GSM', 'კომენტარი', 'ოპერატორი'];
        $valuesArr = [];
        foreach($resultSet as $operatorID => $statsDataArr) {
            foreach($statsDataArr as $stats) {
                $valuesArr[] = [$stats['inserted'], $stats['reason'], $stats['email'], $stats['gsm'], $stats['comment'], sprintf("%s - %s", $stats['name'], $stats['username'])];
            }
        }
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function insertB2BMail(StoreB2BMail $request) {
        $inputArr = $request->input();
//        if(Gate::allows('insertB2BMail') === false) {
//            return response()->json([
//                'message' =>   'unauthorized'
//            ], config('errorCodes.HTTP_UNAUTHORIZED'));
//        };
        DB::beginTransaction();
        $b2bMail = B2BMailSupport::insertB2BMail($inputArr);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.B2BMailSupport-ის შექმნა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - შექმნა B2B Mail, ID - %s",
            $request->user('api')->username, $b2bMail->id));
        DB::commit();
        return response()->json([
            'message' => 'B2B Mail წარმატებით შეიქმნა'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function updateB2BMail(UpdateB2BMail $request) {
        $inputArr = $request->input();
//        if(Gate::allows('updateB2BMail') === false) {
//            return response()->json([
//                'message' =>   'unauthorized'
//            ], config('errorCodes.HTTP_UNAUTHORIZED'));
//        };
        DB::beginTransaction();
        $b2bMail = B2BMailSupport::updateB2BMail($inputArr);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.B2BMailSupport-ის განახლება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - განაახლა B2B Mail, ID - %s",
                                                         $request->user('api')->username, $b2bMail->id));
        DB::commit();
        return response()->json([
            'message' => 'B2B Mail წარმატებით განახლდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
