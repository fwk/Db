<?php
namespace Fwk\Db\Listeners;

use Fwk\Db\Accessor;
use Fwk\Db\Events\BeforeSaveEvent;
use Fwk\Db\Events\BeforeUpdateEvent;

class Timestampable
{
    protected $creationColumn = 'created_at';
    protected $updateColumn = 'updated_at';
    protected $dateFormat = 'Y-m-d H:i:s';

    public function __construct($creationColumn = 'created_at', $updateColumn = 'updated_at',
        $dateFormat = 'Y-m-d H:i:s'
    ) {
        $this->creationColumn   = $creationColumn;
        $this->updateColumn     = $updateColumn;
        $this->dateFormat       = $dateFormat;
    }

    public function onBeforeSave(BeforeSaveEvent $event)
    {
        $date = new \DateTime();
        Accessor::factory($event->getEntity())->set($this->creationColumn, $date->format($this->dateFormat));
    }

    public function onBeforeUpdate(BeforeUpdateEvent $event)
    {
        $date = new \DateTime();
        Accessor::factory($event->getEntity())->set($this->updateColumn, $date->format($this->dateFormat));
    }
}