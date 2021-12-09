<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/30/2019
 * Time: 12:57 PM
 */

namespace App\Http\Controllers\API\UserPanel;


use App\Http\Controllers\Controller;
use App\Logging\Logger;
use Illuminate\Http\Request;

class SipsToOperatorsHistoryController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

}