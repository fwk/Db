<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;

class FreshEvent extends Event
{
    const EVENT_NAME = 'fresh';

    public function __construct()
    {
        parent::__construct(self::EVENT_NAME, array());
    }
}