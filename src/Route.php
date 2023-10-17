<?php
namespace Clicalmani\Routes;

use Clicalmani\Flesco\Providers\ServiceProvider;
use Clicalmani\Flesco\Exceptions\MiddlewareException;
use Clicalmani\Flesco\Http\Requests\Request;

class Route 
{
    use RouteMethods;

    /**
     * Routes signatures
     * 
     * @var array
     */
    protected static $signatures;

    /**
     * Midlewares
     * 
     * @var array
     */
    protected static $middlewares = [];

    /**
     * Route grouping state (for internal use only)
     * 
     * @var bool
     */
    private static $is_grouping = false;

    /**
     * Init routing
     * 
     * @return void
     */
    public static function init() : void
    {
        static::$signatures = [
            'get'     => [], 
            'post'    => [],
            'options' => [],
            'delete'  => [],
            'put'     => [],
            'patch'   => []
        ];
    }

    /**
     * Return the current route
     * 
     * @return string
     */
    public static function currentRoute() : string
    {
        if ( inConsoleMode() ) return '@console';
        
        $url = parse_url(
            $_SERVER['REQUEST_URI']
        );

        return isset($url['path']) ? $url['path']: '/';
    }

    /**
     * Routines getter
     * 
     * @return array
     */
    public static function getSignatures() : array
    {
        return static::$signatures;
    }

    /**
     * Get route signature
     * 
     * @param string $method
     * @param string $route
     * @return mixed
     */
    public static function getRouteSignature(string $method, string $route) : mixed
    {
        return static::$signatures[$method][$route];;
    }

    /**
     * Set route routine
     * 
     * @param string $method
     * @param string $route
     * @param mixed $action
     * @return void
     */
    public static function setRouteSignature(string $method, string $route, mixed $action) : void
    {
        static::$signatures[$method][$route] = $action;
    }

    /**
     * Unset route signature
     * 
     * @param string $method
     * @param string $route
     * @return void
     */
    public static function unsetRouteSignature(string $method, string $route) : void
    {
        unset(static::$signatures[$method][$route]);
    }

    /**
     * Get route method signatures
     * 
     * @param string $method
     * @return array
     */
    public static function getMethodSignatures(string $method) : array
    {
        return static::$signatures[$method];
    }

    /**
     * Get current route method
     * 
     * @return string
     */
    public static function getCurrentRouteMethod() : string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Return all registered middlewares
     * 
     * @return array
     */
    public static function getMiddlewares() : array
    {
        return static::$middlewares;
    }

    /**
     * Extend route middleware
     * 
     * @param string $route
     * @param string $name Middleware name
     * @return void
     */
    public static function extendRouteMiddlewares(string $route, string $name) : void
    {
        static::$middlewares[$route][] = $name;
    }

    /**
     * Get route middlewares
     * 
     * @param string $route
     * @return array
     */
    public static function getRouteMiddlewares(string $route) : array 
    {
        return static::$middlewares[$route];
    }

    /**
     * Verify wether a grouping is ongoing
     * 
     * @return bool
     */
    public static function isGroupRunning() : bool
    {
        return static::$is_grouping;
    }

    /**
     * Start routes grouping
     * 
     * @return void
     */
    public static function startGrouping() : void
    {
        static::$is_grouping = true;
    }

    /**
     * Stop route grouping
     * 
     * @return void
     */
    public static function stopGrouping() : void
    {
        static::$is_grouping = false;
    }

    /**
     * Grouping routes
     * 
     * @param mixed $parameters It can be one or two parameters. if one parameter is specified it must a callable value,
     * otherwise the first argument must be an array and the second a callable value
     * @return mixed
     */
    public static function group( ...$parameters ) : mixed
    {
        switch( count($parameters) ) {
            case 1: return new RouteGroup($parameters[0]);
            case 2: 
                $args = $parameters[0];
                $callback = $parameters[1];
                break;
        }

        /**
         * Prefix routes
         */
        if ( isset($args['prefix']) AND $prefix = $args['prefix']) {

            /**
             * Registered routes
             */
            $routes = self::all();

            self::runGroup($callback);

            self::prefix(array_diff(self::all(), $routes), $prefix);
        }

        /**
         * Middleware
         */
        elseif ( isset($args['middleware']) AND $name = $args['middleware']) {
            self::middleware($name, $callback);
        }

        return null;
    }

    /**
     * Execute registered routes
     * 
     * @param callable $callback
     * @return void
     */
    public static function runGroup($callback) : void
    {
        /**
         * Execution is ongoing
         */
        self::startGrouping();

        $callback();    

        /**
         * Terminate
         */
        self::stopGrouping();
    }

