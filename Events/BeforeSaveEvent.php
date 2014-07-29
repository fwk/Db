<?php
namespace Fwk\Db\Events;

class BeforeSaveEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'beforeSave';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}