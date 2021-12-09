<?php

namespace App\Policies;

use App\Exceptions\InvalidInputException;
use App\Task;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserPolicy {
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
        $templateId = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateId, 'user_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function create($userModel, $priority) {
        $template = User::getUserWithTemplateById(request()->user()->id);
        $templateID = $template['templates_id'];
        $currUserPriority = $template['priority'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'user_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            if(intval($currUserPriority) < intval($priority)) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function delete($user, $priority) {
        $template = User::getUserWithTemplateById(request()->user()->id);
        $templateID = $template['templates_id'];
        $currUserPriority = $template['priority'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'user_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            if(intval($currUserPriority) <= intval($priority)) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

}
