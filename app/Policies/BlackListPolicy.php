<?php

namespace App\Policies;

use App\Task;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BlackListPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function view() {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'blacklist_control');
        } catch(ModelNotFoundException $e) {
            return false;
        }
//        logger()->error($task);
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

    public function createAndDelete() {
        $templateID = User::getUserWithTemplateById(request()->user()->id)['templates_id'];
        try {
            $task = Task::getTaskByTemplateAndName($templateID, 'blacklist_control');
        } catch (ModelNotFoundException $e) {
            return false;
        }
//        logger()->error($task);
        if ($task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

}
