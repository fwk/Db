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
 * @subpackage Mysql
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Mysql;

use Fwk\Db\Driver,
    Fwk\Db\Query;

/**
 * This class transform a Fwk\Db\Query object into a SQL query string
 *
 */
class QueryMaker
{
    /**
     * The driver
     *
     * @var \Fwk\Db\Drivers\Driver
     */
    protected $driver;

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
    
    public function __construct(Driver $driver) {
        $this->driver       = $driver;
    }


    public function execute(Query $query, array $params = array(), 
        array $options = array())
    {

        $sql    = $this->getQueryString($query);
        
        if($query->getType() == Query::TYPE_INSERT) {
            $query = $this->driver->rawQuery($sql);
            return $query;
        }

        $stmt = $this->driver->getHandle()->prepare($sql, $params);
        $x = $stmt->execute($params);
        
        if($query->getType() == Query::TYPE_SELECT) {
            $mode = \PDO::FETCH_ASSOC;

            if($query->getFetchMode() == Query::FETCH_OPT)
                $mode = $query['fetchMode'];

            $res = $stmt->fetchAll($mode);
            return $res;
        }

        return $x;
    }

    /**
     * Returns the SQL representation of the query
     *
     * @param Query $query
     * @return string
     */
    public function getQueryString(Query $query) {
        
        return $this->prepare($query);
    }

