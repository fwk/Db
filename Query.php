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

/**
 * Represents an Object-Oriented SQL-like query interface.
 *
 */
class Query extends \ArrayObject
{
    const TYPE_SELECT   = 'select';
    const TYPE_DELETE   = 'delete';
    const TYPE_INSERT   = 'insert';
    const TYPE_UPDATE   = 'update';

    const JOIN_INNER    = 'inner';
    const JOIN_LEFT     = 'left';

    const WHERE_AND     = 'and';
    const WHERE_OR      = 'or';

    const FETCH_SPECIAL = 0;
    const FETCH_OPT     = 1;

    protected $fetchMode = self::FETCH_SPECIAL;

    /**
     *
     * @var integer
     */
    protected $type;

    protected $options;

    /**
     *
     * @param array $options
     *
     * @return void
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->setFlags(self::ARRAY_AS_PROPS);
    }

    /**
     *
     * @param mixed $columns
     *
     * @return Query
     */
    public function select($columns = null)
    {
        $this->type = self::TYPE_SELECT;
        $this['select'] = $columns;

        return $this;
    }

    public function from($table, $alias = null)
    {
        if (\strpos($table, ' ') !== false) {
            list($table, $alias) = explode(' ', $table);
        }

        $this['from']   = trim($table) . ($alias !== null ? ' '. trim($alias) : null);

        return $this;
    }

    public function setFetchMode($mode)
    {
        $this->fetchMode     = $mode;

        return $this;
    }

    public function getFetchMode()
    {

        return $this->fetchMode;
    }

    /**
     *
     * @param mixed $table
     *
     * @return Query
     */
    public function delete($table)
    {
        $this->type         = self::TYPE_DELETE;
        $this['delete']     = (string) $table;

        return $this;
    }

    /**
     *
     * @param mixed $table
     *
     * @return Query
     */
    public function insert($table)
    {
        $this->type         = self::TYPE_INSERT;
        $this['insert']     = (string) $table;

        return $this;
    }

    /**
     *
     * @param mixed $table
     *
     * @return Query
     */
    public function update($table)
    {
        $this->type         = self::TYPE_UPDATE;
        $this['update']     = (string) $table;

        return $this;
    }

    /**
     *
     * @param  string $condition
     * @return Query
     */
    public function where($condition)
    {
        $this['where']     = $condition;

        return $this;
    }

    /**
     *
     * @param string $condition
     *
     * @return Query
     */
    public function andWhere($condition)
    {
        if(!is_array($this['wheres']))
                $this['wheres']     = array();

        $arr    = $this['wheres'];
        array_push($arr, array('condition' => $condition, 'type' => self::WHERE_AND));
        $this['wheres'] = $arr;

        return $this;
    }

    /**
     *
     * @param string $condition
     *
     * @return Query
     */
    public function orWhere($condition)
    {
        if(!is_array($this['wheres']))
                $this['wheres']     = array();

        $arr    = $this['wheres'];
        array_push($arr, array('condition' => $condition, 'type' => self::WHERE_OR));
        $this['wheres'] = $arr;

        return $this;
    }

    /**
     *
     * @param mixed $limit
     *
     * @return Query
     */
    public function limit($max, $offset = null)
    {
        $this['limit'] = array('first' => $offset, 'max' => $max);

        return $this;
    }

    /**
     *
     * @param string $group
     *
     * @return Query
     */
    public function groupBy($group)
    {
        $this['groupBy']   = $group;

        return $this;
    }

    public function orderBy($column, $order = null)
    {
        $this['orderBy']    = array('column' => $column, 'order' => $order);

        return $this;
    }

    /**
     *
     * @return Query
     */
    public static function factory()
    {
        return new Query();
    }

    public function set($key, $value)
    {
        $vals           = $this['values'];
        $vals[$key]     = $value;
        $this['values'] = $vals;

        return $this;
    }

    public function values(array $values)
    {
        $vals           = $this['values'];
        $this['values'] = array_merge((is_array($vals) ? $vals : array()), $values);

        return $this;
    }

    public function join($table, $localColumn, $foreignColumn = null, $type = Query::JOIN_LEFT, $options = array())
    {
        if (\strpos($table, ' ') !== false) {
            list($columnName, ) = \explode(' ', $table);
        } else {
            $columnName = $table;
        }

        $opts = array_merge(array(
            'column'    => $columnName,
            'relation'  => false,
            'skipped'   => false,
            'reference' => null,
            'entity'    => '\stdClass',
            'entityListeners' => array()
        ), $options);

        $join = array(
            'table'     => $table,
            'local'     => $localColumn,
            'foreign'   => (is_null($foreignColumn) ? $localColumn : $foreignColumn),
            'type'      => $type,
            'options'   => $opts
        );

        $joins = $this['joins'];
        if(!is_array($joins))
            $joins = array();

        array_push($joins, $join);
        $this['joins'] = $joins;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     *
     *
     * @param  string $entityClass
     * @return Query
     */
    public function entity($entityClass, array $listeners = array())
    {
        $this['entity']             = $entityClass;
        $this['entityListeners']    = $listeners;

        return $this;
    }

    /**
     * Prevent 'undefined index' errors
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        if(!$this->offsetExists($key)) {
            return null;
        }

        return parent::offsetGet($key);
    }
}
