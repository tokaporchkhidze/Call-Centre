<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// all controllers in this group are under App\Http\Controllers\API
Route::namespace('API')->group(function() {
    // all routes under this group are checked by auth:api middleware


    Route::get('/passwordReset/initiatePasswordReset', 'UsersController@initiatePasswordReset')->middleware('checkApiHeader');

    Route::post('/passwordReset/checkPasswordResetToken', 'UsersController@checkPasswordResetToken')->middleware('checkApiHeader');

    Route::post('/passwordReset/resetPassword', 'UsersController@resetPassword')->middleware('checkApiHeader');

    Route::middleware(['auth:api'])->group(function() {
        // Get authenticated user by given access token value
        Route::get('/user', 'UsersController@getCurrentUser');
        // Checks if token has been revoked
//        Route::get('/checkToken', function() {
//            return response()->json(['error' => false], config('errorCodes.HTTP_SUCCESS'));
//        });
        // revoke token for requesting user
        Route::delete('/logout', 'UsersController@logout')->middleware('checkApiHeader');

        Route::delete('/logoutFromAllDevices', 'UsersController@logoutFromAllDevices')->middleware('checkApiHeader');

        Route::post('/addUser', 'UsersController@addUser')->middleware('checkApiHeader');

        Route::post('/editUser', 'UsersController@editUser')->middleware('checkApiHeader');

        Route::post('/deleteUser', 'UsersController@deleteUser')->middleware('checkApiHeader');

        Route::post('/changePassword', 'UsersController@changePassword')->middleware('checkApiHeader');

        Route::get('/getUsersWithTemplates', 'UsersController@getUsersWithTemplates')->middleware('checkApiHeader');

        Route::get('/getUsersCountByTemplateId', 'UsersController@getUsersCountByTemplateId')->middleware('checkApiHeader');

        Route::get('/getRecordedInCalls', 'AudiosController@getRecordedInCalls')->middleware('checkApiHeader');

        Route::get('/getRecordedOutCalls', 'AudiosController@getRecordedOutCalls')->middleware('checkApiHeader');

        Route::get('/getAudioFile', 'AudiosController@getAudioFile');

        Route::get('/stats/getCurrentSipStatus', 'AsteriskController@getCurrentSipStatus');

        Route::post('/pause/pauseSip', 'AsteriskController@pauseSip');

        Route::post('/pause/addPauseInPause', 'AsteriskController@addPauseInPause');

        Route::post('/pause/updatePauseReason', 'AsteriskController@updatePauseReason');


        Route::post('/asteriskControl/addNumberInBlackList', 'AsteriskController@addNumberInBlackList')->middleware('checkApiHeader');

        Route::post('/asteriskControl/removeNumberFromBlackList', 'AsteriskController@removeNumberFromBlackList')->middleware('checkApiHeader');

        Route::get('/asteriskControl/getNumbersFromBlackList', 'AsteriskController@getNumbersFromBlackList')->middleware('checkApiHeader');

        Route::get('/asteriskControl/getBlackListHistory', 'AsteriskController@getBlackListHistory')->middleware('checkApiHeader');

        Route::get('/asteriskControl/getBlackListReasons', 'AsteriskController@getBlackListReasons')->middleware('checkApiHeader');

        Route::get('/queueGroups/getGroupsWithQueues', 'QueueGroupsController@getGroupsWithQueues');

        Route::get('/queueGroups/getGroups', 'QueueGroupsController@getGroups');

        Route::namespace('CRR')->group(function() {

            Route::get('/CRR/getCRRReasons', 'CRRReasonsController@getCRRReasons');

            Route::post('/CRR/addReason', 'CRRReasonsController@addReason');

            Route::post('/CRR/editReason', 'CRRReasonsController@editReason');

            Route::post('/CRR/deleteReason', 'CRRReasonsController@deleteReason');

            Route::post('/CRR/reactivateReason', 'CRRReasonsController@reactivateReason');

            Route::get('/CRR/getCRRSuggestions', 'CRRSuggestionsController@getCRRSuggestions');

            Route::post('/CRR/addSuggestion', 'CRRSuggestionsController@addSuggestion');

            Route::post('/CRR/deleteSuggestion', 'CRRSuggestionsController@deleteSuggestion');

            Route::post('/CRR/reactivateSuggestion', 'CRRSuggestionsController@reactivateSuggestion');

            Route::post('/CRR/editSuggestion', 'CRRSuggestionsController@editSuggestion');

            Route::get('/CRR/getCallsWithCRR', 'CRRController@getCallsWithCRR');

            Route::get('/CRR/getLastOrOngoingCall', 'CRRController@getLastOrOngoingCall');

            Route::get('/CRR/getCRRByCaller', 'CRRController@getCRRByCaller');

            Route::post('/CRR/updateCRR', 'CRRController@updateCRR');

            # CRR REPORTING
            Route::post('/CRR/reporting/getCRRReasonsBySkills', 'CRRController@getCRRReasonsBySkills');

            Route::get('/CRR/reporting/getCRRCallCountByGSM', 'CRRController@getCRRCallCountByGSM');

            Route::get('/CRR/reporting/getCRRAllCallsByGSM', 'CRRController@getCRRAllCallsByGSM');

            Route::get('/CRR/reporting/getCRRNonB2BCalls', 'CRRController@getCRRNonB2BCalls');

            Route::get('/CRR/reporting/getCRRCountByOperators', 'CRRController@getCRRCountByOperators');

            Route::get('/CRR/reporting/getCRRByOperators', 'CRRController@getCRRByOperators');

            Route::get('/CRR/reporting/getNotRegisteredCalls', 'CRRController@getNotRegisteredCalls');

            Route::get('/CRR/reporting/getReportsFileListing', 'CRRController@getReportsFileListing');

            Route::get('/CRR/reporting/downloadReport', 'CRRController@downloadReport');

        });

        Route::namespace('B2BMailSupport')->group(function() {

            Route::post('/B2BMailSupport/insertB2BMail', 'B2BMailSupportController@insertB2BMail');

            Route::post('/B2BMailSupport/updateB2BMail', 'B2BMailSupportController@updateB2BMail');

            Route::get('B2BMailSupport/getB2BMailsByOperators', 'B2BMailSupportController@getB2BMailsByOperators');

            Route::get('/B2BMailSupport/getB2BMailReasons', 'B2BMailReasonsController@getB2BMailReasons');

            Route::post('/B2BMailSupport/addB2BMailReason', 'B2BMailReasonsController@addB2BMailReason');

            Route::post('/B2BMailSupport/deleteB2BMailReason', 'B2BMailReasonsController@deleteB2BMailReason');

            Route::post('/B2BMailSupport/reactivateB2BMailReason', 'B2BMailReasonsController@reactivateB2BMailReason');

        });

        Route::namespace('ExcelReports')->group(function() {

            Route::get('/excelReports/generateGeocellCallReport', 'ExcelController@generateGeocellCallReport');

        });

        // all controllers in this group are under App\Http\Controllers\API\UserPanel
        Route::namespace('UserPanel')->group(function() {
            // add sip in database and asterisk config file
            Route::post('/sipControl/addSip', 'SipsController@addSip')->middleware('checkApiHeader');

            Route::post('/sipControl/addSipBulk', 'SipsController@addSipBulk')->middleware('checkApiHeader');

            // delete sip...
            Route::post('/sipControl/deleteSip', 'SipsController@deleteSip')->middleware('checkApiHeader');

            Route::get('/sipControl/getSips', 'SipsController@getSips')->middleware('checkApiHeader');

            Route::get('/sipControl/getSipTemplates', 'SipsController@getSipTemplates')->middleware('checkApiHeader');

            Route::get('/sipControl/getSipTemplateDetails', 'SipsController@getSipTemplate')->middleware('checkApiHeader');

            Route::post('/sipControl/addSipTemplate', 'SipsController@addSipTemplate')->middleware('checkApiHeader');

            Route::post('/sipControl/deleteSipTemplate', 'SipsController@deleteSipTemplate')->middleware('checkApiHeader');

            Route::post('/sipControl/editSipTemplate', 'SipsController@editSipTemplate')->middleware('checkApiHeader');

            Route::post('/queueControl/addTemplate', 'QueuesController@addTemplate')->middleware('checkApiHeader');

            Route::post('/queueControl/editTemplate', 'QueuesController@editTemplate')->middleware('checkApiHeader');

            Route::post('/queueControl/deleteTemplate', 'QueuesController@deleteTemplate')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueues', 'QueuesController@getQueues')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueuesBySip', 'QueuesController@getQueuesBySip')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueueByName', 'QueuesController@getQueueByName')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueueBlock', 'QueuesController@getQueueBlock')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueueTemplateBlock', 'QueuesController@getTemplateBlock')->middleware('checkApiHeader');

            Route::get('/queueControl/getQueueTemplates', 'QueuesController@getQueueTemplates')->middleware('checkApiHeader');

            Route::get('/queueControl/getSipsByQueueName', 'QueuesController@getSipsByQueueName')->middleware('checkApiHeader');

            Route::get('/queueControl/getDistinctSipsByQueues', 'QueuesController@getDistinctSipsByQueues')->middleware('checkApiHeader');

            Route::post('/queueControl/addQueue', 'QueuesController@addQueue')->middleware('checkApiHeader');

            Route::post('/queueControl/addSipInQueue', 'QueuesController@addSipInQueue')->middleware('checkApiHeader');

            Route::post('/queueControl/addSipInQueueBulk', 'QueuesController@addSipInQueueBulk')->middleware('checkApiHeader');

            Route::post('/queueControl/deleteQueue', 'QueuesController@deleteQueue')->middleware('checkApiHeader');

            Route::post('/queueControl/editQueue', 'QueuesController@editQueue')->middleware('checkApiHeader');

            Route::post('/queueControl/deleteSipFromQueue', 'QueuesController@deleteSipFromQueue')->middleware('checkApiHeader');

            Route::post('/queueControl/deleteSipFromAllQueues', 'QueuesController@deleteSipFromAllQueues')->middleware('checkApiHeader');

            Route::get('/queueControl/refreshPriorities', 'QueuesController@refreshPriorities')->middleware('checkApiHeader');

            Route::get('/getTemplatesList', 'TemplatesController@getTemplatesList')->middleware('checkApiHeader');

            Route::get('/getTemplateWithPermissions', 'TemplatesController@getTemplateWithPermissions')->middleware('checkApiHeader');

            Route::post('/addTemplate', 'TemplatesController@addTemplate')->middleware('checkApiHeader');

            Route::post('/deleteTemplate', 'TemplatesController@deleteTemplate')->middleware('checkApiHeader');

            Route::post('/editTemplate', 'TemplatesController@editTemplate')->middleware('checkApiHeader');

            Route::get('/getTasks', 'TasksController@getTasks')->middleware('checkApiHeader');

            Route::post('/operatorControl/addOperator', 'OperatorsController@addOperator')->middleware('checkApiHeader');

            Route::post('/operatorControl/deleteOperator', 'OperatorsController@deleteOperator')->middleware('checkApiHeader');

            Route::post('/operatorControl/addOperatorToSip', 'OperatorsController@addOperatorToSip')->middleware('checkApiHeader');

            Route::post('/operatorControl/removeOperatorFromSip', 'OperatorsController@removeOperatorFromSip')->middleware('checkApiHeader');

            Route::post('/operatorControl/transferOperatorToSip', 'OperatorsController@transferOperatorToSip')->middleware('checkApiHeader');

            Route::get('/operatorControl/getOperatorsWithSips', 'OperatorsController@getOperatorsWithSips')->middleware('checkApiHeader');

            Route::get('/operatorControl/getOperators', 'OperatorsController@getOperators')->middleware('checkApiHeader');

            Route::get('/logs/getLogs', 'LogsController@getActivityLogs')->middleware('checkApiHeader');

            Route::get('/logs/getLogActionMapping', 'LogsController@getLogActionMapping')->middleware('checkApiHeader');

            Route::get('/sipControl/testLockFile', 'SipsController@testLock')->middleware('checkApiHeader');

        });

        Route::namespace('Statistics')->group(function() {

            Route::get('/operatorActivities/getActivitiesList', 'OperatorActivitiesController@getActivitiesList');

            Route::post('/operatorActivities/startActivity', 'OperatorActivitiesController@startActivity');

            Route::post('/operatorActivities/endActivity', 'OperatorActivitiesController@endActivity');

            Route::get('/operatorActivities/getActivityStats', 'OperatorActivitiesController@getActivityStats');

            Route::get('/operatorActivities/getLastActivity', 'OperatorActivitiesController@getLastActivity');

            Route::get('/testFuncton', 'SipStatsController@testFunction');

            Route::get('/stats/getStatsBySips', 'SipStatsController@getStatsBySips')->middleware('checkApiHeader');


            Route::get('/stats/getStatsForSipOnly', 'SipStatsController@getStatsBySips')->middleware('checkApiHeader');

            Route::get('/stats/getStatsByQueue', 'SipStatsController@getStatsByQueue')->middleware('checkApiHeader');

            Route::get('/stats/getOverallQueueStats', 'SipStatsController@getOverallQueueStats')->middleware('checkApiHeader');

            Route::get('/stats/getAnsweredCalls', 'SipStatsController@getAnsweredCalls')->middleware('checkApiHeader');

            Route::get('/stats/getAbandonedCalls', 'SipStatsController@getAbandonedCalls')->middleware('checkApiHeader');

            Route::get('/stats/getOutgoingTransfers', 'SipStatsController@getOutgoingTransfers')->middleware('checkApiHeader');

            Route::get('/stats/getOutgoingTransfersToQueue', 'SipStatsController@getOutgoingTransfersToQueue')->middleware('checkApiHeader');

            Route::get('/stats/getIncomingTransfers', 'SipStatsController@getIncomingTransfers')->middleware('checkApiHeader');

            Route::get('/stats/getIncomingTransfersFromQueue', 'SipStatsController@getIncomingTransfersFromQueue')->middleware('checkApiHeader');

            Route::get('/stats/getOutgoingTransfersDetailedBySip', 'SipStatsController@getOutgoingTransfersDetailedBySip')->middleware('checkApiHeader');

            Route::get('/stats/getTransfersBySips', 'SipStatsController@getTransfersBySips')->middleware('checkApiHeader');

            Route::get('/stats/getDTMF', 'SipStatsController@getDTMF')->middleware('checkApiHeader');

            Route::get('/stats/getDTMFByCategories', 'SipStatsController@getDTMFByCategories')->middleware('checkApiHeader');

            Route::get('/stats/getDTMFMapping', 'SipStatsController@getDTMFMapping')->middleware('checkApiHeader');

            Route::get('/stats/getStatsByInNumber', 'SipStatsController@getStatsByInNumber')->middleware('checkApiHeader');

            Route::get('/stats/getBeforeQueueAbandonedCalls', 'SipStatsController@getBeforeQueueAbandonedCalls')->middleware('checkApiHeader');

            Route::get('/stats/getHoldTimeInQueue', 'SipStatsController@getHoldTimeInQueue')->middleware('checkApiHeader');

            Route::get('/stats/getHoldTimeInQueueHourly', 'SipStatsController@getHoldTimeInQueueHourly')->middleware('checkApiHeader');

            Route::get('/stats/getB2bAndB2cStats', 'SipStatsController@getB2bAndB2cStats')->middleware('checkApiHeader');

            Route::get('/stats/getStatsByCallerNumber', 'SipStatsController@getStatsByCallerNumber')->middleware('checkApiHeader');

            Route::get('/stats/getRepeatedCallsByQueue', 'SipStatsController@getRepeatedCallsByQueue')->middleware('checkApiHeader');

            Route::get('/stats/getCallTimeByQueue', 'SipStatsController@getCallTimeByQueue')->middleware('checkApiHeader');

            Route::get('/stats/getHourlyQueueStats', 'SipStatsController@getHourlyQueueStats')->middleware('checkApiHeader');

            Route::get('/stats/getLiveQueueStats', 'SipStatsController@getLiveQueueStats')->middleware('checkApiHeader');

            Route::get('/stats/getLiveQueueStatsTest', 'SipStatsController@getLiveQueueStatsTest')->middleware('checkApiHeader');

            Route::get('/stats/getLiveStatsBySips', 'SipStatsController@getLiveStatsBySips')->middleware('checkApiHeader');

            Route::get('/stats/getOngoingCallStats', 'SipStatsController@getOngoingCallStats')->middleware('checkApiHeader');

            Route::get('/stats/getRecallsAfterAbandon', 'SipStatsController@getRecallsAfterAbandon')->middleware('checkApiHeader');

            Route::get('/stats/getOutGoingCallsByQueue', 'SipStatsController@getOutGoingCallsByQueue')->middleware('checkApiHeader');

            Route::get('/stats/getOutGoingCallDetailedBySip', 'SipStatsController@getOutGoingCallDetailedBySip')->middleware('checkApiHeader');

            Route::get('/stats/getOutGoingCallDetailedByQueue', 'SipStatsController@getOutGoingCallDetailedByQueue')->middleware('checkApiHeader');

            Route::get('/stats/getCallsByPrefixes', 'SipStatsController@getCallsByPrefixes')->middleware('checkApiHeader');

            Route::get('/stats/getCallsByTypes', 'SipStatsController@getCallsByTypes')->middleware('checkApiHeader');

            Route::get('/stats/getLastPauseStatus', 'SipStatsController@getLastPauseStatus')->middleware('checkApiHeader');

            Route::get('/stats/getPauseStatusDetailed', 'SipStatsController@getPauseStatusDetailed')->middleware('checkApiHeader');

            Route::get('/stats/getPauseStatusDetailedV2', 'SipStatsController@getPauseStatusDetailedV2')->middleware('checkApiHeader');

            Route::get('/stats/getDailyDetailedStatsForSip', 'SipStatsController@getDailyDetailedStatsForSip')->middleware('checkApiHeader');

            Route::get('/stats/getMonthlyDetailedStatsForOperator', 'SipStatsController@getMonthlyDetailedStatsForOperator')->middleware('checkApiHeader');

            Route::get('/stats/sipStatuses/getSipLogins', 'SipStatusController@getSipLogins')->middleware('checkApiHeader');

            Route::get('/stats/sipStatuses/getSipLastStatus', 'SipStatusController@getSipLastStatus')->middleware('checkApiHeader');

            Route::get('/stats/foreignCalls/getForeignCallQueues', 'ForeignCallsController@getForeignCallQueues')->middleware('checkApiHeader');

            Route::get('/stats/foreignCalls/getForeignCalls', 'ForeignCallsController@getForeignCalls')->middleware('checkApiHeader');

            Route::get('/stats/foreignCalls/getForeignCallsDetailed', 'ForeignCallsController@getForeignCallsDetailed')->middleware('checkApiHeader');

        });

        Route::namespace('Notifications')->group(function() {
            Route::get('/notifications/getNotifications', 'NotificationsController@getNotifications');

            Route::get('/notifications/getUnreadNotifcationsCount', 'NotificationsController@getUnreadNotifcationsCount');

            Route::post('/notifications/markAsRead', 'NotificationsController@markAsRead');
        });

    });
});

Route::get('/usertest', 'API\UsersController@getCurrentUser');
