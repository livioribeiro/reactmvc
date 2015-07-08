<?php

namespace ReactMVC\ResourceWatcher;

use Lurker\ResourceWatcher;
use Lurker\Tracker\TrackerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Description of LoopResourceWatcher
 *
 * @author livio
 */
class LoopResourceWatcher extends ResourceWatcher {
    
    const U_SECOND = 1000000;
    
    /**
     * @var LoopInterface
     */
    private $loop;
    
    /**
     * @var TimerInterface
     */
    private $timer;
    
    public function __construct(LoopInterface $loop, TrackerInterface $tracker = null, EventDispatcherInterface $eventDispatcher = null) {
        parent::__construct($tracker, $eventDispatcher);
        
        $this->loop = $loop;
    }
    
    /**
     * {@inheritdoc}
     */
    public function start($checkInterval = 1000000, $timeLimit = null) {
        $this->timer = $this->loop->addPeriodicTimer($checkInterval / self::U_SECOND, function($timer) {
            foreach ($this->getTracker()->getEvents() as $event) {
                $trackedResource = $event->getTrackedResource();

                // fire global event
                $this->getEventDispatcher()->dispatch(
                    'resource_watcher.all',
                    $event
                );

                // fire specific trackingId event
                $this->getEventDispatcher()->dispatch(
                    sprintf('resource_watcher.%s', $trackedResource->getTrackingId()),
                    $event
                );
            }
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop() {
        $this->loop->cancelTimer($this->timer);
    }
}
