<?php
namespace simple;
/**
 * @method static Router get(string $route, Callable $callback)
 * @method static Router post(string $route, Callable $callback)
 * @method static Router put(string $route, Callable $callback)
 * @method static Router delete(string $route, Callable $callback)
 * @method static Router options(string $route, Callable $callback)
 * @method static Router head(string $route, Callable $callback)
 */

class Route
{
    public static $route = array();
    public static $callback = array();
    public static $method = array();
    public static $error_callback;
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );

    public static function __callStatic($method, $params)
    {
        $route = dirname($_SERVER['PHP_SELF']) . $params[0];
        $callback = $params[1];
        static::setRoute($route, $method, $callback);
    }

    public static function setRoute($route, $method, $callback)
    {
        array_push(static::$route, $route);
        array_push(static::$method, strtoupper($method));
        array_push(static::$callback, $callback);
    }

    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    public static function dispatch()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        $found_route = false;
        //去除起始位置双//,兼容 /route or route
        static::$route = str_replace('//', '/', static::$route);
        if (in_array($uri, static::$route)) {
            $route_pos = array_keys(static::$route, $uri);
            //            $route_pos = array_pop($route_pos);
            //相同Route多次执行
            foreach ($route_pos as $route) {
                if (static::$method[$route] == $method) {
                    $found_route = true;
                    if (is_object(static::$callback[$route])) {
                        call_user_func(static::$callback[$route]);
                        //return;
                    } else {
                        $segment = explode('@', static::$callback[$route]);
                        //controller
                        $controller = new $segment[0]();
                        //controller call action
                        $controller->$segment[1]();
                        //return;
                        //call_user_func_array([$controller,$segment[1]],[]);
                    }
                }
            }
        } else {
            $searchs = array_keys(static::$patterns);
            $replaces = array_values(static::$patterns);
            $pos = 0;
            foreach (static::$route as $route) {

                if (strpos($route, ':') !== false) {
                    $route = str_replace($searchs, $replaces, $route);
                }
                if (preg_match('#^' . $route . '$#', $uri, $matched)) {

                    if (static::$method[$pos] == $method) {

                        array_shift($matched); //remove $matched[0] as [1] is the first parameter.)

                        $found_route = true;

                        if (is_object(static::$callback[$pos])) {

                            call_user_func(static::$callback[$pos]);

                        } else {

                            $segment = explode('@', static::$callback[$pos]);

                            $controller = new $segment[0];

                            if (method_exists($controller, $segment[1])) {

                                call_user_func_array([$controller, $segment[1]], $matched);

                            }
                        }
                    }

                }
                $pos++;
            }

        }
        //如果找不到指定匹配路由则输出404callback = error_callback
        if ($found_route === false) {
            if (!static::$error_callback) {
                static::$error_callback = function () {
                    header($_SERVER['SERVER_PROTOCOL'] . '404 Not Found');
                    echo '404 Not Found';
                };
                call_user_func(static::$error_callback);
            }
        }
    }
}