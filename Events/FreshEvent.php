<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;

class FreshEvent extends AbstractEntityEvent
{
    const EVENT_NAME = 'fresh';

    public function getEventName()
    {
        return self::EVENT_NAME;
    }
}