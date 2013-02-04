<?php
namespace Fwk\Db\Listeners;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Fwk\Events\Event;

class QueryLogListener implements LoggerAwareInterface
{
    /**
     *
     * @var LoggerInterface
     */
    protected $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function onAfterQuery(Event $event)
    {
        $sql = $event->bridge->getQueryString();
        $this->logger->debug($sql);
    }
    
    /**
     *
     * @return LoggerInterface
     */
    public function getLogger() 
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger) 
    {
        $this->logger = $logger;
    }
}