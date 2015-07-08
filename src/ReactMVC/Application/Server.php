<?php
namespace ReactMVC\Application;

use DI\ContainerBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Description of Application
 *
 * @author livio
 */
class Server extends Application
{
    private $container;

    /**
     * @param string $applicationPath Path to the root of the application
     */
    public function __construct($applicationPath)
    {
        $builder = new ContainerBuilder();
        $resourcesDir = $applicationPath . DIRECTORY_SEPARATOR . 'resources';
        $configDir = $resourcesDir . DIRECTORY_SEPARATOR . 'config';

        $builder->addDefinitions([
            'application' => $this,
            'app.path' => $applicationPath,
            'app.resources' => $resourcesDir,
            'app.config' => $configDir,
            'app.kernel' => \DI\object(Kernel::class)
        ]);

        $builder->addDefinitions($configDir . DIRECTORY_SEPARATOR . 'di.php');

        $this->container = $builder->build();

        parent::__construct();
    }

    protected function getCommandName(InputInterface $input)
    {
        return 'start';
    }

    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = $this->container->make(ServerCommand::class);

        return $defaultCommands;
    }

    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    public function getContainer()
    {
        return $this->container;
    }

}
