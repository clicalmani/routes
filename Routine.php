<?php
namespace Clicalmani\Flesco\Routes;

class Routine
{
    // static $bindings = [];
    
    function __construct(
        private string $method,
        private string $route,
        private ? string $action,
        private ? string $controller,
        private ? \Closure $callback
    ) {
        if ( '' !== $this->route AND 0 !== strpos($this->route, '/') ) {
            $this->route = "/$this->route";
        }

        if ( Route::$grouping_started ) {
            $this->route = "%PREFIX%$this->route";
        }
    }

    function __get($parameter)
    {
        switch ($parameter) {
            case 'method': return $this->method;
            case 'route': return $this->route;
            case 'action': return $this->action;
            case 'controller': return $this->controller;
            case 'callback': return $this->callback;
        }
    }

    function __set($parameter, $value)
    {
        switch ($parameter) {
            case 'method': $this->method = $value; break;
            case 'route': $this->route = $value; break;
            case 'action': $this->action = $value; break;
            case 'controller': $this->controller = $value; break;
            case 'callback': $this->callback = $value; break;
        }
    }

    function bind()
    {
        if ( $this->method AND $this->route ) {

            // duplicate
            if ( array_key_exists($this->route, Route::$routines[$this->method]) ) {
                throw new \Exception("Duplicate route $this->route => " . json_encode(Route::$routines[$this->method][$this->route]));
            }

            if ( $this->action ) {
                Route::$routines[$this->method][$this->route] = [$this->controller, $this->action];
            } elseif ( $this->callback ) {
                Route::$routines[$this->method][$this->route] = $this->callback;
            }
        }
    }

    function unbind()
    {
        if ( $this->method ) {
            $routine = Route::$routines[$this->method];

            foreach ($routine as $route => $controller) {
                if ($route == $this->route) {
                    unset(Route::$routines[$this->method][$this->route]);
                    break;
                }
            }
        }
    }

    private function revalidateParam($param, $validator)
    {
        $this->unbind();
        
        $this->route = preg_replace('/:' . $param . '([^\/])?/', ':' . $param . $validator, $this->route);

        $this->bind();
    }

    /**
     * Finds whether the parameter value is numeric.
     * 
     * @param $params [mixed] Array or string
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function whereNumber($params)
    {
        if ( is_string($params) ) $params = [$params];

        foreach ($params as $param) self::revalidateParam($param, '@{"type": "numeric"}');
        
        return $this;
    }

    /**
     * Finds whether the parameter value is an int.
     * 
     * @param $param [mixed] Array or string
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function whereInt($params)
    {
        if ( is_string($params) ) $params = [$params];

        foreach ($params as $param) self::revalidateParam($param, '@{"type": "int"}');
        
        return $this;
    }

    /**
     * Finds whether the parameter value is float.
     * 
     * @param $param [string] Array or string
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function whereFloat($params)
    {
        if ( is_string($params) ) $params = [$params];

        foreach ($params as $param) self::revalidateParam($param, '@{"type": "float"}');
        
        return $this;
    }

    /**
     * Verifies if the parameter value exists in the provided list.
     * 
     * @param $param [string] Route parameter
     * @param $list [string] comma seperated list
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function whereEnum($params, $list = [])
    {
        if ( is_string($params) ) $params = [$params];

        foreach ($params as $param) self::revalidateParam($param, '@{"enum": "' . join(',', $list) . '"}');
        
        return $this;
    }

    /**
     * Adds param validation against a regular expresion.
     * 
     * @param $param [string] Route parameter
     * @param $pattern [string] a regular expression pattern without delimeters.
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function where($params, $pattern)
    {
        if ( is_string($params) ) $params = [$params];

        foreach ($params as $param) self::revalidateParam($param, '@{"pattern": "' . $pattern . '"}');
        
        return $this;
    }

    /**
     * Add a navigation hook that is executed before navigation. The callback function is passed the current param value and returns a boolean value.
     * If the callback function returns false, the navigation will be canceled.
     * 
     * @param $param [string] Route parameter
     * @param $callback [Closure] a callback function to be executed before navigation.
     * @return \Clicalmani\Flesco\Routes\Routine
     */
    function guardAgainst($param, $callback)
    {
        $uid = uniqid('gard-');
        
        Route::$registered_guards[$uid] = [
            'param' => $param,
            'callback' => $callback
        ];

        self::revalidateParam($param, '@{"uid": "' . $uid . '"}');

        return $this;
    }
}
