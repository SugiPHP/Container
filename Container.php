<?php
/**
 * SugiPHP Container Class
 *
 * @package SugiPHP.Container
 * @author  Plamen Popov <tzappa@gmail.com>
 * @license http://opensource.org/licenses/mit-license.php (MIT License)
 */

namespace SugiPHP\Container;

class Container implements \ArrayAccess
{
    /**
     * Table of Definitions
     */
    protected $definitions = array();

    /**
     * Table of returned objects
     */
    protected $objects = array();

    /**
     * Table of generated objects
     */
    protected $calcs = array();

    /**
     * Table of all closures that should always return fresh objects.
     */
    protected $factories;

    /**
     * Table of closures that get() method should always return raw results
     */
    protected $raws;

    /**
     * Table of locked keys that cannot be overridden and deleted
     */
    protected $locks = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->factories = new \SplObjectStorage();
        $this->raws = new \SplObjectStorage();
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __unset($name)
    {
        return $this->delete($name);
    }

    /**
     * Sets a parameter defined in an unique key ID. You can set objects as a closures.
     *
     * @param string $id
     * @param mixed $value Value or closure function
     */
    public function set($id, $value)
    {
        if (!empty($this->locks[$id])) {
            throw new Exception("Cannot override locked key {$id}");
        }
        $this->definitions[$id] = $value;
        $this->calcs[$id] = false;
        // unset on override
        unset($this->objects[$id]);
    }

    /**
     * Fetches previously defined parameter or an object.
     *
     * @param string $id
     *
     * @return mixed value, object or NULL if the parameter was not set
     */
    public function get($id)
    {
        if (!isset($this->definitions[$id])) {
            return null;
        }

        if (method_exists($this->definitions[$id], "__invoke")) {
            if (isset($this->raws[$this->definitions[$id]])) {
                return $this->definitions[$id];
            }

            if (isset($this->factories[$this->definitions[$id]])) {
                return $this->definitions[$id]();
            }

            if ($this->calcs[$id]) {
                return $this->objects[$id];
            }
            $obj = $this->definitions[$id]();
            $this->objects[$id] = $obj;
            $this->calcs[$id] = true;

            return $obj;
        }

        return $this->definitions[$id];
    }

    /**
     * Gets or sets callable to return fresh objects.
     * If a callable is given, then it sets that the get() method always to return new objects.
     * If an string (key ID's) is given, then it will return new object.
     *
     * @param mixed $idOrClosure
     * @param mixed
     */
    public function factory($idOrClosure)
    {
        if (is_object($idOrClosure) && method_exists($idOrClosure, "__invoke")) {
            $this->factories->attach($idOrClosure);

            return $idOrClosure;
        }

        if (!isset($this->definitions[$idOrClosure])) {
            return null;
        }

        if (method_exists($this->definitions[$idOrClosure], '__invoke')) {
            return $this->definitions[$idOrClosure]($this);
        }

        return $this->definitions[$idOrClosure];
    }

    /**
     * Returns a raw definition. Used when a closure is set and you want to get the closure not the result of it.
     *
     * @param string $idOrClosure
     *
     * @return mixed Returns whatever it is stored in the key. NULL if nothing is stored.
     */
    public function raw($idOrClosure)
    {
        if (is_object($idOrClosure) && method_exists($idOrClosure, "__invoke")) {
            $this->raws->attach($idOrClosure);

            return $idOrClosure;
        }

        if (!isset($this->definitions[$idOrClosure])) {
            return null;
        }

        return $this->definitions[$idOrClosure];
    }

    /**
     * Checks parameter or object is defined.
     *
     * @param string $id
     *
     * @return boolean
     */
    public function has($id)
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id
     */
    public function delete($id)
    {
        if (!empty($this->locks[$id])) {
            throw new Exception("Cannot delete locked key {$id}");
        }
        if (is_object($this->definitions[$id])) {
            unset($this->factories[$this->definitions[$id]]);
        }
        unset($this->definitions[$id], $this->objects[$id]);
    }

    /**
     * Lock the key, so it cannot be overwritten.
     * Note that there is no unlock method and will never have!
     *
     * @param string $id
     */
    public function lock($id)
    {
        $this->locks[$id] = true;
    }

    /**
     * Method is needed to implement \ArrayAccess.
     *
     * @see set() method
     */
    public function offsetSet($id, $value)
    {
        $this->set($id, $value);
    }

    /**
     * Method is needed to implement \ArrayAccess.
     *
     * @see get() method
     */
    public function offsetGet($id)
    {
        return $this->get($id);
    }

    /**
     * Method is needed to implement \ArrayAccess.
     *
     * @see has() method
     */
    public function offsetExists($id)
    {
        return $this->has($id);
    }

    /**
     * Method is needed to implement \ArrayAccess.
     *
     * @see delete() method
     */
    public function offsetUnset($id)
    {
        $this->delete($id);
    }
}
