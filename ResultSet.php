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
 * ResultSet Utility Class
 */
class ResultSet implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * Storage object
     *
     * @var \SplObjectStorage
     */
    protected $store;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(array $results = array())
    {
        $this->store        = new \SplObjectStorage();
        foreach ($results as $key => $result) {
            if (is_array($result)) {
                $result = (object) $result;
            }

            $this->store->attach($result, $key);
        }
    }

    /**
     *
     * @param \Closure $filter
     *
     * @return ResultSet
     */
    public function filter(\Closure $filter)
    {
        $final = array();
        foreach ($this->store as $obj) {
            $result = call_user_func($filter, $obj);
            $key = $this->store->getInfo();

            if ($result === true) {
                if (is_int($key)) {
                    $final[] = $obj;
                } else {
                    $final[$key] = $obj;
                }
            }
        }

        return new static($final);
    }

    /**
     *
     * @param  mixed   $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        foreach ($this->store as $obj) {
            $data = $this->store->getInfo();
            if ($data === $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        foreach ($this->store as $obj) {
            $data = $this->store->getInfo();
            if ($data === $offset) {
                return $obj;
            }
        }

        return null;
    }

    /**
     *
     * @param mixed $object
     *
     * @return boolean
     */
    public function has($object)
    {
        return $this->store->contains($object);
    }

    /**
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (empty($offset)) {
            $offset = count($this->store);
        }

        foreach ($this->store as $obj) {
            $key = $this->store->getInfo();
            if ($key === $offset) {
                $this->store->detach($obj);
            }
        }

        $this->store->attach($value, $offset);
    }

    /**
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        foreach ($this->store as $obj) {
            $data = $this->store->getInfo();
            if ($data === $offset) {
                $this->store->detach($obj);
            }
        }
    }

    /**
     *
     * @return array
     */
    public function toArray()
    {
        $final= array();
        foreach ($this->store as $object) {
            $final[] = $object;
        }

        return $final;
    }

    /**
     *
     * @return integer
     */
    public function count()
    {
        return $this->store->count();
    }

    /**
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }
}
