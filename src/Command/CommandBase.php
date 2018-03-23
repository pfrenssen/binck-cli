<?php

namespace BinckCli\Command;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Zumba\Mink\Driver\PhantomJSDriver;

/**
 * Base class for commands.
 */
abstract class CommandBase extends Command
{

    /**
     * The Mink session manager.
     *
     * @var \Behat\Mink\Mink
     */
    protected $mink;

    /**
     * The Mink session.
     *
     * @var \Behat\Mink\Session
     */
    protected $session;

    /**
     * Returns the dependency injection container helper.
     *
     * @return \BinckCli\Helper\ContainerHelper
     *   The dependency injection container helper.
     */
    protected function getContainer()
    {
        return $this->getHelper('container');
    }

    /**
     * Returns the configuration manager.
     *
     * @return \BinckCli\Config\ConfigManager
     *   The configuration manager.
     */
    protected function getConfigManager()
    {
        return $this->getContainer()->get('config.manager');
    }

    /**
     * Returns the Mink session manager.
     *
     * @return Mink
     *   The Mink session manager.
     */
    protected function getMink()
    {
        if (empty($this->mink)) {
            $this->mink = $this->initMink();
        }
        return $this->mink;
    }

    /**
     * Returns the Mink session.
     *
     * @return Session
     *   The Mink session.
     */
    protected function getSession()
    {
        return $this->getMink()->getSession();
    }

    /**
     * Initializes Mink.
     *
     * @return Mink
     *   The initialized Mink session manager.
     */
    protected function initMink()
    {
        $mink = new Mink();
        $config = $this->getConfigManager()->get('config');

        // Register PhantomJS driver.
        $host = $config->get('mink.sessions.phantomjs.host');
        $template_cache = $config->get('mink.sessions.phantomjs.template_cache');
        if (!file_exists($template_cache)) mkdir($template_cache);
        $mink->registerSession('phantomjs', new Session(new PhantomJSDriver($host, $template_cache)));

        // Register Selenium driver.
        $browser = $config->get('mink.sessions.selenium2.browser');
        $host = $config->get('mink.sessions.selenium2.host');
        $mink->registerSession('selenium2', new Session(new Selenium2Driver($browser, NULL, $host)));

        $mink->setDefaultSessionName($config->get('mink.default_session'));
        return $mink;
    }

    /**
     * Waits for the given element to appear or disappear from the DOM.
     *
     * @param string $selector
     *   The CSS selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param bool $present
     *   TRUE to wait for the element to appear, FALSE for it to disappear.
     *   Defaults to TRUE.
     *
     * @throws \Exception
     *   Thrown when the element doesn't appear or disappear within 20 seconds.
     */
    protected function waitForElementPresence($selector, $engine = 'css', $present = TRUE)
    {
        $timeout = 20000000;
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            $element = $this->session->getDriver()->find($selector);
            if (!empty($element) === $present) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new \Exception("The element with selector '$selector' is " . ($present ? 'not ' : '') . 'present on the page.');
    }

    /**
     * Waits for the given element to become (in)visible.
     *
     * @param string $selector
     *   The CSS selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param bool $visible
     *   TRUE to wait for the element to become visible, FALSE for it to become
     *   invisible. Defaults to TRUE.
     *
     * @throws \Exception
     *   Thrown when the element doesn't become (in)visible within 20 seconds.
     */
    protected function waitForElementVisibility($selector, $engine = 'css', $visible = TRUE)
    {
        $timeout = 20000000;
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            $element = $this->mink->assertSession()->elementExists($engine, $selector);
            if ($element->isVisible() === $visible) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new \Exception("The element with selector '$selector' is " . ($visible ? 'not ' : '') . 'visible on the page.');
    }

    /**
     * Logs in.
     */
    protected function LogIn()
    {
        $config = $this->getConfigManager()->get('config');
        $base_url = $config->get('base_url');
        $this->session->visit($base_url);
        $this->session->getPage()->fillField('UserName', $config->get('credentials.username'));
        $this->session->getPage()->fillField('Password', $config->get('credentials.password'));
        $this->session->getPage()->pressButton('Inloggen');
        $this->waitForElementPresence('#loginTwoFactor');
        $this->session->getPage()->clickLink('Alleen rekening raadplegen (zonder code)');
        $this->waitForElementPresence('#secondary-nav-left');
    }

    /**
     * Navigates to the portfolio overview.
     */
    protected function visitPortfolioOverview()
    {
        $this->session->visit('https://web.binck.be/PortfolioOverview/Index');
        $this->waitForElementVisibility('//table[contains(concat(" ", normalize-space(@class), " "), " sticky-portfolio-overview-table ")]', 'xpath');
    }

}
