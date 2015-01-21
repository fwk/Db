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
 * @category  Database
 * @package   Fwk\Db
 * @author    Julien Ballestracci <julien@nitronet.org>
 * @copyright 2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      http://www.phpfwk.com
 */
namespace Fwk\Db;

use Fwk\Db\Registry\RegistryState;
use Fwk\Db\Relations\AbstractManyRelation;
use Fwk\Db\Relations\One2Many;
use Fwk\Db\Relations\One2One;

/**
 * This class transforms a resultset from a query into a set of corresponding
 * entities.
 *
 * @category Utilities
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.phpfwk.com
 */
class Hydrator
{
    /**
     * The query
     *
     * @var Query
     */
    protected $query;

    /**
     * The Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Columns description
     *
     * @var array
     */
    protected $columns;

    /**
     * Parsing stack informations
     *
     * @var array
     */
    protected $stack;

    /**
     * Entities that need to be marked as fresh in registry
     *
     * @var array
     */
    protected $markAsFresh;

    /**
     * Constructor
     *
     * @param Query      $query      Executed query
     * @param Connection $connection Database Connection
     * @param array      $columns    Columns description
     *
     * @return void
     */
    public function __construct(Query $query, Connection $connection, array $columns)
    {
        $this->query        = $query;
        $this->columns      = $columns;
        $this->connection   = $connection;

        $joins      = (array) $query['joins'];
        $tables     = array();
        $itx        = 0;

        foreach ($columns as $column => $infos) {
            $itx++;
            $table          = $infos['table'];

            if (isset($tables[$table])) {
                $tables[$table]['columns'][$column] =  $infos['column'];
                continue;
            }

            $skipped    = false;
            $jointure   = false;
            $joinOpt    = false;

            foreach ($joins as $join) {
                $jointure = false;
                $skipped  = false;

                if (\strpos($join['table'], ' ') !== false) {
                    list($joinTable, $alias) = explode(' ', $join['table']);
                } else {
                    $joinTable  = $join['table'];
                    $alias      = null;
                }

                if ($joinTable == $table) {
                    if ($join['options']['skipped'] == true) {
                        $skipped = true;
                    }

                    $jointure  = true;
                    $joinOpt   = $join;
                    break;
                }
            }

            if ($skipped && $jointure) {
                continue;
            }

            $tables[$table]['columns']  = array();
            if ($jointure) {
                $tables[$table]['join'] = $joinOpt;
                $tables[$table]['entity'] = $joinOpt['options']['entity'];
                $tables[$table]['entityListeners']
                    = $joinOpt['options']['entityListeners'];
            } else {
                $tables[$table]['entity']
                    = ($itx == 1 ? $query['entity'] : $this->connection->table($table)->getDefaultEntity());
                $tables[$table]['entityListeners']
                    = (count($query['entityListeners'])  ?
                    $query['entityListeners'] :
                    $this->connection->table($table)->getDefaultEntityListeners()
                );
            }
            $tables[$table]['columns'][$column] =  $infos['column'];
        }

        $this->stack = $tables;
    }

    /**
     * Transform raw results from database into ORM-style entities
     *
     * @param array $results Raw PDO results
     *
     * @return array
     */
    public function hydrate(array $results)
    {
        $final      = array();
        $joinData   = array();

        foreach ($results as $result) {
            $mainObj        = null;
            $mainObjRefs    = null;
            $mainObjTable   = null;

            foreach ($this->stack as $tableName => $infos) {
                $columns    = $infos['columns'];
                $isJoin     = (isset($infos['join']) ? true : false);
                $entityClass= $infos['entity'];
                $ids        = $this->getIdentifiers($tableName, $result);
                $obj        = $this->loadEntityClass(
                    $tableName,
                    $ids,
                    $entityClass,
                    $infos['entityListeners']
                );
                $access     = new Accessor($obj);
                $values     = $this->getValuesFromSet($columns, $result);
                $access->setValues($values);
                $mainObjRefs = $ids;

                foreach ($access->getRelations() as $relation) {
                    $relation->setConnection($this->connection);
                    $relation->setParentRefs($access->get($relation->getLocal()));
                }

                if (!$isJoin) {
                    $mainObj        = $obj;
                    $mainObjTable   = $tableName;
                    unset($access, $values, $isJoin, $columns);
                    if (!in_array($mainObj, $final, true)) {
                        array_push($final, $mainObj);
                    }
                    continue;
                }

                $idsHash    = $tableName . implode(':', $ids);
                $access     = new Accessor($mainObj);
                $columnName = $infos['join']['options']['column'];

                $current = (isset($joinData[$idsHash . $columnName]) ?
                    $joinData[$idsHash . $columnName] :
                    $this->getRelationObject(
                        $mainObj,
                        $columnName,
                        $infos['join'],
                        $entityClass
                    )
                );

                $joinData[$idsHash . $columnName] = $current;
                $current->add($obj, $ids);
                $current->setFetched(true);
                $current->setParentRefs($mainObjRefs);

                $tableObj   = $this->connection->table($mainObjTable);
                $current->setParent(
                    $mainObj,
                    $tableObj->getRegistry()->getEventDispatcher($mainObj)
                );

                $access->set($columnName, $current);

                unset($access, $values, $isJoin, $columns, $current);
            }

            $access = new Accessor($mainObj);
            $relations  = $access->getRelations();
            foreach ($relations as $relation) {
                $tableObj   = $this->connection->table($mainObjTable);
                $relation->setParent(
                    $mainObj,
                    $tableObj->getRegistry()->getEventDispatcher($mainObj)
                );
            }

            unset($mainObj, $mainObjRefs);
        }

        $this->markAsFresh();

        return $final;
    }

