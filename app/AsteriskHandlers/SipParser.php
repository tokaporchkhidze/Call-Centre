<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/9/2019
 * Time: 6:07 PM
 */

namespace App\AsteriskHandlers;


use App\Common\SSH2;
use App\Exceptions\AsteriskExceptions\SipDoesntExists;
use App\Logging\Logger;
use App\Exceptions\AsteriskExceptions\SipAlreadyExists;
use App\Traits\AsteriskParserTrait;

class SipParser {

    use AsteriskParserTrait;

    /**
     * @var Logger
     */
    private $logger;

    private $filePath;

    private $backupPath;

    private $lockHandle;

    private $ssh2;

    private $sftp;

    public function __construct() {
        $this->logger = Logger::instance();
        $this->lockHandle = $this->openFile(config('asterisk.SIP_LOCK_FILE'));
        $this->lockFile($this->lockHandle);
        $this->ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
                                       config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
        $this->sftp = $this->ssh2->getSFTPHandler();
        $this->filePath = sprintf("ssh2.sftp://%s%s", $this->sftp, config('asterisk.SIP_AGENTS'));
        $this->backupPath = sprintf("ssh2.sftp://%s%s", $this->sftp, config('asterisk.SIP_AGENTS_BACKUP'));
    }

    public function __destruct() {
        $this->unlockFile($this->lockHandle);
        fclose($this->lockHandle);
        unset($this->ssh);
    }


