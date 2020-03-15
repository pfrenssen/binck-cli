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
class CashDividendReport extends CommandBase
{

    use ResultsTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('export:cash-dividend-report')
            ->setDescription('Exports a cash dividend report');
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

        // Retrieve info about the past year.
        // @todo Make this configurable through an option on the command line.
        $year = date('Y', strtotime('-1 year'));

        $this->logIn();
        $this->visitResultsOverview();

        $data = $this->getResultHistory($year, 'Cashdividenden');

        if ($data->NoOfPages !== 1) {
            throw new \Exception('There are ' . $data->NoOfPages . ' result pages but multipage results are not implemented yet.');
        }

        $securities = [];
        foreach ($data->ResultsHistoryItems as $item) {
            $name = $item->SecurityName;

            $securities[$name] = [
                'name' => $name,
                'company' => explode(' ', $name)[0],
                'country' => 'Ireland',
                'total' => str_replace([' ', '.', ','], ['', '', '.'], $item->Total),
                'tax' => '€0.00',
            ];
        }

        // Show results in the CLI.
        $headers = [
          'Security',
          'Company',
          'Country',
          'Dividend',
          'Tax paid/withheld abroad',
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
            $amount = mb_substr($security['total'], 1);
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(3, $sheet_row, $amount);
            $sheet->getActiveSheet()->getStyleByColumnAndRow(3, $sheet_row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);

            // Tax paid.
            $amount = mb_substr($security['tax'], 1);
            $sheet->getActiveSheet()->setCellValueByColumnAndRow(4, $sheet_row, $amount);
            $sheet->getActiveSheet()->getStyleByColumnAndRow(4, $sheet_row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);

            $sheet_row++;
        }

        // Export Excel file.
        $writer = \PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
        $writer->setPreCalculateFormulas(true);
        $writer->save('dividends.xlsx');
    }

}
