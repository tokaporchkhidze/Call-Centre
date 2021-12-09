<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/21/2019
 * Time: 11:07 AM
 */

namespace App\AsteriskHandlers;



use App\Common\SSH2;
use App\Logging\Logger;
use App\QueueGroup;
use App\Traits\AsteriskParserTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QueueParser {

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
        $this->lockHandle = $this->openFile(config('asterisk.QUEUE_LOCK_FILE'));
        $this->lockFile($this->lockHandle);
        $this->ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
                               config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
        $this->sftp = $this->ssh2->getSFTPHandler();
        $this->filePath = sprintf("ssh2.sftp://%s%s", $this->sftp, config('asterisk.QUEUE_MAIN'));
        $this->backupPath = sprintf("ssh2.sftp://%s%s", $this->sftp, config('asterisk.QUEUE_MAIN_BACKUP'));
    }

    public function addTemplate($templateBlock) {
        $templateBlock = $this->sanitizeInput($templateBlock, config('asterisk.QUEUE_KEYWORDS'));
        $fileString = file_get_contents($this->filePath);
        if($this->ifTemplateExist($templateBlock, $fileString)) {
            throw new \RuntimeException('template already exists!');
        }
        $searchRes = strrpos($fileString, config('asterisk.QUEUE_TEMPLATE_BLOCK_END'));
        if($searchRes === false) {
            $startTemplateSection = strpos($fileString, config('asterisk.QUEUE_TEMPLATE_SECTION_START'));
            $insertPoint = $startTemplateSection + strlen(config('asterisk.QUEUE_TEMPLATE_SECTION_START'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        } else {
            $lastBlockPos = $searchRes;
            $insertPoint = $lastBlockPos + strlen(config('asterisk.QUEUE_TEMPLATE_BLOCK_END'));
            $startPart = substr($fileString, 0, $insertPoint);
            $endPart = substr($fileString, $insertPoint);
        }
        $templateBlock = sprintf("\n\n%s\n%s\n%s",
                            config('asterisk.QUEUE_TEMPLATE_BLOCK_START'),
                            $templateBlock,
                            config('asterisk.QUEUE_TEMPLATE_BLOCK_END'));
        $newFileStr = sprintf("%s%s%s",
                        $startPart,
                        $templateBlock,
                        $endPart);
        $this->logger->addLogInfo(str_replace("\\", "\\", __METHOD__), [
            'template' => $templateBlock,
            'message' => 'add queue template in file'
        ]);
        return $newFileStr;
    }

    public function deleteTemplate($templateName) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifTemplateExist(sprintf("[%s](!)", $templateName), $fileString) === false) {
            throw new \RuntimeException('template doesnt exist!');
        }
        $templateHeadPos = strpos($fileString, "[".$templateName."]");
        // substr - shesabamisi templatis poziciis shemdeg yvelafers agdebs.
        // strrpos - darchenili stringidan bolos modzebnili ;start_templa_block aris wasashleli templatis dawyebis pozicia
        $startTemplate = strrpos(substr($fileString, 0, $templateHeadPos), config('asterisk.QUEUE_TEMPLATE_BLOCK_START'));
        // substr - achris yvelafers $startTemplate poziciamde.
        // strpos() + $startTemplate - substr_dan migebuli stringshi edzebs pirvelive `;end_template_block`_is pozicias da umatebs mochrili simboloebis raodenobas.
        $endTemplate = strpos(substr($fileString, $startTemplate), config('asterisk.QUEUE_TEMPLATE_BLOCK_END')) + $startTemplate;
        // template section_is dasasrulis pozicia.
        $endTemplateSection = strpos($fileString, config('asterisk.QUEUE_TEMPLATE_SECTION_END')) + strlen(config('asterisk.QUEUE_TEMPLATE_SECTION_END'));
        $filteredQueueSection = $this->deleteAllQueuesByTemplateName($templateName);
        $newFileStr = sprintf("%s%s\n\n%s",
            substr($fileString, 0, $startTemplate),
            ltrim(substr($fileString,
                $endTemplate+strlen(config('asterisk.QUEUE_TEMPLATE_BLOCK_END')),
                $endTemplateSection - ($endTemplate+strlen(config('asterisk.QUEUE_TEMPLATE_BLOCK_END'))))),
            $filteredQueueSection);
        $this->logger->addLogInfo(__METHOD__, [
            'template' => $templateName,
            'message' => "deleted queue template from file"
        ]);
        return $newFileStr;
    }

    private function deleteAllQueuesByTemplateName($templateName) {
        $fileString = file_get_contents($this->filePath);
        $queueSectionArr = $this->getQueuesSection($fileString);
        $queueBlocks = $this->getQueuesBlocks($queueSectionArr);
        $filteredBlocks = "";
        foreach($queueBlocks as $queueBlock) {
            if (strpos($queueBlock[0], $templateName) !== false) {
                continue;
            }
            $blockString = config('asterisk.QUEUE_BLOCK_START') . "\n" . $queueBlock['comment'];
            unset($queueBlock['comment']);
            foreach ($queueBlock as $line) {
                $blockString .= $line . "\n";
            }
            $filteredBlocks .= $blockString . config('asterisk.QUEUE_BLOCK_END') . "\n\n";
            $blockString = "";
        }
        $this->logger->addLogInfo(__METHOD__, [
            'template' => $templateName,
            'message' => 'deleted all queues by template name from file'
        ]);
        return config('asterisk.QUEUE_SECTION_START')."\n".$filteredBlocks.config('asterisk.QUEUE_SECTION_END')."\n";
    }

    private function getQueuesSection($fileString) {
        $start = strpos($fileString, config('asterisk.QUEUE_SECTION_START'));
        $end = strpos($fileString, config('asterisk.QUEUE_SECTION_END'));
        $sipSection = substr($fileString, $start, $end - $start + strlen(config('asterisk.QUEUE_SECTION_END')));
        return explode("\n", $sipSection);
    }

    private function getQueuesBlocks($queueSectionArr) {
        $inQueueBlock = false; // indicates if array cursor is inside queue block or not
        $queues = []; // array consisting of queue blocks
        $queueBlock = ["comment" => ""]; // array of queue block, key is name of config parameter, value is - value of that parameter
        foreach($queueSectionArr as $line) {
            $line = trim($line);
            if(empty($line)) {
                continue;
            }
            // enter if cursor is in template block and line is not equal to block start and block end delimiters
            if($inQueueBlock and $line != config('asterisk.QUEUE_BLOCK_START') and $line != config('asterisk.QUEUE_BLOCK_END')) {

                $commentStart = strpos($line, ";");
                if($commentStart === 0) {
                    $queueBlock['comment'] = $queueBlock['comment'].$line."\n";
                    continue;
                }
                if($commentStart !== false) {
                    $line = substr($line, 0, $commentStart);
                }
                // check if line is template head(name).
                if($line[0] == config('asterisk.QUEUE_HEAD_START_DELIMITER')) {
                    $queueBlock[] = $line;
                } else { // if not split string in to key and value pair and add to template block array
//                    list($key, $value) = explode('=', $line);
                    $queueBlock[] = trim($line);
                }
            }
            if($line == config('asterisk.QUEUE_BLOCK_START')) {
                $inQueueBlock = true;
                continue;
            }
            if($line == config('asterisk.QUEUE_BLOCK_END')) {
                $queues[] = $queueBlock;
                $queueBlock = ["comment" => ""];
                $inQueueBlock = false;
            }
        }
        return $queues;
    }

    private function ifTemplateExist($templateBlock, $fileString) {
        $regex = "/^\[.+\]\(!\)/m";
        $matches = [];
        $result = preg_match($regex, $templateBlock, $matches);
        if($result === 0) {
            throw new \RuntimeException('Template name syntax error!');
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

    public function addQueue($queueBlock) {
        $fileString = file_get_contents($this->filePath);
        $queueBlock = $this->sanitizeInput($queueBlock, config('asterisk.QUEUE_KEYWORDS'));
        $queueHead = substr($queueBlock, 0, strpos($queueBlock, "\n"));
        if(empty($queueHead)) {
            $queueHead = $queueBlock;
        }
        $this->checkQueueHead($queueHead);
        $queueName = substr($queueHead, 1, strpos($queueHead, config('asterisk.QUEUE_HEAD_END_DELIMITER')) - 1);
        $templateNameStartPos = strpos($queueHead, config('asterisk.QUEUE_HEAD_END_DELIMITER')) + 2;
        $template = substr($queueHead, $templateNameStartPos, strlen($queueHead) - $templateNameStartPos - 1);
        if($this->ifTemplateExist(sprintf("[%s](!)", $template), $fileString) === false) {
            throw new \RuntimeException("Given template doesn't exist!");
        }
        if($this->ifQueueExists($queueName, $fileString)) {
            throw new \RuntimeException("Given queue already exists!");
        }
        $queueBlock = sprintf("%s\n%s\n%s\n%s",
            config('asterisk.QUEUE_BLOCK_START'),
            $queueBlock,
            config('asterisk.QUEUE_BLOCK_END'),
            config('asterisk.QUEUE_SECTION_END'));
        $newFileStr = str_replace(config('asterisk.QUEUE_SECTION_END'), $queueBlock, $fileString);
        $this->logger->addLogInfo(__METHOD__, [
            'queueBlock' => $queueBlock,
            'parsedName' => $queueName,
            'parsedTemplate' => $template,
            'message' => 'added queue in file'
        ]);
        return $newFileStr;
    }

    public function editQueue($queueBlock, $currentName, $currentTemplate, &$newName, &$newTemplate) {
        $this->sanitizeInput($queueBlock, config('asterisk.QUEUE_KEYWORDS'));
        $fileString = file_get_contents($this->filePath);
        $queueHead = substr($queueBlock, 0, strpos($queueBlock, "\n"));
        if(empty($queueHead)) {
            $queueHead = $queueBlock;
        }
        $this->checkQueueHead($queueHead);
        $queueName = substr($queueHead, 1, strpos($queueHead, config('asterisk.QUEUE_HEAD_END_DELIMITER')) - 1);
        $templateNameStartPos = strpos($queueHead, config('asterisk.QUEUE_HEAD_END_DELIMITER')) + 2;
        $template = substr($queueHead, $templateNameStartPos, strlen($queueHead) - $templateNameStartPos - 1);
        if($this->ifTemplateExist(sprintf("[%s](!)", $currentTemplate), $fileString) === false) {
            throw new \RuntimeException("Such template doesnt exist!");
        }
        if($this->ifQueueExists($currentName, $fileString) === false) {
            throw new \RuntimeException("Given queue doesnt exist!");
        }
        if($currentTemplate != $template) {
            if($this->ifTemplateExist(sprintf("[%s](!)", $template), $fileString) === false) {
                throw new \RuntimeException("Cannot change to given template, doesnt exist!");
            }
            $newTemplate = $template;
        }
        if($queueName != $currentName) {
            if($this->ifQueueExists($queueName, $fileString)) {
                throw new \RuntimeException("Cannot change to give name, already exists!");
            }
            $newName = $queueName;
        }
        $newQueueBlock = sprintf("\n%s\n", $queueBlock);
        $queueStartPos = strpos($fileString, $currentName);
        $startPartEndPos = strrpos(substr($fileString, 0, $queueStartPos), config('asterisk.QUEUE_BLOCK_START')) + strlen(config('asterisk.QUEUE_BLOCK_START'));
        $endPartStartPos = strpos($fileString, "member", $startPartEndPos);
        if($endPartStartPos === false) {
            $endPartStartPos = strpos($fileString, config('asterisk.QUEUE_BLOCK_END'), $startPartEndPos);
        }
        $startPart = substr($fileString, 0, $startPartEndPos);
        $endPart = substr($fileString, $endPartStartPos);
        $newFileStr = sprintf("%s%s%s",
            $startPart,
            $newQueueBlock,
            $endPart);
        $this->logger->addLogInfo(__METHOD__, [
            'queueBlock' => $queueBlock,
            'parsedName' => $queueName,
            'parsedTemplate' => $template,
            'message' => 'added queue in file'
        ]);
        return $newFileStr;
    }

    public function editTemplate($templateBlock, $currentName, &$newName) {
        $this->sanitizeInput($templateBlock, config('asterisk.QUEUE_KEYWORDS'));
        $fileString = file_get_contents($this->filePath);
        if($this->ifTemplateExist(sprintf("[%s](!)", $currentName), $fileString) === false) {
            throw new \RuntimeException('template doesnt exist!');
        }
        $templateName = substr($templateBlock, 1, strpos($templateBlock, config('asterisk.QUEUE_HEAD_END_DELIMITER')) - 1);
        if(empty($templateName)) {
            throw new \RuntimeException("Cannot get template name from template block, check syntax!");
        }
        if($templateName != $currentName) {
            if($this->ifTemplateExist($templateBlock, $fileString)) {
                throw new \RuntimeException("Cannot change to given name, already exists!");
            }
            $fileString = preg_replace_callback_array([
                "/\($currentName\)/m" => function($matches) use ($templateName){
                    return sprintf("(%s)", $templateName);
                },
                "/\[$currentName\]/m" => function($matches) use ($templateName) {
                    return sprintf("[%s]", $templateName);
                }
            ],
                $fileString);
            $newName = $templateName;
        }
        $newTemplateBlock = sprintf("\n%s\n",$templateBlock);
        $templateHeadPos = strpos($fileString, $templateName);
        $templateBlockStartPos = strrpos(substr($fileString, 0, $templateHeadPos), config('asterisk.QUEUE_TEMPLATE_BLOCK_START')) + strlen(config('asterisk.QUEUE_TEMPLATE_BLOCK_START'));
        $templateBlockEndPos = strpos($fileString, config('asterisk.QUEUE_TEMPLATE_BLOCK_END'), $templateHeadPos);
        $startPart = substr($fileString, 0, $templateBlockStartPos);
        $endPart = substr($fileString, $templateBlockEndPos);
        $newFileString = sprintf("%s%s%s",
            $startPart, $newTemplateBlock, $endPart);
        return $newFileString;
    }

    public function deleteQueue($queueName) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifQueueExists($queueName, $fileString) === false) {
            throw new \RuntimeException('Queue doesnt exist!');
        }
        $queueNameStartPos = strpos($fileString, $queueName);
        $queueBlockStartPos = strrpos(substr($fileString, 0, $queueNameStartPos), config('asterisk.QUEUE_BLOCK_START'));
        $queueBlockEndPos = strpos(substr($fileString, $queueNameStartPos), config('asterisk.QUEUE_BLOCK_END')) + $queueNameStartPos + strlen(config('asterisk.QUEUE_BLOCK_END'));
        $newFileStr = sprintf("%s%s",
            substr($fileString, 0, $queueBlockStartPos),
            ltrim(substr($fileString, $queueBlockEndPos)));
        $this->logger->addLogInfo(__METHOD__, [
            'queueName' => $queueName,
            'message' => 'deleted queue from file'
        ]);
        return $newFileStr;
    }

    public function addSipInQueue(int $sipNumber, string $queueName, $priority) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifQueueExists($queueName, $fileString) === false) {
            throw new \RuntimeException("Such Queue doesnt exist!");
        }
        $queueSection = $this->getQueueSection($queueName, $fileString);
        if($this->checkIfSipInQueue($sipNumber, $queueSection)) {
            throw new \RuntimeException("Sip already in the queue");
        }
        $newSipMember = sprintf(config('asterisk.QUEUE_SIP_MEMBER_FORMAT'), $sipNumber, (isset($priority)) ? ",".$priority : "");
        $replaceReg = sprintf("/\s+%s/m", config('asterisk.QUEUE_BLOCK_END'));
        $newQueueSection = preg_replace($replaceReg, sprintf("\n%s\n%s", $newSipMember, config('asterisk.QUEUE_BLOCK_END')), $queueSection);
        $newFileString = str_replace($queueSection, $newQueueSection, $fileString);
        $this->logger->addLogInfo(__METHOD__, [
            'sipNumber' => $sipNumber,
            'queueName' => $queueName,
            'priority' => $priority ?? null,
            'message' => 'added sip in queue file'
        ]);
        return $newFileString;
    }

    public function addBulkSipInQueue(array $sipArr, string $queueName): string {
        $fileString = file_get_contents($this->filePath);
        if($this->ifQueueExists($queueName, $fileString) === false) {
            throw new \RuntimeException('Such queue Doesnt exist');
        }
        $queueSection = $this->getQueueSection($queueName, $fileString);
        $newSipMember = "";
        foreach($sipArr as $sipNum) {
            if($this->checkIfSipInQueue($sipNum, $queueSection)) {
                throw new \RuntimeException("Sip already in queue: $sipNum");
            }
            $newSipMember .= sprintf(config('asterisk.QUEUE_SIP_MEMBER_FORMAT')."\n", $sipNum, "");
        }
        $replaceReg = sprintf("/\s+%s/m", config('asterisk.QUEUE_BLOCK_END'));
        $newQueueSection = preg_replace($replaceReg, sprintf("\n%s\n%s", rtrim($newSipMember), config('asterisk.QUEUE_BLOCK_END')), $queueSection);
        $newFileString = str_replace($queueSection, $newQueueSection, $fileString);
        $this->logger->addLogInfo(__METHOD__, [
            'sipArr' => implode(',', $sipArr),
            'queueName' => $queueName,
            'message' => 'added given range of sips in file'
        ]);
        return $newFileString;
    }

    public function refreshSipPriorities(array $sipsArr) {
        $queueGroups = QueueGroup::getGroupsWithQueues();
        $fileString = file_get_contents($this->filePath);
        foreach($sipsArr as $sip => $priorities) {
            foreach($priorities as $group => $priority) {
                $queues = array_column($queueGroups[$group]['queues'], "name");
                foreach($queues as $queueName) {
                    $queueSection = $this->getQueueSection($queueName, $fileString);
                    $newQueueSection = preg_replace_callback_array([
                        sprintf("/%s/m", addcslashes(sprintf(config('asterisk.QUEUE_SIP_MEMBER_FORMAT'), $sip, ""), "/"))
                        => function($matches) use ($sip, $priority){
                            return sprintf(config('asterisk.QUEUE_SIP_MEMBER_PRIORITY_FORMAT'), $sip, $priority);
                        },
                        sprintf("/%s.*/m", addcslashes(sprintf(config('asterisk.QUEUE_SIP_MEMBER_PRIORITY_FORMAT'), $sip, ""), "/"))
                        => function($matches) use ($sip, $priority) {
                            return sprintf(config('asterisk.QUEUE_SIP_MEMBER_PRIORITY_FORMAT'), $sip, $priority);
                        }
                    ],
                        $queueSection);
                    $fileString = str_replace($queueSection, $newQueueSection, $fileString);
                }
            }
        }
        return $fileString;
    }

    public function deleteSipFromQueue(int $sipNumber, string $queueName, $priority) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifQueueExists($queueName, $fileString) === false) {
            throw new ModelNotFoundException(sprintf("Such Queue doesnt exist: %s", $queueName));
        }
        $queueSection = $this->getQueueSection($queueName, $fileString);
        if($this->checkIfSipInQueue($sipNumber, $queueSection, $priority) === false) {
            throw new \RuntimeException(sprintf("Sip doesnt exist in queue: %s, priority: %s", $sipNumber, (isset($priority)) ? $priority : "None"));
        }
        $toBeDeletedSip = addcslashes(sprintf(config('asterisk.QUEUE_SIP_MEMBER_FORMAT'), $sipNumber,(isset($priority)) ? ",".$priority : ""), "/");
        $newQueueSection = preg_replace(sprintf("/%s.*/m", $toBeDeletedSip), '', $queueSection);
        $newFileString = str_replace($queueSection, $newQueueSection, $fileString);
        $this->logger->addLogInfo(__METHOD__, [
            'sipNumber' => $sipNumber,
            'queueName' => $queueName,
            'message' => 'deleted sip from queue file'
        ]);
        return $newFileString;
    }

    public function deleteSipFromAllQueues($sipArr) {
        $fileString = file_get_contents($this->filePath);
        if(is_array($sipArr) === false) {
            $sipArr = [$sipArr];
        }
        $toBeDeletedSipArr = [];
        foreach($sipArr as $sipNumber) {
            $toBeDeletedSipArr[] = addcslashes(sprintf(config('asterisk.QUEUE_SIP_MEMBER_FORMAT'), $sipNumber, ""), "/");
        }
        $pregReplace = [];
        foreach($toBeDeletedSipArr as $sipNumber) {
            $pregReplace[] = sprintf("/%s.*/m", $sipNumber);
        }
        $newFileString = preg_replace($pregReplace, '', $fileString);
        $this->logger->addLogInfo(__METHOD__, [
            'sips' => implode(",", $sipArr)
        ]);
        return $newFileString;
    }

    public function getQueueBlock(string $queueName) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifQueueExists($queueName, $fileString) === false) {
            throw new \RuntimeException('Queue doesnt exist!');
        }
        $queueStartPos = strpos($fileString, sprintf("[%s]", $queueName));
        $queueEndPos = strpos($fileString, "member", $queueStartPos);
        if($queueEndPos === false) {
            $queueEndPos = strpos($fileString, config('asterisk.QUEUE_BLOCK_END'), $queueStartPos);
        }
        $queueBlock = substr($fileString, $queueStartPos, $queueEndPos - $queueStartPos);
        return $queueBlock;
    }

    public function getTemplateBlock(string $templateName) {
        $fileString = file_get_contents($this->filePath);
        if($this->ifTemplateExist(sprintf("[%s](!)", $templateName), $fileString) === false) {
            throw new \RuntimeException("Template doesnt exist!");
        }
        $templateStartPos = strpos($fileString, sprintf("[%s](!)", $templateName));
        $templateEndPos = strpos($fileString, config('asterisk.QUEUE_TEMPLATE_BLOCK_END'), $templateStartPos);
        $templateBlock = substr($fileString, $templateStartPos, $templateEndPos - $templateStartPos);
        return $templateBlock;
    }

    private function getQueueSection($queueName, $fileString) {
        $queueHeadStartPos = strpos($fileString, sprintf("[%s]", $queueName));
        $queueStartPos = strrpos(substr($fileString, 0, $queueHeadStartPos), config('asterisk.QUEUE_BLOCK_START'));
        $queueEndPos = strpos(substr($fileString, $queueHeadStartPos), config('asterisk.QUEUE_BLOCK_END')) + $queueHeadStartPos;
        $queueSection = substr($fileString, $queueStartPos,
            $queueEndPos + strlen(config('asterisk.QUEUE_BLOCK_END')) - $queueStartPos);
        return $queueSection;
    }

    private function checkIfSipInQueue($sipNumber, $queueSection, $priority=null) {
        if(isset($priority)) $sipNumber = $sipNumber.",".$priority;
        $regex = "/member\s*=>\s*SIP\/$sipNumber/m";
        $result = preg_match($regex, $queueSection);
        if($result === 1) {
            return true;
        } else {
            return false;
        }
    }

    private function ifQueueExists($queueName, $fileString) {
        $regex = "/^\[$queueName\]\([0-9a-zA-z\-]+\)/m";
        $matches = [];
        $result = preg_match($regex, $fileString, $matches);
        if($result === 0) {
            return false;
        }
        return true;
    }

    private function checkQueueHead($queueHead) {
        $regex = "/^\[[0-9a-zA-z\-]+\]\([0-9a-zA-z\-]+\)/m";
        $matches = [];
        $result = preg_match($regex, $queueHead, $matches);
        if($result === 0) {
            throw new \RuntimeException("incorrect queue head syntax!");
        }
        return true;
    }

    private function makeBackup($file, $copyPath) {
        if(copy($file, $copyPath) === false) {
            $err = error_get_last();
            throw new \RuntimeException(implode(",", $err));
        }
    }

    public function commitChanges($newFileString) {
        $this->makeBackup($this->filePath, $this->backupPath.date("Y-m-d_H:m:s"));
        file_put_contents($this->filePath, $newFileString);
        $this->ssh2->cmd(config('asterisk.QUEUE_RELOAD'));
    }

}