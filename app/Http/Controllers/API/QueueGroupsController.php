<?php

namespace App\Http\Controllers\API;

use App\QueueGroup;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class QueueGroupsController extends Controller {

    public function addGroup(Request $request) {

    }

    public function getGroupsWithQueues(Request $request) {
        $inputArr = $request->input();
        return QueueGroup::getGroupsWithQueues($inputArr['groupName'] ?? null);
    }

    public function getGroups() {
        return QueueGroup::all();
    }

}
