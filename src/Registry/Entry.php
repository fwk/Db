<?php
namespace Fwk\Db\Registry;

use \ArrayAccess;
use Fwk\Db\Accessor;
use \InvalidArgumentException;

/**
 */
class Entry implements ArrayAccess
{
    /**
     * Object identifiers keys
     *
     * @var array
     */
    protected $identifiers = array();

    /**
     * Registry state of the object
     *
     * @var int
     */
    protected $state = RegistryState::UNKNOWN;

    /**
     * Initial object values
     *
     * @var array
     */
    protected $initialValues = array();

    /**
     * @var integer
     */
    protected $storeTs = null;

    /**
     * @var integer
     */
    protected $stateTs = null;

    /**
     * @var integer
     */
    protected $actionTs = null;

    /**
     * The action to happend (if any)
     *
     * @var string
     */
    protected $action = null;

    /**
     * Action priority
     *
     * @var integer
     */
    protected $actionPriority;

    /**
     * The stored object
     *
     * @var mixed
     */
    protected $object;

    /**
     * Class name of the stored object
     *
     * @var string
     */
    protected $className;

    /**
     * Extra data associated with the stored object
     *
     * @var array
     */
    protected $data = array();

    /**
     * Constructor
     *
     * @param mixed $object
     * @param array $ids
     * @param int   $state
     * @param array $data
     *
     * @return void
     * @throws InvalidArgumentException if $object is not an object
     */
    public function __construct($object, array $ids = array(), $state = RegistryState::UNKNOWN, array $data = array())
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException('value is not an object');
        }

        ksort($ids);
        if (!count($ids)) {
            $ids = array('%hash%' => Accessor::factory($object)->hashCode());
        }

        $this->object       =& $object;
        $this->identifiers  = $ids;
        $this->storeTs      = time();
        $this->state        = $state;
        $this->stateTs      = time();
        $this->className    = ltrim(get_class($object), '\\');
        $this->data         = $data;

        if ($this->isState(RegistryState::FRESH)) {
            // object stored as "fresh" -> define initial values
            $this->fresh();
        }
    }

    /**
     * State comparision
     *
     * @param integer $state
     *
     * @return boolean
     */
    public function isState($state)
    {
        return $this->state === $state;
    }

    /**
     * Changes current state
     *
     * @param integer $state
     *
     * @return Entry
     */
    public function setState($state)
    {
        $this->state    = $state;
        $this->stateTs  = time();

        return $this;
    }

    /**
     * Returns the state of the object
     *
     * @return integer
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Returns the date of the current state
     *
     * @return \DateTime
     */
    public function getStateDate()
    {
        $date = new \DateTime();
        $date->setTimestamp($this->stateTs);

        return $date;
    }

    /**
     * Returns the date of the storage
     *
     * @return \DateTime
     */
    public function getStoreDate()
    {
        $date = new \DateTime();
        $date->setTimestamp($this->storeTs);

        return $date;
    }

    /**
     * Defines initial values of the object and mark it as "fresh"
     *
     * @return Entry
     */
    public function fresh()
    {
        $accessor               = new Accessor($this->object);
        $this->initialValues    = $accessor->toArray(array($accessor, 'everythingAsArrayModifier'));

        $this->setState(RegistryState::FRESH);

        return $this;
    }

    /**
     * Test this Entry against identifiers and className
     *
     * @param array $identifiers
     * @param string $className
     *
     * @return boolean
     */
    public function match(array $identifiers, $className = null)
    {
        ksort($identifiers);

        if ($identifiers === $this->identifiers) {
            if (null !== $className && $this->className == ltrim($className, '\\')) {
                return true;
            } elseif (null === $className) {
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * Test this Entry against an object instance
     *
     * @param mixed $object
     *
     * @return boolean
     */
    public function matchObject($object)
    {
        return $this->object === $object;
    }

    /**
     * Tells if the objects values has been changed
     *
     * @return boolean
     */
    public function hasChanged()
    {
        if ($this->isState(RegistryState::CHANGED)) {
            return true;
        }

        if (!$this->isState(RegistryState::FRESH)) {
            return false;
        }

        $accessor   = new Accessor($this->object);
        $values     = $accessor->toArray(array($accessor, 'everythingAsArrayModifier'));
        $diff       = array_diff_assoc($this->initialValues, $values);

        if (!count($diff)) {
            return false;
        }

        $this->setState(RegistryState::CHANGED);

        return true;
    }

    /**
     * Returns an array of changed values
     *
     * @return array
     */
    public function getChangedValues()
    {
        $accessor   = new Accessor($this->object);
        $values     = $accessor->toArray(array($accessor, 'everythingAsArrayModifier'));

        $diff       = array();
        foreach ($values as $key => $val) {
            if(!isset($this->initialValues[$key]) || $this->initialValues[$key] !== $val) {
                $diff[$key] = $val;
            }
        }

        if (count($diff) && $this->isState(RegistryState::FRESH)) {
            $this->setState(RegistryState::CHANGED);
        }

        return $diff;
    }

    /**
     * Tells if an action is defined
     *
     * @return boolean
     */
    public function hasAction()
    {
        return !empty($this->action);
    }

    /**
     * Defines an action
     *
     * @param string $action
     *
     * @return Entry
     */
    public function setAction($action, $priority)
    {
        $this->action           = $action;
        $this->actionTs         = time();
        $this->actionPriority   = $priority;

        return $this;
    }

    /**
     * Returns the defined action if any, or null
     *
     * @return string|null
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     *
     * @return integer
     */
    public function getActionPriority()
    {
        return $this->actionPriority;
    }

    /**
     * Getter for associated data
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed|null
     */
    public function data($key, $default = false)
    {
        if (!array_key_exists($key, $this->data) && $default != false) {
            $this->data[$key] = $default;
        }

        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Merge datas with the existing ones
     *
     * @param array $data
     *
     * @return Entry
     */
    public function mergeData(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Factory method
     *
     * @param mixed $object
     * @param array $ids
     * @param int   $state
     * @param array $data
     *
     * @return Entry
     */
    public static function factory($object, array $ids = array(), $state = RegistryState::UNKNOWN, array $data = array())
    {
        return new self($object, $ids, $state, $data);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return array_key_exists($offset, $this->data) ? $this->data[$offset] : null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Returns the stored object
     *
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * String representation of the Entry
     *
     * @return string
     */
    public function __toString()
    {
        return get_class($this) . '@' . spl_object_hash($this);
    }

    /**
     * @return array
     */
    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    /**
     * @param array $identifiers
     *
     * @return Entry
     */
    public function setIdentifiers(array $identifiers)
    {
        $this->identifiers = $identifiers;

        return $this;
    }
}