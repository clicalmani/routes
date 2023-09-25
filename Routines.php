<?php
namespace Clicalmani\Flesco\Routes;

use Clicalmani\Flesco\Collection\Collection;

class Routines implements \ArrayAccess
{
    private string $resource;

    static array $resources  = [];

    function __construct(private Collection $storage = new Collection) {
        $this->resource = '';
    }
    
    /**
	 * (non-PHPdoc)
	 * @see ArrayAccess::offsetExists()
	 */
	function offsetExists(mixed $routine) : bool
	{
        return $this->storage->filter(function($rt) use($routine) {
            return $rt == $routine;
        })->count();
    }

    /** (non-PHPdoc)
     * @see ArrayAccess::offsetGet()
     */
    function offsetGet(mixed $index) : mixed
    {
        return $this->storage->filter(function($routine, $key) use($index) {
            return $index == $key;
        })->first();
    }

    /**
	 * (non-PHPdoc)
	 * @see ArrayAccess::offsetSet()
	 */
	function offsetSet(mixed $old, mixed $new) : void
	{
        $this->storage->map(function($routine, $key) use($old, $new) {
            if ($routine == $old) {
                return $new;
            }

            return $routine;
        });
    }

    /**
	 * (non-PHPdoc)
	 * @see ArrayAccess::offsetUnset()
	 */
	function offsetUnset(mixed $routine) : void
	{
        $this->storage->filter(function($rt, $key) use($routine) {
            return $rt != $routine;
        })->first();
    }

    function merge(Routines $routines)
    {
        $this->storage->merge($routines);
    }

    function addResource(string $resource, Routines $routines) : void
    {
        $this->resource = $resource;

        if ( ! array_key_exists($resource, static::$resources) ) 
            static::$resources[$resource] = [
                'object' => $routines, 
                'methods' => [],                //     'methods' => [
                                                //         'method1' => ['caller' => $closure, 'args' => ['arg1', 'arg2', ...]]
                                                //         ....
                                                //     ]
                'properties' => [],
                'joints'     => []
            ];
    }

    /**
     * Override the default not found behavior. It accept a closer that return the desired behavior.
     * 
     * @param Closure $closure a function that returns a response type.
     * @return $this for chaining purpose.
     */
    function missing($closure)
    {
        if ( $this->resource ) {
            self::$resources[$this->resource]['methods'] = [
                'missing' => [
                    'caller' => $closure,
                    'args' => []
                ]
            ];
        }

        return $this;
    }

    /**
     * Show distinct rows on resources view
     * 
     * @param boolean $bool set to true to select distinct rows, or false otherwise. default false
     * @return $this for chaining purpose.
     */
    function distinct($bool = false)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['distinct'] = $bool;
        }

        return $this;
    }

    /**
     * Ignore on create action
     * 
     * @param boolean $bool set to true to ignore duplicate keys, or false to disable. default false
     * @return $this for chaining purpose
     */
    function ignore($bool = false)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['ignore'] = $bool;
        }

        return $this;
    }

    function join($class_or_object, $foreign_key, $original_key, $includes = [], $excludes = [])
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['joints'][] = [
                'class' => $class_or_object,
                'foreign' => $foreign_key,
                'original' => $original_key,
                'includes' => $includes,
                'excludes' => $excludes
            ];
        }

        return $this;
    }

    function joinInclude($class_or_object, $foreign_key, $original_key, $includes)
    {
        return $this->join($class_or_object, $foreign_key, $original_key, $includes, []);
    }

    function joinExclude($class_or_object, $foreign_key, $original_key, $excludes)
    {
        return $this->join($class_or_object, $foreign_key, $original_key, [], $excludes);
    }

    function from(string $fields)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['from'] = $fields;
        }

        return $this;
    }

    function calcRows(bool $calc = false)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['calc'] = $calc;
        }

        return $this;
    }

    function limit(int $limit = 0)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['limit'] = $limit;
        }

        return $this;
    }

    function offset(int $offset = 0)
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['offset'] = $limit;
        }

        return $this;
    }

    function orderBy($order = 'NULL')
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['order_by'] = $order;
        }

        return $this;
    }
}