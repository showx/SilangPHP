<?php
/*LICENSE
+-----------------------------------------------------------------------+
| SilangPHP Framework                                                   |
+-----------------------------------------------------------------------+
| This program is free software; you can redistribute it and/or modify  |
| it under the terms of the GNU General Public License as published by  |
| the Free Software Foundation. You should have received a copy of the  |
| GNU General Public License along with this program.  If not, see      |
| http://www.gnu.org/licenses/.                                         |
| Copyright (C) 2020. All Rights Reserved.                              |
+-----------------------------------------------------------------------+
| Supports: http://www.github.com/silangtech/SilangPHP                  |
+-----------------------------------------------------------------------+
*/
declare(strict_types=1);
namespace SilangPHP;
use \FastRoute\RouteCollector;

/**
 * 简单路由
 */
class Route
{
    public static $middlewares = [];
    public static $group_middlewares = [];
    public static $routes = [];
    public static $vars = [];
    public static $handler = [];
    public static $prefix = '';
    public static $dispatcher = null;

    public static function use(...$handler)
    {
        self::$middlewares = array_merge(self::$middlewares, $handler);
    }

    public static function GROUP($prefix, callable $callback, ...$middlewares)
    {
        self::addGroup($prefix, $callback, $middlewares);
    }

    public static function GET($route, $handler, ...$middlewares)
    {
        self::addRoute('GET', $route, $handler, $middlewares);
    }

    public static function POST($route, $handler, ...$middlewares)
    {
        self::addRoute('GET', $route, $handler, $middlewares);
    }

    public static function PUT($route, $handler, ...$middlewares)
    {
        self::addRoute('PUT', $route, $handler, $middlewares);
    }

    public static function DELETE($route, $handler, ...$middlewares)
    {
        self::addRoute('DELETE', $route, $handler, $middlewares);
    }

    public static function PATCH($route, $handler, ...$middlewares)
    {
        self::addRoute('PATCH', $route, $handler, $middlewares);
    }

    public static function HEAD($route, $handler, ...$middlewares)
    {
        self::addRoute('HEAD', $route, $handler, $middlewares);
    }

    public static function addGroup($prefix, callable $callback, ...$middlewares)
    {
        self::$prefix = $prefix;
        self::$group_middlewares = array_merge(self::$group_middlewares, $middlewares);
        $callback();
        self::$prefix = '';
        self::$group_middlewares = [];
    }

    public static function addRoute($httpMethod, $route, $handler, ...$middlewares)
    {
        $middlewares_tmp = array_merge(self::$middlewares, self::$group_middlewares);
        if(!empty($middlewares))
        {
            $middlewares_tmp = array_merge($middlewares_tmp, $middlewares);
        }
        $handler = array_merge($middlewares_tmp, [$handler]);
        $routes = ['method' => $httpMethod, 'route' => self::$prefix.$route, 'handler' => $handler];
        self::$routes = array_merge(self::$routes, [$routes]);
    }

    /**
     * 路由开始
     * @param string $pathInfo
     * @return bool|mixed
     * @throws \ReflectionException
     */
    public static function start($uri = '', $method = 'GET', Context $c = null)
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        try{
            if(empty(self::$dispatcher)){
                self::$dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
                    foreach (self::$routes as $route) {
                        $r->addRoute($route['method'], $route['route'], $route['handler']);
                    }
                });
            }
            $pos = strpos($uri, '?');
            if (false !== $pos) {
                $uri = substr($uri, 0, $pos);
            }
        }catch(\Exception $e){
            echo $e->getMessage();
        }
        $uri = rawurldecode($uri);
        if(self::$dispatcher)
        {
            $res = '';
            $routeInfo = self::$dispatcher->dispatch($method, $uri);
            switch ($routeInfo[0]) {
                case \FastRoute\Dispatcher::NOT_FOUND:
                    // return '404 NOT_FOUND';
                    $c->response->withStatus(404);
                    $c->response->end('404');
                    break;
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    $allowedMethods = $routeInfo[1][0];
                    // var_dump($allowedMethods);
                    // return 'METHOD_NOT_ALLOWED';
                    $c->response->withStatus(405);
                    $c->response->end('METHOD_NOT_ALLOWED');
                    break;
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    $middlewaresParams = ['c' => $c];
                    $vars = array_merge($vars, $middlewaresParams);
                    $c->vars = $vars;
                    // 多个handler只允许中间件的存在
                    if(is_array($handler))
                    {
                        $c->handler = $handler;
                        if(count($c->handler) == 1)
                        {
                            $handler = array_shift($c->handler);
                            $res = self::hander($handler, $vars);
                        }else{
                            $handler = array_shift($c->handler);
                            $res = self::hander($handler, $middlewaresParams);
                        }
                    }else{
                        $res = self::hander($handler, $vars);
                    }
                    break;
            }
            return $res;
        }
    }

    public static function hander($handler, $vars)
    {
        if(!is_callable($handler))
        {
            $res = '404';
            // 一般是中间件,不允许同时多个handler
            if(class_exists($handler))
            {
                $res = (new $handler)->handle($vars['c']);
            }else{
                $control = explode('@', $handler);
                $control[0] = str_replace('/', '\\', $control[0]);
                if(class_exists($control[0]))
                {
                    $ins = new $control[0];
                    $reflection = new \ReflectionMethod($ins, $control[1]);
                    $n_vars = [];
                    // 有参数的情况下才注入
                    foreach($reflection->getParameters() AS $arg){
                        $name_key = $arg->getName();
                        if(array_key_exists($name_key, $vars)){
                            $n_vars[$name_key] = $vars[$name_key];
                        }
                    }
                    // $res = $reflection->invoke(...$n_vars);
                    $res = call_user_func_array(array($ins, $control[1]), $n_vars);
                }
            }
        }else{
            $refFunction = new \ReflectionFunction($handler);
            $parameters = $refFunction->getParameters();
            $n_vars = [];
            if($parameters){
                foreach($parameters as $parameter){
                    $name_key = $parameter->getName();
                    if(array_key_exists($name_key, $vars)){
                        $n_vars[$name_key] = $vars[$name_key];
                    }
                }
            }
            $res = call_user_func_array($handler, $n_vars);
        }
        return $res;
    }
}
