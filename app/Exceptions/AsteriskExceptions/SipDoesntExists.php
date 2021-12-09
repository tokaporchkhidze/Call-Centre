<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/10/2019
 * Time: 4:35 PM
 */

namespace App\Exceptions\AsteriskExceptions;


use Throwable;

class SipDoesntExists extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        $this->message = "Aseti sipi ar arsebobs!";
        $this->code = config('errorCodes.SIP_DOESNT_EXIST');
    }

    public function render($request) {
        return response()->json([
            'error' => true,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line
        ], config('errorCodes.HTTP_INTERNAL_ERROR'));
    }

}