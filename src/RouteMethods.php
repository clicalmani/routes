<?php 
namespace Clicalmani\Routes;

/**
 * Route methods trait
 * 
 * @package clicalmani/routes
 * @author @clicalmani
 */
trait RouteMethods 
{
    /**
     * Method GET
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function get(string $route, mixed $action = null) : RouteValidator|RouteGroup
    { 
        return self::register('get', $route, $action);
    }

    /**
     * Method POST
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function post(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return self::register('post', $route, $action);
    }

    /**
     * Method PATCH
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function patch(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return self::register('patch', $route, $action);
    }

    /**
     * Method PUT
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function put(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return self::register('put', $route, $action);
    }

    /**
     * Method OPTIONS
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function options(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return self::register('options', $route, $action);
    }

    /**
     * Method DELETE
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    public static function delete(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return self::register('delete', $route, $action);
    }

    /**
     * Any method
     * 
     * @param string $route
     * @param mixed $action
     * @return void
     */
    public static function any(string $route, mixed $action) : void
    {
        foreach (self::getSignatures() as $method => $arr) {
            self::setRouteSignature($method, $route, $action);
        }
    }

    /**
     * Match multiple methods
     * 
     * @param array $matches
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\ResourceRoutines
     */
    public static function match(array $matches, string $route, mixed $action) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($matches as $method) {
            $method = strtolower($method);
            if ( array_key_exists($method, self::getSignatures()) ) {
                $routines[] = self::register($method, $route, $action);
            }
        }

        return $routines;
    }

    /**
     * Resource route
     * 
     * @param string $resource
     * @param string $controller
     * @return \Clicalmani\Routes\ResourceRoutines
     */
    public static function resource(string $resource, string $controller = null) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        $routes = [
            'get'    => ['index' => '', 'create' => 'create', 'show' => ':id@{"pattern": "[0-9]+"}', 'edit' => ':id@{"pattern": "[0-9]+"}/edit'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id@{"pattern": "[0-9]+"}'],
            'patch'  => ['update' => ':id@{"pattern": "[0-9]+"}'],
            'delete' => ['destroy' => ':id@{"pattern": "[0-9]+"}']
        ];

        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                $routines[] = self::register($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }

        $routines->addResource($resource, $routines);

        return $routines;
    }

    /**
     * Multiple resources
     * 
     * @param mixed $resource
     * @return \Clicalmani\Routes\ResourceRoutines
     */
    public static function resources(mixed $resources) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($resources as $resource => $controller) {
            $routines->merge(self::resource($resource, $controller));
        }

        return $routines;
    }

    /**
     * API resource
     * 
     * @param mixed $resource
     * @param string $controller [Optional] Controller class
     * @return \Clicalmani\Routes\ResourceRoutines
     */
    public static function apiResource(mixed $resource, ?string $controller = null) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        $routes = [
            'get'    => ['index' => '', 'create' => ':id@{"pattern": "[0-9]+"}'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id@{"pattern": "[0-9]+"}'],
            'patch'  => ['update' => ':id@{"pattern": "[0-9]+"}'],
            'delete' => ['destroy' => ':id@{"pattern": "[0-9]+"}']
        ];

        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                $routines[] = self::register($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }

        $routines->addResource($resource, $routines);

        return $routines;
    }

    /**
     * Multiple resources
     * 
     * @param mixed $resources
     * @return \Clicalmani\Routes\ResourceRoutines
     */
    public static function apiResources(mixed $resources) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($resources as $resource => $controller) {
            $routines->merge(self::apiResource($resource, $controller));
        }

        return $routines;
    }
}
