<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/9/2019
 * Time: 5:35 PM
 */

namespace App\Http\Controllers\API\UserPanel;


use App\AsteriskHandlers\QueueParser;
use App\Common\SSH2;
use App\Exceptions\ModelAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteSip;
use App\Http\Requests\EditSipTemplate;
use App\Http\Requests\StoreSip;
use App\Logging\Logger;
use App\Operator;
use App\Queue;
use App\Sip;
use App\SipTemplate;
use App\Traits\AsteriskParserTrait;
use App\Traits\SipOperatorTrait;
use App\Traits\SipQueueTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\AsteriskHandlers\SipParser;
use Illuminate\Support\Facades\DB;

class SipsController extends Controller {

    use SipOperatorTrait, SipQueueTrait, AsteriskParserTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var SipParser
     */
    private $sipParser;

    public function __construct(SipParser $sipParser) {
        $this->logger = Logger::instance();
        $this->sipParser = $sipParser;
    }

    public function addSip(StoreSip $request) {
        $this->authorize('createAndDelete', Sip::class);
        $inputArr = $request->input();
        $operator = null;
        if($request->has('operatorID')) {
            $operator = Operator::find($inputArr['operatorID']);
        }
        $templateId = SipTemplate::where('name', $inputArr['templateName'])->firstOrFail()->id;
        $newFileString = $this->sipParser->addSip($inputArr['sipNumber'], $inputArr['templateName'], $inputArr['comment'] ?? null);
        if( Sip::checkIfSipExistsByNumber($inputArr['sipNumber']) ) {
            throw new ModelAlreadyExists("Sip already exists in database, check config file!!!");
        }
        DB::beginTransaction();
        $sip = Sip::create([
            'sip' => $inputArr['sipNumber'],
            'sip_templates_id' => $templateId
        ]);
        if($operator != null) {
            $this->createOperatorSipBridge($operator, $sip);
            if($operator->trainee == 0) {
                $this->addOperatorSipLogEntry($operator, $sip);
            }
        }
        $this->logger->addLogInfo(__METHOD__, [
            'sip' => $inputArr['sipNumber'],
            'sip_templates_id' => $templateId
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის დამატება'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, დაამატა სიპი - %s, გაწერა %s - %s",
                $request->user('api')->username,
                $sip->sip,
                ($operator != null) ? ($operator->trainee == 1) ? "სტაჟიორზე": "ოპერატორზე" : "ოპერატორზე",
                ($operator != null) ? $operator->first_name." ".$operator->last_name : "არ მიუთითებია" ));
        $this->sipParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Sip has been added!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function addSipBulk(Request $request) {
        $this->authorize("createAndDelete", Sip::class);
        $inputArr = $request->input();
        $newFileString = $this->sipParser->addSipBulk($inputArr['start'], $inputArr['end'], $inputArr['templateName'], $inputArr['comment'] ?? null);
        $sipNumbers = range($inputArr['start'], $inputArr['end'], 1);
        $sips = Sip::whereIn('sip', $sipNumbers)->get()->toArray();
        if(empty($sips) === false) {
            throw new ModelAlreadyExists('Sips in these range exist in DB, check config file!');
        }
        if(SipTemplate::checkIfSipTemplateExistsByName($inputArr['templateName']) === false) {
            throw new ModelNotFoundException('Template doesnt exist in DB, check config file!!!');
        }
        DB::beginTransaction();
        $templateId = SipTemplate::where('name', $inputArr['templateName'])->first()->id;
        Sip::bulkInsert($sipNumbers, $templateId);
        $this->sipParser->commitChanges($newFileString);
        DB::commit();
        $this->logger->addLogInfo(__METHOD__, [
            'start' => intval($inputArr['start']),
            'end' => intval($inputArr['end']),
            'range' => implode(',', $sipNumbers),
            'message' => 'added sips in DB'
        ]);
        return response([
            'message' => 'Given sip range has beend added!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteSip(DeleteSip $request) {
        $this->authorize('createAndDelete', Sip::class);
        $this->authorize("createAndDelete", Queue::class);
        $inputArr = $request->input();
        $newFileString = $this->sipParser->deleteSip($inputArr['sipNumber'], $inputArr['templateName']);
        $sip = Sip::where('sip', $inputArr['sipNumber'])->with('operator')->first();
        if($sip == null) {
            throw new ModelNotFoundException('Sip doesnt exist, check config file!!!');
        }
        $queueNames = $this->getQueueDisplayNamesForLog($sip->sip);
        DB::beginTransaction();
        $queueParser = new QueueParser();
        $newQueueFileString = $queueParser->deleteSipFromAllQueues($sip->sip);
        $this->deleteAllSipQueuePairsFromDB($sip);
        if($sip->operator != null) {
            if($sip->operator->trainee == 0) {
                $this->updateOperatorSipLogEntry($sip->operator, $sip);
            }
        }
        $sip->delete();
        $this->logger->addLogInfo(__METHOD__, [
            "sip" => $inputArr['sipNumber']
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის წაშლა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, წაშალა სიპი - %s, წაიშალა რიგებიდან - %s.",
            $request->user('api')->username, $sip->sip,
            (count($queueNames) == 0) ? "არც ერთი რიგი არ მოიძებნა" : implode(",", $queueNames)));
        $this->sipParser->commitChanges($newFileString);
        $queueParser->commitChanges($newQueueFileString);
        DB::commit();
        return response()->json([
            'message' => 'Sip has been deleted!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getSipTemplates() {
        $sipTemplate = SipTemplate::all();
        $this->logger->addLogInfo(__METHOD__, [
            'SipTemplate' => 'All'
        ]);
        return $sipTemplate;
    }

    public function getSipTemplate(Request $request) {
        $templateName = $request->input('templateName');
        $sipTemplate = $this->sipParser->getSipTemplate($templateName);
        $this->logger->addLogInfo(__METHOD__, [
            'templateName' => $templateName
        ]);
        return $sipTemplate;
    }

    public function addSipTemplate(Request $request) {
        $this->authorize('createAndDeleteTemplate', Sip::class);
        $templateBlock = $request->input('templateBlock');
        $newFileString = $this->sipParser->addSipTemplate($templateBlock);
        $matches = [];
        $result = preg_match("/^\[.+\]\(!\)/m", $templateBlock, $matches);
        if($result === 0) {
            throw new \RuntimeException("Invalid template Head syntax");
        }
        $templateName = substr($matches[0], 1, strpos($matches[0], config('asterisk.SIP_TEMPLATE_HEAD_LAST_CHAR')) - 1);
        if(SipTemplate::checkIfSipTemplateExistsByName($templateName)) {
            throw new ModelAlreadyExists("Such Template Allready exists, check config file");
        }
        DB::beginTransaction();
        $sipTemplate = SipTemplate::create([
            'name' => $templateName
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'sipTemplate' => $templateName
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის შაბლონის დამატება'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, დაამატა სიპის შაბლონი - %s.", $request->user('api')->username, $sipTemplate->name));
        $this->sipParser->commitChanges($newFileString);
        DB::commit();
        sleep(5);
        return response()->json([
            'message' => 'Template has beed added!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteSipTemplate(Request $request, QueueParser $queueParser) {
        $this->authorize('createAndDeleteTemplate', Sip::class);
        $templateName = $request->input('templateName');
        $newQueueFileString = false;
        $newFileString = $this->sipParser->deleteSipTemplate($templateName);
        if(SipTemplate::checkIfSipTemplateExistsByName($templateName) === false) {
            throw new ModelNotFoundException("Such template doesnt exist, check config file");
        }
        DB::beginTransaction();
        $template = SipTemplate::where('name', $templateName)->first();
        $sipsModels = Sip::where('sip_templates_id', $template->id)->get();
        $sipsArr = $sipsModels->toArray();
        $sipNumbers = array_column($sipsArr, "sip");
        $sipIds = array_column($sipsArr, "id");
        if(count($sipsArr) !== 0) {
            $this->deleteAllSipQueuePairsFromDB($sipIds);
            $newQueueFileString = $queueParser->deleteSipFromAllQueues($sipNumbers);
        }
        SipTemplate::where('name', $templateName)->delete();
        foreach($sipsModels as $sipModel) {
            if($sipModel->operators_id != null) {
                $operator = Operator::find($sipModel->operators_id);
                if($operator->trainee == 0) {
                    $this->updateOperatorSipLogEntry($operator, $sipModel);
                }
            }
        }
        Sip::where('sip_templates_id', $template->id)->delete();
        $this->logger->addLogInfo(__METHOD__, [
            'templateName' => $templateName
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის შაბლონის წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, წაშალა სიპის შაბლონი - %s, წაიშალა სიპები - %s.",
                $request->user()->username,
                $template->name,
                (count($sipNumbers) === 0) ? "ასეთი შაბლონით არ მოიძებნა" : implode(",", $sipNumbers)));
        if($newQueueFileString) {
            $queueParser->commitChanges($newQueueFileString);
        }
        $this->sipParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Template has been deleted!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function editSipTemplate(EditSipTemplate $request) {
        $this->authorize('createAndDeleteTemplate', Sip::class);
        $inputArr = $request->input();
//        logger()->error($inputArr);
        $newName = false;
        $newFileString = $this->sipParser->editTemplate($inputArr['templateBlock'], $inputArr['currentTemplateName'], $newName);
        $sipTemplate = SipTemplate::where('name', $inputArr['currentTemplateName'])->first();
        if($sipTemplate == null) {
            throw new \RuntimeException("Such sip template doesnt exist in DB, check config file!");
        }
//        logger()->error($newName);
        DB::beginTransaction();
        if($newName) {
            if(SipTemplate::where('name', $newName)->first() != null) {
                throw new \RuntimeException("Such sip template already exists in DB, check config file!");
            }
            $sipTemplate->name = $newName;
            $sipTemplate->save();
        }
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის შაბლონის რედაქტირება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, დაარედაქტირა სიპის შაბლონი - %s",
                                                         $request->user('api')->username, $inputArr['currentTemplateName']));
        $this->sipParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
                'message' => 'Sip template has been edited!'
            ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getSips() {
        $sipsWithTemplates =  Sip::getSipsWithTemplatesAndOperatorsAndQueues();
//        $this->logger->addLogInfo(__METHOD__, [
//            'getSips' => 'Alls'
//        ]);
        return $sipsWithTemplates;
    }

    public function testLock() {
//        $ssh = null;
//        $sftp = $this->getSFTPHandler($ssh);
//        $filePath = sprintf("ssh2.sftp://%s//asterisk_configs/etc/tmp_test/sip_agents.conf", $sftp);
//        return $filePath;
//        $fileHandler = $this->openFile($filePath);
//        $lockHandler = $this->openFile(config('asterisk.SIP_LOCK_FILE'));
//        $res = $this->lockFile($lockHandler);
//        if ($res === LOCK_EX) {
//            return "LOCKEEEEEEEEEED!!!!!!!!!!!!!";
//        }
////        sleep(5);
//        return file_get_contents($filePath);
        $ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
                         config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
//        $ssh2->cmd("cd /asterisk_configs");
        return $ssh2->cmd(config('asterisk.SIP_RELOAD'));
    }

}
