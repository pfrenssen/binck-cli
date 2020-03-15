<?php

namespace BinckCli\Command\Export;

use BinckCli\Command\CommandBase;
use GuzzleHttp\Cookie\SetCookie;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to export a report about cash dividends that were paid.
 */
class CashDividendReport extends CommandBase
{


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

        // Retrieve the verification token that is used in AJAX requests.
        $element = $this->session->getPage()->find('xpath', '//body/div[@class = "wrap"]/input[@name = "__RequestVerificationToken"][1]');
        if (empty($element)) throw new \Exception('Could not find the request verification token.');
        $request_verification_token = $element->getValue();

        $client = new \GuzzleHttp\Client();

        $cookie_jar = new \GuzzleHttp\Cookie\CookieJar();
        foreach (static::COOKIES as $domain => $cookie_names) {
            foreach ($cookie_names as $cookie_name) {
                $cookie_value = $this->session->getCookie($cookie_name);
                if (empty($cookie_value)) throw new \InvalidArgumentException("Value for cookie '$cookie_name' could not be retrieved.");
                $cookie_jar->setCookie(new SetCookie([
                    'Domain'  => $domain,
                    'Name'    => $cookie_name,
                    'Value'   => $cookie_value,
                    'Discard' => true
                ]));
            }
        }

        $result = $client->request('POST', 'https://web.binck.be/ResultsOverview/GetResultHistory', [
            'cookies' => $cookie_jar,
            'headers' => [
                'Origin' => 'https://web.binck.be',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.9,en-GB;q=0.8,de;q=0.7,nl;q=0.6',
                'X-Requested-With' => 'XMLHttpRequest',
                'Connection' => 'keep-alive',
                'Pragma' => 'no-cache',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.109 Safari/537.36',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Cache-Control' => 'no-cache',
                'Referer' => 'https://web.binck.be/ResultsOverview/Index',
                '__RequestVerificationToken' => $request_verification_token,
                'DNT' => '1',
            ],
            'query' => [
                'page' => '1',
                'sortProperty' => 'SecurityName',
                'sortOrder' => '0',
                'resultsType' => 'Position',
                'positionType' => 'All',
                'category' => 'Cashdividenden',
                'year' => $year,
            ],
        ]);

        $result_code = $result->getStatusCode();
        if ($result_code != 200) throw new \Exception("Request to retrieve dividend data returned status code $result_code.");

        $data = json_decode($result->getBody()->getContents());

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
