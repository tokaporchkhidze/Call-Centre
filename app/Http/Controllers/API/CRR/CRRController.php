<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/16/2019
 * Time: 12:08 PM
 */

namespace App\Http\Controllers\API\CRR;


use App\AsteriskStatistics\QueueLog;
use App\CRR;
use App\CRRSuggestion;
use App\ExcelFileCreator;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRR\GetCallsWithCRR;
use App\Http\Requests\CRR\StoreCRR;
use App\Jobs\CrrExcelGenerator;
use App\Logging\Logger;
use App\SipToOperatorHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\DateTimeTrait;

ini_set('max_execution_time', -1);

class CRRController extends Controller {

    use DateTimeTrait;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    private function getCorrectDatesForOperatorSipMapping($inputStartDate, $inputEndDate, $pairedDate, $removedDate) {
        if($pairedDate <= $inputStartDate) {
            $startDate = $inputStartDate;
        } else {
            $startDate = $pairedDate;
        }
        if(isset($removedDate)) {
            if($removedDate <= $inputEndDate) {
                $endDate = $removedDate;
            } else {
                $endDate = $inputEndDate;
            }
        } else {
            $endDate = $inputEndDate;
        }
        return [$startDate, $endDate];
    }

    private function getUniqeSipsByOperators($operatorsHistoryArr) {
        $sips = [];
        foreach($operatorsHistoryArr as $operatorArr) {
            foreach($operatorArr as $val) {
                $sips[] = $val['sip'];
            }
        }
        return array_unique($sips);
    }

    public function getCRRSuggestionMapping() {
        return CRRSuggestion::all();
    }

    public function getCallsWithCRR(GetCallsWithCRR $request) {
        $inputArr = $request->input();
        if(isset($inputArr['sipNumber'])) {
            return QueueLog::getCallsWithCRR($inputArr['sipNumber'], $inputArr['startDate'], $inputArr['endDate']);
        } else if(isset($inputArr['caller'])) {
            return QueueLog::getCallsWithCRRByCaller($inputArr['caller'], $inputArr['startDate'], $inputArr['endDate']);
        } else {
            return QueueLog::getCallsWithCRR(null, $inputArr['startDate'], $inputArr['endDate']);
        }
    }

    public function getLastOrOngoingCall(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getLastOrOngoingCall($inputArr['sipNumber']);
    }

