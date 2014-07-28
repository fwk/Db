<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;
use Fwk\Db\Connection;

class ConnectionStateChangeEvent extends Event
{
    const EVENT_NAME = 'connectionStateChange';

    /**
     * Constructor
     *
     * @param Connection $connection The DB Connection
     *
     * @return void
     */
    public function __construct(Connection $connection, $previousState, $newState)
    {
        parent::__construct(self::EVENT_NAME, array(
            'connection'    => $connection,
            'previousState' => $previousState,
            'newState'      => $newState
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

    /**
     * @return integer
     */
    public function getPreviousState()
    {
        return $this->previousState;
    }

    /**
     * @return integer
     */
    public function getNewState()
    {
        return $this->newState;
    }
}