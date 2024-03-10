<?php 
namespace Clicalmani\Routes;

use Clicalmani\Flesco\Http\Requests\Request;
use Clicalmani\Flesco\Providers\RouteServiceProvider;

/**
 * Routing class
 * 
 * @package clicalmani/routes 
 * @author @clicalmani
 */
class Routing extends Route
{
    /**
     * Routes guards
     * 
     * @var array
     */
    private static $registered_guards = [];

    /**
     * Supported route parameters types for validation purpose
     * 
     * @var array
     */
    private const PARAM_TYPES = [
        'numeric',
        'int',
        'integer',
        'float',
        'string'
    ];

    /**
     * Get route sequences
     * 
     * @param string $route
     * @return array 
     */
    private static function getSequence(string $route) : array
    {
        return preg_split('/\//', $route, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Capture the request route, verify its existance and its validity. 
     * 
     * @return mixed The route signature key (the route syntax as defined) on success, false otherwise
     */
    public static function route() : mixed
    {
        $key = self::find( 
            self::getAlpha( 
                self::getCurrentRouteMethod()
            ) 
        );

        // Run before navigation hook
        if ($hook = RouteHooks::getBeforeHook($key)) return $hook( new Request );

        // Fire TPS
        RouteServiceProvider::fireTPS($key);
        
        return $key;
    }

    /**
     * Computes routes with the same number of sequences as the current route
     * 
     * @param string $method Only signatures of the defined method will be searched for
     * @return array 
     */
    private static function getAlpha(string $method) : array
    {
        // Computed sequences
        $alpha = [];

        // Current route length
        $len = count( self::getSequence( current_route() ) );
            
        foreach (self::getMethodSignatures($method) as $sroute => $controller) {

            $sseq = self::getSequence($sroute);

            if ($len !== count($sseq)) continue;

            $alpha[$sroute] = $sseq;
        }

        return $alpha;
    }

    /**
     * Find the requested route
     * 
     * @param array $alpha
     * @return mixed Route signature key on success or false on failure
     */
    private static function find(array $alpha) : mixed
    {
        // Current route sequences
        $nseq = self::getSequence( current_route() );

        // Routes with same structure as the current route (same number of parameters and paths)
        $matches = [];
        
        foreach ($alpha as $sroute => $sseq) {

            // Reconstruct the route (put together the different parts) and compare it to the current route
            if ( self::compare( self::rebuild($sseq) ) ) {
                $matches[$sroute] = $sseq;
            }
        }
        
        // Retrieve params positions and values
        $a = array_diff($nseq, ...array_values($matches));
        
        // Single match
        if ( count($matches) == 1) {

            $sseq   = array_values($matches)[0];

            foreach ($a as $key => $value) {

                // Escape routes which did not pass validation
                if (false == self::isValidParameter($sseq[$key], $value)) return false;
            }

            return array_keys($matches)[0];
        }
        
        // Match routes with parameters
        if ( !empty($a) ) {
            
            foreach ($matches as $sroute => $sseq) {
                foreach ($a as $key => $value) {
                    if ($arr = array_splice($sseq, $key, 1, $value)) 
                        if (self::compare($sseq) && self::isValidParameter($arr[0], $value)) return $sroute;
                }
            }
        } 
        
        // Match routes without parameters
        else {
            foreach ($matches as $sroute => $sseq) {
                if (self::compare($sseq)) return $sroute;
            }
        }
        
        return false;
    }

    /**
     * Rebuild a route sequences by replacing parameters by their values (from the request)
     * 
     * @param array $sequence
     * @return array 
     */
    private static function rebuild(array $sequence) : array
    {
        // Current route sequences
        $current_sequence = self::getSequence( current_route() );

        // Holds the requested route parameters
        $parameters = [];
        
        foreach ($current_sequence as $index => $part) {

            if ( in_array($sequence[$index], array_diff($sequence, $current_sequence)) ) {
                $parameters[] = $part . '@' . $index;
            }
        }

        if (empty($parameters)) return $current_sequence;
        
        foreach ($parameters as $param) {
            $arr = explode('@', $param);
            $param = $arr[0];
            $index = $arr[1];

            if (preg_match('/^:/', $sequence[$index])) {
                array_splice($sequence, $index, 1, $param);
            }
        }
        
        return $sequence;
    }

    /**
     * compare route sequences to the current route sequences
     * 
     * @param array $sequences
     * @return bool true on success, of false on failure.
     */
    private static function compare(array $sequences) : bool
    {
        if ('/' . Route::getApiPrefix() . '/svc' === current_route()) return true;
        return '/' . join('/', $sequences) === current_route();
    }

    /**
     * Verify a parameter validity
     * 
     * @param string $param
     * @param string $value
     * @return bool true on success, false on failure
     */
    private static function isValidParameter(string $param, string $value)
    {
        if ( self::hasValidator($param) ) {
            
            $validator = self::getValidator($param);
            
            if ($validator AND self::validateParameter($validator, $value)) $name = self::getParameterName($param);
            else return false;

        } else $name = substr($param, 1); // Remove (:) character

        /**
         * Make the parameter available to the request.
         * We use the global $_REQUEST array so that the parameter can be access through global variables 
         * such as $_GET and $_POST
         */
        $_REQUEST[$name] = $value;

        return true;
    }

    /**
     * Register a route guard
     * 
     * @param string $uid Guard's unique id
     * @param string $param Parameter to guard against
     * @param string $callback Callback function
     * @return void
     */
    public static function registerGuard(string $uid, string $param, callable $callback) : void
    {
        static::$registered_guards[$uid] = [
            'param' => $param,
            'callback' => $callback
        ];
    }

    /**
     * Get a navigation guard
     * 
     * @param string $uid Guard unique id
     * @return mixed Route guard on success, or null on failure
     */
    private static function getGuard(string $uid) : mixed
    {
        if ( array_key_exists($uid, static::$registered_guards) ) {
            return static::$registered_guards[$uid];
        }
        
        return null;
    }

    private static function isHyphenParameter(string $param, string $value)
    {
        return preg_match('/^(\d)+-(\d+)$/', $param);
        $parts = explode('-', $param);
        $values = explode('-', $value);

        if (count($parts) == 2 AND count($values) == 2) {
            // $start = self::validateParameter($parts[0], $values[0]);
            // $end = self::validateParameter($parts[1], $values[1]);

            // if (false == $start OR false == $end) return false;
        }

        return true;
    }

    /**
     * Verify wether a parameter has a validator
     * 
     * @param string $param
     * @return int<0, max>|false true on success, false on failure
     */
    private static function hasValidator(string $param) : int|false
    {
        return strpos($param, '@');
    }

    /**
     * Retrive the parameter's validation part
     * 
     * @param string $param
     * @return \stdClass|null
     */
    private static function getValidator(string $param) : \stdClass|null
    {
        $validator = json_decode( substr($param, self::hasValidator($param) + 1 ) );

        if ( ! json_last_error() ) return $validator;

        return null;
    }

    /**
     * Retrive parameter name for parameters with a validator.
     * 
     * @param string $param
     * @return string|false
     */
    private static function getParameterName(string $param) : string|false
    {
        return substr($param, 1, self::hasValidator($param) - 1 );
    }

    /**
     * Validate a parameter
     * 
     * @param \stdClass $validator
     * @param string $value
     * @return bool true on success, false on failure
     */
    private static function validateParameter(\stdClass $validator, string $value) : bool
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

                    /**
                     * Token validation
                     */
                    case 'token': $valid = (bool) verify_token($value); break;
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
         * 
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
         * Route guard is a user provided callback function which must return true on success or false on failure
         */
        elseif (@ $validator->uid) { 

            // Retrieve the guard
            $guard = self::getGuard($validator->uid);

            if ( $guard && is_callable($guard['callback']) ) {

                // Pass parameter value to the guard
                $valid = $guard['callback']($value);
            }
        }

        return $valid;
    }
}
