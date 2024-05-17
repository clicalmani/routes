<?php
namespace Clicalmani\Routes;

class Facade 
{
    /**
     * PHP magic __callStatic
     * 
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args) : mixed
    {
        try {
            $class = self::getClass();
            $args_count = 0;

            if ( method_exists($class, "$method") ) {
                $args_count = ( new \ReflectionClass($class) )->getMethod($method)->getNumberOfParameters();
                return with( new $class )->{"$method"}( ...$args );
            }

            throw new \Exception(
                sprintf("Method %s does not exists on class %s. Called at line %d in %s", $method, get_called_class(), __LINE__, __CLASS__)
            );

        } catch (\ArgumentCountError $e) {
            throw new \ArgumentCountError(
                sprintf("Too few arguments to function %s::%s(), %d passed in %s on line %d and exactly %d expected.", get_called_class(), $method, count($args), __CLASS__, __LINE__, $args_count)
            );
        }
    }

    /**
     * PHP function get_called_class() wrapper
     * 
     * @return string
     */
    private static function getClass() : string
    {
        $class = get_called_class();
        $class = "Clicalmani\Routes\Internal\\" . substr($class, strrpos($class, "\\") + 1);

        if ( class_exists($class) ) return $class;

        throw new \Exception(sprintf("Facade class %s does not exists.", $class));
    }
}
