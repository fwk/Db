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

use Fwk\Db\Relation;

/**
 * This class is a simple accessor for values of objects
 *
 * If getters/setters are found, we use them first.
 * If not, we try to directly get/set the value (\ArrayAccess or \stdClass)
 *
 */
class Accessor
{
    /**
     * The object to access
     *
     * @var mixed
     */
    protected $object;

    /**
     * Reflector for the object
     *
     * @var \ReflectionObject
     */
    protected $reflector;

    /**
     * Override properties visibility ?
     *
     * @var boolean
     */
    protected $force = false;

    /**
     * Constructor
     *
     * @param mixed $object
     */
    public function __construct($object)
    {
        $this->object   = $object;
    }

    /**
     * Returns all relations objects from an entity
     *
     * @return array<ColumnName,Relation>
     */
    public function getRelations()
    {
        $values     = $this->toArray();
        $final      = array();

        foreach ($values as $key => $value) {
            if ($value instanceof Relation) {
                $final[$key]   = $value;
            }
        }

        return $final;
    }

    public static function everythingAsArrayModifier($value)
    {
        if ($value instanceof Relation) {
            /**
             * @todo Boucle infinie!!!  $value->hasChanged()
             */
            $value  = sprintf('relation:%s-%u', (/* $value->hasChanged() */ true ? (string) \microtime() : "static"), (string) $value->isFetched());
        }

        if (\is_array($value)) {
            foreach ($value as $key => $val) {
                if (is_object($val)) {
                    $accessor   = self::factory($val);
                    $val        = $accessor->toArray();
                }
                $value[$key]    = $val;
            }
        }

        return $value;
    }

    /**
     * Try to retrieve a value from the object
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        $obj        = $this->object;
        $getter     = "get". ucfirst($key);

        if (\method_exists($obj, $getter) && \is_callable(array($obj, $getter))) {
            return \call_user_func(array($obj, $getter));
        } elseif ($obj instanceof \stdClass && isset($obj->{$key})) {
            return  $obj->{$key};
        } elseif ($obj instanceof \ArrayAccess && $obj->offsetExists($key)) {
            return  $obj->offsetGet($key);
        } else {
            $reflector  = $this->getReflector();
            try {
                $prop   = $reflector->getProperty($key);
                if (($prop->isPrivate() || $prop->isProtected()) && $this->force) {
                    $prop->setAccessible(true);
                }

                return $prop->getValue($obj);
            } catch (\ReflectionException $e) {
            }
        }

        return false;
    }

    /**
     * Try to set a value
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return boolean
     */
    public function set($key, $value)
    {
        $obj        = $this->object;
        $setter     = "set". ucfirst($key);

        if (\method_exists($obj, $setter) && \is_callable(array($obj, $setter))) {
            \call_user_func(array($obj, $setter), $value);

            return true;
        }

        if ($obj instanceof \stdClass) {
            $obj->{$key}    = $value;

            return true;
        }

        if ($obj instanceof \ArrayAccess) {
            $obj->offsetSet($key, $value);

            return true;
        }

        $reflector  = $this->getReflector();
        try {
            $prop   = $reflector->getProperty($key);

            if (($prop->isPrivate() || $prop->isProtected()) && $this->force) {
                $prop->setAccessible(true);
            }

            if ($prop->isPublic() || $this->force === true) {
                $prop->setValue($obj, $value);

                return true;
            }
        } catch (\ReflectionException $e) {
        }

        return false;
    }

    /**
     * Set multiple values
     *
     * @param array $values
     *
     * @return
     */
    public function setValues(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Gets a reflector for the object
     *
     * @return \ReflectionObject
     */
    public function getReflector()
    {
        if (!isset($this->reflector)) {
            $this->reflector = new \ReflectionObject($this->object);
        }

        return $this->reflector;
    }

    public function toArray($modifier = null)
    {
        $reflector  = $this->getReflector();
        $final      = array();

        foreach ($reflector->getProperties() as $property) {
            $value = $this->get($property->getName());

            if (\is_callable($modifier)) {
                $value  = \call_user_func_array($modifier, array($value));
            }

            $final[$property->getName()] = $value;
        }

        return $final;
    }

    /**
     * Produces a unique hash code based on values
     *
     *
     */
    public function hashCode($algo  = 'md5')
    {
        $array  = $this->toArray();
        \ksort($array);
        $str    = \get_class($this->object);

        foreach ($array as $key => $value) {
            if($value instanceof Relation)
                continue;

            if (is_object($value)) {
                $tmp    = self::factory($value);
                $value  = $tmp->toArray();
            }

            if (is_array($value)) {
                $value = \json_encode($value);
            }

            $str .= $value;
        }

        return \hash($algo, $str);
    }

    /**
     *
     * @param mixed $object
     *
     * @throws \InvalidArgumentException
     *
     * @return Accessor
     */
    public static function factory($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException("Argument is not an object");
        }

        return new static($object);
    }

    public function overrideVisibility($bool)
    {
        $this->force = (bool) $bool;
    }
}
