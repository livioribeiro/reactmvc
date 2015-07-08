<?php

namespace ReactMVC\Console;

use Symfony\Component\Console\Application;

use ReactMVC\Console\Commands\ServerStartCommand;

/**
 * Description of Application
 *
 * @author livio
 */
class Manager extends Application {
    
    private $applicationPath;
    
    /**
     * @param string $applicationPath Path to the root of the application
     */
    public function __construct($applicationPath) {
        parent::__construct('React MV-What?', '1.0.0-alpha');
        
        $this->applicationPath = $applicationPath;
        
        $this->add(new ServerStartCommand($applicationPath));
    }
    
    public function getApplicationPath() {
        return $this->applicationPath;
    }
}