    /**
     * Return Relation object of an entity
     *
     * @param mixed  $object      The entity
     * @param string $columnName  Relation's column name
     * @param array  $join        Join descriptor (array)
     * @param string $entityClass Relation's entity class name
     *
     * @return Relation
     */
    public function getRelationObject($object, $columnName, array $join,
                                      $entityClass = null
    ) {
        $access = new Accessor($object);
        $test   = $access->get($columnName);

        if (strpos($join['table'], ' ') !== false) {
            list($table, ) = explode(' ', $join['table']);
        } else {
            $table = $join['table'];
        }

        if ($test instanceof \Fwk\Db\Relation && $entityClass == $test->getEntity()) {
            if ($test instanceof One2One) {
                return $test;
            } elseif ($test instanceof AbstractManyRelation && $join['options']['reference'] == $test->getReference()) {
                return $test;
            }
        }

        if (strpos($join['table'], ' ') !== false) {
            list($table, ) = explode(' ', $join['table']);
        } else {
            $table = $join['table'];
        }

        $ref    = new One2Many(
            $join['local'],
            $join['foreign'],
            $table,
            $entityClass,
            $join['options']['entityListeners']
        );

        if (!empty($join['options']['reference'])) {
            $ref->setReference($join['options']['reference']);
        }

        // $ref->setRegistry($this->connection->table($table)->getRegistry());
        $ref->setConnection($this->connection);

        return $ref;
    }

    /**
     * Transforms aliased results into results with real columns names
     *
     * @param array $columns   Columns description
     * @param array $resultSet Result set
     *
     * @return array
     */
    public function getValuesFromSet(array $columns, array $resultSet)
    {
        $final = array();
        foreach ($columns as $alias => $realName) {
            $final[$realName] = $resultSet[$alias];
        }

        return $final;
    }

    /**
     * Loads an entity
     *
     * @param string $tableName   Table's name
     * @param array  $identifiers Entity identifiers
     * @param string $entityClass Entity class name
     * @param array  $listeners   Entity's listeners
     *
     * @return mixed
     */
    protected function loadEntityClass($tableName, array $identifiers,
                                       $entityClass = null, array $listeners = array()
    ) {
        $tableObj = $this->connection->table($tableName);
        $registry = $tableObj->getRegistry();

        if ($entityClass === null) {
            $entityClass = $tableObj->getDefaultEntity();
        }

        $obj = $registry->get($identifiers, $entityClass);

        if (null === $obj) {
            $obj = new $entityClass;
            $registry->store(
                $obj, $identifiers, RegistryState::FRESH, array(
                    'listeners' => $listeners
                )
            );
            $this->markAsFresh[] = array(
                'registry' => $registry,
                'entity' => $obj,
                'table' => $tableObj
            );
        }

        return $obj;
    }

    /**
     * Mark freshly built entities as fresh (aka "just fetched")
     *
     * @return void
     */
    protected function markAsFresh()
    {
        if (!is_array($this->markAsFresh) || !count($this->markAsFresh)) {
            return;
        }

        foreach ($this->markAsFresh as $infos) {
            $infos['registry']->defineInitialValues(
                $infos['entity'],
                $this->connection,
                $infos['table']
            );
        }

        unset($this->markAsFresh);
    }

    /**
     * Returns an array of identifiers for the given table
     *
     * @param string $tableName Table's name
     * @param array  $results   Raw PDO results
     *
     * @return array
     */
    protected function getIdentifiers($tableName, array $results)
    {
        $tableObj   = $this->connection->table($tableName);
        $tableIds   = $tableObj->getIdentifiersKeys();

        if (!count($tableIds)) {
            return array();
        }

        $final = array();
        foreach ((array) $this->columns as $colName  => $infos) {
            if ($infos['table'] == $tableName
                && in_array($infos['column'], $tableIds)
            ) {
                $final[$infos['column']] = $results[$colName];
            }
        }

        return $final;
    }
}