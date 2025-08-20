<?php

namespace emteknetnz\JsUpdater\Commands;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'base',
    description: 'Base command for other commands',
)]
class BaseCommand extends Command
{
    /**
     * An instance of ContainerInterface
     */
    protected ContainerInterface $container;
    
    /**
     * Sets the container property.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }
}
