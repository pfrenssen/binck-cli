<?php

namespace BinckCli\Config;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

/**
 * Represents a single configuration file.
 */
class Config
{
    /**
     * The parsed configuration.
     *
     * @var array
     *   An array of configuration, keyed by type.
     */
    protected $config = [];

    /**
     * The path of the configuration file.
     *
     * @var string
     */
    protected $path;

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
     * @param string $filename
     *   The name of the config file to parse, without .yml extension.
     */
    public function __construct(Parser $parser, Dumper $dumper, $filename)
    {
        $this->parser = $parser;
        $this->dumper = $dumper;
        $this->path = __DIR__ . "/../../config/$filename.yml";

        // The optional '.dist' file contains default values. Load this first,
        // then override the default values with the actual ones.
        $this->parse($this->path . '.dist');
        $this->parse($this->path);
    }

    /**
     * Parses the file at the given path, and stores the result locally.
     *
     * @param string $path
     *   The path to the configuration file to parse.
     */
    protected function parse($path)
    {
        if (file_exists($path) && $config = $this->parser->parse(file_get_contents($path))) {
            $this->config = array_replace_recursive($this->config, $config);
        }
    }

    /**
     * Returns a configuration value.
     *
     * @param string $key
     *   The configuration key. Separate hierarchical keys with a period.
     * @param mixed $default
     *   The default value to return if the configuration element is not set.
     *
     * @return array|mixed
     *   The requested configuration.
     */
    public function get($key, $default = '')
    {
        $config = $this->config;
        foreach (explode('.', $key) as $element) {
            if (!empty($config[$element])) {
                $config = $config[$element];
            } else {
                return $default;
            }
        }
        return $config;
    }

    /**
     * Stores the given value in the configuration.
     *
     * @param string $key
     *   The configuration key. Separate hierarchical keys with a period.
     * @param mixed $value
     *   The value to set.
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                $config[$key] = [];
            }
            $config = &$config[$key];
        }
        $config = $value;

        return $this;
    }

    /**
     * Saves the current configuration to file storage.
     */
    public function save()
    {
        $yaml = $this->dumper->dump($this->config, 5);
        file_put_contents($this->path, $yaml);
    }

    /**
     * Returns the full configuration array.
     *
     * @return array
     *   The full configuration array.
     */
    public function getConfig()
    {
        return $this->config;
    }

}
