<?php


namespace App\Common\ExcelGenerators;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CRRExcelReport {

    /**
     * @var Spreadsheet
     */
    private $spreadSheet;

    public function __construct() {
        ini_set('max_execution_time', -1);
        $this->spreadSheet = new Spreadsheet();
    }

    public function setMetaData($metaDataArr) {
        $this->spreadSheet->getProperties()
            ->setCreator('Toka Porchkhidze')
            ->setLastModifiedBy('Toka Porchkhidze')
            ->setTitle($metaDataArr['title'] ?? 'No Title')
            ->setCategory('Silknet Call Centre');
    }

    public function setHeaderCellStyles($columnCount) {
        $styleArray = [
            'font' => [
                'bold' => true,
            ]
        ];
        $sheet = $this->spreadSheet->getActiveSheet();
        $sheet->getStyleByColumnAndRow(1, 1, $columnCount, 1)->applyFromArray($styleArray);
    }

    public function setHeaderCellValues($values) {
        $columnCount = count($values);
        $sheet = $this->spreadSheet->getActiveSheet();
        $sheet->fromArray($values, null, "A1");
    }

    public function populateSheet($dataArr) {
        $this->spreadSheet->getActiveSheet()->fromArray($dataArr, null, "A2", true);
    }

    public function saveFile($filePath) {
        $writer = new Xlsx($this->spreadSheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($filePath);
        $this->spreadSheet->disconnectWorksheets();
        unset($this->spreadSheet);
    }

}