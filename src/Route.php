<?php
namespace Clicalmani\Routes;

/**
 * @method static string currentRoute()
 * @method static mixed currentRouteSignature(?string $signature = null)
 * @method static array getSignatures()
 * @method static void setSignatures(array $signatures)
 * @method static mixed getRouteSignature(string $method, string $route)
 * @method static void defineRouteSignature(string $method, string $route, mixed $action)
 * @method static void undefineRouteSignature(string $method, string $route)
 * @method static array getMethodSignatures(string $method)
 * @method static string getCurrentRouteMethod()
 * @method static array getMiddlewares()
 * @method static void extendRouteMiddlewares(string $route, string $name)
 * @method static mixed getRouteMiddlewares(string $route)
 * @method static void resetRouteMiddlewares(string $route, ?array $middlewares = [])
 * @method static bool isGroupRunning()
 * @method static void startGrouping()
 * @method static void stopGrouping()
 * @method static mixed group( ...$parameters )
 * @method static string[] all()
 * @method static string gateway()
 * @method static string getApiPrefix()
 * @method static bool isApi()
 * @method static mixed middleware(string $name, mixed $callback = null)
 * @method static int|bool isRouteAuthorized(string $route, ?Request $request = null)
 * @method static mixed getController(string $method, string $route)
 * @method static void pattern(string $param, string $pattern)
 * @method static mixed name(string $route, ?string $name = null)
 * @method static void registerPattern(string $param, string $pattern)
 * @method static array getGlobalPatterns()
 * @method static mixed resolve(mixed ...$params)
 * @method static mixed findByName(string $name)
 * @method static \Clicalmani\Routes\Internal\RouteGroup controller(string $class)
 */
class Route extends Facade
{
    // ...
}
