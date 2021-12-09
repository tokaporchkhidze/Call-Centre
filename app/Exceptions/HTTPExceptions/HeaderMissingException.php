<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/9/2019
 * Time: 8:12 PM
 */

namespace App\Exceptions\HTTPExceptions;


use Throwable;

class HeaderMissingException extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);

        $this->message= "Incorrect header accept value";
        $this->code = config('errorCodes.HTTP_NOT_ACCEPTABLE');
    }

    public function render($request) {
        return response()->json([
            'error' => true,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line
        ], config('errorCodes.HTTP_NOT_ACCEPTABLE'));
    }

}