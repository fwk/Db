<?php
namespace Fwk\Db\Events;

class AfterSaveEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'afterSave';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}