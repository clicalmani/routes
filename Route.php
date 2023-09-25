<?php
namespace Clicalmani\Flesco\Routes;

use Clicalmani\Flesco\Providers\ServiceProvider;
use Clicalmani\Flesco\Exceptions\MiddlewareException;

class Route {
    
    public static $routines;
    public static $route_middlewares = [];
    public static $registered_guards = [];
    
    private const PARAM_TYPES = [
        'numeric',
        'int',
        'integer',
        'float',
        'string'
    ];
    private const VALIDATORS = [
        'pattern',
        'enum'
    ];
    public static $current_route;
    public static $grouping_started = false;

    public static function currentRoute()
    {
        $url = parse_url(
            $_SERVER['REQUEST_URI']
        );

        $current_route = isset($url['path']) ? $url['path']: '/';
        return $current_route;
    }

    public static function get($route, $callable) 
    { 
        return self::bindRoutine('get', $route, $callable);
    }

    public static function post($route, $callable) {
        return self::bindRoutine('post', $route, $callable);
    }

    public static function patch($route, $callable) {
        return self::bindRoutine('patch', $route, $callable);
    }

    public static function put($route, $callable) {
        return self::bindRoutine('put', $route, $callable);
    }

    public static function options($route, $callable) {
        return self::bindRoutine('options', $route, $callable);
    }

    public static function any($route, $callback) 
    {
        foreach (self::$routines as $method => $arr) {
            self::$routines[$method][$route] = $callback;
        }
    }

    public static function match($matches, $route, $callable)
    {
        if ( ! is_array($matches) ) return;

        $routines = new Routines;

        foreach ($matches as $method) {
            $method = strtolower($method);
            if ( array_key_exists($method, self::$routines) ) {
                $routines[] = self::bindRoutine($method, $route, $callable);
            }
        }

        return $routines;
    }

    public static function group( ...$parameters )
    {
        switch( count($parameters) ) {
            case 1: return new RouteGroup($parameters[0]);
            case 2: 
                $args = $parameters[0];
                $callable = $parameters[1];
                break;
        }

        /**
         * Prefix routes
         */
        if ( isset($args['prefix']) AND $prefix = $args['prefix']) {

            $routes = self::all();

            /**
             * |--------------------------------------
             * | Start route grouping
             * |----------------------------------------
             * |
             * | Prepend a prefix placeholder to the route (%PREFIX%)
             * | which will be replaced by the correct prefix.
             * |
             * |
             */
            self::startGrouping($callable);

            $grouped_routes = array_diff(self::all(), $routes);
            self::setPrefix($grouped_routes, $prefix);
            return;
        }

        /**
         * Middleware
         */
        if ( isset($args['middleware']) AND $name = $args['middleware']) {
            self::middleware($name, $callable);
        }
    }

    public static function startGrouping($callable)
    {
        /**
         * |--------------------------------------
         * | Start route grouping
         * |----------------------------------------
         * |
         * | Prepend a prefix placeholder to the route (%PREFIX%)
         * | which will be replaced by the correct prefix.
         * |
         * |
         */
        static::$grouping_started = true;

        $callable();    

        /**
         * Terminate grouping
         */
        static::$grouping_started = false;
    }

    public static function delete($route, $callable)
    {
        return self::bindRoutine('delete', $route, $callable);
    }

