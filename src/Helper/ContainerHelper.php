<?php

namespace BinckCli\Helper;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContainerHelper extends Helper
{

    protected $container;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    public function get($id)
    {
        if ($this->container->has($id)) {
            return $this->container->get($id);
        }

        throw new \InvalidArgumentException("Service '$id' is not defined.'");
    }

    public function getName()
    {
        return 'container';
    }

}
