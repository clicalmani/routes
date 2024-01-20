<?php
namespace Clicalmani\Routes;

use Clicalmani\Collection\Collection;

class ResourceRoutines implements \ArrayAccess
{
    /**
     * Routine resource
     * 
     * @var string
     */
    private string $resource;

    /**
     * Registered resources
     * 
     * @var array
     */
    private static array $resources  = [];

    public function __construct(private ?Collection $storage = new Collection) {
        $this->resource = '';
    }
    
    /**
	 * (non-PHPdoc)
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists(mixed $routine) : bool
	{
        return $this->storage->filter(function($rt) use($routine) {
            return $rt == $routine;
        })->count();
    }

    /** (non-PHPdoc)
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet(mixed $index) : mixed
    {
        return $this->storage->filter(function($routine, $key) use($index) {
            return $index == $key;
        })->first();
    }

    /**
	 * (non-PHPdoc)
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet(mixed $old, mixed $new) : void
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
	public function offsetUnset(mixed $routine) : void
	{
        $this->storage->filter(function($rt, $key) use($routine) {
            return $rt != $routine;
        })->first();
    }

    /**
     * Get resource routines
     * 
     * @param string $resource
     * @return \stdClass|null
     */
    public static function getRoutines(string $resource) : \stdClass|null
    {
        if ( array_key_exists($resource, static::$resources) ) {
			return (object) static::$resources[$resource];
		}
        
        return null;
    }

    /**
     * Merge resources
     * 
     * @param self $routine
     * @return void
     */
    public function merge(self $routine) : void
    {
        $this->storage->merge($routine);
    }

    /**
     * Add new resource
     * 
     * @param string $resource
     * @param self $routine
     * @return void
     */
    public function addResource(string $resource, self $routine) : void
    {
        $this->resource = $resource;

        if ( ! array_key_exists($resource, static::$resources) ) 
            static::$resources[$resource] = [
                'object' => $routine, 
                'methods' => [],                //     'methods' => [
                                                //         'method1' => ['caller' => $closure, 'args' => ['arg1', 'arg2', ...]]
                                                //         ....
                                                //     ]
                'properties' => [],
                'joints'     => []
            ];
    }

    /**
     * Override the default not found behaviour.
     * 
     * @param callable $closure A closure function that returns the response type.
     * @return static
     */
    public function missing(callable $closure) : static
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
     * Show distinct rows on resource view
     * 
     * @param bool $enable
     * @return static
     */
    public function distinct(?bool $enable = false) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['distinct'] = $enable;
        }

        return $this;
    }

    /**
     * Ignore primary key duplicate warning
     * 
     * @param bool $enable
     * @return static
     */
    public function ignore(?bool $enable = false) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['ignore'] = $enable;
        }

        return $this;
    }

    /**
     * Join resources
     * 
     * @param mixed $class_or_object
     * @param string $foreign_key
     * @param string $original_key
     * @param array $includes Restrict to some specific resource methods
     * @param array $excludes Exclude some specific resource methods
     * @return static
     */
    public function join(mixed $class_or_object, string $foreign_key, ?string $original_key = null, ?array $includes = [], ?array $excludes = []) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['joints'][] = [
                'class'    => $class_or_object,
                'foreign'  => $foreign_key,
                'original' => $original_key,
                'includes' => $includes,
                'excludes' => $excludes
            ];
        }

        return $this;
    }

    /**
     * Join with inclusion
     * 
     * @param mixed $class_or_object
     * @param string $foreign_key
     * @param string $original_key
     * @param array $includes
     * @return static
     */
    public function joinInclude(mixed $class_or_object, string $foreign_key, ?string $original_key, ?array $includes = []) : static
    {
        return $this->join($class_or_object, $foreign_key, $original_key, $includes, []);
    }

    /**
     * Join with exclusion
     * 
     * @param mixed $class_or_object
     * @param string $foreign_key
     * @param string $original_key
     * @param array $includes
     * @return static
     */
    public function joinExclude($class_or_object, $foreign_key, $original_key, $excludes)
    {
        return $this->join($class_or_object, $foreign_key, $original_key, [], $excludes);
    }

    /**
     * From statement when deleting from multiple tables
     * 
     * @param string $table
     * @return static
     */
    public function from(string $table) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['from'] = $table;
        }

        return $this;
    }

    /**
     * Enable SQL CAL_FOUND_ROWS
     * 
     * @param bool $enable
     * @return static
     */
    public function calcRows(?bool $enable = false) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['calc'] = $enable;
        }

        return $this;
    }

    /**
     * Limit number of rows in the result set
     * 
     * @param int $count
     * @return static
     */
    public function limit(?int $count = 0) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['limit'] = $count;
        }

        return $this;
    }

    /**
     * Limit offset
     * 
     * @param int $offset
     * @return static
     */
    public function offset(?int $offset = 0) : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['offset'] = $offset;
        }

        return $this;
    }

    /**
     * Order by
     * 
     * @param string $order
     * @return static
     */
    public function orderBy(?string $order = 'NULL') : static
    {
        if ( $this->resource ) {
            static::$resources[$this->resource]['properties']['order_by'] = $order;
        }

        return $this;
    }
}
