<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/15/2019
 * Time: 6:06 PM
 */

namespace App\Exceptions;

use Throwable;

class InvalidInputException extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        if($message == "") {
            $this->message = "Invalid input";
        }
        $this->code = config('errorCodes.HTTP_UNPROCESSABLE_ENTITY');
    }

    public function render() {
        return response()->json([
            'error' => true,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line
        ], config('errorCodes.HTTP_UNPROCESSABLE_ENTITY'));
    }

}