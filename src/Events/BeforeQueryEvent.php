<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2014, Julien Ballestracci <julien@nitronet.org>.
 * All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP Version 5.3
 *
 * @category   Database
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2014 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.nitronet.org/fwk
 */
namespace Fwk\Db\Events;

use Fwk\Db\Query;
use Fwk\Db\QueryBridge;
use Fwk\Events\Event;
use Fwk\Db\Connection;

/**
 * This event is fired before a Query is sent to be executed by the Connection
 *
 * @category Events
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
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
    public function __construct(Connection $connection, Query $query,
        array $queryParams = array(), array $queryOptions = array(),
        $results = array()
    ) {
        parent::__construct(
            static::EVENT_NAME, array(
                'connection'    => $connection,
                'query'         => $query,
                'queryParameters'   => (array)$queryParams,
                'queryOptions'  => (array)$queryOptions,
                'results'       => &$results,
                'bridge'        => null
            )
        );
    }

    /**
     * Returns the Database Connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the Query
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns the Query's parameters (i.e values)
     *
     * @return array
     */
    public function getQueryParameters()
    {
        return $this->queryParameters;
    }

    /**
     * Returns the Query's driver options
     *
     * @return array
     */
    public function getQueryOptions()
    {
        return $this->queryOptions;
    }

    /**
     * Returns the Query's results (if any)
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Defines the Query
     *
     * @param Query $query The Query
     *
     * @return void
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * Defines Driver options for this query
     *
     * @param array $queryOptions Driver options for this query
     *
     * @return void
     */
    public function setQueryOptions(array $queryOptions)
    {
        $this->queryOptions = $queryOptions;
    }

    /**
     * Defines parameters (i.e values) for the query
     *
     * @param array $queryParameters Parameters (values) for the Query
     *
     * @return void
     */
    public function setQueryParameters(array $queryParameters)
    {
        $this->queryParameters = $queryParameters;
    }

    /**
     * Defines Results for this Query
     *
     * @param mixed $results Query's results
     *
     * @return void
     */
    public function setResults($results)
    {
        $this->results = $results;
    }

    /**
     * Defines the QueryBridge for this query
     *
     * @param QueryBridge $bridge The QueryBridge used with this Query
     *
     * @return void
     */
    public function setQueryBridge(QueryBridge $bridge)
    {
        $this->bridge = $bridge;
    }

    /**
     * Returns the defined QueryBridge for this Query
     *
     * @return QueryBridge
     */
    public function getQueryBridge()
    {
        return $this->bridge;
    }
}