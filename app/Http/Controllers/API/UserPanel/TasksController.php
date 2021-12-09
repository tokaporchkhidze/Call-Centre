<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/14/2019
 * Time: 11:28 AM
 */

namespace App\Http\Controllers\API\UserPanel;


use App\Http\Controllers\Controller;
use App\Logging\Logger;
use App\Task;

class TasksController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getTasks() {
        $tasks = Task::all();
        return $tasks;
    }

}