<?php

namespace App\Policies;

use App\Task;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SipPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct() {

    }

    public function view() {
        $templateId = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateId, 'sip_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function createAndDelete() {
        $templateId = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateId, 'sip_control');
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
        $templateId = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateId, 'template_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

}
