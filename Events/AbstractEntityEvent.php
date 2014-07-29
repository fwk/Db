<?php
namespace Fwk\Db\Events;

use Fwk\Db\Table;
use Fwk\Events\Event;
use Fwk\Db\Connection;

abstract class AbstractEntityEvent extends Event
{
    /**
     * Constructor
     *
     * @param Connection $connection The DB Connection
     *
     * @return void
     */
    public function __construct(Connection $connection, Table $table, $entity)
    {
        parent::__construct($this->getEventName(), array(
            'connection'    => $connection,
            'table'         => $table,
            'entity'        => $entity
        ));
    }

    abstract public function getEventName();

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}