    /**
     *
     * @param Query $query
     *
     * @return string
     */
    public function prepare(Query $query) {
        $type = $query->getType();
        switch ($type) {
            case Query::TYPE_DELETE:
                $parts = array(
                    'delete' => true
                );
                $call = array($this, 'deleteQuery');
                break;

            case Query::TYPE_INSERT:
                $parts = array(
                    'insert' => true,
                    'values' => true
                );
                $call = array($this, 'insertQuery');
                break;

            case Query::TYPE_SELECT:
                $parts = array(
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
                throw new \RuntimeException(sprintf('QueryMaker: unknown query type "%s"', (string) $type));
        }

        foreach ($parts as $part => $required) {
            if ($required == true && !$query->offsetExists($part))
                throw new \RuntimeException (sprintf('QueryMaker: missing required part "%s"', $part));
        }

        $table = $query['from'];
        $schema = $this->driver->getConnection()->getSchema();
        
        if(!empty($table)) {
            if(strpos($table, ' '))
                    list($table, ) = explode(' ', $table);
            
            $decl   = $schema->getDeclaredEntity($table);
            $query->entity($decl);
        }
        
        if(!empty($query['entity']) && $query['entity'] != "\stdClass") {
            $load   = \Fwk\Loader::getInstance()->load($query['entity']);
            if(!$load)
                throw new \RuntimeException (sprintf('Unable to load entity class "%s"', $query['entity']));
            
            $obj        = new $query['entity'];
            $access     = new \Fwk\Db\Entity\Accessor($obj);
            $relations  = $access->getRelations();
            
            foreach($relations as $colName => $relation) {
                $tblName = $relation->getTableName();
                $ent     = $relation->getEntityClass();
                if($ent == null || $ent == "\stdClass") {
                    $relation->setEntityClass($schema->getDeclaredEntity($tblName));
                }
                $relation->prepare($query, $colName);
            }
        }
        return trim(\call_user_func($call, $query));
    }

    /**
     *
     * @return string
     */
    public function selectQuery(Query $query) {
        $str = "SELECT";

        $queryJoins = $query['joins'];

        $from = $this->getSelectFrom($query['from'], $queryJoins);
        $columns = $this->getSelectColumns($query['select'], $query['from'], $query, $queryJoins);
        $joins = (isset($query['joins']) ? $this->getSelectJoins($queryJoins) : null);
        $where = (isset($query['where']) ? $this->getWhere($query) : null);
        $groupBy = (isset($query['groupBy']) ? $this->getGroupBy($query['groupBy']) : null);
        $orderBy = (isset($query['orderBy']) ? $this->getOrderBy($query['orderBy']) : null);
        $limit = (isset($query['limit']) ? $this->getLimit($query['limit']) : null);
        $query = implode(' ', array($str, $columns, $from, $joins, $where, $orderBy, $groupBy, $limit));

        // echo $query . '<br />';

        return $query;
    }

    /**
     *
     * @return string
     */
    public function deleteQuery(Query $query) {
        $str = "DELETE";

        $from = $this->getFrom($query['delete'], $query->getType());
        $where = (isset($query['where']) ? $this->getWhere($query) : null);
        $limit = (isset($query['limit']) ? $this->getLimit($query['limit']) : null);
        
        if($where === null && $limit === null) {
            $table = $query['delete'];
            if($table instanceof \Fwk\Db\Table)
                $table = $table->getName();
            
            return sprintf('TRUNCATE TABLE `%s`', $table);
        }
        
        return implode(' ', array($str, $from, $where, $limit));
    }


    /**
     *
     * @return string
     */
    public function updateQuery(Query $query) {
        $str = "UPDATE";

        $from = $this->getFrom($query['update'], $query->getType());
        $set = $this->getUpdateSet($query['values']);
        $where = (isset($query['where']) ? $this->getWhere($query) : null);
        $limit = (isset($query['limit']) ? $this->getLimit($query['limit']) : null);

        $query = implode(' ', array($str, $from, $set, $where, $limit));

        return $query;
    }

    /**
     *
     * @return string
     */
    public function insertQuery(Query $query) {
        $str = "INSERT INTO";

        $from = $this->getFrom($query['insert']);
        $vals = $this->getInsertValues($query['values']);

        $query = implode(' ', array($str, $from, $vals));

        //echo $query;

        return $query;
    }


    protected function getOrderBy(array $orderBy) {
        $column = $orderBy['column'];
        $order = $orderBy['order'];

        if(strpos($column, '.') !== false) {
            list($table, $column) = \explode('.', $column);
        }

        $col = $this->getColumnAlias($column);
        return sprintf("ORDER BY %s %s",  $col, ($order == true ? 'ASC' : 'DESC'));
    }

    protected function getSelectJoins(array $joins) {

        $str = null;

        $tbls = \array_values ($this->tablesAliases);
        $defaultTable = \array_shift ($tbls);

        foreach($joins as $join) {

            $type = $join['type'];
            if($type == Query::JOIN_LEFT)
                $str .= 'LEFT JOIN ';

            elseif($type == Query::JOIN_INNER)
                $str .= 'INNER JOIN ';

            elseif($type == Query::JOIN_OUTTER)
                $str .= 'OUTTER JOIN ';

            else
                throw new  \Exception(sprintf('Unknown join type "%s"', $type));

            $local = $join['local'];
            $foreign = $join['foreign'];

            if(strpos($join['table'], ' ') !== false) {
                $tIdx = \explode(' ', $join['table']);
                $join['table'] = $tIdx[0];
            }

            if(\strpos($foreign, '.') === false) {
                $foreign = $this->getTableAlias($join['table']) .'.'. $foreign;
            }

            if(\strpos($local, '.') === false) {
                $local = $this->getTableAlias($defaultTable) .'.'. $local;
            }

            if(strpos($join['table'], ' ') !== false) {
                $tIdx = \explode(' ', $join['table']);
                $join['table'] = $tIdx[0];
            }

            $str .= sprintf('%s %s ON %s = %s ',
                                $join['table'],
                                $this->getTableAlias($join['table']),
                                $local,
                                $foreign
                    );
        }

        return trim($str);

    }

    protected function getInsertValues(array $values) {
        $cols = array_keys($values);
        $vals = array_values($values);
        $str = '(`'. implode('`, `', $cols) .'`) VALUES (';
        $final = array();
        foreach($vals as $value) {
            $value = $this->getCleanInsertValue($value);
            array_push($final, $value);
        }

        return $str . implode(', ', $final) .')';
    }

    protected function getCleanInsertValue($value) {
        if($value instanceof Expression)
            return (string)$value;

        if($value === null)
            return 'NULL';

        return $this->driver->escape((string)$value);
    }

    /**
     *
     * @param Array $values
     *
     * @return string
     */
    protected function getUpdateSet(array $values) {
        if(!count($values))
            return '';
        
        $str = "SET";
        $driver = $this->driver;
        $final = array();
        foreach($values as $key => $value) {
            $value = $this->getCleanInsertValue($value);
            array_push($final, "`$key` =$value");
        }

        return $str . ' ' . implode(', ', $final);
    }

    /**
     *
     * @param mixed $columns
     *
     * @param mixed $tables
     *
     * @return string
     */
    protected function getSelectColumns($columns, $tables, $query, $joins = null) {
        srand();

        if($query->getFetchMode() != Query::FETCH_SPECIAL) {
            return $query['select'];
        }
        
        if (!$columns || $columns == '*')
            $columns = $this->getSelectColumnsFromTables($tables, $joins);

        elseif (is_string($columns))
            $columns = $this->getSelectColumnsFromString($columns, $tables);

        $this->columnsAliases = $columns;

        $final = array();
        foreach($columns as $alias => $column) {
            if(!$column['function'])
                \array_push($final, $this->getTableAlias($column['table']) .'.'. trim($column['column']) .' AS '. $alias);

            else
                // it's a function
                \array_push($final, $column['column'] .' AS '. $alias);
        }

        return \implode(', ', $final);
    }


    /**
     *
     * @param mixed $table
     *
     * @return string
     */
    protected function getFrom($table, $type = Query::TYPE_SELECT) {
        if($table instanceof Table)
            $table = $table->getName();

        return ($type == Query::TYPE_DELETE ? 'FROM ' : '') . $table;
    }

    /**
     *
     * @return array
     */
    public function getColumnsAliases() {
        return (is_array($this->columnsAliases) ? $this->columnsAliases : array());
    }

    public function getColumnAlias($colName) {
        $columns = $this->getColumnsAliases();

        foreach($columns as $alias => $column) {
            if($column['column'] == $colName)
                return $alias;
        }

        return $colName;
    }

    /**
     *
     * @param mixed $tables
     *
     * @return string
     */
    protected function getSelectFrom($tables, $joins = null) {

        if (is_string($tables) && \strpos($tables, ',') !== false)
            $tables = \explode(',', $tables);

        if (!is_array($tables))
            $tables = array($tables);

        $joinned = array();
        if(is_array($joins) && count($joins)) {

            foreach($joins as $k => $join) {

                array_push($tables, $join['table']);

                if(strpos($join['table'], ' ') !== false) {
                    $tIdx = explode(' ', $join['table']);
                    $join['table'] = $tIdx[0];

                    $js = $joins;
                    $js[$k]['table'] = $tIdx[0];
                    $query['joins'] = $js;
                }

                array_push($joinned, $join['table']);
            }
        }

        $passed = array();
        $tbls = array();

        foreach ($tables as $table) {
            if($table instanceof Table)
                $table = $table->getName();

            $table = trim($table);
            if (\strpos($table, ' ') !== false)
                list($table, $alias) = \explode(' ', $table);

            else
                $alias = 't' . (\is_array($this->tablesAliases) ? count($this->tablesAliases) : '0');

            if(\in_array($table, $passed))
                    continue;

            $this->tablesAliases[trim($alias)] = trim($table);

            if(!in_array($table, $joinned))
                \array_push($tbls, \implode(' ', array($table, $alias)));
        }

       return 'FROM '. implode(', ', $tbls);
    }


    /**
     *
     * @return string
     */
    protected function getTableAlias($tableName) {
        if(!is_array($this->tablesAliases))
                return $tableName;

        $k = \array_search($tableName, $this->tablesAliases);
        return (false === $k ? $tableName : $k);
    }


    /**
     *
     * @param mixed $tables
     *
     * @return array
     */
    protected function getSelectColumnsFromTables($tables, $joins = null) {
        if (is_string($tables) && \strpos($tables, ',') !== false)
            $tables = \explode(',', $tables);

        if (!is_array($tables))
            $tables = array($tables);

        if(is_array($joins) && count($joins)) {
            foreach($joins as $join)
                array_push($tables, $join['table']);
        }

        foreach($tables as $table) {
             $table = trim($table);

             if (is_string($table) && \strpos($table, ' ') !== false)
                list($table, ) = \explode(' ', $table);

            
            $cols = $this->driver->getConnection()->table($table)->getColumns();
            foreach($cols as $column) {
                $colName = $column->getName();
                $asName = 'c'. \rand(1000, 9999);
                if(isset($columns[$asName])) {
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
            $asName = 'c'. \rand(1000, 9999);
        }

        return $columns;
    }

    /**
     *
     * @param string $str
     *
     * @return array
     */
    protected function getSelectColumnsFromString($str, $tables) {

        $str = trim($str);
        $current = null;
        $funcLevel = 0;
        $currentIsFunc = false;
        $columns = array();
        for($x = 0; $x < strlen($str); $x++) {

            $letter = $str{$x};
            $current .= $letter;

            if($current == '*') {
                $wasStar = true;
                continue;
            }

            if($letter == '(') {
                $funcLevel++;
            }

            elseif($letter == ')') {
                $funcLevel--;
                $currentIsFunc = true;
            }

             $tbls = \array_values ($this->tablesAliases);
            $defaultTable = \array_shift ($tbls);

            if(($letter == ',' || $x == strlen($str) -1) && $funcLevel == 0) {
                $column = ($letter == ',' ? substr($current, 0, strlen($current)-1) : $current);

                if(!$currentIsFunc) {
                    if(\strpos($column, '.') !== false) {

                        list($table, $column) = \explode ('.', $column);

                        if(isset($this->tablesAliases[trim($table)]))
                                $table = $this->tablesAliases[trim($table)];

                        if($column == '*') {
                            $columns = array_merge($columns, $this->getSelectColumnsFromTables($table));
                            $x++;
                            $current = null;
                            continue;
                        }

                    } else {
                        $table = $defaultTable;
                    }

                    if(\stripos($column, 'AS') !== false) {
                        list($column, $asName) = explode((\strpos($column, 'as') !== false ? ' as ' : ' AS '), $column);
                    } else
                        $asName = $column . '__'. \rand(1000, 9999);

                     $columns[$asName] = array(
                         'table'        => trim($table),
                         'column'       => trim($column),
                         'function'     => false,
                         'alias'        => true
                     );

                } else {

                    if(\stripos($column, 'AS') !== false) {
                        if(\preg_match_all('/\) AS ([A-Za-z0-9_]+)/i', $column, $matchesarray)) {
                                $asName = $matchesarray[1][0];
                                $column = \substr($column, 0, strlen($column) - strlen($asName) - 4);
                        }
                    } else
                        $asName = 'func__'. \rand(1000, 9999);

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

        if(isset($wasStar))
            $columns = array_merge($columns, $this->getSelectColumnsFromTables($tables));

        return $columns;
    }

    /**
     *
     * @return string
     */
    protected function getWhere(Query $query) {
        $where = $query['where'];
        $wheres = $query['wheres'];

        $str =  "WHERE $where";

        if(!is_array($wheres) OR !count($wheres))
            return $str;

        $whs = array();
        foreach($wheres as $w)  {
           \array_push($whs, $w['type'] .' '. $w['condition']);
        }

        return $str . ' ' .\implode(' ', $whs);
    }

    /**
     *
     * @param mixed $limit
     *
     * @return string
     */
    protected function getLimit($limit) {
        return "LIMIT $limit";
    }

    /**
     *
     * @param mixed $groupBy
     *
     * @return string
     */
    protected function getGroupBy($groupBy) {
        return "GROUP BY $groupBy";
    }
}