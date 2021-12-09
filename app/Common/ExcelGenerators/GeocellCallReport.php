<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 5/13/2019
 * Time: 2:49 PM
 */

namespace App\Common\ExcelGenerators;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;


class GeocellCallReport {

    /**
     * @var array
     */
    private $excelData;

    /**
     * @var Spreadsheet
     */
    private $spreadSheet;

    public function __construct(array $excelData) {
        $this->excelData = $excelData;
        $this->spreadSheet = new Spreadsheet();
    }

    public function generateReport() {
        $columnCount = count($this->excelData);
        $dates = array_keys($this->excelData);
        $this->setMetaData($columnCount);
        $this->setHeaderCellStyles($columnCount);
        $this->setHeaderCellValues($dates);
        $this->populateIVRSection(3, 2, $columnCount);
        $this->populateTotalCallsSection(8, 2, $columnCount, "total");
        $this->populateTotalCallsSection(13, 2, $columnCount, "b2b");
        $this->populateTotalCallsSection(18, 2, $columnCount, "b2c");
        $this->populateTotalCallsSection(23, 2, $columnCount, "prepaid");
        $this->populateTotalCallsSection(28, 2, $columnCount, "avg");
        $this->populateCallCenterAgentsSection(33, 2, $columnCount);
        $this->populateKPISection(36, 2, $columnCount);
        $writer = new Xlsx($this->spreadSheet);
        $writer->save("/var/www/callCentre/app/AsteriskHandlers/test.xlsx");
    }

    private function setMetaData(int $columnCount): void {
        $this->spreadSheet->getProperties()
            ->setCreator("Toka Porchkhidze")
            ->setLastModifiedBy("Toka Porchkhidze")
            ->setTitle("Geocell Call Report")
            ->setCategory("Silknet Call Center");
        $this->spreadSheet->getSheet(0)->setTitle("Call")->getDefaultRowDimension()->setRowHeight(12);
        $this->spreadSheet->getSheet(0)->getStyleByColumnAndRow(2, 1, $columnCount+2, 50)->getFont()->setSize(9);
    }

