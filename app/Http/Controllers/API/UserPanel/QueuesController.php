<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/15/2019
 * Time: 3:33 PM
 */

namespace App\Http\Controllers\API\UserPanel;


use App\AsteriskHandlers\QueueParser;
use App\AsteriskHandlers\SipParser;
use App\Exceptions\ModelAlreadyExists;
use App\Http\Controllers\Controller;
use App\Logging\Logger;
use App\Queue;
use App\QueueGroup;
use App\QueuesToGroups;
use App\QueueTemplate;
use App\Sip;
use App\SipToQueue;
use App\TemplateToQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueuesController extends Controller {

    /**
     * @var QueueParser
     */
    private $queueParser;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(QueueParser $queueParser) {
        $this->queueParser = $queueParser;
        $this->logger = Logger::instance();
    }

    /**
     * Function returns all queues from DB!
     *
     * Route:
     *
     * @return Queue[]|\Illuminate\Database\Eloquent\Collection
     * @throws
     */
    public function getQueues() {
        $queuesWithTemplates = Queue::getQueuesWithTemplates();
//        $this->logger->addLogInfo(__METHOD__, [
//            'message' => 'get all queues with templates'
//        ]);
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, მოითხოვა რიგების სია.",
//            request()->user('api')->username));
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getQueues'));
        return $queuesWithTemplates;
    }

    /**
     * Function returns all queues with corresponding queue templates from DB!
     *
     * @return QueueTemplate[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getQueueTemplates() {
        $templates = QueueTemplate::all();
//        $this->logger->addLogInfo(__METHOD__, [
//            'getQueueTemplates' => 'all',
//            'message' => 'get all queue templates'
//        ]);
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, მოითხოვა რიგების შაბლონების სია.",
//            request()->user('api')->username));
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getQueueTemplates'));
        return $templates;
    }

    /**
     * Function returns sips by queue name from DB!
     *
     * @param Request $request
     * @return array
     */
    public function getSipsByQueueName(Request $request) {
        $request->validate([
            'queueName' => ['required', 'string'],
        ]);
        $inputArr = $request->input();
        $queue = null;
        $queue = Queue::where('name', $inputArr['queueName'])->first();
        if($queue == null) {
            throw new \RuntimeException("Queue doesnt exist in DB!");
        }
        $sips = Queue::getSipsByQueueName($inputArr['queueName']);
//        $this->logger->addLogInfo(__METHOD__, [
//            'queueName' => $inputArr['queueName'],
//            'message' => 'get all sips in queue by queue\'s name'
//        ]);
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, მოითხოვა სიპების სია, რიგისთვის - %s",
//            $request->user('api')->username, $queue->display_name));
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getSipsByQueueName'));
        return $sips;
    }

    public function getDistinctSipsByQueues(Request $request) {
        $inputArr = $request->input();
        $request->validate([
            'queueArr' => ['required', 'array']
        ]);
        return Queue::getDistinctSipsByQueues($inputArr['queueArr']);
    }

    /**
     * Function gets queue by name from DB!
     *
     * @param Request $request
     * @return mixed
     */
    public function getQueueByName(Request $request) {
        $request->validate([
            'queueName' => ['required', 'string'],
        ]);
        $queueName = $request->input('queueName');
        $queue = Queue::where('name', $queueName)->firstOrFail();
//        $this->logger->addLogInfo(__METHOD__, [
//            'queueName' => $queueName,
//            'message' => 'get queue by name'
//        ]);
//        $this->logger->addLogInfo("API", config('logging.mongo_mapping.getQueueByName'));
//        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, მოითხოვა რიგი - %s",
//            $request->user('api')->username, $queue->display_name));
        return $queue;
    }

    /**
     * Function adds template in asterisk queue config file and DB.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function addTemplate(Request $request) {
        $this->authorize('createAndDeleteTemplate', Queue::class);
        $templateBlock = $request->input('templateBlock');
        $request->validate([
            'templateBlock' => ['required'],
        ]);
        // add new template block in queue config file
        $newFileString = $this->queueParser->addTemplate($request->input('templateBlock'));
        $matches = [];
        $result = preg_match("/^\[[0-9a-zA-z\-]+\]\(!\)/m", $templateBlock, $matches);
        if($result === 0) {
            throw new \RuntimeException("არასწორი სინტაქსი შაბლონის ქუდისთვის!");
        }
        // for database get template name from template block string
        $templateHead = $matches[0];
        $templateName = substr($templateHead, 1, -4);
        DB::beginTransaction();
        $queueTemplate = QueueTemplate::create([
            'name' => $templateName
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'template' => $templateBlock,
            'message' => 'add queue template in DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის შაბლონის დამატება'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, დაამატა რიგის შაბლონი - %s.", $request->user('api')->username, $queueTemplate->name));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Template has been Added!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteTemplate(Request $request) {
        $this->authorize('createAndDeleteTemplate', Queue::class);
        $request->validate([
            'templateName' => ['required'],
        ]);
        $templateName = $request->input('templateName');
        if(isset($templateName) === false) {
            return response()->json([
                'templateName is required field!'
            ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
        }
        $newFileString = $this->queueParser->deleteTemplate($templateName);
        if(QueueTemplate::checkIfExistsByName($templateName) === false) {
            throw new ModelNotFoundException("Such Template doesnt exist, check config file!");
        }
        DB::beginTransaction();
        $template = QueueTemplate::where("name", $templateName)->first();
        $queues = Queue::where('queue_templates_id', $template->id)->get();
        $queueNames = array();
        if($queues != null) {
            foreach ($queues as $queue) {
                $queueNames[] = $queue->display_name;
            }
        }
        $template->delete();
        Queue::where('queue_templates_id', $template->id)->delete();
        $this->logger->addLogInfo(__METHOD__, [
            'template' => $templateName,
            'messagee' => 'deleted queue template from DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის შაბლონის წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, წაშალა რიგის შაბლონი - %s, შაბლონთან ერთად წაიშალა რიგები - %s",
                $request->user('api')->username, $template->name,
                (count($queueNames) == 0) ? "ასეთი შაბლონით რიგირ არ მოიძებნა" : implode(',', $queueNames)));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Deleted queue template!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function addQueue(Request $request) {
        $this->authorize('createAndDelete', Queue::class);
        $request->validate([
            'queueBlock' => ['required'],
            'displayName' => ['required'],
            'groupIDArr' => ['nullable', 'array']
        ]);
        $inputArr = $request->input();
        $newFileString = $this->queueParser->addQueue($inputArr['queueBlock']);
        $matches = [];
        $result = preg_match("/^\[.+\]\([0-9a-zA-z\-]+\)/m", $inputArr['queueBlock'], $matches);
        if($result === 0) {
            throw new \RuntimeException("Invalid Queue Head syntax");
        }
        $queueHead = $matches[0];
        $queueName = substr($queueHead, 1, strpos($inputArr['queueBlock'], config('asterisk.QUEUE_HEAD_END_DELIMITER')) - 1);
        $templateName = substr($queueHead, strpos($queueHead, config('asterisk.QUEUE_HEAD_TEMPLATE_START_DELIMITER')) + 1, -1);
        if(Queue::checkIfExistsByName($queueName)) {
            throw new ModelAlreadyExists("Such Queue already exists in DB, check config file");
        }
        if(QueueTemplate::checkIfExistsByName($templateName) === false) {
            throw new ModelNotFoundException("Such template doesnt exist in DB, check config file");
        }
        DB::beginTransaction();
        $queue = Queue::create([
            'name' => $queueName,
            'display_name' => $inputArr['displayName'],
            'description' => $inputArr['description'] ?? null,
            'queue_templates_id' => QueueTemplate::where('name', $templateName)->first()->id
        ]);
        if(isset($inputArr['groupIDArr'])) {
            $this->createQueueToGroupBridge($queue->id, $inputArr['groupIDArr']);
            $groupNameArr = QueueGroup::whereIn("id", $inputArr['groupIDArr'])->get()->pluck('display_name')->toArray();
        }
        $this->logger->addLogInfo(__METHOD__, [
            'queueBlock' => $inputArr['queueBlock'],
            'parsedQueueName' => $queueName,
            'parsedTemplateName' => $templateName,
            'displayName' => $inputArr['displayName'],
            'groupIDs' => isset($inputArr['groupIDArr']) ? implode(",", $inputArr['groupIDArr']) : "",
            'groupNames' => isset($inputArr['groupIDArr']) ? implode(",", $groupNameArr) : "",
            'message' => 'added queue in DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის დამატება'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, დაამატა რიგი - %s. ჯგუფები - %s",
                    $request->user('api')->username, $queue->display_name,
                    (isset($inputArr['groupIDArr'])) ? implode(",", $groupNameArr) : ""));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response([
            'message' => 'Queue has been added'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    private function createQueueToGroupBridge(int $queueID, array $groupIDArr) {
//        logger()->error($queueID);
//        logger()->error($groupIDArr);
        if(QueuesToGroups::ifAllreadyExists($queueID, $groupIDArr)) throw new \RuntimeException("რიგი უკვე ჯგუფის წევრია!");
        foreach($groupIDArr as $groupID) {
            QueuesToGroups::create([
                'queues_id' => $queueID,
                'queue_groups_id' => $groupID
            ]);
        }
        return true;
    }

    public function deleteQueue(Request $request) {
        $this->authorize('createAndDelete', Queue::class);
        $request->validate([
            'queueName' => ['required']
        ]);
        $queueName = $request->input('queueName');
        $newFileString = $this->queueParser->deleteQueue($queueName);
        if(Queue::checkIfExistsByName($queueName) === false) {
            throw new ModelNotFoundException("Such queue doesnt exist, check config file");
        }
        DB::beginTransaction();
        $queue = Queue::where('name', $queueName)->first();
        Queue::where('name', $queueName)->delete();
        SipToQueue::where('queues_id', $queue->id)->delete();
        TemplateToQueue::where('queues_id', $queue->id)->delete();
        QueuesToGroups::where('queues_id', $queue->id)->delete();
        $this->logger->addLogInfo(__METHOD__, [
            'queueName' => $queueName,
            'message' => 'deleted queue from DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, წაშალა რიგი - %s.", $request->user('api')->username, $queue->display_name));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Queue has been deleted!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function addSipInQueue(Request $request, SipParser $sipParser) {
        $request->validate([
            'sipNumber' => ['required'],
            'queueName' => ['required']
        ]);
        $inputArr = $request->input();
        $this->authorize("createAndDeleteSip", [Queue::class, $inputArr['queueName']]);
        $sipFileString = file_get_contents($sipParser->getSipConfigPath());
        if($sipParser->ifSipExists($inputArr['sipNumber'], $sipFileString) === false) {
            throw new \RuntimeException('Sip doesnt exist!');
        }
        $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        if($sip == null) {
            throw new ModelNotFoundException("Sip doesnt exist in DB, check config file!!!");
        }
        $newFileString = $this->queueParser->addSipInQueue($inputArr['sipNumber'], $inputArr['queueName'], $inputArr['priority'] ?? null);

        $queue = Queue::where('name', $inputArr['queueName'])->first();
        if($queue == null) {
            throw new ModelNotFoundException("queue doesnt exist, check config file!");
        }
        if(SipToQueue::checkIfExistsBySipAndQueue($sip->id, $queue->id)) {
            throw new ModelAlreadyExists("Sip is already in queue, check config file");
        }
        DB::beginTransaction();
        SipToQueue::create([
            'sips_id' => $sip->id,
            'queues_id' => $queue->id,
            'priority' => $inputArr['priority'] ?? 0
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'sipNumber' => intval($inputArr['sipNumber']),
            'queueName' => $inputArr['queueName'],
            'message' => 'add sip in queue DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის რიგში დამატება'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, სიპი - %s, დაამატა რიგში - %s",
                $request->user('api')->username, $sip->sip, $queue->display_name));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Sip added in queue!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function addSipInQueueBulk(Request $request, SipParser $sipParser) {
        $request->validate([
            'sipArr' => ['required', 'array'],
            'queueName' => ['required'],
        ]);
        $inputArr = $request->input();
        $this->authorize("createAndDeleteSip", [Queue::class, $inputArr['queueName']]);
        $sipFileString = file_get_contents($sipParser->getSipConfigPath());
        $sipArr = $inputArr['sipArr'];
        foreach($sipArr as $sipNum) {
            if($sipParser->ifSipExists($sipNum, $sipFileString) === false) {
                throw new ModelNotFoundException("Sip doesnt exist: $sipNum!");
            }
        }
        $sips = Sip::whereIn('sip', $sipArr)->get();
        $sipsCount = $sips->count();
        if(count($sipArr) != $sipsCount) {
            throw new \RuntimeException("Some sips doesnt exist in DB, check config file");
        }
        $newFileString = $this->queueParser->addBulkSipInQueue($sipArr, $inputArr['queueName']);
        $queue = Queue::where('name', $inputArr['queueName'])->first();
        if($queue == null) {
            throw new ModelNotFoundException("Queue doesnt exist in DB, check config file!");
        }
        if(SipToQueue::checkIfExistsBySipAndQueueBulk($sipArr, $queue->id)) {
            throw new ModelAlreadyExists('Sip in queue already exists, check config file!');
        }
        $insertBulk = array();
        foreach($sips as $sip) {
            $insertBulk[] = [
                'sips_id' => $sip->id,
                'queues_id' => $queue->id
            ];
        }
        DB::beginTransaction();
        SipToQueue::insert($insertBulk);
        $sipsString = implode(',', $sipArr);
        $this->logger->addLogInfo(__METHOD__, [
            'sipArr' => $sipsString,
            'queueName' => $inputArr['queueName'],
            'message' => 'added given range of sips in DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რამოდენიმე სიპის რიგში დამატება'));
        $this->logger->addLogInfo("displayName",
            sprintf("მომხამრებელმა - %s, დაამატა სიპები - %s, რიგში - %s.",
                $request->user('api')->username, $sipsString, $inputArr['queueName']));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
            'message' => 'Given range of sips was inserted in queue!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteSipFromQueue(Request $request, SipParser $sipParser) {
        $request->validate([
            'sipNumber' => ['required'],
            'queueName' => ['required'],
        ]);
        $inputArr = $request->input();
        $this->authorize("createAndDeleteSip", [Queue::class, $inputArr['queueName']]);
        $sipFileString = file_get_contents($sipParser->getSipConfigPath());
        if($sipParser->ifSipExists($inputArr['sipNumber'], $sipFileString) === false) {
            throw new \RuntimeException(sprintf('Sip doesnt exist: %s', $inputArr['sipNumber']));
        }
        $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        if($sip == null) {
            throw new ModelNotFoundException(sprintf("Sip doesnt exist in DB, check config file: %s", $inputArr['sipNumber']));
        }
        $newFilesTring = $this->queueParser->deleteSipFromQueue($inputArr['sipNumber'], $inputArr['queueName'], $inputArr['priority'] ?? null);
        if(Queue::checkIfExistsByName($inputArr['queueName']) === false) {
            throw new \RuntimeException("Queue doesnt exist, check config file!");
        }
        DB::beginTransaction();
        $queue = Queue::where('name', $inputArr['queueName'])->first();
        SipToQueue::where('queues_id', $queue->id)->where('sips_id', $sip->id)->delete();
        $this->logger->addLogInfo(__METHOD__, [
            'sipNumber' => $sip->sip,
            'queueName' => $queue->name,
            'queueDisplayName' => $queue->display_name,
            'message' => 'deleted sip from queue DB'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგიდან სიპის წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხამრებელმა - %s, წაშალა სიპი - %s, რიგიდან - %s.",
                $request->user('api')->username, $sip->sip, $queue->display_name));
        $this->queueParser->commitChanges($newFilesTring);
        DB::commit();
        return response()->json([
            'message' => 'Sip has been removed from queue'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function deleteSipFromAllQueues(Request $request) {
        $this->authorize("createAndDelete", Queue::class);
        $inputArr = $request->input();
        $newFileString = $this->queueParser->deleteSipFromAllQueues($inputArr['sipNumber']);
        $sip = Sip::where('sip', $inputArr['sipNumber'])->first();
        DB::beginTransaction();
        $queues = Queue::getQueuesBySip($sip->sip);
        $queuesNames = array();
        if(count($queues) != 0) {
            foreach($queues as $queue) {
                $queuesNames[] = $queue['display_name'];
            }
        }
        SipToQueue::where('sips_id', $sip->id)->delete();
        $this->logger->addLogInfo(__METHOD__, [
            'sip' => $inputArr['sipNumber']
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის ყველა შესაძლო რიგიდან წაშლა'));
        $this->logger->addLogInfo("displayInfo",
            sprintf("მომხმარებელმა - %s, წაშალა სიპი - %s, ყველა შესაძლო რიგიდან - %s.", $request->user('api')->username,
                $sip->sip, (count($queuesNames) == 0) ? "სიპზე არც ერთი რიგი არ მოიძებნა" : implode(",", $queuesNames)));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
    }

    public function editQueue(Request $request) {
        $this->authorize("createAndDelete", Queue::class);
        $inputArr = $request->input();
        $newName = false;
        $newTemplate = false;
        $newFileString =  $this->queueParser->editQueue($inputArr['queueBlock'], $inputArr['currentName'],
                                                        $inputArr['currentTemplate'], $newName, $newTemplate);
        $queue = Queue::where('name', $inputArr['currentName'])->first();
        if($queue == null) {
            throw new \RuntimeException("Queue doesnt exist in DB, check config file!");
        }
        $queueTemplate = QueueTemplate::find($queue->queue_templates_id);
        if($queueTemplate == null) {
            throw new \RuntimeException("Queue template doesnt exist in DB, check config file!");
        }
        if($queueTemplate->name != $inputArr['currentTemplate']) {
            throw new \RuntimeException("Given queue and template combination is not correct!");
        }
        DB::beginTransaction();
        if($newName) {
            if(Queue::where('name', $newName) != null) {
                throw new \RuntimeException("Queue already exists in DB, check config file!");
            }
            $queue->name = $newName;
        }
        if(isset($inputArr['newDisplayName']) && $queue->display_name != $inputArr['newDisplayName']) {
            if(Queue::where('display_name', $inputArr['newDisplayName'])->first() != null) {
                throw new \RuntimeException("Such display name already exists in DB!");
            }
            $queue->display_name = $inputArr['newDisplayName'];
        }
        if(isset($inputArr['newDescription']) && $queue->description != $inputArr['newDescription']) {
            $queue->description = $inputArr['newDescription'];
        }
        if($newTemplate) {
            $newQueueTemplate = QueueTemplate::where('name', $newTemplate)->first();
            if($newQueueTemplate == null) {
                throw new \RuntimeException("Cannot change queue's template, doesnt exist in DB, check config file!");
            }
            $queue->queue_templates_id = $newQueueTemplate->id;
        }
        $queue->save();
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის რედაქტირება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, დაარედაქტირა რიგი - %s", $request->user('api')->username, $inputArr['currentName']));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
        return response()->json([
                'message' => 'Queue has been edited!'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function editTemplate(Request $request) {
        $this->authorize("createAndDeleteTemplate", Queue::class);
        $inputArr = $request->input();
        $newName = false;
        $newFileString = $this->queueParser->editTemplate($inputArr['templateBlock'], $inputArr['currentName'], $newName);
        $template = QueueTemplate::where("name", $inputArr['currentName'])->first();
        if($template == null) {
            throw new \RuntimeException("Such template doesnt exist in DB, check config file!");
        }
        DB::beginTransaction();
        if($newName) {
            if(QueueTemplate::where('name', $newName)->first() != null) {
                throw new \RuntimeException("Cannot change to given name, already exists!");
            }
            $template->name = $newName;
            $template->save();
        }
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.რიგის შაბლონის რედაქტირება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, დაარედაქტირა რიგის შაბლონი - %s",
                                                         $request->user('api')->username, $inputArr['currentName']));
        $this->queueParser->commitChanges($newFileString);
        DB::commit();
    }

    public function getQueueBlock(Request $request) {
        $inputArr = $request->input();
        $queueBlock = $this->queueParser->getQueueBlock($inputArr['queueName']);
        $queue = Queue::where('name', $inputArr['queueName'])->first();
        if($queue == null) {
            throw new \RuntimeException("Such queue doesnt exist in DB, check config file!");
        }
        return response()->json([
            'queueBlock' => $queueBlock,
            'queue' => $queue
        ]);
    }

    public function getTemplateBlock(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        $templateBlock = $this->queueParser->getTemplateBlock($inputArr['templateName']);
        $template = QueueTemplate::where('name', $inputArr['templateName'])->first();
        if($template == null) {
            throw new \RuntimeException("Such template doesnt exist in DB, check config file!");
        }
        return response()->json([
            'templateBlock' => $templateBlock,
            'template' => $template
        ]);
    }

    public function getQueuesBySip(Request $request) {
        $inputArr = $request->input();
        return Queue::getQueuesBySip($inputArr['sipNumber']);
    }

    public function refreshPriorities(Request $request, SipParser $sipParser) {
        $inputArr = $request->input();
        if(isset($inputArr['sipNumber']) && is_numeric($inputArr['sipNumber'])) {
            # refreshi konkretuli sipistvis
            $sipsArr = Sip::getSipsWithQueueGroups($inputArr['sipNumber']);
        } else {
            # refreshi yvela sipistvis
            $sipsArr = Sip::getSipsWithQueueGroups();
        }
        $sipFileString = file_get_contents($sipParser->getSipConfigPath());
        foreach(array_keys($sipsArr) as $sip) {
            if($sipParser->ifSipExists($sip, $sipFileString) === false) {
                throw new \RuntimeException('Sip[$sip] doesnt exist!');
            }
            $sip = Sip::where('sip', $sip)->first();
            if($sip == null) {
                throw new ModelNotFoundException("Sip doesnt exist in DB, check config file!!!");
            }
        }
        $priorities = array();
        foreach($sipsArr as $sip => $queueGroupsArr) {
            if(array_search(config('asterisk.PREPAID_GROUP'), $queueGroupsArr) !== false) {
                $priorities[$sip][config('asterisk.PREPAID_GROUP')]
                    = $this->calculatePriority($queueGroupsArr, config('asterisk.PREPAID_GROUP'));
            }
            if(array_search(config('asterisk.B2C_GROUP'), $queueGroupsArr) !== false) {
                $priorities[$sip][config('asterisk.B2C_GROUP')]
                    = $this->calculatePriority($queueGroupsArr, config('asterisk.B2C_GROUP'));
            }
            if(array_search(config('asterisk.B2B_GROUP'), $queueGroupsArr) !== false) {
                $priorities[$sip][config('asterisk.B2B_GROUP')]
                    = $this->calculatePriority($queueGroupsArr, config('asterisk.B2B_GROUP'));
            }
        }
        $newFileString = $this->queueParser->refreshSipPriorities($priorities);
        $this->queueParser->commitChanges($newFileString);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.სიპის პრიორიტეტების განახლება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s სიპის პრიორიტეტები განაახლა!", $request->user('api')->username));
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    private function calculatePriority(array $allQueueGroups, string $currGroup): int {

        if(empty($allQueueGroups)) return config('asterisk.DEFAULT_PRIORITY');
        switch($currGroup) {
            case config('asterisk.PREPAID_GROUP'):
                return count($allQueueGroups);
            case config('asterisk.B2C_GROUP'):
                # B2C-is prioritetis logika:
                # 3kill = 2prioriteti, 2skill = 1prioriteti
                # yvela sxva shemtxveva arasworia da default prioritets vadzlevt.
                if(count($allQueueGroups) == 3) {
                    return 2;
                } else if(count($allQueueGroups) == 2) {
                    return 1;
                } else {
                    return config('asterisk.DEFAULT_PRIORITY');
                }
            case config('asterisk.B2B_GROUP'):
                return 1; // am momentistvis B2B_is logika ar aqvs, yoveltvis prioriteti = 1.
            default:
                throw new \RuntimeException("Unknown queue group, cannot calculate priority!");
        }
    }

}
