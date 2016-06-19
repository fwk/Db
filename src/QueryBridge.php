<?php
/**
 * Fwk
 *
 * Copyright (c) 2011-2012, Julien Ballestracci <julien@nitronet.org>.
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
 * @package    Fwk
 * @subpackage Db
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db;

use Fwk\Db\Connection;
use Fwk\Db\Query;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * This class transform a Fwk\Db\Query object into a SQL query string
 *
 */
class QueryBridge
{
    const STATE_INIT    = 0;
    const STATE_READY   = 1;
    const STATE_ERROR   = 2;

    /**
     * The Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     *
     * @var array
     */
    protected $tablesAliases;

    /**
     *
     * @var array
     */
    protected $columnsAliases;

    /**
     *
     * @var QueryBuilder
     */
    protected $handle;

    protected $state = self::STATE_INIT;

    protected $queryString;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     *
     * @param  Query                             $query
     * @param  array                             $params
     * @param  array                             $options
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function execute(Query $query, array $params = array(), array $options = array())
    {
        $this->queryString = $sql = $this->prepare($query, $options);

        if ($query->getType() == Query::TYPE_INSERT) {
            return $this->connection->getDriver()->executeUpdate($sql, $params);
        }

        $this->handle->setParameters($params);

        return $this->handle->execute();
    }

    /**
     *
     * @param Query $query
     *
     * @return string
     */
    public function prepare(Query $query)
    {
        if ($this->state !== self::STATE_INIT) {
            return null;
        }

        $this->handle   = $this->connection->getDriver()->createQueryBuilder();
        $type           = $query->getType();
        switch ($type) {
            case Query::TYPE_DELETE:
                $parts  = array(
                    'delete' => true
                );
                $call = array($this, 'deleteQuery');
                break;

            case Query::TYPE_INSERT:
                $parts  = array(
                    'insert' => true,
                    'values' => true
                );
                $call = array($this, 'insertQuery');
                break;

            case Query::TYPE_SELECT:
                $parts  = array(
                    'select' => true,
                    'from' => true
                );
                $call = array($this, 'selectQuery');
                break;

            case Query::TYPE_UPDATE:
                $parts = array(
                    'update' => true
                );
                $call = array($this, 'updateQuery');
                break;

            default:
                return null;
        }

        foreach ($parts as $part => $required) {
            if ($required == true && !$query->offsetExists($part)) {
                throw new Exception(sprintf('Missing required query part "%s"', $part));
            }
        }

        $table = $query['from'];
        if (!empty($table)) {
            if(strpos($table, ' ')) {
                list($table, ) = explode(' ', $table);
            }

            $decl = $this->connection->table($table)->getDefaultEntity($table);
            if(empty($query['entity']) || $query['entity'] == "\stdClass") {
                $query->entity($decl);
            }
        }

        if (!empty($query['entity']) && $query['entity'] != "\stdClass") {
            $obj        = new $query['entity'];
            $access     = new Accessor($obj);
            $relations  = $access->getRelations();

            foreach ($relations as $colName => $relation) {
                $relation->prepare($query, $colName);
            }
        }

        return trim(\call_user_func($call, $query));
    }

    /**
     *
     * @return void
     */
    public function selectQuery(Query $query)
    {
        $queryJoins = $query['joins'];

        $this->getSelectFrom($query['from'], $queryJoins);
        $this->getSelectColumns($query['select'], $query['from'], $query, $queryJoins);
        if (isset($query['joins'])) { $this->getSelectJoins($queryJoins); }
        if (isset($query['where'])) { $this->getWhere($query); }
        if (isset($query['groupBy'])) { $this->getGroupBy($query['groupBy']); }
        if (isset($query['orderBy'])) { $this->getOrderBy($query['orderBy']); }
        if (isset($query['limit'])) { $this->getLimit($query['limit']); }

        return $this->handle->getSQL();
    }

