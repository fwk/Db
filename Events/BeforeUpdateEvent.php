<?php
namespace Fwk\Db\Events;

class BeforeUpdateEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'beforeUpdate';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}