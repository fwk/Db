<?php
namespace Fwk\Db\Events;

class AfterQueryEvent extends BeforeQueryEvent
{
    const EVENT_NAME = 'afterQuery';
}