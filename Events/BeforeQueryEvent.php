<?php
namespace Fwk\Db\Events;

use Fwk\Db\Query;
use Fwk\Events\Event;
use Fwk\Db\Connection;

class BeforeQueryEvent extends Event
{
    const EVENT_NAME = 'beforeQuery';

    /**
     * Constructor
     *
     * @param Connection $connection   The DB Connection
     * @param Query      $query        The Query
     * @param array      $queryParams  Query Parameters
     * @param array      $queryOptions Query Options
     *
     * @return void
     */
    public function __construct(Connection $connection, Query $query, $queryParams = array(), $queryOptions = array())
    {
        parent::__construct(self::EVENT_NAME, array(
            'connection'    => $connection,
            'query'         => $query,
            'queryParameters'   => (array)$queryParams,
            'queryOptions'  => (array)$queryOptions,
            'results'       => array()
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
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function getQueryParameters()
    {
        return $this->queryParameters;
    }

    /**
     * @return array
     */
    public function getQueryOptions()
    {
        return $this->queryOptions;
    }

    /**
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}