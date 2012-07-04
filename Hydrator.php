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

use Fwk\Db\Query,
    Fwk\Db\Connection, 
    Fwk\Db\Accessor, 
    Fwk\Db\Registry,
    Fwk\Db\Relations\One2Many;


/**
 * This class transforms a resultset from a query into a set of corresponding
 * entities.
 *
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
     *
     * @param Query $query
     * @param Connection $connection
     * @param array $columns 
     */
    public function __construct(Query $query, Connection $connection, array $columns)
    {
        $this->query        = $query;
        $this->columns      = $columns;
        $this->connection   = $connection;

        $joins      = (array) $query['joins'];
        $tables     = array();
        $it         = 0;

        foreach ($columns as $column => $infos) {
            $it++;
            $table          = $infos['table'];

            if (!isset($tables[$table])) {
                $skipped    = false;
                $jointure   = false;
                $joinOpt       = false;

                foreach ($joins as $join) {
                    $jointure = false;
                    $skipped  = false;

                    if(\strpos($join['table'], ' ') !== false)
                            list($joinTable, $alias) = explode(' ', $join['table']);

                    else {
                        $joinTable  = $join['table'];
                        $alias      = null;
                    }

                    if ($joinTable == $table) {
                        if($join['options']['skipped'] == true)
                            $skipped = true;

                        $jointure  = true;
                        $joinOpt   = $join;
                        break;
                    }
                }

                if ($skipped && $jointure) {
                    continue;
                }

                $tables[$table]['columns']  = array();
                if($jointure)
                    $tables[$table]['join'] = $joinOpt;

                $tables[$table]['entity']   = ($jointure ? $joinOpt['options']['entity'] : ($it == 1 ? $query['entity'] : "\stdClass"));
            }

            $realColumnName = $infos['column'];
            $function       = (bool) $infos['function'];
            $alias          = $infos['alias'];

            $tables[$table]['columns'][$column] =  $realColumnName;
        }

        $this->stack    = $tables;
    }

    /**
     *
     * @param  array $results
     * @return array
     */
    public function hydrate(array $results)
    {
        $final      = array();
        $joinData   = array();
        $mainObj    = null;

        foreach ($results as $result) {
            $mainObj        = null;
            $mainObjRefs    = null;
            $mainObjTable   = null;

            foreach ($this->stack as $tableName => $infos) {
                $columns    = $infos['columns'];
                $isJoin     = (isset($infos['join']) ? true : false);
                $entityClass= $infos['entity'];
                $ids        = $this->getIdentifiers($tableName, $result);
                $obj        = $this->loadEntityClass($tableName, $ids, $entityClass);
                $access     = new Accessor($obj);
                $values     = $this->getValuesFromSet($columns, $result);
                $access->setValues($values);
                $mainObjRefs    = $ids;
                
                foreach ($access->getRelations() as $columnName => $relation) {
                    $relation->setConnection($this->connection);
                    $relation->setParentRefs($access->get($relation->getLocal()));
                }
                
                if (!$isJoin) {
                    $mainObj        = $obj;
                    $mainObjTable   = $tableName;
                    $idsHash        = $tableName . implode(':', $ids);
                    unset($access, $values, $isJoin, $columns);
                    if (!in_array($mainObj, $final, true)) {
                        array_push($final, $mainObj);
                    }
                    continue;
                }

                $access     = new Accessor($mainObj);
                $columnName = $infos['join']['options']['column'];
                $reference  = $infos['join']['options']['reference'];

                $current = (isset($joinData[$idsHash . $columnName]) ?
                        $joinData[$idsHash . $columnName] :
                        $this->getRelationObject($mainObj, $columnName, $infos['join'], $entityClass)
                );

                $joinData[$idsHash . $columnName] = $current;
                $current->add($obj, $ids);
                $current->setFetched(true);
                $current->setParentRefs($mainObjRefs);

                $tableObj   = $this->connection->table($mainObjTable);
                $current->setParent($mainObj, $tableObj->getRegistry()->getEventDispatcher($mainObj));

                $access->set($columnName, $current);

                unset($access, $values, $isJoin, $columns, $current);
            }

            
            $access = new Accessor($mainObj);
            $relations  = $access->getRelations();
            foreach ($relations as $columnName => $relation) {
                $tableObj   = $this->connection->table($mainObjTable);
                $relation->setParent($mainObj, $tableObj->getRegistry()->getEventDispatcher($mainObj));
            }
 
            unset($mainObj, $mainObjRefs);
        }

        $this->markAsFresh();

        return $final;
    }

    /**
     *
     * @param  mixed                   $object
     * @param  string                  $columnName
     * @param  array                   $join
     * @param  string                  $entityClass
     * @return \Fwk\Db\Relation
     */
    public function getRelationObject($object, $columnName, array $join, $entityClass = '\stdClass')
    {
        $access     = new Accessor($object);
        $test       = $access->get($columnName);

        if($test instanceof \Fwk\Db\Relation)

            return $test;

        $ref    = new One2Many($join['local'], $join['foreign'], $join['table'], $entityClass);
        if (!empty($join['options']['reference'])) {
            $current->setReference($join['options']['reference']);
        }

        return $ref;
    }

    public function getValuesFromSet(array $columns, array $resultSet)
    {
        $final = array();
        foreach ($columns as $alias => $realName) {
            $final[$realName]   = $resultSet[$alias];
        }

        return $final;
    }

    /**
     * Loads an entityClass
     *
     * @param  string $entityClass
     * @return mixed
     */
    protected function loadEntityClass($tableName, array $identifiers, $entityClass = null)
    {
        $tableObj   = $this->connection->table($tableName);
        $registry   = $tableObj->getRegistry();

        if($entityClass === null)
                $entityClass    = $tableObj->getDefaultEntity();

        $obj        = $registry->get($identifiers);

        if (null === $obj) {
            $obj      = new $entityClass;
            $registry->store($obj, $identifiers, Registry::STATE_FRESH);
            $this->markAsFresh[]    = array('registry' => $registry, 'entity' => $obj);
        }

        return $obj;
    }

    protected function markAsFresh()
    {
        if(!\is_array($this->markAsFresh) || !count($this->markAsFresh))

                return;

        foreach ($this->markAsFresh as $infos) {
            $infos['registry']->defineInitialValues($infos['entity']);
        }

        unset($this->markAsFresh);
    }

    /**
     * Returns an array of identifiers for the given table
     *
     * @param  string $tableName
     * @param  array  $results
     * @return array
     */
    protected function getIdentifiers($tableName, array $results)
    {
        $tableObj   = $this->connection->table($tableName);
        $tableIds   = $tableObj->getIdentifiersKeys();

        if (!count($tableIds))

            return array();

        $final = array();
        foreach ($tableIds as $identifier) {
            foreach ((array) $this->columns as $colName  => $infos) {
                if ($infos['table'] == $tableName && in_array($infos['column'], $tableIds)) {
                    $final[$infos['column']]   = $results[$colName];
                }
            }
        }

        return $final;
    }
}
