<?php

namespace BinckCli\Config;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

/**
 * Configuration management service.
 */
class ConfigManager {

    /**
     * The Yaml parser.
     *
     * @var \Symfony\Component\Yaml\Parser
     */
    protected $parser;

    /**
     * The Yaml dumper.
     *
     * @var \Symfony\Component\Yaml\Dumper
     */
    protected $dumper;

    /**
     * Config constructor.
     *
     * @param \Symfony\Component\Yaml\Parser $parser
     *   The Yaml parser.
     * @param \Symfony\Component\Yaml\Dumper $dumper
     *   The Yaml dumper.
     */
    public function __construct(Parser $parser, Dumper $dumper)
    {
        $this->parser = $parser;
        $this->dumper = $dumper;
    }

    /**
     * Returns a Config object for the given config file.
     *
     * @param string $filename
     *   The filename of the config object to return, e.g. 'config.yml'.
     * @return \BinckCli\Config\Config
     *   The configuration object.
     */
    public function get($filename)
    {
        return new Config($this->parser, $this->dumper, $filename);
    }

}
