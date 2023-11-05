<?php
namespace Clicalmani\Routes;

use Clicalmani\Flesco\Support\Log;

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

    private $controller;

    public function __construct(private ?\Closure $callback = null) 
    {
        if ($this->callback) $this->group($this->callback);
    }

    public function group(callable $callback)
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

                    Route::defineRouteSignature($method, $key, $action);
                    Route::resetRouteMiddlewares($key, $middlewares);
                }
            }
        }

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
     * Return the grouped routes
     * 
     * @return array
     */
    public function getGroup()
    {
        return $this->group;
    }

    public function setController(string $class)
    {
        $this->controller = $class;
    }

    public function getController()
    {
        return $this->controller;
    }
}
