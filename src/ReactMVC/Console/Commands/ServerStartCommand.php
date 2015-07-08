<?php

namespace ReactMVC\Console\Commands;

use Lurker\Event\FilesystemEvent;
use React;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use ReactMVC\ResourceWatcher\LoopResourceWatcher;

/**
 * Description of RunCommand
 *
 * @author livio
 */
class ServerStartCommand extends Command {
    
    private $applicationPath;
    
    public function __construct($applicationPath) {
        parent::__construct();
        
        $this->applicationPath = $applicationPath;
    }

    protected function configure() {
        $this
            ->setName('server:start')
            ->setDescription('Start the application in debug mode, restarting it when resources are changed')
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'Address to listen', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen', '8000')
            ->addOption('noreload', null, InputOption::VALUE_NONE, 'Do not restart server when resources change');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $noreload = $input->getOption('noreload');
        
        $script = realpath("{$this->applicationPath}/index.php");
        $cmd = "exec php $script -H $host -p $port -d";
        
        $loop = \React\EventLoop\Factory::create();
        $process = $this->startServerProcess($loop, $cmd);
        
        if (!$noreload) {
            $watcher = new LoopResourceWatcher($loop);
            $watcher->track('src', realpath("{$this->applicationPath}/src"));
            
            $config = realpath("{$this->applicationPath}/resources/config");
            if ($config) {
                $watcher->track('resources.config', $config);
            }

            $restartServerFunction = function (FilesystemEvent $event) use ($loop, &$process, $cmd) {
                $process->terminate(9);
                $process = $this->startServerProcess($loop, $cmd);
            };

            $watcher->addListener('all', $restartServerFunction);
            $watcher->start();
        }
        
        $loop->run();
    }
    
    private function startServerProcess($loop, $cmd) {
        $process = new React\ChildProcess\Process($cmd);
        $stdout = new React\Stream\Stream(fopen('php://stdout', 'w'), $loop);

        $process->on('exit', function($exitCode, $termSignal) use ($stdout) {
            $stdout->close();
        });

        $process->start($loop);

        $loop->nextTick(function($timer) use ($process, $stdout) {
            $process->stdout->pipe($stdout);
            $process->stderr->pipe($stdout);
        });
        
        return $process;
    }
}
