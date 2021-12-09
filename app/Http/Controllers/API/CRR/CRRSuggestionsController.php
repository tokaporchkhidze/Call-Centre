<?php

namespace App\Http\Controllers\API\CRR;

use App\CRRSuggestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRR\DeleteSuggestion;
use App\Http\Requests\CRR\EditSuggestion;
use App\Http\Requests\CRR\ReactivateSuggestion;
use App\Http\Requests\CRR\StoreSuggestion;
use App\Logging\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CRRSuggestionsController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getCRRSuggestions() {
        return CRRSuggestion::all();
    }

    public function addSuggestion(StoreSuggestion $request) {
        if(Gate::allows('addCRRSuggestion') === false) {
            return response()->json([
                'message' => 'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $inputArr = $request->input();
        DB::beginTransaction();
        CRRSuggestion::create([
            'suggestion' => $inputArr['suggestion']
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR suggestion-ის შექმნა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s შექმნა suggestion - %s",
            $request->user('api')->username, $inputArr['suggestion']));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function reactivateSuggestion(ReactivateSuggestion $request) {
        if(Gate::allows('reactivateCRRSuggestion') === false) {
            return response()->json([
                'message' => 'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $inputArr = $request->input();
        DB::beginTransaction();
        $suggestion = CRRSuggestion::find($inputArr['id']);
        $suggestion->isactive = "YES";
        $suggestion->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR suggestion-ის აღდგენა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s აღადგინა suggestion - %s",
                                                         $request->user('api')->username, $suggestion->suggestion));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ]);
    }

    public function editSuggestion(EditSuggestion $request) {
        if(Gate::allows('editCRRSuggestion') === false) {
            return response()->json([
                'message' => 'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $inputArr = $request->input();
        DB::beginTransaction();
        $suggestion = CRRSuggestion::find($inputArr['id']);
        $currSuggestion = $suggestion->suggestion;
        $suggestion->suggestion = $inputArr['newSuggestion'];
        $suggestion->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR suggestion-ის აღდგენა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s შეცვალა suggestion: ძველი: %s, ახალი: %s",
                                                         $request->user('api')->username, $currSuggestion, $inputArr['newSuggestion']));
        DB::commit();
    }

    public function deleteSuggestion(DeleteSuggestion $request) {
        if(Gate::allows('deleteCRRSuggestion') === false) {
            return response()->json([
                'message' => 'unauthorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $inputArr = $request->input();
        DB::beginTransaction();
        $suggestion = CRRSuggestion::find($inputArr['id']);
        $suggestion->isactive = "NO";
        $suggestion->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.CRR suggestion-ის გაუქმება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s გააუქმა suggestion - %s",
                                                         $request->user('api')->username, $suggestion->suggestion));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ]);
    }

}
