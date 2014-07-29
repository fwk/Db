<?php
namespace Fwk\Db\Events;

class BeforeDeleteEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'beforeDelete';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}