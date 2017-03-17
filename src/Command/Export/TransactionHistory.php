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
        }
    }

}
