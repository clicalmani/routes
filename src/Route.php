<?php
namespace Clicalmani\Routes;

use Clicalmani\Flesco\Providers\ServiceProvider;
use Clicalmani\Flesco\Exceptions\MiddlewareException;
use Clicalmani\Flesco\Http\Requests\Request;
use Clicalmani\Flesco\Http\Response\Response;
use Clicalmani\Flesco\Support\Log;

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
     * Current route signature
     * 
     * @var string
     */
    protected static $current_route_signature;

    /**
     * Routes signatures
     * 
     * @var array
     */
    protected static $signatures;

    /**
     * Named routes
     * 
     * @var array
     */
    protected static $names = [];

    /**
     * Midlewares
     * 
     * @var array
     */
    protected static $middlewares = [];

    protected static $patterns = [];

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

        /**
         * Register route services
         */
        with( new \App\Providers\RouteServiceProvider )->register();
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
     * Get or set current route signature
     * 
     * @param string $signature
     * @return mixed
     */
    public static function currentRouteSignature(?string $signature = null) : mixed
    {
        if ($signature) return static::$current_route_signature = $signature;
        return static::$current_route_signature;
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
     * @return mixed
     */
    public static function getRouteMiddlewares(string $route) : mixed 
    {
        return @ static::$middlewares[$route];
    }

    public static function resetRouteMiddlewares(string $route, ?array $middlewares = []) 
    {
        if (!$middlewares) unset(static::$middlewares[$route]);
        else static::$middlewares[$route] = $middlewares;
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
        if ( $middleware = self::isMiddleware($name) ) 
            return self::registerMiddleware($callback ? $callback: $middleware, $name);

        throw new MiddlewareException("Unknow middleware $name specified");
    }

    /**
     * Verify a middleware by name
     * 
     * @param string $class_or_name
     * @return mixed
     */
    private static function isMiddleware(string $class_or_name) : mixed
    {
        /**
         * Inline middleware
         */
        if (class_exists($class_or_name)) $middleware = $class_or_name;

        /**
         * Global middleware
         */
        else $middleware = ServiceProvider::getProvidedMiddleware(self::gateway(), $class_or_name);

        if ( null === $middleware ) return false;
        
        return new $middleware;
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
        $middleware?->boot();

        $method  = self::getCurrentRouteMethod();
        $signatures = self::$signatures[$method];
            
        foreach ($signatures as $key => $action) {
            
            if ( in_array($key, $routes) ) continue;    // Not part of the middleware
            
            self::extendRouteMiddlewares($key, $name);
        }
    }

    /**
     * Verfify if current route is behind a middleware
     * 
     * @return int|bool
     */
    public static function isRouteAuthorized(string $route, ?Request $request = null) : int|bool
    {
        $names = self::getRouteMiddlewares($route);

        foreach ($names as $name) {
            if ($middleware = ServiceProvider::getProvidedMiddleware(self::gateway(), $name)) ;
            else $middleware = $name;

            if ( $middleware )
                with( new $middleware )->handle(
                    $request,
                    new Response,
                    fn() => http_response_code()
                );

            $response_code = http_response_code();
            
            if (200 !== $response_code) return $response_code;
        }
        
        return 200; // Authorized
    }

    /**
     * Register new route
     * 
     * @param string $method
     * @param string $route
     * @param mixed $callback
     * @param bool $bind
     * @return \Clicalmani\Routes\RouteValidator|\Clicalmani\Routes\RouteGroup
     */
    private static function register(string $method, string $route, mixed $callback, bool $bind = true) : RouteValidator|RouteGroup
    {
        $action      = null;
        $controller  = null;

        if (!preg_match('/\?:.*([^\/])?/', $route)) {
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
            } elseif (!$callback) $action = 'invoke';
        }

        /**
         * Optional parameters needs to be grouped
         */
        else {
            
            $options = self::createOptions($route);
            
            return tap((new RouteGroup(function() use($options, $method, $callback, $bind) {
                foreach ($options as $option) self::register($method, $option, $callback, $bind);
            }))->prefix(''), fn(RouteGroup $group) => $group->routes = $options);
        }

        return tap( 
            new RouteValidator($method, $route, $action, $controller, $callback),
            function(RouteValidator $validator) use($bind) {
                if ($bind) $validator->bind();
            }
        );
    }

    /**
     * Create route options
     * 
     * @param string $route
     * @return array Options
     */
    private static function createOptions(string $route) : array
    {
        preg_match_all('/\?:[a-zA-Z0-9]+([^\/])?/', $route, $matches);
        $arr = preg_split('/\//', $route, -1, PREG_SPLIT_NO_EMPTY);
        $inters = array_intersect($arr, [...array_values($matches[0])]);
        $diff = array_diff($arr, [...array_values($matches[0])]);
        
        $routes = [];
        $tmp = $route;

        $routes[] = $route; // Plain route
        
        // Without options
        foreach ($inters as $param) $tmp = preg_replace("/\\$param\/?/", "", $tmp);
        $routes[] = rtrim($tmp, '/');

        if (count($matches[0]) > 1) {
            // Single option
            $tmp = join('/', $diff);
            foreach ($inters as $param) $routes[] = $tmp . "/$param";

            foreach ($inters as $pos => $param) {
                $routes[] = rtrim(preg_replace("/\\$param\/?/", "", $route), '/');
            }
        }
        
        return collection()->exchange($routes)->map(fn(string $route) => str_replace('?', '', $route))->toArray();
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

    /**
     * Set a global pattern
     * 
     * @param string $param Parameter name
     * @param string $pattern A regular expression pattern without delimiters
     * @return void
     */
    public static function pattern(string $param, string $pattern): void
    {
        static::$patterns[$param] = $pattern;
    }

    /**
     * Name a route or get the route name. That will be useful for redirection.
     * 
     * @param string $route
     * @param ?string $name Optinal route name
     * @return mixed
     */
    public static function name(string $route, ?string $name = null) : mixed
    {
        if (!$name) return static::$names[$route];

        return static::$names[$route] = $name;
    }

    /**
     * Register global pattern
     * 
     * @param string $param Parameter name
     * @param string $pattern A regular expression pattern without delimiters
     * @return void
     */
    public static function registerPattern(string $param, string $pattern) : void
    {
        static::$patterns[$param] = $pattern;
    }

    /**
     * Get global patterns
     * 
     * @return array
     */
    public static function getGlobalPatterns()
    {
        return static::$patterns;
    }

    /**
     * Revolve a named route
     * 
     * @param mixed ...$params 
     * @return mixed
     */
    public static function resolve(mixed ...$params) : mixed
    {
        /**
         * The first parameter is the route name
         */
        $route = array_shift($params);

        if ($signature = self::findByName($route)) $route = $signature;
        
        preg_match_all('/:[^\/]+/', (string) $route, $matches);

        if ( count($matches) ) {
            foreach ($matches[0] as $i => $param) {
                $route = preg_replace('/' . $param . '([^\/])?/', $params[$i], $route);
            }
        }

        return $route;
    }

    /**
     * Find the route signature giving the route name
     * 
     * @param string $name Route name
     * @return mixed
     */
    public static function findByName(string $name) : mixed
    {
        foreach (static::$names as $key => $n) {
            if ($name === $n) return $key;
        }

        return null;
    }

    /**
     * Controller routes
     * 
     * @param string $class Controller class
     * @return \Clicalmani\Routes\RouteGroup
     */
    public static function controller(string $class) : RouteGroup
    {
        return instance(
            RouteGroup::class, 
            fn(RouteGroup $instance) => $instance->controller = $class
        );
    }
}
