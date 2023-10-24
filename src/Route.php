<?php
namespace Clicalmani\Routes;

use Clicalmani\Flesco\Providers\ServiceProvider;
use Clicalmani\Flesco\Exceptions\MiddlewareException;
use Clicalmani\Flesco\Http\Requests\Request;

/**
 * Route class
 * 
 * @package clicalmani\routes
 * @author @clicalmani
 */
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
        // Do not route in console mode
        if ( inConsoleMode() ) return '@console';
        
        $url = parse_url(
            $_SERVER['REQUEST_URI']
        );

        return isset($url['path']) ? $url['path']: '/';
    }

    /**
     * Signatures getter
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
     * Define route signature
     * 
     * @param string $method Route method
     * @param string $route User defined route signature. It's the key index and must be unique.
     * @param mixed $action Route action
     * @return void
     */
    public static function defineRouteSignature(string $method, string $route, mixed $action) : void
    {
        static::$signatures[$method][$route] = $action;
    }

    /**
     * Unset a registered route signature. After a call of this function, all navigation to the route will be void.
     * 
     * @param string $method
     * @param string $route
     * @return void
     */
    public static function undefineRouteSignature(string $method, string $route) : void
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
        return strtolower( $_SERVER['REQUEST_METHOD'] );
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
     * Extend route middlewares
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
     * Group routes
     * 
     * @param mixed $parameters It can be one or two parameters. if one parameter is passed it must a callable value,
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

        // Prefix routes
        if ( isset($args['prefix']) AND $prefix = $args['prefix']) 
            return with( new RouteGroup($callback) )->prefix($prefix);
        
        // Middleware
        if ( isset($args['middleware']) AND $name = $args['middleware']) 
            return self::middleware($name, $callback);
        
        return null;
    }

    /**
     * Run routes group
     * 
     * @param callable $callback
     * @return void
     */
    public static function runGroup($callback) : void
    {
        /**
         * Start grouping
         */
        self::startGrouping();

        // Run grouped routes
        $callback();    

        /**
         * Stop grouping
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

        foreach (self::$signatures as $signature) {
            foreach ($signature as $key => $action) {
                $routes[] = $key;
            }
        }

        return $routes;
    }

    /**
     * Prefix routes
     * 
     * @param string|array $routes
     * @param string $prefix
     * @return array
     */
    public static function prefix(string|array $routes, string $prefix) : array
    {
        if ( is_string($routes) ) {
            $routes = [$routes];
        }

        $ret = [];

        foreach (self::$signatures as $method => $signature) {
            foreach ($signature as $key => $action) {
                if ( in_array($key, $routes) ) {
                    
                    // Temporary unregister route
                    self::undefineRouteSignature($method, $key);
                    
                    if (preg_match('/%PREFIX%/', $key)) {
                        $key = str_replace('%PREFIX%', $prefix, $key);
                    } else $key = $prefix . $key;

                    /**
                     * Prepend backslash (/)
                     */
                    if (false == preg_match('/^\//', $key)) {
                        $key = "/$key";
                    }

                    $ret[] = $key;

                    self::defineRouteSignature($method, $key, $action);
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
     * Detect api gateway
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
     * Attach a middleware
     * 
     * @param string $name
     * @param mixed $callback If omitted the middleware will be considered as an inline middleware
     * @return mixed
     */
    public static function middleware(string $name, mixed $callback = null) : mixed
    {
        if ( self::isMiddleware($name) ) {

            $gateway = self::gateway();
            $middleware = ServiceProvider::getProvidedMiddleware($gateway, $name);
            
            self::registerMiddleware($callback ? $callback: new $middleware, $name);

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
         * Verify if the name is registered in the middleware service provider
         */
        $middleware = ServiceProvider::getProvidedMiddleware($gateway, $name);
        
        if ( null === $middleware ) 
            throw new MiddlewareException("Middleware $name can not be found");
        
        /**
         * Must have a handler
         */
        if ( ! method_exists( $middleware, 'handler') ) 
            throw new MiddlewareException("Handler method not provided in $name middleware");

        /**
         * Must have have an authorize method
         */
        if ( ! method_exists( $middleware, 'authorize') ) 
            throw new MiddlewareException("Authorize method not provided in $name middleware");

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
        // Gather registered routes
        $routes = self::all();

        if ($middleware instanceof \Closure) {
            $middleware();
        } else {

            // Run middleware route group
            $handler = $middleware->handler();
            
            if (false != $handler) {
                if ( file_exists( $handler ) ) {
                    include_once $handler;
                } else {
                    throw new MiddlewareException('Can not find handler provided');
                }
            }
        }

        $method  = self::getCurrentRouteMethod();
        $signatures = self::$signatures[$method];
            
        foreach ($signatures as $key => $action) {
            
            if ( in_array($key, $routes) ) continue;    // Not part of the middleware
            
            self::extendRouteMiddlewares($key, $name);
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

            foreach ($names as $name) {

                $middleware = ServiceProvider::getProvidedMiddleware($gateway, $name);
                $authorized = with ( new $middleware )?->authorize(
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
     * Register new route
     * 
     * @param string $method
     * @param string $route
     * @param mixed $callback
     * @param bool $bind
     * @return \Clicalmani\Routes\RouteValidator
     */
    private static function register(string $method, string $route, mixed $callback, bool $bind = true) : RouteValidator
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

        $validator = new RouteValidator($method, $route, $action, $controller, $callback);
        
        if ( $bind ) {
            $validator->bind();
        }

        return $validator;
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
        return @ self::$signatures[$method][$route];
    }
}
