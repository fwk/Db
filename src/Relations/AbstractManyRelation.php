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
namespace Fwk\Db\Relations;

use Fwk\Db\Registry\Registry;
use Fwk\Db\Relation;
use Fwk\Db\Registry\RegistryState;
use Fwk\Db\Accessor;

/**
 * Abstract utility class for *Many Relations
 *
 * @category Relations
 * @package  Fwk\Db
 * @author   Julien Ballestracci <julien@nitronet.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://www.nitronet.org/fwk
 */
abstract class AbstractManyRelation extends AbstractRelation implements
    \ArrayAccess, \Countable
{
    /**
     * Name of the column to be used as the index for this relation's entities
     *
     * @var string
     */
    protected $reference;

    /**
     * Name of the column to use for sorting this relation's entities
     *
     * @var array
     */
    protected $orderBy;

    /**
     * Test if an entity is present in this relation.
     * Triggers a fetch if the relation is FETCH_LAZY
     *
     * @param string|integer $offset The index key
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        $this->fetch();
        $array = $this->toArray();

        return array_key_exists($offset, $array);
    }

    /**
     * Returns the entity at the given index (reference) if any, or null.
     * Triggers a fetch if the relation is FETCH_LAZY
     *
     * @param string|integer $offset The index key
     *
     * @return object|null
     */
    public function offsetGet($offset)
    {
        $this->fetch();
        $array  = $this->toArray();

        return (array_key_exists($offset, $array) ? $array[$offset] : null);
    }

    /**
     * Adds an entity to this relation at the given index
     *
     * @param string|integer $offset The index key
     * @param object         $value  The entity
     *
     * @return Relation|void
     */
    public function offsetSet($offset, $value)
    {
        $this->fetch();

        return $this->add($value);
    }

    /**
     * Removes an entity at the given index
     * Triggers a fetch if the relation is FETCH_LAZY
     *
     * @param string|integer $offset The index key
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->fetch();

        $obj    = $this->offsetGet($offset);
        if (null === $obj) {
            return;
        }

        parent::remove($obj);
    }

    /**
     * Count entities in this relation.
     * Triggers a fetch if the relation is FETCH_LAZY
     *
     * @return int
     */
    public function count()
    {
        $this->fetch();

        return $this->getRegistry()->count();
    }

     /**
     * Defines the column to use as index for this relation's entities
     *
     * @param string $column The column's name
      *
     * @return Relation
     */
    public function setReference($column)
    {
        $this->reference = $column;

        return $this;
    }

    /**
     * Defines the column to use to sort entities in this relation
     *
     * @param string $column    The column's name
     * @param string $direction The direction (asc or desc)
     *
     * @return Relation
     */
    public function setOrderBy($column, $direction = null)
    {
        $this->orderBy = array('column' => $column, 'direction' => $direction);

        return $this;
    }

    /**
     * Returns the column used as reference (index)
     *
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * Returns the orderBy for this relation
     *
     * @return array
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * Adds an entity to the collection
     *
     * @param object $object      The entity
     * @param array  $identifiers List of identifiers (PK) for this entity
     *
     * @return void
     */
    public function add($object, array $identifiers = array())
    {
        if ($this->has($object)) {
            return;
        }

        $data = array('reference' => null);
        if (!empty($this->reference)) {
            $access             = new Accessor($object);
            $reference          = $access->get($this->reference);
            $data['reference']  = $reference;
        }

        $this->getRegistry()->store(
            $object,
            $identifiers,
            RegistryState::REGISTERED,
            $data
        );
    }

    /**
     * Adds a set of objects to this relation
     *
     * @param array $objects List of entities
     *
     * @return Relation
     */
    public function addAll(array $objects)
    {
        foreach ($objects as $object) {
            $this->add($object);
        }

        return $this;
    }

    /**
     * Returns an array of all entities in this relation
     *
     * @return array
     */
    public function toArray()
    {
        $this->fetch();

        $final = array();
        $list = $this->getRegistry()->getStore();
        foreach ($list as $entry) {
            if ($entry->getAction() == Registry::ACTION_DELETE) {
                continue;
            }

            if (empty($this->reference)) {
                $final[] = $entry->getObject();
                continue;
            }

            $ref    = $entry->data('reference', null);
            $final[$ref]  = $entry->getObject();
        }

        return $final;
    }
}