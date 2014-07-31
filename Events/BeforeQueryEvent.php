<?php
namespace Fwk\Db\Events;

use Fwk\Db\Query;
use Fwk\Db\QueryBridge;
use Fwk\Events\Event;
use Fwk\Db\Connection;

class BeforeQueryEvent extends Event
{
    const EVENT_NAME = 'beforeQuery';

    protected $query;
    protected $queryOptions = array();
    protected $queryParameters = array();
    protected $results = null;

    /**
     * Constructor
     *
     * @param Connection $connection   The DB Connection
     * @param Query      $query        The Query
     * @param array      $queryParams  Query Parameters
     * @param array      $queryOptions Query Options
     * @param mixed      $results      Query results/pre-results
     *
     * @return void
     */
    public function __construct(Connection $connection, Query $query, array $queryParams = array(),
        array $queryOptions = array(), $results = array()
    ) {
        parent::__construct(static::EVENT_NAME, array(
            'connection'    => $connection,
            'query'         => $query,
            'queryParameters'   => (array)$queryParams,
            'queryOptions'  => (array)$queryOptions,
            'results'       => &$results,
            'bridge'        => null
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
     * @return mixed
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * @param Query $query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * @param array $queryOptions
     */
    public function setQueryOptions(array $queryOptions)
    {
        $this->queryOptions = $queryOptions;
    }

    /**
     * @param array $queryParameters
     */
    public function setQueryParameters(array $queryParameters)
    {
        $this->queryParameters = $queryParameters;
    }

    /**
     * @param mixed $results
     */
    public function setResults($results)
    {
        $this->results = $results;
    }

    public function setQueryBridge(QueryBridge $bridge)
    {
        $this->bridge = $bridge;
    }

    /**
     *
     * @return QueryBridge
     */
    public function getQueryBridge()
    {
        return $this->bridge;
    }
}