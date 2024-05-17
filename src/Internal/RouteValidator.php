<?php
namespace Clicalmani\Routes\Internal;

use Clicalmani\Routes\Route;
use Clicalmani\Routes\Routing;

/**
 * RouteValidator class
 * 
 * @package clicalmani/routes 
 * @author @clicalmani
 */
class RouteValidator
{
    public function __construct(
        private string $method,
        private string $route,
        private ?string $action,
        private ?string $controller,
        private ?\Closure $callback
    ) {
        // Auto prepend backslash
        if ( '' !== $this->route AND 0 !== strpos($this->route, '/') ) {
            $this->route = "/$this->route";
        }

        // Prepend group prefix when grouping routes
        if ( Route::isGroupRunning() ) {
            $this->route = "%PREFIX%$this->route";
        }
        
        /**
         * Validate global patterns
         */
        foreach (Route::getGlobalPatterns() as $param => $pattern) {

            /**
             * Check if the current route has a global pattern
             */
            if (preg_match('/:' . $param . '([^\/])?/', $this->route)) {
                $this->route = preg_replace('/:' . $param . '([^\/])?/', ':' . $param . '@{"pattern": "' . $pattern . '"}', $this->route);
            }
        }
    }

    /**
     * @override Getter
     */
    public function __get(mixed $parameter)
    {
        switch ($parameter) {
            case 'method': return $this->method;
            case 'route': return $this->route;
            case 'action': return $this->action;
            case 'controller': return $this->controller;
            case 'callback': return $this->callback;
        }
    }

    /**
     * @override Setter
     */
    public function __set(mixed $parameter, mixed $value)
    {
        switch ($parameter) {
            case 'method': $this->method = $value; break;
            case 'route': $this->route = $value; break;
            case 'action': $this->action = $value; break;
            case 'controller': $this->controller = $value; break;
            case 'callback': $this->callback = $value; break;
        }
    }

    /**
     * Check for duplicate routes and define route signature
     * 
     * @return void
     */
    public function bind() : void
    {
        if ( $this->method AND $this->route ) {

            // duplicate
            if ( array_key_exists($this->route, Route::getMethodSignatures($this->method)) ) {
                throw new \Exception("Duplicate route $this->route => " . json_encode(Route::getRouteSignature($this->method, $this->route)));
            }

            $action = null;

            /**
             * Route controller
             */
            if ( $this->action ) $action = [$this->controller, $this->action];

            /**
             * Route function
             */
            elseif ( $this->callback )  $action = $this->callback;
            
            if (NULL !== $action) Route::defineRouteSignature($this->method, $this->route, $action);
        }
    }

    /**
     * Undefine route signature
     * 
     * @return void
     */
    public function unbind() : void
    {
        if ( $this->method ) {

            foreach (Route::getMethodSignatures($this->method) as $route => $controller) {
                if ($route == $this->route) {
                    Route::undefineRouteSignature($this->method, $this->route);
                    break;
                }
            }
        }
    }

    /**
     * Revalidate a parameter
     * 
     * @param string $param
     * @param string $validator
     * @return void
     */
    private function revalidateParam(string $param, string $validator) : void
    {
        $this->unbind();
        
        $this->route = preg_replace('/:' . $param . '([^\/])?/', ':' . $param . $validator, $this->route);

        $this->bind();
    }

    /**
     * Validate numeric parameter's value.
     * 
     * @param string|array $params
     * @return static
     */
    public function whereNumber(string|array $params) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"type": "numeric"}');
        
        return $this;
    }

    /**
     * Validate integer parameter's value.
     * 
     * @param string|array $params
     * @return static
     */
    public function whereInt(string|array $params) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"type": "int"}');
        
        return $this;
    }

    /**
     * Validate float parameter's value
     * 
     * @param string|array $params
     * @return static
     */
    public function whereFloat(string|array $params) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"type": "float"}');
        
        return $this;
    }

    /**
     * Validate parameter's against an enumerated values.
     * 
     * @param string|array $params
     * @param ?array $list Enumerated list
     * @return static
     */
    public function whereEnum(string|array $params, ?array $list = []) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"enum": "' . join(',', $list) . '"}');
        
        return $this;
    }

    /**
     * Validate a token
     * 
     * @param string|array $params
     * @return static
     */
    public function whereToken(string|array $params) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"type": "token"}');
        
        return $this;
    }

    /**
     * Validate parameter's value against a regular expression.
     * 
     * @param string|array $params
     * @param string $pattern A regular expression pattern without delimeters. Back slash (/) character will be used as delimiter
     * @return static
     */
    public function where(string|array $params, string $pattern) : static
    {
        $params = (array)$params;

        foreach ($params as $param) $this->revalidateParam($param, '@{"pattern": "' . $pattern . '"}');
        
        return $this;
    }

    /**
     * Add a before navigation hook. The callback function is passed the current param value and returns a boolean value.
     * If the callback function returns false, the navigation will be canceled.
     * 
     * @param string $param
     * @param callable $callback A callback function to be executed before navigation. The function receive the parameter value
     * as it's unique argument and must return false to halt the navigation, or true otherwise.
     * @return static
     */
    public function guardAgainst($param, $callback) : static
    {
        $uid = uniqid('gard-');
        
        Routing::registerGuard($uid, $param, $callback);
        $this->revalidateParam($param, '@{"uid": "' . $uid . '"}');

        return $this;
    } 

    /**
     * Define route middleware
     * 
     * @param string $name Middleware name all class
     * @return static
     */
    public function middleware(string $name_or_class) : static
    {
        Route::extendRouteMiddlewares($this->route, $name_or_class);

        return $this;
    }

    /**
     * Define route name
     * 
     * @param string $name
     * @return void
     */
    public function name(string $name) : void
    {
        Route::name($this->route, $name);
    }
}
