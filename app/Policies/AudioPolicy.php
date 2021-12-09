<?php

namespace App\Policies;

use App\Queue;
use App\Task;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AudioPolicy {

    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    public function view($user, $requestSip=null) {
        $templateID = User::getUserWithTemplateById($user->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'audio_control');
        } catch(ModelNotFoundException $e) {
            if(isset($requestSip)) {
                $user = User::getUserWithOperatorAndSip($user->id);
                if(isset($user['sip']) and $user['sip'] == $requestSip) {
                    return true;
                }
            }
            return false;
        }
//        logger()->error($task);
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function play($audio, $queueName) {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'audio_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
//        logger()->error($task);
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            $res = Queue::ifBelongsToTemplate($templateID, $queueName);
            if($res === false) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function download($audio, $queueName) {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'audio_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
//        logger()->error($task);
        if($task['permission'] == config('permissions.WRITE')) {
            $res = Queue::ifBelongsToTemplate($templateID, $queueName);
            if($res === false) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

}