    /**
     *
     * @return string
     */
    public function deleteQuery(Query $query)
    {
        $from = $query['delete'];
        if (strpos($from,' ')) {
            list($from, $alias) = explode(' ', $from);
        } else {
            $alias = null;
        }

        $this->handle->delete($from, $alias);
        if (isset($query['where'])) { $this->getWhere($query); }
        if (isset($query['limit'])) { $this->getLimit($query['limit']); }

        return $this->handle->getSQL();
    }

    /**
     *
     * @return string
     */
    public function updateQuery(Query $query)
    {
        $update = $query['update'];
        if (strpos($update,' ')) {
            list($update, $alias) = explode(' ', $update);
        } else {
            $alias = null;
        }
        $this->handle->update($update, $alias);
        $this->getUpdateSet($query['values']);
        if (isset($query['where'])) { $this->getWhere($query); }
        if (isset($query['limit'])) { $this->getLimit($query['limit']); }

        return $this->handle->getSQL();
    }

    /**
     *
     * @return string
     */
    public function insertQuery(Query $query)
    {
        $str = "INSERT INTO";

        $table = $query['insert'];
        if($table instanceof Table)
            $table = $table->getName();

        $vals = $this->getInsertValues($query['values']);

        $query = implode(' ', array($str, $table, $vals));

        return $query;
    }

    protected function getOrderBy(array $orderBy)
    {
        $column = $orderBy['column'];
        $order = $orderBy['order'];

        if (strpos($column, '.') !== false) {
            list(, $column) = \explode('.', $column);
        }

        $col = $this->getColumnAlias($column);

        $this->handle->orderBy($col, $order);
    }

    protected function getSelectJoins(array $joins)
    {
        foreach ($joins as $join) {

            $type = $join['type'];
            $table = $join['table'];
            if (strpos($table, ' ') !== false) {
                list($table, ) = explode(' ', $table);
            }

            $keys = array_keys($this->tablesAliases);
            $first = array_shift($keys);
            $fromAlias = $this->getTableAlias($first);
            $alias = $this->getTableAlias($table);
            $local = $join['local'];
            $foreign = $join['foreign'];

            if (\strpos($foreign, '.') === false) {
                $foreign = $alias .'.'. $foreign;
            }

            if (\strpos($local, '.') === false) {
                $local = $fromAlias .'.'. $local;
            }

            $cond = sprintf('%s = %s', $local, $foreign);
            if ($type == Query::JOIN_LEFT) {
                $this->handle->leftJoin($fromAlias, $table, $alias, $cond);
            } else {
                $this->handle->join($fromAlias, $table, $alias, $cond);
            }
        }
    }

    protected function getInsertValues(array $values)
    {
        $cols = array_keys($values);
        $vals = array_values($values);
        $str = '(`'. implode('`, `', $cols) .'`) VALUES (';
        $final = array();
        foreach ($vals as $value) {
            $value = $this->getCleanInsertValue($value);
            array_push($final, $value);
        }

        return $str . implode(', ', $final) .')';
    }

    protected function getCleanInsertValue($value)
    {
        $value = trim($value);
        if ($value === '?' || strpos($value, ':') === 0) {
            return $value;
        }

        /*
        if($value instanceof Expression)

            return (string) $value;
        */

        if ($value === null) {
            return 'NULL';
        }

        return $this->connection->getDriver()->quote((string) $value);
    }

    /**
     *
     * @param Array $values
     *
     * @return string
     */
    protected function getUpdateSet(array $values)
    {
        foreach ($values as $key => $value) {
            $this->handle->set($key, $value);
        }
    }

