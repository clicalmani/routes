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

    public function __construct(private \Closure $callable) 
    {
        $routes = Route::all();
        Route::runGroup($this->callable);
        $this->group = array_diff(Route::all(), $routes);
    }

    /**
     * Prefix routes group
     * 
     * @param string $prefix
     * @return static
     */
    public function prefix(string $prefix) : static
    {
        $this->group = Route::prefix($this->group, $prefix); 
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
}
