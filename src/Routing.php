<?php 
namespace Clicalmani\Routes;

class Routing extends Route
{
    /**
     * Routes guards
     * 
     * @var array
     */
    public static $registered_guards = [];

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
     * Check wether the current route is registered
     * 
     * @param string $method
     * @return mixed 
     */
    public static function exists(?string $method = null) : mixed
    {
        $method = $method ?? strtolower( Route::getCurrentRouteMethod() );
        $alpha  = self::getAlpha($method);
        $sroute = self::find($alpha);
        
        if ($sroute) {

            /**
             * Keep a copy for next demand
             */
            // self::$current_route = $sroute;

            return $sroute;
        }

        return false;
    }

    /**
     * Computes routes with same number of sequences as the current route
     * 
     * @param string $method
     * @return array 
     */
    private static function getAlpha(string $method) : array
    {
        $alpha = [];

        $nseq = self::getSequence( current_route() );
        
        $len        = count($nseq);
        $signatures = self::getMethodSignatures($method);
            
        foreach ($signatures as $sroute => $controller) {
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
     * @return mixed string on success or false on failure
     */
    private static function find(array $alpha) : mixed
    {
        /**
         * Current route sequences
         */
        $nseq    = self::getSequence( current_route() );

        /**
         * Route with same structure as the current route (parameters and paths)
         */
        $matches = [];
        
        foreach ($alpha as $sroute => $sseq) {

            /**
             * Reconstruct (put together the different parts) the route and compare it to the current route
             */
            if ( self::compare( self::rebuild($sseq) ) ) {
                $matches[$sroute] = $sseq;
            }
        }
        
        /**
         * Retrieve params indexes and values
         */
        $a = array_diff($nseq, ...array_values($matches));
        
        /**
         * That's definitly the requested route
         */
        if ( count($matches) == 1) {

            $sseq   = array_values($matches)[0];

            foreach ($a as $key => $value) {
                /**
                 * Escape routes which did not pass validation
                 */
                if (false == self::isValidParameter($sseq[$key], $value)) return false;
            }

            return array_keys($matches)[0];
        }
        
        /**
         * Match routes with parameters
         */
        if ( !empty($a) ) {
            
            foreach ($matches as $sroute => $sseq) {
                foreach ($a as $key => $value) {
                    if ($arr = array_splice($sseq, $key, 1, $value)) 
                        if (self::compare($sseq) && self::isValidParameter($arr[0], $value)) return $sroute;
                }
            }
        } 
        
        /**
         * Match routes without parameters
         */
        else {
            foreach ($matches as $sroute => $sseq) {
                if (self::compare($sseq)) return $sroute;
            }
        }
        
        return false;
    }

    /**
     * Rebuild a route sequence by replacing parameters by their values (from request)
     * 
     * @param array $sequence
     * @return array 
     */
    private static function rebuild(array $sequence) : array
    {
        // Current route sequences
        $current_sequence  = self::getSequence( current_route() );

        // Holds the requested route parameters
        $parameters  = [];
        
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
     * compare route sequences to the requested route sequences
     * 
     * @param array $sequences
     * @return bool true on success, of false on failure.
     */
    private static function compare(array $sequences) : bool
    {
        return '/' . join('/', $sequences) == current_route();
    }

    /**
     * Verify a parameter validity
     * 
     * @param string $param
     * @param string $value
     * @return bool true on success, or false on failure
     */
    private static function isValidParameter(string $param, string $value)
    {
        if ( self::hasValidator($param) ) {
            
            $validator = self::getValidator($param);
            
            if ($validator AND self::validateParameter($validator, $value)) $name = self::getParameterName($param);
            else return false;

        } else $name = substr($param, 1); // Remove (:) caracter

        /**
         * Add to the request
         */
        $_REQUEST[$name] = $value;

        return true;
    }


    private static function isHyphenParameter(string $param, string $value)
    {
        return preg_match('/^(\d)+-(\d+)$/', $param);
        $parts = explode('-', $param);
        $values = explode('-', $value);

        if (count($parts) == 2 AND count($values) == 2) {
            $start = self::validateParameter($parts[0], $values[0]);
            $end = self::validateParameter($parts[1], $values[1]);

            if (false == $start OR false == $end) return false;
        }

        return true;
    }

    /**
     * Verify wether a parameter has a validator
     * 
     * @param string $param
     * @return int<0, max>|false true on success, or false on failure
     */
    private static function hasValidator(string $param) : int|false
    {
        return strpos($param, '@');
    }

    /**
     * Retrive the parameter validation part
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
     * Retrive parameter name
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
     * @param string $param
     * @param string $value
     * @return bool true on success, or false on failure
     */
    private static function validateParameter($validator, $value) : bool
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
            $guard = @ self::$registered_guards[$validator->uid];

            if ( $guard ) {
                $valid = $guard['callback']($value);
            }
        }

        return $valid;
    }
}