    /**
     *
     * @param mixed $columns
     *
     * @param mixed $tables
     *
     * @return void
     */
    protected function getSelectColumns($columns, $tables, Query $query, $joins = null)
    {
        if ($query->getFetchMode() != Query::FETCH_SPECIAL) {
            $this->handle->select((empty($query['select']) ? '*' : $query['select']));
            return;
        }

        if (!$columns || $columns == '*') {
            $columns = $this->columnsAliases = $this->getSelectColumnsFromTables($tables, $joins);
        } elseif (is_string($columns)) {
            $columns = $this->columnsAliases = $this->getSelectColumnsFromString($columns, $tables);
        }

        $final = array();
        foreach ($columns as $alias => $column) {
            if (!$column['function']) {
                array_push($final, $this->getTableAlias($column['table']) .'.'. trim($column['column']) .' AS '. $alias);
            } else {
                // it's a function
                array_push($final, $column['column'] .' AS '. $alias);
            }
        }

        $this->handle->select(\implode(', ', $final));
    }

    /**
     *
     * @return array
     */
    public function getColumnsAliases()
    {
        return (is_array($this->columnsAliases) ? $this->columnsAliases : array());
    }

    /**
     * @param string $colName
     *
     * @return int|string
     */
    public function getColumnAlias($colName)
    {
        $columns = $this->getColumnsAliases();
        foreach ($columns as $alias => $column) {
            if ($column['column'] == $colName) {
                return $alias;
            }
        }

        return $colName;
    }

    /**
     *
     * @param mixed $tables
     * @param array $joins
     *
     * @return void
     */
    protected function getSelectFrom($tables, $joins = null)
    {
        if (is_string($tables) && \strpos($tables, ',') !== false) {
            $tables = \explode(',', $tables);
        }

        if (!is_array($tables)) {
            $tables = array($tables);
        }

        $joinned = array();
        if (is_array($joins)) {
            $js = $joins;
            foreach ($joins as $k => $join) {
                array_push($tables, $join['table']);

                if (strpos($join['table'], ' ') !== false) {
                    list($tble,$alias) = explode(' ', $join['table']);
                    $join['table'] = $tble;
                } else {
                    $tble = $join['table'];
                    $alias = 'j'. (is_array($this->tablesAliases) ? count($this->tablesAliases) : '0');
                }

                $js[$k]['table'] = $tble;
                $js[$k]['alias'] = $alias;

                array_push($joinned, $join['table']);
            }
        }

        $tbls = array();
        foreach ($tables as $table) {
            if ($table instanceof Table) {
                $table = $table->getName();
            }

            $table = trim($table);
            if (\strpos($table, ' ') !== false) {
                list($table, $alias) = \explode(' ', $table);
            } else {
                if (\is_array($this->tablesAliases)) {
                    $alias = array_search($table, $this->tablesAliases);
                    if (!$alias) {
                        $alias = 't'. count($this->tablesAliases);
                    }
                } else {
                    $alias = 't0';
                }
            }

            $this->tablesAliases[trim($alias)] = trim($table);

            if (!in_array($table, $joinned)) {
                \array_push($tbls, array('table' => $table, 'alias' => $alias));
            }
        }

        foreach ($tbls as $infos) {
            $this->handle->from($infos['table'], $infos['alias']);
        }
    }


    /**
     *
     * @return string
     */
    protected function getTableAlias($tableName)
    {
        if (!is_array($this->tablesAliases)) {
           return $tableName;
        }

        $k = \array_search($tableName, $this->tablesAliases);

        return (false === $k ? $tableName : $k);
    }


    /**
     *
     * @param mixed $tables
     *
     * @return array
     */
    protected function getSelectColumnsFromTables($tables, $joins = null)
    {
        srand();

        if (is_string($tables) && \strpos($tables, ',') !== false) {
            $tables = \explode(',', $tables);
        }

        if (!is_array($tables)) {
            $tables = array($tables);
        }

        if (is_array($joins) && count($joins)) {
            foreach ($joins as $join) {
                array_push($tables, $join['table']);
            }
        }

        $columns = array();
        foreach ($tables as $table) {
            $table = trim($table);

            if (is_string($table) && \strpos($table, ' ') !== false) {
                list($table, ) = \explode(' ', $table);
            }

            $cols = $this->connection->table($table)->getColumns();
            foreach ($cols as $column) {
                $colName = $column->getName();
                $asName = 'c'. \rand(1000, 9999);
                if (isset($columns[$asName])) {
                    srand();
                    $asName = 'c'. \rand(1000, 9999);
                }

                $columns[$asName] = array(
                    'table'        => $table,
                    'column'       => $colName,
                    'function'     => false,
                    'alias'        => false
                );
            }
        }

        return $columns;
    }

