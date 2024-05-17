<?php 
namespace Clicalmani\Routes\Internal;

use Clicalmani\Routes\RouteGroup;

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
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function get(string $route, mixed $action = null) : RouteValidator|RouteGroup
    { 
        return $this->register('get', $route, $action);
    }

    /**
     * Method POST
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function post(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return $this->register('post', $route, $action);
    }

    /**
     * Method PATCH
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function patch(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return $this->register('patch', $route, $action);
    }

    /**
     * Method PUT
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function put(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return $this->register('put', $route, $action);
    }

    /**
     * Method OPTIONS
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function options(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return $this->register('options', $route, $action);
    }

    /**
     * Method DELETE
     * 
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\RouteValidator|\Clicalmani\Routes\Internal\RouteGroup
     */
    public function delete(string $route, mixed $action) : RouteValidator|RouteGroup
    {
        return $this->register('delete', $route, $action);
    }

    /**
     * Any method
     * 
     * @param string $route
     * @param mixed $action
     * @return void
     */
    public function any(string $route, mixed $action) : void
    {
        foreach ($this->getSignatures() as $method => $arr) {
            $this->setRouteSignature($method, $route, $action);
        }
    }

    /**
     * Match multiple methods
     * 
     * @param array $matches
     * @param string $route
     * @param mixed $action
     * @return \Clicalmani\Routes\Internal\ResourceRoutines
     */
    public function match(array $matches, string $route, mixed $action) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($matches as $method) {
            $method = strtolower($method);
            if ( array_key_exists($method, $this->getSignatures()) ) {
                $routines[] = $this->register($method, $route, $action);
            }
        }

        return $routines;
    }

    /**
     * Resource route
     * 
     * @param string $resource
     * @param string $controller
     * @return \Clicalmani\Routes\Internal\ResourceRoutines
     */
    public function resource(string $resource, string $controller = null) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        $routes = [
            'get'    => ['index' => '', 'create' => 'create', 'show' => ':id', 'edit' => ':id/edit'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id'],
            'patch'  => ['update' => ':id'],
            'delete' => ['destroy' => ':id']
        ];

        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                $routines[] = $this->register($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }

        $routines->addResource($resource, $routines);

        return $routines;
    }

    /**
     * Multiple resources
     * 
     * @param mixed $resource
     * @return \Clicalmani\Routes\Internal\ResourceRoutines
     */
    public function resources(mixed $resources) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($resources as $resource => $controller) {
            $routines->merge($this->resource($resource, $controller));
        }

        return $routines;
    }

    /**
     * API resource
     * 
     * @param mixed $resource
     * @param ?string $controller Controller class
     * @param ?array $actions Customize actions
     * @return \Clicalmani\Routes\Internal\ResourceRoutines
     */
    public function apiResource(mixed $resource, ?string $controller = null, ?array $actions = []) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        $routes = [
            'get'    => ['index' => '', 'create' => ':id/?:hash'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id'],
            'patch'  => ['update' => ':id'],
            'delete' => ['destroy' => ':id']
        ];
        
        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                if ( !empty($actions) && !in_array($action, $actions) ) continue;
                $routines[] = $this->register($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }
        
        $routines->addResource($resource, $routines);

        return $routines;
    }

    /**
     * Multiple resources
     * 
     * @param mixed $resources
     * @return \Clicalmani\Routes\Internal\ResourceRoutines
     */
    public function apiResources(mixed $resources) : ResourceRoutines
    {
        $routines = new ResourceRoutines;

        foreach ($resources as $resource => $controller) {
            $routines->merge($this->apiResource($resource, $controller));
        }

        return $routines;
    }
}
