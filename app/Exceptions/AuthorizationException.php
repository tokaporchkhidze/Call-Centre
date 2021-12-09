<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/16/2019
 * Time: 1:17 PM
 */

namespace App\Exceptions;


use Throwable;

class AuthorizationException extends \Exception {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        if($message == "") {
            $this->message = "This action is forbidden";
        }
        $this->code = config('errorCodes.HTTP_FORBIDDEN');
    }

    public function render() {
        $responseArr = [
            'error' => true,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line
        ];
        if(request()->wantsJson()) {
            return response()->json($responseArr, config('errorCodes.HTTP_FORBIDDEN'));
        }
        return response($responseArr, config('errorCodes.HTTP_FORBIDDEN'));
    }

}