    /**
     * Return registered routes
     * 
     * @return array
     */
    public static function all() : array
    {
        $routes = [];

        foreach (self::$signatures as $routine) {
            foreach ($routine as $route => $controller) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Prefix routes
     * 
     * @param mixed $routes
     * @param string $prefix
     * @return array
     */
    public static function prefix($routes, $prefix) : array
    {
        if ( is_string($routes) ) {
            $routes = [$routes];
        }

        $ret = [];

        foreach (self::$signatures as $method => $routine) {
            foreach ($routine as $route => $controller) {
                if ( in_array($route, $routes) ) {
                    
                    unset(self::$signatures[$method][$route]);

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

                    self::$signatures[$method][$route] = $controller;
                }
            }
        }

        return $ret;
    }

    /**
     * Get route gateway
     * 
     * @return string
     */
    public static function gateway() : string
    {
        return self::isApi() ? 'api': 'web';
    }

    /**
     * API prefix
     * 
     * @return string
     */
    public static function getApiPrefix() : string
    {
        return with(new \App\Providers\RouteServiceProvider)->getApiPrefix();
    }

    /**
     * Verify wether the gateway is api
     * 
     * @return bool
     */
    public static function isApi() : bool
    {
        $api = self::getApiPrefix();
        
        return preg_match(
            "/^\/$api/", 
            current_route()
        );
    }

    /**
     * Attach middleware
     * 
     * @param string $name
     * @param mixed $callback
     * @return mixed
     */
    public static function middleware(string $name, mixed $callback = null) : mixed
    {
        if ( self::isMiddleware($name) ) {

            $gateway = self::gateway();
            $middleware = new ServiceProvider::$providers['middleware'][$gateway][$name];
            
            self::registerMiddleware($callback ? $callback: $middleware, $name);

            return $middleware;
        } 

        throw new MiddlewareException("Unknow middleware $name specified");
    }

    /**
     * Verify a middleware by name
     * 
     * @param string $name
     * @return bool
     */
    private static function isMiddleware(string $name) : bool
    {
        $gateway = self::gateway();

        /**
         * Check wether the name is registered
         */
        if ( ! isset(ServiceProvider::$providers['middleware'][$gateway][$name]) ) 
            throw new MiddlewareException('Middleware can not be found');
        
        $middleware = new ServiceProvider::$providers['middleware'][$gateway][$name];
        
        /**
         * Must have a handler
         */
        if ( ! method_exists( $middleware, 'handler') ) 
            throw new MiddlewareException('Handler method not provided');

        /**
         * Must have have an authorize method
         */
        if ( ! method_exists( $middleware, 'authorize') ) 
            throw new MiddlewareException('Authorize method not provided');

        return true;
    }

    /**
     * Register a middleware
     * 
     * @param mixed $middleware
     * @param string $name
     * @return void
     */
    private static function registerMiddleware(mixed $middleware, string $name) : void
    {
        // Registered routes not part of the middleware
        $routes = self::all();

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

        $method  = strtolower( self::getCurrentRouteMethod() );
        $routine = self::$signatures[$method];
            
        foreach ($routine as $sroute => $controller) {
            
            if ( in_array($sroute, $routes) ) continue;               // Not part of the middleware
            
            self::extendRouteMiddlewares($sroute, $name);
        }
    }

    /**
     * Return the current route registered middlewares
     * 
     * @return mixed
     */
    public static function getCurrentRouteMiddlewares() : mixed
    {
        $current_route = self::currentRoute();
        
        if ( self::isApi() && strpos(self::currentRoute(), self::getApiPrefix()) === 1 ) 
            $current_route = substr(self::currentRoute(), strlen(self::getApiPrefix()) + 1);   // Remove api prefix
        
        $middlewares = null;

        /**
         * Not grouped routes
         */
        if ( array_key_exists($current_route, self::getMiddlewares()) ) 
            $middlewares = self::getRouteMiddlewares($current_route);

        /**
         * Grouped routes
         */
        elseif (  array_key_exists('%PREFIX%' . $current_route, self::getMiddlewares()) ) 
            $middlewares = self::getRouteMiddlewares('%PREFIX%' . $current_route);
        
        return $middlewares;
    }

    /**
     * Verfify if current route is behind a middleware
     * 
     * @return bool
     */
    public static function isCurrentRouteAuthorized(?Request $request = null) : bool
    {
        $gateway = self::gateway();
        $authorized = true;

        if ($names = self::getCurrentRouteMiddlewares()) {

            rsort($names);
            
            foreach ($names as $name) {

                $authorized = with ( new ServiceProvider::$providers['middleware'][$gateway][$name] )?->authorize(
                    ( $gateway === 'web' ) ? ( new Request )->user(): $request
                );

                if (false == $authorized) {
                    return false;
                }
            }
        }
        
        return $authorized;
    }

    /**
     * Register a route
     * 
     * @param string $method
     * @param string $route
     * @param mixed $callback
     * @param bool $bind
     * @return \Clicalmani\Routes\Routine
     */
    private static function register(string $method, string $route, mixed $callback, bool $bind = true) : Routine
    {
        $action     = null;
        $controller = null;

        /**
         * Class method action
         */
        if ( is_array($callback) AND count($callback) == 2 ) {
            $action = $callback[1];
            $controller = $callback[0];
            $callback = null;
        } 

        /**
         * Magic invoke method
         */
        elseif ( is_string($callback) ) {
            $action = 'invoke';
            $controller = $callback;
            $callback = null;
        }

        $routine = new Routine($method, $route, $action, $controller, $callback);
        
        if ( $bind ) {
            $routine->bind();
        }

        return $routine;
    }

    /**
     * Get route controller
     * 
     * @param string $method
     * @param string $route
     * @return mixed
     */
    public static function getController(string $method, string $route) : mixed
    {
        return self::$signatures[$method][$route];
    }
}