    public static function resource(string $resource, string $controller = null) : Routines
    {
        $routines = new Routines;

        $routes = [
            'get'    => ['index' => '', 'create' => 'create', 'show' => ':id', 'edit' => ':id/edit'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id'],
            'patch'  => ['update' => ':id'],
            'delete' => ['destroy' => ':id']
        ];

        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                $routines[] = self::bindRoutine($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }

        return $routines;
    }

    public static function resources(mixed $resources) : Routines
    {
        $routines = new Routines;

        foreach ($resources as $resource => $controller) {
            $routines->merge(self::resource($resource, $controller));
        }

        return $routines;
    }

    /**
     * Binds resource routes
     * 
     * Query id: comma separated values for resources with multiple keys
     * 
     * @param $resource [mixed] string or array
     * @param $controller [string] string a class extending \Clicalmani\Flesco\Http\Controllers\RequestController::class
     * @return \Clicalmani\Flesco\Routes\Routines
     */
    public static function apiResource(mixed $resource, string $controller = null) : Routines
    {
        $routines = new Routines;

        $routes = [
            'get'    => ['index' => '', 'create' => ':id'],
            'post'   => ['store' => ''],
            'put'    => ['update' => ':id'],
            'patch'  => ['update' => ':id'],
            'delete' => ['destroy' => ':id']
        ];

        foreach ($routes as $method => $sigs) {
            foreach ($sigs as $action => $sig) {
                $routines[] = self::bindRoutine($method, $resource . '/' . $sig, [$controller, $action]);
            }
        }

        $routines->addResource($resource, $routines);

        return $routines;
    }

    public static function apiResources(mixed $resources) : Routines
    {
        $routines = new Routines;

        foreach ($resources as $resource => $controller) {
            $routines->merge(self::apiResource($resource, $controller));
        }

        return $routines;
    }

    public static function allRoutes()
    {
        $routes = [];

        foreach (self::$routines as $routine) {
            foreach ($routine as $route => $controller) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    public static function all()
    {
        return self::allRoutes();
    }

    public static function setPrefix($routes, $prefix)
    {
        if ( is_string($routes) ) {
            $routes = [$routes];
        }

        $ret = [];

        foreach (self::$routines as $method => $routine) {
            foreach ($routine as $route => $controller) {
                if ( in_array($route, $routes) ) {
                    
                    unset(self::$routines[$method][$route]);

                    /**
                     * Prepend backslash (/)
                     */
                    if (false == preg_match('/^\//', $route)) {
                        $route = "/$route";
                    }

                    if (preg_match('/%PREFIX%/', $route)) {
                        $route = str_replace('%PREFIX%', $prefix, $route);
                    } else $route = $prefix . $route;

                    /**
                     * Prepend backslash (/)
                     */
                    if (false == preg_match('/^\//', $route)) {
                        $route = "/$route";
                    }

                    $ret[] = $route;

                    self::$routines[$method][$route] = $controller;
                }
            }
        }

        return $ret;
    }

    public static function getGateway()
    {
        return self::isApi() ? 'api': 'web';
    }

    public static function getApiPrefix()
    {
        return with(new \App\Providers\RouteServiceProvider)->getApiPrefix();
    }

    public static function isApi()
    {
        $api = self::getApiPrefix();
        
        return preg_match(
            "/^\/$api/", 
            current_route()
        );
    }

    public static function middleware($name, $callable = null) 
    {
        if ( self::isMiddleware($name) ) {

            $gateway = self::getGateway();
            $middleware = new ServiceProvider::$providers['middleware'][$gateway][$name];
            
            self::registerMiddleware($callable ? $callable: $middleware, $name);

            return $middleware;
        } 

        throw new MiddlewareException("Unknow middleware $name specified");
    }

    private static function isMiddleware($name)
    {
        $gateway = self::getGateway();

        if ( ! isset(ServiceProvider::$providers['middleware'][$gateway][$name]) ) 
            throw new MiddlewareException('Middleware can not be found');
        
        $middleware = new ServiceProvider::$providers['middleware'][$gateway][$name];
        
        /**
         * This allows to verify whether the current middleware inherited from Middleware class
         */
        if ( ! method_exists( $middleware, 'handler') ) 
            throw new MiddlewareException('Handler method not provided');
        if ( ! method_exists( $middleware, 'authorize') ) 
            throw new MiddlewareException('Authorize method not provided');

        return true;
    }

    private static function registerMiddleware($middleware, $name)
    {
        // Routes to exclude in the middleware
        $routes = self::allRoutes();

        if ($middleware instanceof \Closure) {
            $middleware();
        } else {

            // Register middleware routes
            $handler = $middleware->handler();
            
            if (false != $handler) {
                if ( file_exists( $handler ) ) {
                    include_once $handler;
                } else {
                    throw new MiddlewareException('Can not find handler provided');
                }
            }
        }

        $method  = strtolower( $_SERVER['REQUEST_METHOD'] );
        $routine = self::$routines[$method];
            
        foreach ($routine as $sroute => $controller) {
            
            if ( in_array($sroute, $routes) ) continue;               // Exclude route
            
            if ( !isset(self::$route_middlewares[$sroute]) ) {
                self::$route_middlewares[$sroute]   = [];
                self::$route_middlewares[$sroute][] = $name;
            } else {
                self::$route_middlewares[$sroute][] = $name; 
            }
        }
    }

    public static function getCurrentRouteMiddlewares()
    {
        $current_route = self::$current_route;
        
        if ( self::isApi() ) {
            if ( strpos(self::$current_route, self::getApiPrefix()) === 1 ) {
                $current_route = substr(self::$current_route, strlen(self::getApiPrefix()) + 1);   // Remove api prefix
            }
        }

        $middlewares = null;
        
        if ( array_key_exists($current_route, self::$route_middlewares) ) {
            $middlewares = self::$route_middlewares[$current_route];
        } elseif (  array_key_exists('%PREFIX%' . $current_route, self::$route_middlewares) ) {
            $middlewares = self::$route_middlewares['%PREFIX%' . $current_route];
        }

        return $middlewares;
    }

    /**
     * Verfify if current route is behind a middleware
     * 
     * @return mixed
     */
    static function isCurrentRouteAuthorized($request = null) : mixed
    {
        $gateway = self::getGateway();
        $authorize = true;

        if ($names = self::getCurrentRouteMiddlewares()) {

            rsort($names);
            
            foreach ($names as $name) {
                $middleware = new ServiceProvider::$providers['middleware'][$gateway][$name];
                $authorize = $middleware->authorize(
                    ( $gateway === 'web' ) ? ( new \Clicalmani\Flesco\Http\Requests\Request )->user(): $request
                );

                if (false == $authorize) {
                    return false;
                }
            }
        }
        
        return $authorize;
    }

    static function exists($method = null)
    {
        $alpha = self::getAlpha($method);
        $sroute = self::compareSequences($alpha);
        
        if ($sroute) {
            self::$current_route = $sroute;
            return $sroute;
        }

        return false;
    }

    private static function bindRoutine(string $method, string $route, mixed $callable, bool $bind = true) : mixed
    {
        if ( is_array($callable) AND count($callable) == 2 ) {
            $routine = new Routine($method, $route, $callable[1], $callable[0], null);
        } elseif ( is_string($callable) ) {
            $routine = new Routine($method, $route, 'invoke', $callable, null);
        } elseif ( 'Closure' === get_class($callable) ) {
            $routine = new Routine($method, $route, null, null, $callable);
        }
        
        if ( isset($routine) AND $bind ) {
            $routine->bind();
            return $routine;
        }

        return null;
    }

    public static function getController($method, $route)
    {
        return self::$routines[$method][$route];
    }

    /**
     * Explodes route against back slashes (/)
     * 
     * @param $route [string] the route to explode
     * @return array result
     */
    private static function getSequence($route)
    {
        $seq = preg_split('/\//', $route, -1, PREG_SPLIT_NO_EMPTY);
        return $seq;
        // return collection()->exchange($seq)->map(function($part) {
        //     return explode('@', $part)[0];
        // })->toArray();
    }

    /**
     * Computes routes with same length as the current route
     * 
     * The recipe here is to grab the most relevant routes, to avoid searching all the mesh
     * The current route will be compared to each of the selected routes
     * We first search for differences by comparing all the part broken against back slashes (/)
     * that allows us to find easily route parameters for the first try (that means some sequence may not be probabily a parameter)
     * that's not a matter for now because we will deal with them later.
     * After replacing all the parameters, we then try to rebuild the route and compare it to the current route to find a mamtch.
     * 
     * @param $method [string] Request method
     * @return Array containing the routes
     */
    private static function getAlpha($method)
    {
        $alpha = [];

        $nseq = self::getSequence( current_route() );
        
        $len     = count($nseq);
        $routine = self::$routines[$method];
            
        foreach ($routine as $sroute => $controller) {
            $sseq = self::getSequence($sroute);

            if ($len !== count($sseq)) continue;

            $alpha[$sroute] = $sseq;
        }

        return $alpha;
    }

    /**
     * Each route is exploded against back slash (/), that allows to find easily the different parts of the route
     * 
     * @param $alpha [array] an array of the routes with the same length as the current route
     * @return mixed string on success or false on failure
     */
    private static function compareSequences($alpha)
    {
        $nseq  = self::getSequence( current_route() );
        $beta  = [];
        
        foreach ($alpha as $sroute => $sseq) {
            if ( self::isSameRoute( self::build($sroute, $sseq) ) ) {
                $beta[$sroute] = $sseq;
            } else unset($beta[$sroute]);
        }
        
        // Find real params (indexes and values)
        $a = array_diff($nseq, ...array_values($beta));
        
        if ( count($beta) == 1) {
            $sroute = array_keys($beta)[0];
            $sseq   = $beta[$sroute];

            foreach ($a as $key => $value) {

                /**
                 * Twin parameters are sperated by a slash (-)
                 */
                $twin = explode('-', $sseq[$key]);
                $twin_values = explode('-', $value);

                if (count($twin) == 2 AND count($twin_values) == 2) {
                    $first_twin = self::registerParameter($twin[0], $twin_values[0]);
                    $second_twin = self::registerParameter($twin[1], $twin_values[1]);

                    if (false == $first_twin OR false == $second_twin) return false;
                }

                /**
                 * Spread parameters are sperated by amper's and (&)
                 */
                $spread = explode('&', $sseq[$key]);
                $values = explode('&', $value);

                if (count($spread) == count($values)) {
                    foreach ($spread as $i => $k) {
                        $valid = self::registerParameter($k, $values[$i]);

                        if (false == $valid) return false;
                    }
                }
                
                $valid = self::registerParameter($sseq[$key], $value);

                if (false == $valid) return false;
            }

            return $sroute;
        }
        
        if ( !empty($a) ) {
            $params_keys = [];
            
            foreach ($beta as $sroute => $sseq) {
                foreach ($a as $key => $value) {
                    $b = array_splice($sseq, $key, 1, $value);
                    if (self::isSameRoute($sseq) && self::registerParameter($b[0], $value)) return $sroute;
                }
            }
        } else {
            foreach ($beta as $sroute => $sseq) {
                if (self::isSameRoute($sseq)) return $sroute;
            }
        }
        
        return false;
    }

    /**
     * Builds route parts to be comparable to the current route. This method is capable of finding 
     * the exact parameters passed to the route. It replace each parameter to the appropriate position 
     * for it to be comparable to the current route.
     * 
     * @param $sroute [string] The syntical route to be matched with
     * @param $sseq [array] The route sequences 
     * @see \Clicalmani\Flesco\Routes::getSequence for mor details
     * @return array different sequences of the route after parameters replaced.
     */
    private static function build($sroute, $sseq)
    {
        $nseq  = self::getSequence( current_route() );
        $beta  = [];
        
        foreach ($nseq as $index => $part) {

            if ( in_array($sseq[$index], array_diff($sseq, $nseq)) ) {
                $beta[] = $part . '@' . $index;
            }
        }

        if (empty($beta)) return $nseq;
        
        foreach ($beta as $param) {
            $arr = explode('@', $param);
            $param = $arr[0];
            $index = $arr[1];

            if (preg_match('/^:/', $sseq[$index])) {
                array_splice($sseq, $index, 1, $param);
            }
        }
        
        return $sseq;
    }

    /**
     * compares to the current route
     * 
     * @param $sequences [array] 
     * @see \Clicalmani\Flesco\Routes::getSequence for mor details
     * @return boolean true on success, of false on failure.
     */
    private static function isSameRoute($sequences)
    {
        return '/' . join('/', $sequences) == current_route();
    }

    /**
     * Register a request parameter
     * 
     * Here we are simply using the default global variable $_REQUEST to populate request parameters
     * 
     * @param $param [string] parameter name
     * @param $value [string] parameter value
     * @return boolean true on success, or false on failure
     */
    private static function registerParameter($param, $value)
    {
        if (self::hasValidator($param)) {
            
            $validator = self::getValidator($param);
            
            if ($validator AND self::validateParameter($validator, $value)) $name = self::getParameterName($param);
            else return false;

        } else $name = substr($param, 1);

        $_REQUEST[$name] = $value;

        return true;
    }

    /**
     * Determine whether the specified parameter name as argument has validator or not
     * 
     * @see \Clicalmani\Flesco\Routes\Route::validateParameter for possible validators
     * @param $param [string] parameter name
     * @return boolean true on success, or false on failure
     */
    private static function hasValidator($param)
    {
        return strpos($param, '@');
    }

    /**
     * Retrive the parameter validator part
     */
    private static function getValidator($param)
    {
        $validator = json_decode( substr($param, strpos($param, '@') + 1) );

        if ( ! json_last_error() ) return $validator;

        return null;
    }

    /**
     * Retrive parameter name
     */
    private static function getParameterName($param)
    {
        return substr($param, 1, strpos($param, '@') - 1);
    }

    /**
     * Validate a parameter which name is provided as first argument and its value as second argument
     * 
     * @param $param [string] parameter to be validated
     * @param $value [string] parameter value
     * @return boolean true on success, or false on failure
     */
    private static function validateParameter($validator, $value)
    {
        $valid = false;

        if ( @ $validator->type) {

            if (in_array(@ $validator->type, self::PARAM_TYPES)) {

                /**
                 * |----------------------------------------------------------------------------
                 * |                 ***** Primitive types validation *****
                 * |----------------------------------------------------------------------------
                 * 
                 * Usage:
                 * 
                 * {"type": "validator"} 
                 * 
                 * validators match one of the following: numeric, int, integer and float
                 */
                switch($validator->type) {

                    /**
                     * Number validation: whether parameter value is a numerical value
                     */
                    case 'numeric': $valid = is_numeric($value); break;

                    /**
                     * Integer validation: whether parameter value is an int
                     * Not to be confound with numerical value. An int is not forcibly a string
                     */
                    case 'int':
                    case 'integer': $valid = is_int($value); break;

                    /**
                     * Float validation: whether parameter value is a float value
                     */
                    case 'float': $valid = is_float($value); break;
                }
            }
        } 
        
        /**
         * |----------------------------------------------------------------------
         * |                    ***** Enumeration *****
         * |----------------------------------------------------------------------
         * 
         * Parameter will be validated against a predefined values (a list of values)
         * 
         * Usage:
         * 
         * {"enum": "value1, valu2, ..."}
         */
        elseif(@ $validator->enum) {
            $enum = explode(',', $validator->enum);
            $valid = in_array($value, $enum);
        } 
        
        /**
         * |---------------------------------------------------------------------
         * |              ***** Regular Expression *****
         * |---------------------------------------------------------------------
         * |
         * 
         * Usage:
         * {"pattern": "a regular expression pattern without delimeters"}
         */
        elseif(@ $validator->pattern) {
            $valid = @ preg_match('/^' . $validator->pattern . '$/', $value);
        } 
        
        /**
         * |---------------------------------------------------------------------
         * |              ***** Route Guards *****
         * |---------------------------------------------------------------------
         * |
         * 
         * Route guard is a user provided callback function which returning value allows to determine whether
         * to navigate to the route or not.
         * Each guard is registered with a unique id.
         */
        elseif (@ $validator->uid) { 
            $guard = @ self::$registered_guards[$validator->uid];

            if ( $guard ) {
                $valid = $guard['callback']($value);
            }
        }

        return $valid;
    }
}