    private function setHeaderCellStyles(int $columnCount):void {
        $styleArray = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'B7DEE8'
                ]
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'color' => ['argb' => '92CDDC']
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => '002060']
            ]
        ];
        $sheet = $this->spreadSheet->getSheet(0);
        $sheet->getStyleByColumnAndRow(2, 1, $columnCount+2, 1)->applyFromArray($styleArray);
        $styleArray['fill']['startColor'] = ['argb' => 'FFFFFF'];
        $sheet->getStyleByColumnAndRow(2, 3, $columnCount+2, 50)->getFill()->setFillType(FILL::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyleByColumnAndRow(2, 2, $columnCount+2, 2)->applyFromArray($styleArray);
        $sheet->getColumnDimension('A')->setWidth(2.71);
        $sheet->getColumnDimension('B')->setWidth(71.14);
        $sheet->getCell('B2')->getStyle()->getFont()->setBold(true)->getColor()->setRGB('002060');
    }

    private function setHeaderCellValues(array $dates):void {
//        logger()->error(count($dates));
        $row = 1;
        $sheet = $this->spreadSheet->getActiveSheet();
        $i = 0;
        for($column=3; $column < count($dates)+3; $column++) {
            $date = new \DateTime($dates[$i++]);
            $sheet->setCellValueByColumnAndRow($column, $row, $date->format('M-y'));
        }
        $sheet->setCellValueByColumnAndRow(2, 2, 'GEOCELL');
    }

    private function populateIVRSection(int $startingRow, int $startingColumn, int $columnCount) {
        $sheet = $this->spreadSheet->getSheet(0);
        $styleArray = [
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'color' => ['argb' => '92CDDC']
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => '002060']
            ]
        ];
        $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Automatic IVR Service");
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow, $columnCount+2, $startingRow)->applyFromArray($styleArray);
        $styleArray['font']['bold'] = false;
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow+1, $columnCount+2, $startingRow+4)->applyFromArray($styleArray);
        $sheet->fromArray(
            [
                ['Total'],
                ['Button #1 (Georgian)'],
                ['Button #2 (Russian)'],
                ['Button #3 (English)'],
            ],
            null,
            sprintf("B%s", $startingRow+1)
        );
        $dates = array_keys($this->excelData);
        $totalDTMF = [];
        $georgianDTMF = [];
        $russianDTMF = [];
        $englishDTMF = [];
        foreach($dates as $date) {
            $totalDTMF[] = $this->excelData[$date]['IVR']['total'];
            $georgianDTMF[] = $this->excelData[$date]['IVR']['georgian'];
            $russianDTMF[] = $this->excelData[$date]['IVR']['russian'];
            $englishDTMF[] = $this->excelData[$date]['IVR']['english'];
        }
        $arrayData = [$totalDTMF, $georgianDTMF, $russianDTMF, $englishDTMF];
        $sheet->fromArray($arrayData, null, sprintf("C%s", $startingRow+1), true);
    }

    private function populateTotalCallsSection(int $startingRow, int $startingColumn, int $columnCount, string $callGroup): void {
        $sheet = $this->spreadSheet->getSheet(0);
        $totalCalls = [];
        foreach($this->excelData as $date => $data) {
            switch($callGroup) {
                case "total":
                    $totalCalls[$date] = $data['totalCallStats']['total'];
                    $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Total Calls");
                    break;
                case "prepaid":
                    $totalCalls[$date] = $data['totalCallStats']['prepaid'];
                    $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Recieved calls by PRE PAID");
                    break;
                case "b2c":
                    $totalCalls[$date] = $data['totalCallStats']['b2c'];
                    $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Recieved calls by B2C");
                    break;
                case "b2b":
                    $totalCalls[$date] = $data['totalCallStats']['b2b'];
                    $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Recieved calls by B2B");
                    break;
                case "avg":
                    $totalCalls[$date] = $data['totalCallStats']['avgTotalStats'];
                    $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Avg calls by per day");
                    break;
                default:
                    throw new \RuntimeException("Incorrect group was given in Total call section!");
            }
        }
        $styleArray = [
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'color' => ['argb' => '92CDDC']
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => '002060']
            ]
        ];
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow, $columnCount+2, $startingRow)->applyFromArray($styleArray);
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow+4, $columnCount+2, $startingRow+4)->applyFromArray($styleArray);
        $sheet->getStyleByColumnAndRow($startingColumn+1, $startingRow+4, $columnCount+2, $startingRow+4)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyleByColumnAndRow($startingColumn+1, $startingRow+4, $columnCount+2, $startingRow+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);
        unset($styleArray['borders']);
        $styleArray['font']['bold'] = false;
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow+1, $columnCount+2, $startingRow+3)->applyFromArray($styleArray);
        $sheet->fromArray(
            [
                ['Total'],
                ['Answered'],
                ['Abandoned'],
                ['Responses']
            ],
            null,
            sprintf("B%s", $startingRow+1)
        );
        $totalArr = [];
        $answeredArr = [];
        $abandonedArr = [];
        $responsesArr = [];
        foreach($totalCalls as $monthData) {
            $totalArr[] = $monthData['total'];
            $answeredArr[] = $monthData['answered'];
            $abandonedArr[] = $monthData['abandoned'];
            $responsesArr[] = $monthData['responses'];
        }
        $arrayData = [$totalArr, $answeredArr, $abandonedArr, $responsesArr];
        $sheet->fromArray($arrayData, null, sprintf("C%s", $startingRow+1), true);
    }

    private function populateCallCenterAgentsSection(int $startingRow, int $startingColumn, int $columnCount): void {
        $sheet = $this->spreadSheet->getSheet(0);
        $styleArray = [
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'color' => ['argb' => '92CDDC']
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => '002060']
            ]
        ];
        $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Call Center Agents");
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow, $columnCount+2, $startingRow)->applyFromArray($styleArray);
        $styleArray['font']['bold'] = false;
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow+1, $columnCount+2, $startingRow+2)->applyFromArray($styleArray);
        $sheet->fromArray(
            [
                ['Total Inbound Call Duration/Answered Calls, min (ნასაუბრები ზარები წუთებში)'],
                ['days']
            ],
            null,
            sprintf("B%s", $startingRow+1)
        );
        $dates = array_keys($this->excelData);
        $totalMin = [];
        $days = [];
        foreach($dates as $date) {
            $totalMin[] = $this->excelData[$date]['callCenterAgentStats']['totalMin'];
            $days[] = $this->excelData[$date]['callCenterAgentStats']['days'];
        }
        $arrayData = [$totalMin, $days];
        $sheet->fromArray($arrayData, null, sprintf("C%s", $startingRow+1), true);
    }

    private function populateKPISection(int $startingRow, int $startingColumn, int $columnCount): void {
        $sheet = $this->spreadSheet->getSheet(0);
        $styleArray = [
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'color' => ['argb' => '92CDDC']
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => '002060']
            ]
        ];
        $sheet->setCellValueByColumnAndRow($startingColumn, $startingRow, "Other KPI(total_Geocell)");
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow, $columnCount+2, $startingRow)->applyFromArray($styleArray);
        $styleArray['font']['bold'] = false;
        $sheet->getStyleByColumnAndRow($startingColumn, $startingRow+1, $columnCount+2, $startingRow+7)->applyFromArray($styleArray);
        $sheet->getStyleByColumnAndRow($startingColumn+1, $startingRow+1, $columnCount+2, $startingRow+5)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyleByColumnAndRow($startingColumn+1, $startingRow+1, $columnCount+2, $startingRow+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);
        $sheet->getStyleByColumnAndRow($startingColumn+1, $startingRow+6, $columnCount+2, $startingRow+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);
        $sheet->fromArray(
            [
                ['Answer in 30 sec.(ნაპასუხები)'],
                ['Abandon in 30 sec.(გათიშული)'],
                ['Average of Waiting Time per month (sec) საშ. მოლოდილის დრო(წმ) სანამ ვუპასუხებთ'],
                ['Maximum of waiting  Time per month (min)'],
                ['Average of Talking Time საშ, საუბრის დრო'],
                ['Unwanted call Ratio(UCR)'],
                ['Average Time of Abandonment (sec)']
            ],
            null,
            sprintf("B%s", $startingRow+1)
        );
        $dates = array_keys($this->excelData);
        $answered = [];
        $abandoned = [];
        $avgWaitTimeSec = [];
        $maxWaitTimeMin = [];
        $avgTalkTimeMin = [];
        $avgAbandonTimeSec = [];
        $UCR = [];
        foreach($dates as $date) {
            $answered[] = $this->excelData[$date]['kpiStats']['answered'];
            $abandoned[] = $this->excelData[$date]['kpiStats']['abandoned'];
            $avgWaitTimeSec[] = $this->excelData[$date]['kpiStats']['avgWaitTimeSec'];
            $avgTalkTimeMin[] = $this->excelData[$date]['kpiStats']['avgTalkTimeMin'];
            $UCR[] = $this->excelData[$date]['kpiStats']['UCR'];
            $maxWaitTimeMin[] = $this->excelData[$date]['kpiStats']['maxWaitTimeMin'];
            $avgAbandonTimeSec[] = $this->excelData[$date]['kpiStats']['avgAbandonTimeSec'];
        }
        $arrayData = [$answered, $abandoned, $avgWaitTimeSec, $maxWaitTimeMin, $avgTalkTimeMin, $UCR, $avgAbandonTimeSec];
        $sheet->fromArray($arrayData, null, sprintf("C%s", $startingRow+1), true);
    }

}