<?php

declare(strict_types = 1);

namespace BinckCli\Traits;

use BinckCli\Command\CommandBase;
use GuzzleHttp\Cookie\SetCookie;

/**
 * Reusable code for interacting with the "Results" pages.
 *
 * @see https://web.binck.be/ResultsOverview/Index
 */
trait ResultsTrait
{
    /**
     * Returns the result history.
     *
     * @param string $year
     *   Either the year for which to return results, or "SinceStart" to return
     *   all results. Defaults to "SinceStart".
     * @param string $category
     *   Either an empty string to return all results, or one of the following
     *   values:
     *   - 'Aandelen' - to return stocks.
     *   - 'Cashdividenden' - to return dividends.
     *   - 'Trackers' - to return ETFs.
     *   Defaults to an empty string.
     * @param int $page
     *   The page to return. Starts counting at 1. Defaults to 1.
     *
     * @return array
     *   An array containing the result history data.
     *
     * @throws \Exception
     */
    protected function getResultHistory(string $year = "SinceStart", string $category = "", int $page = 1) {
        $client = new \GuzzleHttp\Client();
        $result = $client->request('POST', 'https://web.binck.be/ResultsOverview/GetResultHistory', [
            'cookies' => $this->getCookies(),
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
                '__RequestVerificationToken' => $this->getToken(),
                'DNT' => '1',
            ],
            'query' => [
                'page' => (string) $page,
                'sortProperty' => 'SecurityName',
                'sortOrder' => '0',
                'resultsType' => 'Position',
                'positionType' => 'All',
                'category' => $category,
                'year' => $year,
            ],
        ]);

        $result_code = $result->getStatusCode();
        if ($result_code != 200) throw new \Exception("Request to retrieve dividend data returned status code $result_code.");

        return json_decode($result->getBody()->getContents());
    }

    /**
     * Returns the position mutations for the given security.
     *
     * @param int $security_id
     *   The security ID for which to return the mutations.
     * @param int $page
     *   The page to return. Starts counting at 1. Defaults to 1.
     *
     * @return array
     *   An array of position mutation data.
     *
     * @throws \Exception
     */
    protected function getPositionMutations(int $security_id, int $page = 1) {
        $client = new \GuzzleHttp\Client();
        $result = $client->request('POST', 'https://web.binck.be/PositionMutationsHistory/GetPositionMutations', [
            'cookies' => $this->getCookies(),
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
                '__RequestVerificationToken' => $this->getToken(),
                'DNT' => '1',
            ],
            'query' => [
                'page' => (string) $page,
                'sortProperty' => 'TransactionDate',
                'sortOrder' => 'Desc',
                'securityId' => (string) $security_id,
            ],
        ]);

        $result_code = $result->getStatusCode();
        if ($result_code != 200) throw new \Exception("Request to retrieve position mutations returned status code $result_code.");

        return json_decode($result->getBody()->getContents());
    }

    /**
     * Returns the verification token that is used in AJAX requests.
     *
     * @return string
     *   The verification token.
     *
     * @throws \Exception
     */
    protected function getToken() {
        $element = $this->session->getPage()->find('xpath', '//body/div[@class = "wrap"]/input[@name = "__RequestVerificationToken"][1]');
        if (empty($element)) throw new \Exception('Could not find the request verification token.');
        return $element->getValue();
    }

    /**
     * Returns the cookies.
     *
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    protected function getCookies() {
        $cookie_jar = new \GuzzleHttp\Cookie\CookieJar();
        foreach (CommandBase::COOKIES as $domain => $cookie_names) {
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
        return $cookie_jar;
    }
}
