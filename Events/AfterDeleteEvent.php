<?php
namespace Fwk\Db\Events;

class AfterDeleteEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'afterDelete';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}