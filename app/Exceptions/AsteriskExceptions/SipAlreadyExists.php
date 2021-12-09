<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/9/2019
 * Time: 7:36 PM
 */

namespace App\Exceptions\AsteriskExceptions;


use Throwable;

class SipAlreadyExists extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        $this->message = "Aseti Sip ukve Arsebobs";
        $this->code = config('errorCodes.SIP_ALREADY_EXIST');

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