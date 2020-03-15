<?php

namespace BinckCli\Command\Export;

use BinckCli\Command\CommandBase;
use BinckCli\Traits\ResultsTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to export an overview of investments.
 *
 * This currently exports the investments from the previous year.
 */
class InvestmentsOverview extends CommandBase
{

    use ResultsTrait;

    const TRANSACTION_TYPE_MAPPING = [
        'Aankoop' => 'Purchase',
        'Deponering' => 'Deposit',
        'Lichting' => 'Delisting',
        'Verkoop' => 'Sale',
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('export:investments')
            ->setDescription('Exports an investments overview');
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

        // Receive data from last year's ETF's.
        // @todo Support other categories.
        $funds = [];
        $page = 1;
        do {
            $data = $this->getResultHistory($year, 'Trackers', $page);
            foreach ($data->ResultsHistoryItems as $item) {
                $funds[$item->SecurityId] = $item->SecurityName;
            }
        } while ($page++ < $data->NoOfPages);

        // Initialize an Excel sheet to save the data in.
        $sheet = new \PHPExcel();
        $sheet->setActiveSheetIndex(0);
        $sheet_row = 1;

        for ($i = 0; $i < 7; $i++) $sheet->getActiveSheet()->getColumnDimensionByColumn($i)->setAutoSize(TRUE);

        $headers = [
            'Fund name',
            'Domicile',
            'Transaction date',
            'Transaction type',
            'Number',
            'Share price',
            'Total shares',
            'Purchase price',
        ];
        foreach ($headers as $column => $header) {
            $sheet->getActiveSheet()->setCellValueByColumnAndRow($column, $sheet_row, $header);
        }
        $sheet_row++;

        // Visit the transaction history page of each fund and retrieve the
        // data.
        foreach ($funds as $id => $name) {
            $transactions = [];
            $output->writeln("\n<info>$name</info>");
            $data = $this->getPositionMutations($id);

            if ($data->NoOfPages !== 1) {
                throw new \Exception('There are ' . $data->NoOfPages . ' result pages but multipage results are not implemented yet.');
            }

            $transactions = array_map(function (\stdClass $mutation) {
                return [
                    'Date' => $mutation->TransactionDate,
                    'Transaction' => $mutation->TransactionType,
                    'Number' => $mutation->Mutation,
                    'Share price' => $mutation->Price,
                    'Position' => $mutation->NewPosition,
                ];
            }, $data->PositionMutationDetails);

            // Show progress output in the CLI while working.
            $table = new Table($output);
            $table
                ->setHeaders(['Date', 'Transaction', 'Number', 'Share price', 'Position'])
                ->setRows($transactions);
            $table->render();

            foreach ($transactions as $transaction) {
                // Effect name.
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(0, $sheet_row, $name);

                // Domicile.
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(1, $sheet_row, 'Ireland');

                // Date of transaction.
                $date = array_shift($transaction);
                $date = \DateTime::createFromFormat('d/m/Y', $date);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(2, $sheet_row, \PHPExcel_Shared_Date::PHPToExcel($date));
                $sheet->getActiveSheet()->getStyleByColumnAndRow(2, $sheet_row)->getNumberFormat()->setFormatCode('dd.mm.yyyy');

                // Type of transaction.
                $type = $this->translateTransactionType(array_shift($transaction));
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(3, $sheet_row, $type);

                // Quantity of shares.
                $quantity = strtr(array_shift($transaction), ['.' => '']);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(4, $sheet_row, $quantity);
                if ($quantity < 0) $sheet->getActiveSheet()->getStyleByColumnAndRow(4, $sheet_row)->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));

                // Share price.
                $price = strtr(array_shift($transaction), [',' => '.']);
                $currency = \PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
                if (substr($price, 0, 1) === '$') {
                    $price = substr($price, 2);
                    $currency = \PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE;
                }
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(5, $sheet_row, $price);
                $sheet->getActiveSheet()->getStyleByColumnAndRow(5, $sheet_row)->getNumberFormat()->setFormatCode($currency);

                // Total position.
                $position = strtr(array_shift($transaction), ['.' => '']);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(6, $sheet_row, $position);

                // Purchase price.
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(7, $sheet_row, "=E$sheet_row*F$sheet_row");
                $sheet->getActiveSheet()->getStyleByColumnAndRow(7, $sheet_row)->getNumberFormat()->setFormatCode($currency);

                $sheet_row++;
            }
            $sheet_row++;
        }

        // Export Excel file.
        $writer = \PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
        $writer->setPreCalculateFormulas(true);
        $writer->save('investments.xlsx');
    }

    protected function translateTransactionType($type)
    {
        return self::TRANSACTION_TYPE_MAPPING[$type];
    }

}
