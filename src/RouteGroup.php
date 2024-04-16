<?php
namespace Clicalmani\Routes;

/**
 * RouteGroup class
 * 
 * @package clicalmani/routes 
 * @author @clicalmani
 */
class RouteGroup
{
    /**
     * Holds the grouped routes
     * 
     * @var array
     */
    private $group;

    /**
     * Group controller
     * 
     * @var string
     */
    private $controller;

    /**
     * Group routes
     * Usefull for validation optional parameters
     * 
     * @var string[]
     */
    private $routes = [];

    /**
     * Constructor
     * 
     * @param ?\Closure $callback Call back function
     */
    public function __construct(private ?\Closure $callback = null) 
    {
        if ($this->callback) $this->group($this->callback);
    }

    /**
     * Controller group
     * 
     * @param callable $callback
     * @return static
     */
    public function group(callable $callback) : static
    {
        $this->callback = $callback;
        $routes = Route::all();
        $this->run();
        $this->group = array_diff(Route::all(), $routes);
        return $this;
    }

    /**
     * Run routes group
     * 
     * @param callable $callback
     * @return void
     */
    public function run() : void
    {
        /**
         * Start grouping
         */
        Route::startGrouping();

        // Run grouped routes
        call($this->callback);

        /**
         * Stop grouping
         */
        Route::stopGrouping();
    }

    /**
     * Prefix routes group
     * 
     * @param string $prefix
     * @return static
     */
    public function prefix(string $prefix) : static
    {
        $keys = [];

        foreach (Route::getSignatures() as $method => $signature) {
            foreach ($signature as $key => $action) {
                if ( in_array($key, $this->group) ) {

                    // Temporary unregister route
                    Route::undefineRouteSignature($method, $key);
                    $middlewares = Route::getRouteMiddlewares($key);
                    
                    if (preg_match('/%PREFIX%/', $key)) {
                        $key = str_replace('%PREFIX%', $prefix, $key);
                    } else $key = $prefix . $key;

                    /**
                     * Prepend backslash (/)
                     */
                    if (false == preg_match('/^\//', $key)) {
                        $key = "/$key";
                    }
                    
                    if ($this->controller) $action = [$this->controller, @ $action[0] ? $action[0]: 'invoke'];

                    if (preg_match('/\?:.*([^\/])?/', $key)) {
                        echo $key . '<br>';
                    }

                    $keys[] = $key;

                    Route::defineRouteSignature($method, $key, $action);
                    Route::resetRouteMiddlewares($key, $middlewares);
                }
            }
        }

        $this->group = $keys;

        return $this;
    }

    /**
     * Define a middleware on the routes group
     * 
     * @param string $name
     * @return void
     */
    public function middleware(string $name) : void
    {
        $method = Route::getCurrentRouteMethod();
        
        foreach (Route::getMethodSignatures($method) as $key => $action) {
            
            if ( !in_array($key, $this->group)) continue;  // Exclude route
            
            Route::extendRouteMiddlewares($key, $name);
        }
    }

    /**
     * Set parameter pattern. Useful for optional parameters
     * 
     * @param string $param
     * @param string $pattern
     * @return static
     */
    public function pattern(string $param, string $pattern) : static
    {
        return $this->patterns([$param], [$pattern]);
    }

    /**
     * Set multiple patterns
     * 
     * @see RouteGroup::patterns()
     * @param string[] $params
     * @param string[] $patters
     * @return static
     */
    public function patterns(array $params, array $patterns) : static
    {
        foreach ($this->group as $route) {
            foreach (Route::getSignatures() as $method => $signature) {
                foreach ($signature as $key => $action) {
                    if (true === $this->equal($route, $key)) {
                        Route::undefineRouteSignature($method, $key);
                        $middlewares = Route::getRouteMiddlewares($key);
                        foreach ($params as $i => $param) {
                            $key = str_replace('@{"pattern": "' . $patterns[$i] . '"}', '', $key);
                            $key = preg_replace('/:' . $param . '([^\/]+)?/', ':' . $param . '@{"pattern": "' . $patterns[$i] . '"}', $key);
                        }
                        Route::defineRouteSignature($method, $key, $action);
                        Route::resetRouteMiddlewares($key, $middlewares);
                    }
                }
            }
        }

        return $this;
    }

    private function equal(string $entry, string $signature)
    {
        if ($entry === $signature) return true;

        $diff = array_diff(
            collection(preg_split('/[\/]/', $signature, -1, PREG_SPLIT_NO_EMPTY))->map(fn($sig) => explode('@', $sig)[0])->toArray(),
            preg_split('/[\/]/', $entry, -1, PREG_SPLIT_NO_EMPTY)
        );
        
        if (!$diff) return true;
        
        return false;
    }

    /**
     * (non-PHPDoc)
     * @overriden
     * 
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($name === 'controller') return $this->controller;
        elseif ($name === 'routes') return $this->routes;
    }

    /**
     * (non-PHPDoc)
     * @overriden
     * 
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set(string $name, mixed $value)
    {
        if ($name === 'controller') $this->controller = $value;
        elseif ($name === 'routes') $this->routes = $value;
    }
}
