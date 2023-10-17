<?php
namespace Clicalmani\Routes;

class RouteGroup
{
    private $group;

    public function __construct(private \Closure $callable) 
    {
        $routes = Route::all();
        Route::runGroup($this->callable);
        $this->group = array_diff(Route::all(), $routes);
    }

    public function prefix($prefix)
    {
        $this->group = Route::prefix($this->group, $prefix); 
        return $this;
    }

    public function middleware($name)
    {
        $method     = strtolower( Route::getCurrentRouteMethod() );
        
        foreach (Route::getMethodSignatures($method) as $sroute => $controller) {
            
            if ( !in_array($sroute, $this->group)) continue;  // Exclude route
            
            Route::extendRouteMiddlewares($sroute, $name);
        }
    }
}