    /**
     *
     * @param string $str
     *
     * @return array
     */
    protected function getSelectColumnsFromString($str, $tables)
    {
        $str = trim($str);
        $current = null;
        $funcLevel = 0;
        $currentIsFunc = false;
        $columns = array();
        for ($x = 0; $x < strlen($str); $x++) {
            $letter = $str{$x};
            $current .= $letter;

            if ($current == '*') {
                $wasStar = true;
                continue;
            }

            if ($letter == '(') {
                $funcLevel++;
            } elseif ($letter == ')') {
                $funcLevel--;
                $currentIsFunc = true;
            }

            $tbls = \array_values ($this->tablesAliases);
            $defaultTable = \array_shift ($tbls);

            if (($letter == ',' || $x == strlen($str) -1) && $funcLevel == 0) {
                $column = ($letter == ',' ? substr($current, 0, strlen($current)-1) : $current);

                if (!$currentIsFunc) {
                    if (\strpos($column, '.') !== false) {
                        list($table, $column) = \explode ('.', $column);
                        if (isset($this->tablesAliases[trim($table)])) {
                                $table = $this->tablesAliases[trim($table)];
                        }
                        if ($column == '*') {
                            $columns = array_merge($columns, $this->getSelectColumnsFromTables($table));
                            $x++;
                            $current = null;
                            continue;
                        }
                    } else {
                        $table = $defaultTable;
                    }

                    if (\stripos($column, 'AS') !== false) {
                        list($column, $asName) = explode((\strpos($column, 'as') !== false ? ' as ' : ' AS '), $column);
                    } else {
                        $asName = $column . '__'. \rand(1000, 9999);
                    }

                    $columns[$asName] = array(
                        'table'        => trim($table),
                        'column'       => trim($column),
                        'function'     => false,
                        'alias'        => true
                    );
                } else {
                    if (\stripos($column, 'AS') !== false
                        && \preg_match_all('/\) AS ([A-Za-z0-9_]+)/i', $column, $matchesarray)
                    ) {
                        $asName = $matchesarray[1][0];
                        $column = \substr($column, 0, strlen($column) - strlen($asName) - 4);
                    } else {
                        $asName = 'func__'. \rand(1000, 9999);
                    }

                    $columns[$asName] = array(
                        'table'        => $defaultTable,
                        'column'       => trim($column),
                        'function'     => true,
                        'alias'        => true
                    );
                }

                $current = null;
                $currentIsFunc = false;
            }
        }

        if (isset($wasStar)) {
            $columns = array_merge($columns, $this->getSelectColumnsFromTables($tables));
        }

        return $columns;
    }

    /**
     *
     * @return string
     */
    protected function getWhere(Query $query)
    {
        $where = $query['where'];
        $wheres = $query['wheres'];

        $this->handle->where($where);

        if(!is_array($wheres) OR !count($wheres))

            return;

        foreach ($wheres as $w) {
            if ($w['type'] == Query::WHERE_OR) {
                $this->handle->orWhere($w['condition']);
            } else {
                $this->handle->andWhere($w['condition']);
            }
        }
    }

    /**
     *
     * @param mixed $limit
     *
     * @return string
     */
    protected function getLimit(array $limit)
    {
        if ($limit['first'] !== null) {
            $this->handle->setFirstResult($limit['first']);
        }

        $this->handle->setMaxResults($limit['max']);
    }

    /**
     *
     * @param mixed $groupBy
     *
     * @return string
     */
    protected function getGroupBy($groupBy)
    {
        $this->handle->groupBy($groupBy);
    }

    /**
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }
}
