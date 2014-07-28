<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;
use Fwk\Db\Connection;

class DisconnectEvent extends Event
{
    const EVENT_NAME = 'disconnect';

    /**
     * Constructor
     *
     * @param Connection $connection The DB Connection
     *
     * @return void
     */
    public function __construct(Connection $connection)
    {
        parent::__construct(self::EVENT_NAME, array(
            'connection' => $connection
        ));
    }


    /**
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}