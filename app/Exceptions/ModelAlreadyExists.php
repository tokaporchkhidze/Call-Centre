<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/11/2019
 * Time: 12:03 PM
 */

namespace App\Exceptions;


use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class ModelAlreadyExists extends ModelNotFoundException {

    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        if($message == "") {
            $this->message = 'ასეთი ჩანაწერი უკვე არსებობს!';
        }
        $this->code = config('errorCodes.MODEL_ALREADY_EXIST');
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