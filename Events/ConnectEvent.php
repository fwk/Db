<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;
use Fwk\Db\Connection;

class ConnectEvent extends Event
{
    const EVENT_NAME = 'connect';

    /**
     * Constructor
     *
     * @param Connection $connection The DB Connection
     *
     * @return void
     */
    public function __construct(Connection $connection)
    {
        parent::__construct(static::EVENT_NAME, array(
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