<?php

namespace App\Http\Controllers\API\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\OperatorActivity\EndActivity;
use App\Http\Requests\OperatorActivity\StartActivity;
use App\OperatorActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OperatorActivitiesController extends Controller {

    public function getActivitiesList() {
        return config('operatorActivities');
    }

    public function startActivity(StartActivity $request) {
        $inputArr = $request->input();
        OperatorActivity::create([
            'activity' => $inputArr['activity'],
            'sip' => $inputArr['sipNumber'],
            'started' => Carbon::now()
        ]);
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function endActivity(EndActivity $request) {
        $inputArr = $request->input();
        $activityModel = OperatorActivity::where("sip", $inputArr['sipNumber'])->whereNull('ended')->first();
        $activityModel->ended = Carbon::now();
        $activityModel->save();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getActivityStats(Request $request) {
        $inputArr = $request->input();
        $activityResultSet = OperatorActivity::getActivityStats($inputArr['sipArr'], $inputArr['startDate'], $inputArr['endDate']);
        return $activityResultSet;
    }

    public function getLastActivity(Request $request) {
        $sip_number = $request->input('sipNumber');
        $activity_info = OperatorActivity::getLastActivity($sip_number);
        return $activity_info;
    }



}
