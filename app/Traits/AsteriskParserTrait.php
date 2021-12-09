<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/6/2019
 * Time: 3:14 PM
 */

namespace App\Traits;


trait AsteriskParserTrait {

    private function lockFile(&$fileHandle) {
        $wouldBlock = "";
        if(flock($fileHandle, LOCK_EX, $wouldBlock) === false) {
            throw new \RuntimeException("Cannot get lock on file!");
        } else {
//            logger()->error('locked file');
        }
        return true;
    }

    private function unlockFile(&$filehandle) {
        if(flock($filehandle, LOCK_UN) === false) {
            throw new \RuntimeException("Cannot unlock file!");
        }
    }

    private function openFile($filePath) {
        $fileHandle = fopen($filePath, "r");
        if($fileHandle === false) {
            $err = error_get_last();
            throw new \RuntimeException($err['message']);
        }
        return $fileHandle;
    }

    private function closeFile(&$fileHandle) {
        fclose($fileHandle);
    }

    private function sanitizeInput(string $inputString, array $stringsToremove) {
        $sanitizedString = $inputString;
        foreach($stringsToremove as $string) {
            $regex = sprintf("/.*%s.*\\s?/m", $string);
            $sanitizedString = preg_replace($regex, "", $sanitizedString);
        }
        return $sanitizedString;
    }



}