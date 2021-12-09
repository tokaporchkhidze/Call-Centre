<?php

namespace App\Jobs;

use App\Common\ExcelGenerators\CRRExcelReport;
use App\Events\ExcelCreated;
use App\Events\ExcelNotCreated;
use App\ExcelFileCreator;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class CrrExcelGenerator implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $headerArr;

    private $valuesArr;

    private $absoluteFilePath;

    /**
     * user which ordered the job
     *
     * @var User
     */
    private $user;

    public $tries = 1;

    public $timeout = 1800;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($headerArr, $valuesArr, $absoluteFilePath, $user) {
        $this->headerArr = $headerArr;
        $this->valuesArr = $valuesArr;
        $this->user = $user;
        $this->absoluteFilePath = $absoluteFilePath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CRRExcelReport $crrReportClass) {
        if(file_exists($this->absoluteFilePath)) {
            return;
        }
        ini_set('memory_limit', -1);
        $crrReportClass->setHeaderCellStyles(count($this->headerArr));
        $crrReportClass->setHeaderCellValues($this->headerArr);
        $crrReportClass->populateSheet($this->valuesArr);
        $crrReportClass->saveFile($this->absoluteFilePath);
        ExcelFileCreator::createUserReportBridge($this->user->id, substr($this->absoluteFilePath, strrpos($this->absoluteFilePath, "/")+1));
        event(new ExcelCreated($this->absoluteFilePath, $this->user));
    }

    public function failed($exception = null)
    {
        event(new ExcelNotCreated(['message' => sprintf('Generation of %s failed', substr($this->absoluteFilePath, strrpos($this->absoluteFilePath, "/")))]));
    }

}