    public function addSip($sipNumber, $templateName, $comment) {
        //first of all check if such sip exists in config file, because
        //sips must be unique
        $fileString = file_get_contents($this->filePath);
        if($this->ifSipExists($sipNumber, $fileString)) {
            throw new \RuntimeException('Sip already exists');
        }
        if($this->ifTemplateExists("[".$templateName."](!)", $fileString) === false) {
            throw new \RuntimeException('Such template doesnt exist,first create template, then sip');
        }
        if($comment != null) {
            $comment = ";".$comment;
        }
        $newSipBlock = sprintf("\n\n%s\n%s\n%s",
            config('asterisk.SIP_AGENT_BLOCK_START'),
            sprintf(config('asterisk.SIP_BLOCK_TEMPLATE'),
                ($comment != null) ? str_replace("\n", "\n;", $comment)."\n" : "",
                    $sipNumber, $templateName, $sipNumber, $sipNumber),
            config('asterisk.SIP_AGENT_BLOCK_END'));
        $searchRes = strrpos($fileString, config('asterisk.SIP_AGENT_BLOCK_END'));
        if($searchRes === false) { // this means no other sips in config file yet!!!
            $startSipSection = strpos($fileString, config('asterisk.SIP_AGENT_SECTION_START'));
            $insertPoint = $startSipSection + strlen(config('asterisk.SIP_AGENT_SECTION_START')); // on this index will be inserted new sip
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        } else {
            $lastSipBlockPost = $searchRes;
            $insertPoint = $lastSipBlockPost + strlen(config('asterisk.SIP_AGENT_BLOCK_END'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        }
        $newFileString = sprintf("%s%s%s",
            $startPart,
            $newSipBlock,
            $endPart);
        $this->logger->addLogInfo(__METHOD__, [
            'sip' => $sipNumber,
            'template' => $templateName,
            'comment' => $comment ?? ""
        ]);
        return $newFileString;
    }

    public function addSipBulk(int $start, int $end, string $templateName, $comment) {
        $sipNumbers = range($start, $end, 1);
        $fileString = file_get_contents($this->filePath);
        foreach($sipNumbers as $sipNumber) {
            if($this->ifSipExists($sipNumber, $fileString)) {
                throw new \RuntimeException(sprintf("Sip already exists: %s", $sipNumber));
            }
        }
        if($this->ifTemplateExists(sprintf("[%s](!)", $templateName), $fileString) === false) {
            throw new \RuntimeException("Template doesnt exist!");
        }
        if($comment != null) {
            $comment = ";".$comment;
        }
        $newSipBlock = "";
        foreach($sipNumbers as $sipNumber) {
            $newSipBlock .= sprintf("\n\n%s\n%s\n%s",
                config('asterisk.SIP_AGENT_BLOCK_START'),
                sprintf(config('asterisk.SIP_BLOCK_TEMPLATE'),
                    ($comment != null) ? str_replace("\n", "\n;", $comment)."\n" : "",
                    $sipNumber, $templateName, $sipNumber, $sipNumber),
                config('asterisk.SIP_AGENT_BLOCK_END'));
        }
        // position of last `;end_sip_block`
        $searchRes = strrpos($fileString, config('asterisk.SIP_AGENT_BLOCK_END'));
        if($searchRes === false) { // this means no other sips in config file yet!!!
            $startSipSection = strpos($fileString, config('asterisk.SIP_AGENT_SECTION_START'));
            $insertPoint = $startSipSection + strlen(config('asterisk.SIP_AGENT_SECTION_START')); // on this index will be inserted new sip
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        } else {
            $lastSipBlockPost = $searchRes;
            $insertPoint = $lastSipBlockPost + strlen(config('asterisk.SIP_AGENT_BLOCK_END'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        }
        $newFileString = sprintf("%s%s%s",
            $startPart,
            $newSipBlock,
            $endPart);
        $this->logger->addLogInfo(__METHOD__, [
            'start' => $start,
            'end' => $end,
            'range' => implode(',', $sipNumbers),
            'message' => 'added sips in file'
        ]);
        return $newFileString;
    }

    public function deleteSip($sipNumber, $templateName) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifSipExists($sipNumber, $fileString) === false) {
            throw new \RuntimeException('Such sip doesnt exist, nothing to delete');
        }
        $stringToSearch = sprintf("[%s](%s)", $sipNumber, $templateName);
        if(strpos($fileString, $stringToSearch) === false) {
            throw new \RuntimeException('Template and sip combination is not right');
        }
        $sipHeadPos = strpos($fileString, $stringToSearch);
        $sipEndPos = strpos($fileString, config('asterisk.SIP_AGENT_BLOCK_END'), $sipHeadPos);
        $sipStartPos = strrpos(substr($fileString, 0, $sipEndPos), config('asterisk.SIP_AGENT_BLOCK_START'));
        $beforeSipPart = substr($fileString, 0, $sipStartPos);
        $afterSipPart = substr($fileString, $sipEndPos + strlen(config('asterisk.SIP_AGENT_BLOCK_END')));
        $newFileString = sprintf("%s%s", rtrim($beforeSipPart), $afterSipPart);
        $this->logger->addLogInfo(__METHOD__, [
            'sip' => $sipNumber,
            'templateName' => $templateName
        ]);
        return $newFileString;
    }

    /**
     * if sip is in file return true, otherwise return false
     * lines started with asterisk config comment symbol is ignored
     * @param string $sipNumber
     * @param string $fileString
     * @return array|boolean
     */
    public function ifSipExists($sipNumber, $fileString) {
        $regex = "/^\[$sipNumber\]\([a-zA-Z0-9\-\_]+\)/m";
        $matches = [];
        $result = preg_match($regex, $fileString, $matches);
        if($result === 0) {
            return false;
        } else if($result === false) {
            throw new \RuntimeException('Error performing Regex');
        }
        return true;
    }

    /**
     * returns array of template blocks.
     * @return array
     */
    public function getSipTemplates() {
        $templateArr = $this->getTemplateSection();
        $templateBlocks = $this->getTemplateBlocks($templateArr);
        return $templateBlocks;
    }

    public function getSipTemplate($templateName) {
//        $fileString = file_get_contents($this->filePath);
//        $templateHead = sprintf("[%s](!)", $templateName);
//        if($this->ifTemplateExists($templateHead, $fileString) === false) {
//            throw new \RuntimeException('Template doesnt exist!!!');
//        }
//        $templateHeadStartPos = strpos($fileString, $templateHead);
//        $templateStartPos = strrpos(substr($fileString, 0, $templateHeadStartPos), config('asterisk.SIP_TEMPLATE_BLOCK_START'));
//        $templateEndPos = strpos(substr($fileString, $templateHeadStartPos), config('asterisk.SIP_TEMPLATE_BLOCK_END')) + $templateHeadStartPos;
//        $templateStr = substr($fileString, $templateStartPos + strlen(config('asterisk.SIP_TEMPLATE_BLOCK_START')), $templateEndPos - $templateStartPos - strlen(config('asterisk.SIP_TEMPLATE_BLOCK_START')));
//        $tmpArr = explode("\n", $templateStr);
//        $resultArr = ["comment" => []];
//        foreach($tmpArr as $line) {
//            $line = trim($line);
//            if(empty($line)) {
//                continue;
//            }
//            if($line[0] == config('asterisk.ASTERISK_COMMENT')) {
//                $resultArr["comment"][] = substr($line, 1);
//                continue;
//            }
//            if($line == $templateHead) {
//                $resultArr["templateHead"] = $templateHead;
//                continue;
//            }
//            list($key, $value) = explode("=", $line);
//            $resultArr[trim($key)] = trim($value);
//        }
//        return $resultArr;
        $fileString = file_get_contents($this->filePath);
        $templateStartPos = strpos($fileString, sprintf("[%s](!)", $templateName));
        if($templateStartPos === false) {
            throw new \RuntimeException("Such template doesnt exist in config file!");
        }
        $templateEndPos = strpos($fileString, config('asterisk.SIP_TEMPLATE_BLOCK_END'), $templateStartPos);
        $templateBlock = substr($fileString, $templateStartPos, $templateEndPos - $templateStartPos);
        return $templateBlock;
    }

    /**
     * read whole sip_agents.conf file
     * return only section of where templates are defined
     * templates should be defined inside of comment blocks like this:
     *    ;start_of_templates
     *      .......
     *    ;end_of_templates
     *
     * @return array
     */
    private function getTemplateSection() {
        $fileArr = explode("\n",file_get_contents($this->filePath));
        $templateSection = false; // indicates if array cursor is inside templates section or not
        $templateArr = [];
        foreach($fileArr as $line) {
            $line = trim($line);
            if($templateSection) {
                $templateArr[] = $line;
            }
            if($line == config('asterisk.SIP_TEMPLATE_SECTION_START')) {
                $templateArr[] = $line;
                $templateSection = true;
            }
            if($line == config('asterisk.SIP_TEMPLATE_SECTION_END')) {
                break;
            }
        }
        return $templateArr;
    }

    private function getTemplateBlocks($templateArr) {
        $inTemplate = false; // indicates if array cursor is inside template block or not
        $templates = []; // array consisting of template blocks
        $templateBlock = ["comment" => ""]; // array of template block, key is name of config parameter, value is - value of that parameter
        foreach($templateArr as $line) {
            $line = trim($line);
            if(empty($line)) {
                continue;
            }
            // enter if cursor is in template block and line is not equal to block start and block end delimiters
            if($inTemplate and $line != config('asterisk.SIP_TEMPLATE_BLOCK_START') and $line != config('asterisk.SIP_TEMPLATE_BLOCK_END')) {

                $commentStart = strpos($line, ";");
                if($commentStart === 0) {
                    $templateBlock['comment'] = $templateBlock['comment'].$line."\n";
                    continue;
                }
                if($commentStart !== false) {
                    $line = substr($line, 0, $commentStart);
                }
                // check if line is template head(name).
                if($line[0] == config('asterisk.SIP_TEMPLATE_HEAD_FIRST_CHAR') and strpos($line, "!") !== false) {
                    $templateBlock['templateHead'] = $line;
                } else { // if not split string in to key and value pair and add to template block array
                    list($key, $value) = explode('=', $line);
                    $templateBlock[trim($key)] = trim($value);
                }
            }
            if($line == config('asterisk.SIP_TEMPLATE_BLOCK_START')) {
                $inTemplate = true;
                continue;
            }
            if($line == config('asterisk.SIP_TEMPLATE_BLOCK_END')) {
                $templates[] = $templateBlock;
                $templateBlock = ["comment" => ""];
                $inTemplate = false;
            }
        }
        return $templates;
    }


    public function addSipTemplate($templateBlock) {
        $templateBlock = $this->sanitizeInput($templateBlock, config('asterisk.SIP_KEYWORDS'));
        $fileString = file_get_contents($this->filePath);
        if($this->ifTemplateExists($templateBlock, $fileString)) {
            throw new \RuntimeException('Template already exists');
        }
        //find end of templates section
        $sectionEndPos = strpos($fileString, config('asterisk.SIP_TEMPLATE_SECTION_END'));
        if($sectionEndPos === false) {
            throw new \RuntimeException('cannot find template block section end delimiter in config file');
        }
        $searchRes = strrpos($fileString, config('asterisk.SIP_TEMPLATE_BLOCK_END'));
        if($searchRes === false) {
            $templateSectionStart = strpos($fileString, config('asterisk.SIP_TEMPLATE_SECTION_START'));
            $insertPoint = $templateSectionStart+strlen(config('asterisk.SIP_TEMPLATE_SECTION_START'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        } else {
            $lastBLockEnd = $searchRes;
            $insertPoint = $lastBLockEnd + strlen(config('asterisk.SIP_TEMPLATE_BLOCK_END'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        }
        $templateBlock = sprintf("\n\n%s\n%s\n%s", config('asterisk.SIP_TEMPLATE_BLOCK_START'), $templateBlock, config('asterisk.SIP_TEMPLATE_BLOCK_END'));
        $newFileString = sprintf("%s%s%s",
                $startPart,
                $templateBlock,
                $endPart);
        $this->logger->addLogInfo(__METHOD__, [
            'sipTemplate' => $templateBlock
        ]);
        return $newFileString;
    }

    public function ifTemplateExists($templateBlock, $fileString) {
        $regex = "/^\[.+\]\(!\)/m";
        $matches = [];
        $result = preg_match($regex, $templateBlock, $matches);
        if($result === 0) {
            return false;
        } else if($result === false) {
            $err = error_get_last();
            throw new \RuntimeException(implode(',', $err));
        }
        $templateName = $matches[0];
        if(strpos($fileString, $templateName) === false) {
            return false;
        } else {
            return true;
        }
    }

    public function deleteSipTemplate($templateName) {
        $fileString = file_get_contents($this->filePath);
        $templateHeadPos = strpos($fileString, "[".$templateName."]");
        if($templateHeadPos === false) {
            throw new \RuntimeException('Cannot find such template');
        }
        // substr - shesabamisi templatis poziciis shemdeg yvelafers agdebs.
        // strrpos - darchenili stringidan bolos modzebnili ;start_templa_block aris wasashleli templatis dawyebis pozicia
        $startTemplate = strrpos(substr($fileString, 0, $templateHeadPos), config('asterisk.SIP_TEMPLATE_BLOCK_START'));
        // substr - achris yvelafers $startTemplate poziciamde.
        // strpos() + $startTemplate - substr_dan migebuli stringshi edzebs pirvelive `;end_template_block`_is pozicias da umatebs mochrili simboloebis raodenobas.
        $endTemplate = strpos(substr($fileString, $startTemplate), config('asterisk.SIP_TEMPLATE_BLOCK_END')) + $startTemplate;
        // template section_is dasasrulis pozicia.
        $endTemplateSection = strpos($fileString, config('asterisk.SIP_TEMPLATE_SECTION_END')) + strlen(config('asterisk.SIP_TEMPLATE_SECTION_END'));
        $filteredTemplateSection = sprintf("%s%s",
                                            substr($fileString, 0, $startTemplate),
                                            ltrim(substr($fileString,
                                                $endTemplate+strlen(config('asterisk.SIP_TEMPLATE_BLOCK_END')),
                                                $endTemplateSection - ($endTemplate+strlen(config('asterisk.SIP_TEMPLATE_BLOCK_END'))))));
        $newFileString = $filteredTemplateSection."\n\n".$this->deleteAllSipsByTemplateName($templateName);
        $this->logger->addLogInfo(__METHOD__, [
            'templateName' => $templateName,
            'message' => 'deleted template from file'
        ]);
        return $newFileString;
    }

    private function deleteAllSipsByTemplateName($templateName) {
        $fileString = file_get_contents($this->filePath);
        $sipSectionArr = $this->getSipsSection($fileString);
        $sipBlocks = $this->getSipBlocks($sipSectionArr);
        $filteredBlocks = "";
        foreach($sipBlocks as $sipBlock) {
            if(strpos($sipBlock[0], $templateName) !== false) {
                continue;
            }
            $blockString = config('asterisk.SIP_AGENT_BLOCK_START')."\n".$sipBlock['comment'];
            unset($sipBlock['comment']);
            foreach($sipBlock as $line) {
                $blockString .= $line."\n";
            }
            $filteredBlocks .= $blockString.config('asterisk.SIP_AGENT_BLOCK_END')."\n\n";
            $blockString = "";
        }
        $this->logger->addLogInfo(__METHOD__, [
            'templateName' => $templateName,
            'message' => 'deleted all sips by template name from file'
        ]);
        return config('asterisk.SIP_AGENT_SECTION_START')."\n".$filteredBlocks.config('asterisk.SIP_AGENT_SECTION_END')."\n";
    }

    private function getSipsSection($fileString) {
        $start = strpos($fileString, config('asterisk.SIP_AGENT_SECTION_START'));
        $end = strpos($fileString, config('asterisk.SIP_AGENT_SECTION_END'));
        $sipSection = substr($fileString, $start, $end - $start + strlen(config('asterisk.SIP_AGENT_SECTION_END')));
        return explode("\n", $sipSection);
    }

    private function getSipBlocks($sipSectionArr) {
        $inSipBlock = false; // indicates if array cursor is inside sip block or not
        $sips = []; // array consisting of sip blocks
        $sipBlock = ["comment" => ""]; // array of sip block, key is name of config parameter, value is - value of that parameter
        foreach($sipSectionArr as $line) {
            $line = trim($line);
            if(empty($line)) {
                continue;
            }
            // enter if cursor is in template block and line is not equal to block start and block end delimiters
            if($inSipBlock and $line != config('asterisk.SIP_AGENT_BLOCK_START') and $line != config('asterisk.SIP_AGENT_BLOCK_END')) {

                $commentStart = strpos($line, ";");
                if($commentStart === 0) {
                    $sipBlock['comment'] = $sipBlock['comment'].$line."\n";
                    continue;
                }
                if($commentStart !== false) {
                    $line = substr($line, 0, $commentStart);
                }
                // check if line is template head(name).
                if($line[0] == config('asterisk.SIP_TEMPLATE_HEAD_FIRST_CHAR')) {
                    $sipBlock[] = $line;
                } else { // if not split string in to key and value pair and add to template block array
//                    list($key, $value) = explode('=', $line);
                    $sipBlock[] = trim($line);
                }
            }
            if($line == config('asterisk.SIP_AGENT_BLOCK_START')) {
                $inSipBlock = true;
                continue;
            }
            if($line == config('asterisk.SIP_AGENT_BLOCK_END')) {
                $sips[] = $sipBlock;
                $sipBlock = ["comment" => ""];
                $inSipBlock = false;
            }
        }
        return $sips;
    }

    public function editTemplate($templateBlock, $currTemplateName, &$newName) {
        $this->sanitizeInput($templateBlock, config('asterisk.SIP_KEYWORDS'));
        $fileString = file_get_contents($this->filePath);
        $templateHead = substr($templateBlock, 0, strpos($templateBlock, PHP_EOL));
        if(empty($templateHead)) {
            $templateHead = $templateBlock;
        }
        $this->checkTemplateHeadSyntax($templateHead);
        if($this->ifTemplateExists(sprintf("[%s](!)", $currTemplateName), $fileString) === false) {
            throw new \RuntimeException("Such template doesnt exist!");
        }
        $templateName = substr($templateHead, 1, strrpos($templateHead, config('asterisk.SIP_TEMPLATE_HEAD_LAST_CHAR')) - 1);
        if($templateName != $currTemplateName) {
            if($this->ifTemplateExists(sprintf("[%s](!)", $templateName), $fileString)) {
                throw new \RuntimeException("Cannot change template name, already exists!");
            }
            $fileString = preg_replace_callback_array([
                                                          "/\($currTemplateName\)/m" => function($matches) use ($templateName){
                                                              return sprintf("(%s)", $templateName);
                                                          },
                                                          "/\[$currTemplateName\]/m" => function($matches) use ($templateName) {
                                                              return sprintf("[%s]", $templateName);
                                                          }
                                                      ],
                                                      $fileString);
            $newName = $templateName;
        }
        $templateHeadPos = strpos($fileString, $templateHead);
        $endOfStartBlockDelimiter = strrpos(substr($fileString, 0, $templateHeadPos), config('asterisk.SIP_TEMPLATE_BLOCK_START')) + strlen(config('asterisk.SIP_TEMPLATE_BLOCK_START'));
        $endOfEndBlockDelimiter = strpos($fileString, config('asterisk.SIP_TEMPLATE_BLOCK_END'), $endOfStartBlockDelimiter);
        $startPart = substr($fileString, 0, $endOfStartBlockDelimiter);
        $endPart = substr($fileString, $endOfEndBlockDelimiter);
        $newFileString = sprintf("%s%s%s",
            $startPart,
            sprintf("\n%s\n", $templateBlock),
            $endPart);
        return $newFileString;
    }

    private function checkTemplateHeadSyntax($templateHead) {
        $regex = "/^\[[0-9a-zA-z\-]+\(!\)$/m";
        $result = preg_match($regex, $templateHead, $matches);
        if($result === 0) {
            throw new \RuntimeException('Incorrect template head syntax');
        }
        return true;
    }

    private function makeBackup($file, $copyPath) {
        if(copy($file, $copyPath) === false) {
            $err = error_get_last();
            throw new \RuntimeException(implode(",", $err));
        }
    }

    public function commitChanges($newFileStr) {
        $this->makeBackup($this->filePath, $this->backupPath.date("Y-m-d_H:i:s"));
        file_put_contents($this->filePath, $newFileStr);
        $this->ssh2->cmd(config('asterisk.SIP_RELOAD'));
    }

    public function getSipConfigPath() {
        return $this->filePath;
    }

}
