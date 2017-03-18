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
        $xpath = '//table[@id="ctl00_ctl00_Content_Content_OverzichtRepeater_ctl00_PortefeuilleOverzicht"]//a[@data-logging-name="PositionOverview"]';
        /** @var NodeElement $element */
        foreach ($this->session->getPage()->findAll('xpath', $xpath) as $element) {
            $url = $element->getAttribute('href');
            // The query argument contains information about the fund.
            $query = parse_url($url, PHP_URL_QUERY);
            parse_str($query, $fund_info);
            $funds[$fund_info['fondsId']] = $fund_info['fondsNaam'];
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
            'Total',
            'Share price',
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
            $this->session->visit('https://login.binck.be/Klanten/Portefeuille/PositieOpbouw.aspx?fondsId=' . $id);
            $xpath = '//table[@id="ctl00_ctl00_Content_Content_Posities"]/tbody/tr';
            /** @var NodeElement[] $rows */
            $rows = $this->session->getPage()->findAll('xpath', $xpath);

            // The top row is the table header, for some reason the developers
            // have put the header in the table body. Discard it.
            array_shift($rows);

            foreach ($rows as $row) {
                $columns = $row->findAll('css', 'td');

                // The first column contains action links. Discard it.
                array_shift($columns);

                $transactions[] = array_map(function (NodeElement $column) {
                    return $column->getText();
                }, $columns);
            }

            $table = new Table($output);
            $table
                ->setHeaders(['Date', 'Transaction', 'Number', 'Position', 'Share price'])
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
                $type = array_shift($transaction);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(3, $sheet_row, $type);

                // Quantity of shares.
                $quantity = strtr(array_shift($transaction), ['.' => '']);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(4, $sheet_row, $quantity);
                if ($quantity < 0) $sheet->getActiveSheet()->getStyleByColumnAndRow(4, $sheet_row)->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));

                // Total position.
                $position = strtr(array_shift($transaction), ['.' => '']);
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(5, $sheet_row, $position);

                // Share price.
                $price = strtr(array_shift($transaction), [',' => '.']);
                $currency = \PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE;
                if (substr($price, 0, 1) === '$') {
                    $price = substr($price, 1);
                    $currency = \PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE;
                }
                $sheet->getActiveSheet()->setCellValueByColumnAndRow(6, $sheet_row, $price);
                $sheet->getActiveSheet()->getStyleByColumnAndRow(6, $sheet_row)->getNumberFormat()->setFormatCode($currency);
                $sheet_row++;
            }
            $sheet_row++;
        }

        // Export Excel file.
        $writer = \PHPExcel_IOFactory::createWriter($sheet, 'Excel2007');
        $writer->save('export.xlsx');

    }

}
