<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/14/2019
 * Time: 3:06 PM
 */

namespace App\Http\Controllers\API\UserPanel;



use App\Exceptions\InvalidInputException;
use App\Exceptions\ModelAlreadyExists;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteTemplate;
use App\Http\Requests\GetTemplate;
use App\Http\Requests\StoreTemplate;
use App\Logging\Logger;
use App\Queue;
use App\Task;
use App\Template;
use App\TemplateToQueue;
use App\TemplateToTask;
use App\UserToTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplatesController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getTemplatesList() {
        $templates = Template::all();
        return $templates;
    }

    /**
     * Route: get('/getTemplateWithPermissions', 'TemplatesController@getTemplateWithPermissions')->middleware('checkApiHeader');
     * return template with tasks and queues assigned to it.
     * template id is required input field
     * @param GetTemplate $request
     * @throws
     * @return mixed
     */
    public function getTemplateWithPermissions(GetTemplate $request) {
        $templateId = $request->input('templateId');
        $template = Template::where('id', $templateId)->firstOrFail();
        $template->tasks = Task::getTasksByTemplateId($templateId);
        try {
            $template->queues = Queue::getQueuesByTemplateId($templateId);
        } catch (ModelNotFoundException $e) {
            $template->queues = [];
        }
        return $template;
    }

    /**
     * add new template in system
     * Route: post('/addTemplate', 'TemplatesController@addTemplate')->middleware('checkApiHeader');
     * @param StoreTemplate $request
     * @throws
     */
    public function addTemplate(StoreTemplate $request) {
        $this->authorize('createAndDelete', Template::class);
        $templateName = $request->input('templateName');
        $templateDisplayName = $request->input('templateDisplayName');
        if(Template::where('name', $templateName)->orWhere('display_name', $templateDisplayName)->first() != null) {
            throw new ModelAlreadyExists("შაბლონი ასეთი სახელით ან გამოსაჩენი სახელით უკვე არსებობს");
        }
        $tasks = $request->input('tasks');
        $queues = $request->input('queues');
        $taskIds = [];
        foreach($tasks as $task) {
            $taskIds[] = $task['id'];
        }
        $taskCount = Task::getTaskCountByIds($taskIds);
        if(count($tasks) != $taskCount) {
            throw new InvalidInputException('Dont manipulate my tasks ids');
        }
        $queueCount = Queue::getQueuesCountByIds($queues);
        if($queueCount != count($queues)) {
            throw new InvalidInputException('Dont manipulate my queue ids');
        }
        try {
            DB::beginTransaction();
            $template = Template::create([
                    'name' => $templateName,
                    'display_name' => $templateDisplayName
            ]);
            TemplateToTask::createTemplateTaskBridge($tasks, $template->id);
            TemplateToQueue::createTemplateQueueBridge($queues, $template->id);
            $this->logger->addLogInfo(__METHOD__, [
                'templateName' => $templateName,
                'templateDisplayName' => $templateDisplayName,
                'tasks' => $tasks,
                'queues' => $queues,
                'message' => 'added use template'
            ]);
            $this->logger->addLogInfo("API", config('logging.mongo_mapping.შაბლონის დამატება'));
            $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, დაამატა შაბლონი - %s.",
                                                             $request->user('api')->username, $templateName));
            DB::commit();
            return response()->json([
                'message' => 'Added new template'
            ], config('errorCodes.HTTP_SUCCESS'));
        } catch(QueryException $e) {
            $exceptionInfo = [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            throw $e;
        }
    }

    public function deleteTemplate(DeleteTemplate $request) {
        $this->authorize('createAndDelete', Template::class);
        $templateId = $request->input('templateId');
        $template = Template::where('id', $templateId)->first();
        if($template == null) {
            throw new ModelNotFoundException('Template not found, cannot delete');
        }
        try {
            DB::beginTransaction();
            Template::destroy($templateId);
            $userToTemplate = UserToTemplate::where('templates_id', $templateId)->delete();
            $templateToTask = TemplateToTask::where('templates_id', $templateId)->delete();
            $templateToQueue = TemplateToQueue::where('templates_id', $templateId)->delete();
            $this->logger->addLogInfo(__METHOD__, [
                'template' => $template->toArray(),
                'userToTemplateCount' => $userToTemplate,
                'templateToTaskCount' => $templateToTask,
                'templateToQueueCount' => $templateToQueue,
                'message' => 'deleted template'
            ]);
            $this->logger->addLogInfo("API", config('logging.mongo_mapping.შაბლონის წაშლა'));
            $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, წაშალა შაბლონი - %s.", $request->user('api')->username, $template->display_name));
            DB::commit();
            return response()->json([
                'message' => 'Deleted user template'
            ], config('errorCodes.HTTP_SUCCESS'));
        } catch(QueryException $e) {
            $exceptionInfo = [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            throw $e;
        }
    }

    public function editTemplate(Request $request) {
        $this->authorize('createAndDelete', Template::class);
        $templateName = $request->input('templateName');
        $templateDisplayName = $request->input('templateDisplayName');
        if(Template::where('name', $templateName)->orWhere('display_name', $templateDisplayName)->first() == null) {
            throw new ModelAlreadyExists("შაბლონი არ არსებობს");
        }
        $tasks = $request->input('tasks');
        $queues = $request->input('queues');
        $taskIds = [];
        foreach($tasks as $task) {
            $taskIds[] = $task['id'];
        }
        $taskCount = Task::getTaskCountByIds($taskIds);
        if(count($tasks) != $taskCount) {
            throw new InvalidInputException('Dont manipulate my tasks ids');
        }
        $queueCount = Queue::getQueuesCountByIds($queues);
        if($queueCount != count($queues)) {
            throw new InvalidInputException('Dont manipulate my queue ids');
        }
        DB::beginTransaction();
        $templateID = Template::where('name', $templateName)->first()->id;
        TemplateToTask::where('templates_id', $templateID)->delete();
        TemplateToQueue::where('templates_id', $templateID)->delete();
        TemplateToTask::createTemplateTaskBridge($tasks, $templateID);
        TemplateToQueue::createTemplateQueueBridge($queues, $templateID);
        $this->logger->addLogInfo(__METHOD__, [
            'templateName' => $templateName,
            'templateDisplayName' => $templateDisplayName,
            'tasks' => $tasks,
            'queues' => $queues,
            'message' => 'Updated user template'
        ]);
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.შაბლონის რედაქტირება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s, დაარედაქტირა შაბლონი - %s.", $request->user('api')->username, $templateDisplayName));
        DB::commit();
        return response()->json([
            'message' => 'Updated template'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}