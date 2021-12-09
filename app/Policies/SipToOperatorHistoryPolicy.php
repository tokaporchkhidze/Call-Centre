<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SipToOperatorHistoryPolicy
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
            $task = Task::getTaskByTemplateAndName($templateId, 'user_control'); // shesacvleliaa
        } catch(ModelNotFoundException $e) {
            return false;
        }
        if($task['permission'] == config('permissions.READ') or $task['permission'] == config('permissions.WRITE')) {
            return true;
        } else {
            return false;
        }
    }

}
