<?php

namespace BinckCli\Command\Export;

use Behat\Mink\Element\NodeElement;
use BinckCli\Command\CommandBase;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to export the transaction history.
 */
class TransactionHistory extends CommandBase
{

    const TRANSACTION_TYPE_MAPPING = [
        'Aankoop' => 'Purchase',
        'Verkoop' => 'Sale',
        'Deponering' => 'Deposit',
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('export:transaction-history')
            ->setDescription('Exports the transaction history');
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
        $this->visitPortfolioOverview();

        // Retrieve info about the funds to collect from the links leading to
        // the transaction history pages of the funds.
        $funds = [];
        $xpath = '//table[contains(concat(" ", normalize-space(@class), " "), " sticky-portfolio-overview-table ")]//button[contains(concat(" ", normalize-space(@class), " "), " context-menu ")]';
        /** @var NodeElement $element */
        foreach ($this->session->getPage()->findAll('xpath', $xpath) as $element) {
            $data = json_decode($element->getAttribute('data-request'));
            // Only consider funds of type '0'. Skip all other types (e.g. type '2' is cash dividends).
            if ($data->SecurityType != 0) continue;
            $funds[$data->SecurityId] = $element->getAttribute('data-title');
        }

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
            $this->session->visit('https://web.binck.be/PositionMutationsHistory?securityId=' . $id . '&filterMode=All');
            // The data seems to appear in two distinct steps: first the table appears but is not yet filled with data.
            // Then when the info about the security appears above the table the contents are also filled.
            $this->waitForElementVisibility('//table[@id="PositionMutationsHistoryTable"]/tbody/tr/td', 'xpath');
            $this->waitForElementVisibility('//h1[@id="SecurityHeader"]', 'xpath');
            // In any case wait for the spinner to disappear.
            $this->waitForElementPresence('#spinner', 'css', false);

            $xpath = '//table[@id="PositionMutationsHistoryTable"]/tbody/tr';
            /** @var NodeElement[] $rows */
            $rows = $this->session->getPage()->findAll('xpath', $xpath);

            foreach ($rows as $row) {
                $columns = $row->findAll('css', 'td');

                $transactions[] = array_map(function (NodeElement $column) {
                    return $column->getText();
                }, $columns);
            }

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
        $writer->save('export.xlsx');
    }

    protected function translateTransactionType($type)
    {
        return self::TRANSACTION_TYPE_MAPPING[$type];
    }

}