    public function updateCRR(StoreCRR $request) {
        $inputArr = $request->input();
        (strtolower($inputArr['autosave']) == "true") ? $inputArr['status'] = 1 : $inputArr['status'] = 2;
        DB::beginTransaction();
        CRR::updateCRR($inputArr);
        $this->logger->addLogInfo("API", config("logging.mongo_mapping.CRR-ის შექმნა/განახლება"));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s - შექმნა/განაახლა CRR ზარისთვის - ზარის კოდი: %s, CRR კოდი: %s",
                                                         $request->user('api')->username,
                                                         $inputArr['uniqueID'], $inputArr['CRRUniqueID']));
        DB::commit();
        return response()->json([
            'message' => 'CRR წარმატებით განახლდა!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRReasonsBySkills(Request $request) {
        $inputArr = $request->input();
        $dates = $this->getPeriodArr($inputArr['intervalBy'], new \DateTime($inputArr['startDate']), new \DateTime($inputArr['endDate']));
        $filePath = sprintf("%s/%s/%s", config('crrReporting.REPORTS_DIR'), 'questionReport',
            sprintf("crrQuestionReport_%s_%s_%s.xlsx",
                date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate'])), $inputArr['intervalBy']));

        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report Already Exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $datesArr = [];
        $headerArr = ['Reasons'];
        foreach($dates as $date) {
            $headerArr[] = $date;
            $datesArr[$date] = "0";
        }
        $headerArr[] = 'totalCount';
        $resultSet = CRR::getCRRReasonsBySkills($inputArr['reasonsIDArr'], $inputArr['startDate'], $inputArr['endDate'], $datesArr, $this->getIntervalFormat($inputArr['intervalBy'], "sql"));
        $valuesArr = [];
        foreach($resultSet as $reason => $data) {
            $tmpArr = [$reason];
            $tmpArr = array_merge_recursive($tmpArr, array_values($data));
            $valuesArr[] = $tmpArr;
        }
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRCallCountByGSM(Request $request) {
        $inputArr = $request->input();
        $filePath = sprintf('%s/%s/%s', config('crrReporting.REPORTS_DIR'), 'GSMReport', sprintf('GSMReportCount_%s_%s.xlsx',
            date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report Already Exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $headerArr = ['აბონენტის#(Bill)', 'აბონენტის#(CRR)', 'რაოდენობა'];
        $valuesArr = CRR::getCRRCallCountByGSM($inputArr['startDate'], $inputArr['endDate']);
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRAllCallsByGSM(Request $request) {
        $inputArr = $request->input();
        $headerArr = ['აბონენტის#(Bill)', 'აბონენტის#(CRR)', 'დრო'];
        $filePath = sprintf('%s/%s/%s', config('crrReporting.REPORTS_DIR'), 'GSMReport', sprintf('GSMReportCDR_%s_%s.xlsx',
            date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report Already Exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $valuesArr = CRR::getCRRAllCallsByGSM($inputArr['startDate'], $inputArr['endDate']);
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRNonB2BCalls(Request $request) {
        $inputArr = $request->input();
        $filePath = sprintf('%s/%s/%s', config('crrReporting.REPORTS_DIR'), 'notB2BHours', sprintf('notB2BHours_%s_%s.xlsx',
            date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report Already Exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $headerArr = ['აბონენტის#(Bill)', 'აბონენტის#(CRR)', 'დრო'];
        $valuesArr = CRR::getCRRNonB2BCalls($inputArr['startDate'], $inputArr['endDate']);
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRByCaller(Request $request) {
        $inputArr = $request->input();
        return CRR::getCRRByCaller($inputArr['caller'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getCRRCountByOperators(Request $request) {
        $inputArr = $request->input();
        $filePath = sprintf("%s/%s/%s", config('crrReporting.REPORTS_DIR'), 'userReport', sprintf("userReport_%s_%s_%s.xlsx",
            $inputArr['intervalBy'], date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report already exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $operatorsHistoryArr = SipToOperatorHistory::getSipsForOperatorByDate($inputArr['personalIDsArr'], $inputArr['startDate'], $inputArr['endDate']);
        $dates = $this->getPeriodArr($inputArr['intervalBy'], new \DateTime($inputArr['startDate']), new \DateTime($inputArr['endDate']));
        $headerArr = ['თარიღი'];
        $neededSips = $this->getUniqeSipsByOperators($operatorsHistoryArr);
        $resultSet = CRR::getCRRCountByOperatorsGrouped($this->getIntervalFormat($inputArr['intervalBy'], "sql"), $inputArr['startDate'], $inputArr['endDate'], $neededSips);
        $valuesArr = [];
        foreach($dates as $date) {
            $tmpArr = [$date];
            foreach($operatorsHistoryArr as $operatorDataArr) {
                $crrCount = 0;
                $operatorHeaderName = sprintf('%s - %s', $operatorDataArr[0]['name'], $operatorDataArr[0]['username']);
                if(!in_array($operatorHeaderName, $headerArr)) {
                    $headerArr[] = $operatorHeaderName;
                }
                foreach($operatorDataArr as $operatorData) {
                    $operSip = sprintf("SIP/%s", $operatorData['sip']);
                    list($startDate, $endDate) = $this->getCorrectDatesForOperatorSipMapping($inputArr['startDate'], $inputArr['endDate'], $operatorData['paired_at'], $operatorData['removed_at'] ?? null);
                    $startDateFormatted = date($this->getIntervalFormat($inputArr['intervalBy'], "date"), strtotime($startDate));
                    $endDateFormatted = date($this->getIntervalFormat($inputArr['intervalBy'], "date"), strtotime($endDate));
                    // if operator was on sip less than one day and current date doesnt equal to operators date skip
                    if( ( (strtotime($endDate)-strtotime($startDate)) < 24*60*60 ) and $startDateFormatted != $date) continue;
                    // if operator was on sip less than one day and current date equals to operators date we need to get crr
                    // count specifically for operators assigned time on sip
                    if( ( (strtotime($endDate)-strtotime($startDate)) < 24*60*60 ) and $startDateFormatted == $date) {
                        if($startDateFormatted != $endDateFormatted) {
                            $endDate = $startDateFormatted." 23:59:59";
                        }
                        // dasamatebelia funqcia romelic dajgupebis gareshe wamoigebs pirdapir counts gadacemuli drois intervalsitvis !!!
                        $crrCount += CRR::getCRRCountByOperators($startDate, $endDate, $operatorData['sip']);
                        continue;
                    }
                    $sipDataArr = $resultSet[$operSip][$date] ?? null;
                    if(!isset($sipDataArr)) continue;
                    $crrCount += $sipDataArr['counter'];
                }
                $tmpArr[] = $crrCount;
            }
            $valuesArr[] = $tmpArr;
        }
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getCRRByOperators(Request $request) {
        $inputArr = $request->input();
        $joinType = strtolower($inputArr['joinType']);
        if($joinType == "registered") {
            $joinStr = "inner";
        } else if($joinType == "all" or $joinType == "unregistered") {
            $joinStr = "left";
        } else {
            throw new \RuntimeException("Incorrect joinType was given!");
        }
        $filePath = sprintf("%s/%s/%s",
            config('crrReporting.REPORTS_DIR'), 'fullReport', sprintf("crr_report_%s_%s_%s.xlsx", $joinType,
                date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report already exists'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $operatorsHistoryArr = SipToOperatorHistory::getSipsForOperatorByDate($inputArr['personalIDsArr'], $inputArr['startDate'], $inputArr['endDate']);
        $neededSips = $this->getUniqeSipsByOperators($operatorsHistoryArr);
        $allSips = CRR::getCRRByOperators($joinStr, $joinType, $inputArr['startDate'], $inputArr['endDate'], $neededSips);
        $valuesArr = [];
        foreach($operatorsHistoryArr as $operatorArr) {
            foreach($operatorArr as $operator) {
                $pairedDate = $operator['paired_at'];
                $removedDate = $operator['removed_at'];
                list($startDate, $endDate) = $this->getCorrectDatesForOperatorSipMapping($inputArr['startDate'], $inputArr['endDate'], $pairedDate, $removedDate ?? null);
                $sip = sprintf("SIP/%s", $operator['sip']);
                $sipDataArr = $allSips[$sip] ?? null;
                if(!isset($sipDataArr)) {
                    // no data for given sip
                    continue;
                }

                foreach($sipDataArr as $sipData) {
                    $eventDate = $sipData['time'];
                    if($eventDate >= $startDate and $eventDate <= $endDate) {
                        $valuesArr[] = [$sipData['real_number'], $sipData['number'], $sipData['queuename'] ,$sipData['skill'],
                            $sipData['queuename'], $sipData['language'], sprintf("%s - User: %s", $operatorArr[0]['name'], $operatorArr[0]['username']),
                            $sipData['agent'], $sipData['reason'], $sipData['time'], $sipData['duration'], $sipData['comment'], $sipData['suggestion'],
                            $sipData['isunwanted'], $sipData['category'], $sipData['type'], $sipData['isactive']];
                    }
                }
            }
        }
        $headerArr = ['აბონენტი(Bill)', 'აბონენტი', 'სქილი(Bill)', 'სქილი', 'ენა(Bill)', 'ენა','ოპერატორი', 'სიპი',
            'ზარის მიზეზი', 'დრო', 'ხანგრძლივობა', 'კომენტარი', 'suggestion','არასასურველი', 'კატეგორია', 'ტიპი', 'აქტიური'];
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getNotRegisteredCalls(Request $request) {
        $inputArr = $request->input();
        $filePath = sprintf("%s/%s/%s", config('crrReporting.REPORTS_DIR'), 'unregisteredCRRReport',
            sprintf("unregisteredCRRReport_%s_%s.xlsx", date("YmdHi", strtotime($inputArr['startDate'])), date("YmdHi", strtotime($inputArr['endDate']))));
        if(file_exists($filePath)) {
            return response()->json([
                'message' => 'Report already Exists!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $operatorsHistoryArr = SipToOperatorHistory::getSipsForOperatorByDate($inputArr['personalIDsArr'], $inputArr['startDate'], $inputArr['endDate']);
        $neededSips = $this->getUniqeSipsByOperators($operatorsHistoryArr);
        $resultSet = CRR::getNotRegisteredCalls($inputArr['startDate'], $inputArr['endDate'], $neededSips);
        $headerArr = ['პასუხის დრო', 'სიპ ნომერი', 'აბონენტის#', 'ოპერატორი', 'მომხმარებელი'];
        $valuesArr = [];
        foreach($operatorsHistoryArr as $operatorDataArr) {
            foreach($operatorDataArr as $operatorData) {
                list($startDate, $endDate) = $this->getCorrectDatesForOperatorSipMapping($inputArr['startDate'], $inputArr['endDate'], $operatorData['paired_at'], $operatorData['removed_at'] ?? null);
                $sipDataArr = $resultSet[sprintf("SIP/%s", $operatorData['sip'])] ?? null;
                if(!isset($sipDataArr)) continue; // No data for given sip
                foreach($sipDataArr as $sipData) {
                    $eventDate = $sipData['answer_time'];
                    if($eventDate >= $startDate and $eventDate <= $endDate) {
                        $valuesArr[] = [$eventDate, $sipData['agent'], $sipData['caller'], $operatorData['name'], $operatorData['username']];
                    }
                }
            }
        }
        CrrExcelGenerator::dispatch($headerArr, $valuesArr, $filePath, $request->user('api'))->onConnection(config('crrReporting.JOB_CONNECTION_NAME'));
        return response()->json([
            'message' => 'Dispatched job for creating Excel file'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getReportsFileListing() {
        $crrReportDir = config('crrReporting.REPORTS_DIR');
        $directories = array_slice(scandir($crrReportDir), 2);
        $resultSet = [];
        foreach($directories as $dir) {
            $absoluteDirPath = sprintf("%s/%s", config('crrReporting.REPORTS_DIR'), $dir);
            /*$reportList = array_map(function($val) {
                return substr($val, 0, strrpos($val, ".xlsx"));
            }, array_slice(scandir($absoluteDirPath), 2));*/
            $fileList = array_slice(scandir($absoluteDirPath), 2);
            $reportList = [];
            foreach($fileList as $fileName) {
                $fileLastModified = filemtime(sprintf("%s/%s", $absoluteDirPath, $fileName));
                $reportList[] = [
                    'fileName' => substr($fileName, 0, strrpos($fileName, ".xlsx")),
                    'fileCreated' => date("Y-m-d H:i:s", $fileLastModified),
                    'fileCreator' => ExcelFileCreator::where("file", $fileName)->orderBy('inserted', 'desc')->first()->user->username ?? "Unknown",
                ];
            }
            $resultSet[config('crrReporting.DIR_TO_INTERFACE_NAMES')[$dir]] = $reportList;
        }
        return $resultSet;
    }

    public function downloadReport(Request $request) {
        $inputArr = $request->input();
        $dir = array_search($inputArr['directory'], config('crrReporting.DIR_TO_INTERFACE_NAMES'));
        $fileName = sprintf("%s.xlsx", $inputArr['fileName']);
        $filePath = sprintf("%s/%s/%s", config('crrReporting.REPORTS_DIR'), $dir, $fileName);
        return response()->download($filePath, $fileName, ['Access-Control-Expose-Headers' => '*']);
    }

}
