<?php

namespace ReactMVC\Application;

use DI\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Description of RunCommand
 *
 * @author livio
 */
class ServerCommand extends Command {
    
    private $container;
    
    public function __construct(Container $container) {
        parent::__construct();
        
        $this->container = $container;
    }
    
    protected function configure() {
        $this
            ->setName('start')
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'Address to listen', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen', '8000')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Start server in debug mode (Output all debug messages)');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $debug = $input->getOption('debug');
        
        if ($debug) {
            $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
            $output->setDecorated(true);
        }
        
        if ($debug || !$this->container->has(LoggerInterface::class)) {
            $logger = new ConsoleLogger($output);
            $this->container->set(LoggerInterface::class, $logger);
        }
        
        $kernel = $this->container->get('app.kernel');
        
        try {
            $kernel->run($host, $port, $debug);
        }
        catch (\Exception $e) {
            $this->getApplication()->renderException($e, $output);
        }
    }
}
