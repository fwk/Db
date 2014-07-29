<?php
namespace Fwk\Db\Events;

class AfterUpdateEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'afterUpdate';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}