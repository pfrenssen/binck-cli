<?php

namespace BinckCli\Command\Export;

use BinckCli\Command\CommandBase;
use BinckCli\Traits\ResultsTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to export a report about cash dividends that were paid.
 */
class SalesReport extends CommandBase
{

    use ResultsTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('export:sales-report')
            ->setDescription('Exports a sales report');
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->session = $this->getSession();

        $this->logIn();
        $this->visitResultsOverview();

        // Retrieve info about the past year.
        // @todo Make this configurable through an option on the command line.
        $year = date('Y', strtotime('-1 year'));

        $securities = [];
        $page = 1;
        do {
            // Receive data from last year's ETF's.
            // @todo Support other categories.
            $data = $this->getResultHistory($year, 'Trackers', $page);
            foreach ($data->ResultsHistoryItems as $item) {
                $name = $item->SecurityName;

                $securities[$name] = [
                    'name' => $name,
                    'company' => explode(' ', $name)[0],
                    'country' => 'Ireland',
                    'profit' => str_replace([' ', '.', ','], ['', '', '.'], $item->RealizedResult),
                ];
            }
        } while ($page++ < $data->NoOfPages);

        // Show results in the CLI.
        $headers = [
          'Security',
          'Company',
          'Country',
          'Profit',
        ];

        $table = new Table($output);
        $table
          ->setHeaders($headers)
          ->setRows($securities);
        $table->render();

        // Initialize an Excel sheet to save the data in.
        $sheet = new \PHPExcel();
        $sheet->setActiveSheetIndex(0);
        $sheet_row = 1;

        for ($i = 0; $i < 5; $i++) $sheet->getActiveSheet()->getColumnDimensionByColumn($i)->setAutoSize(TRUE);

        foreach ($headers as $column => $header) {
            $sheet->getActiveSheet()->setCellValueByColumnAndRow($column, $sheet_row, $header);
        }
        $sheet_row++;

        foreach ($securities as $security) {
            // Security name.
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(0, $sheet_row, $security['name']);

            // Company.
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(1, $sheet_row, $security['company']);

            // Country.
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(2, $sheet_row, $security['country']);

            // Total.
            $amount = mb_substr($security['profit'], 1);
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(3, $sheet_row, $amount);
            $sheet->getActiveSheet()->getStyleByColumnAndRow(3, $sheet_row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);

            $sheet_row++;
        }

        // Export Excel file.
        $writer = \PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
        $writer->setPreCalculateFormulas(true);
        $writer->save('sales.xlsx');
    }

}
