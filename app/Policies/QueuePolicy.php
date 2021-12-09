<?php

namespace App\Policies;

use App\Queue;
use App\Task;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QueuePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    public function view() {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'queue_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
//        logger()->error($templateID);
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function createAndDelete() {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'queue_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function createAndDeleteTemplate() {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'template_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function createAndDeleteSip($queueModel, $queueName) {
        $templateID = User::getUserWithTemplateById(request()->user('api')->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'sip_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            $res = Queue::ifBelongsToTemplate($templateID, $queueName);
            if($res) {
                return true;
            }
            return false;
        } else {
            return false;
        }
    }

}
