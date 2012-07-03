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
 * @subpackage Relations
 * @author     Julien Ballestracci <julien@nitronet.org>
 * @copyright  2011-2012 Julien Ballestracci <julien@nitronet.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://www.phpfwk.com
 */
namespace Fwk\Db\Relations;

use Fwk\Db\EntityEvents,
    Fwk\Db\Relation,
    Fwk\Db\Registry,
    Fwk\Db\Exception, 
    Fwk\Db\Connection, 
    Fwk\Events\Dispatcher,
    \IteratorAggregate;

/**
 * Abstract utility class for *Many Relations
 */
abstract class AbstractManyRelation extends AbstractRelation implements
    \ArrayAccess, \Countable
{
    /**
     * @var string
     */
    protected $reference;
    
    /**
     * @var array
     */
    protected $orderBy;
    
    public function offsetExists($offset)
    {
        $this->fetch();
        $array  = $this->toArray();
        return \array_key_exists($offset, $array);
    }

    public function offsetGet($offset)
    {
        $this->fetch();
        $array  = $this->toArray();
        return (\array_key_exists($offset, $array) ? $array[$offset] : null);
    }

    public function offsetSet($offset, $value)
    {
        $this->fetch();
        return parent::add($value);
    }

    public function offsetUnset($offset)
    {
        $this->fetch();
        
        $obj    = $this->offsetGet($offset);
        if(null === $obj)
            return;

        return parent::remove($obj);
    }

    public function count()
    {
        $this->fetch();

        return count($this->getRegistry()->getStore());
    }
        
     /**
     * Sets a column to use as a reference
     * 
     * @param string $column
      * 
     * @return Relation
     */
    public function setReference($column)
    {
        $this->reference = $column;

        return $this;
    }

    /**
     *
     * @param type $column
     * @param type $direction
     * 
     * @return Relation 
     */
    public function setOrderBy($column, $direction = null)
    {
        $this->orderBy = array('column' => $column, 'direction' => $direction);

        return $this;
    }
    
    /**
     *
     * @return string 
     */
    public function getReference()
    {
        
        return $this->reference;
    }

    /**
     *
     * @return array
     */
    public function getOrderBy()
    {
        
        return $this->orderBy;
    }
    
    /**
     * Adds an object to the collection
     *
     * @param mixed $object
     */
    public function add($object, array $identifiers = array())
    {
        if($this->has($object)) {
            return;
        }
        
        $data = array('reference' => null);
        if(!empty($this->reference)) {
            $access             = new Accessor($object);
            $reference          = $access->get($this->reference);
            $data['reference']  = $reference;
        }

        $this->getRegistry()->store($object, $identifiers, Registry::STATE_NEW, $data);
    }
}