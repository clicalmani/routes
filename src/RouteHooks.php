<?php 
namespace Clicalmani\Routes;

class RouteHooks 
{
    /**
     * Route hooks
     * 
     * @var array
     */
    protected static $hooks = [];

    /**
     * Get route registered before navigation hoooks
     * 
     * @param string $route
     * @return mixed
     */
    public static function getBeforeHook(string $route) : mixed
    {
        if (array_key_exists($route, static::$hooks)) return static::$hooks[$route]['before'];

        return null;
    }

    /**
     * Register a new before navigation hook
     * 
     * @param string $route
     * @param callable $hook
     * @return void
     */
    public static function registerBeforeHook(string $route, callable $hook) : void 
    {
        static::$hooks[$route]['before'] = $hook;
    }

    /**
     * Get route registered after navigation hoooks
     * 
     * @param string $route
     * @return mixed
     */
    public static function getAfterHook(string $route) : mixed
    {
        if (array_key_exists($route, static::$hooks)) return static::$hooks[$route]['after'];

        return null;
    }

    /**
     * Register a new after navigation hook
     * 
     * @param string $route
     * @param callable $hook
     * @return void
     */
    public static function registerAfterHook(string $route, callable $hook) : void 
    {
        static::$hooks[$route]['after'] = $hook;
    }
}
