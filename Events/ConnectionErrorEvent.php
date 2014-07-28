<?php
namespace Fwk\Db\Events;

use Fwk\Events\Event;
use Fwk\Db\Connection;
use \Exception;

class ConnectionErrorEvent extends Event
{
    const EVENT_NAME = 'connectionError';

    /**
     * Constructor
     *
     * @param Connection $connection The DB Connection
     *
     * @return void
     */
    public function __construct(Connection $connection, Exception $exception)
    {
        parent::__construct(self::EVENT_NAME, array(
            'connection'    => $connection,
            'exception'     => $exception
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
     * @return Exception
     */
    public function getException()
    {
        return $this->exception;
    }
}