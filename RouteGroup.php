<?php
namespace Clicalmani\Flesco\Routes;

class RouteGroup
{
    private $group;

    public function __construct(private \Closure $callable) 
    {
        $routes = Route::all();
        Route::startGrouping($this->callable);
        $this->group = array_diff(Route::all(), $routes);
    }

    public function prefix($prefix)
    {
        $this->group = Route::setPrefix($this->group, $prefix); 
        return $this;
    }

    public function middleware($name)
    {
        $method  = strtolower( $_SERVER['REQUEST_METHOD'] );
        $routine = Route::$routines[$method];
        
        foreach ($routine as $sroute => $controller) {
            
            if ( !in_array($sroute, $this->group)) continue;  // Exclude route
            
            Route::$route_middlewares[$sroute][] = $name; 
        }
    }
}
