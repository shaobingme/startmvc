<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

namespace startmvc\core;

use startmvc\core\Middleware;

class Router 
{
    /**
     * 单例实例
     * @var Router
     */
    protected static $instance;
    
    /**
     * 获取Router实例
     * @return Router
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 保护构造函数，防止外部实例化
     */
    protected function __construct()
    {
    }
    
    /**
     * 防止克隆
     */
    private function __clone()
    {
    }
    
    /**
     * 保存所有注册的路由
     * @var array
     */
    protected static $routes = [];
    
    /**
     * 当前路由组前缀
     * @var string
     */
    protected static $prefix = '';
    
    /**
     * 当前路由组中间件
     * @var array
     */
    protected static $middleware = [];
    
    /**
     * 路由参数模式
     * @var array
     */
    protected static $patterns = [
        ':id' => '(\d+)',
        ':slug' => '([a-z0-9-]+)',
        ':any' => '(.+)',
        ':num' => '([0-9]+)',
        ':alpha' => '([a-zA-Z]+)',
        ':alphanum' => '([a-zA-Z0-9]+)'
    ];
    
    /**
     * 简单模式替换规则
     * @var array
     */
    protected static $simplePatterns = [
        '(:any)' => '(.+)',
        '(:num)' => '([0-9]+)',
        '(:alpha)' => '([a-zA-Z]+)',
        '(:alphanum)' => '([a-zA-Z0-9]+)'
    ];
    
    /**
     * 添加GET路由
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    public static function get($uri, $action, $middleware = [])
    {
        self::addRoute('GET', $uri, $action, $middleware);
    }
    
    /**
     * 添加POST路由
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    public static function post($uri, $action, $middleware = [])
    {
        self::addRoute('POST', $uri, $action, $middleware);
    }
    
    /**
     * 添加PUT路由
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    public static function put($uri, $action, $middleware = [])
    {
        self::addRoute('PUT', $uri, $action, $middleware);
    }
    
    /**
     * 添加DELETE路由
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    public static function delete($uri, $action, $middleware = [])
    {
        self::addRoute('DELETE', $uri, $action, $middleware);
    }
    
    /**
     * 添加支持任意HTTP方法的路由
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    public static function any($uri, $action, $middleware = [])
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        foreach ($methods as $method) {
            self::addRoute($method, $uri, $action, $middleware);
        }
    }
    
    /**
     * 创建路由组
     * @param array|string $attributes 路由组属性或前缀
     * @param callable $callback 路由定义回调
     * @return void
     */
    public static function group($attributes, callable $callback)
    {
        // 保存当前组状态
        $previousPrefix = self::$prefix;
        $previousMiddleware = self::$middleware;
        
        // 设置新组属性
        if (is_string($attributes)) {
            self::$prefix .= $attributes;
        } else {
            if (isset($attributes['prefix'])) {
                self::$prefix .= '/' . trim($attributes['prefix'], '/');
            }
            
            if (isset($attributes['middleware'])) {
                $middleware = (array) $attributes['middleware'];
                self::$middleware = array_merge(self::$middleware, $middleware);
            }
        }
        
        // 执行回调
        $callback();
        
        // 恢复先前状态
        self::$prefix = $previousPrefix;
        self::$middleware = $previousMiddleware;
    }
    
    /**
     * 添加RESTful资源路由
     * @param string $name 资源名称
     * @param string $controller 控制器类
     * @return void
     */
    public static function resource($name, $controller)
    {
        $name = trim($name, '/');
        self::get("/$name", "$controller@index");
        self::get("/$name/create", "$controller@create");
        self::post("/$name", "$controller@store");
        self::get("/$name/:id", "$controller@show");
        self::get("/$name/:id/edit", "$controller@edit");
        self::put("/$name/:id", "$controller@update");
        self::delete("/$name/:id", "$controller@destroy");
    }
    
    /**
     * 添加路由规则
     * @param string $method HTTP方法
     * @param string $uri 路由URI
     * @param mixed $action 控制器方法或回调函数
     * @param array $middleware 中间件数组
     * @return void
     */
    protected static function addRoute($method, $uri, $action, $middleware = [])
    {
        // 处理前缀
        $uri = self::$prefix . '/' . trim($uri, '/');
        $uri = trim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }
        
        // 存储路由
        self::$routes[$method][$uri] = [
            'action' => $action,
            'middleware' => $middleware
        ];
    }
    
    /**
     * 根据URI和方法匹配路由
     * @param string $uri 请求URI
     * @param string $method HTTP方法
     * @return array|null 匹配的路由和参数
     */
    public static function match($uri, $method)
    {
        $uri = trim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }
        
        // 检查精确匹配
        if (isset(self::$routes[$method][$uri])) {
            return [self::$routes[$method][$uri], []];
        }
        
        // 检查模式匹配
        foreach (self::$routes[$method] ?? [] as $route => $data) {
            $pattern = self::compileRoute($route);
            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                array_shift($matches); // 移除完整匹配
                return [$data, $matches];
            }
        }
        
        return null;
    }
    
    /**
     * 将路由转换为正则表达式
     * @param string $route 路由URI
     * @return string 编译后的正则表达式
     */
    protected static function compileRoute($route)
    {
        if (strpos($route, ':') !== false) {
            foreach (self::$patterns as $key => $pattern) {
                $route = str_replace($key, $pattern, $route);
            }
        }
        
        return str_replace('/', '\/', $route);
    }
    
    /**
     * 从配置文件加载路由定义
     * @param array $routes 路由配置数组
     * @return void
     */
    public static function loadFromConfig(array $routes)
    {
        foreach ($routes as $route) {
            if (is_array($route) && count($route) >= 2) {
                $pattern = $route[0];
                $action = $route[1];
                
                // 检查是否为正则表达式格式 /pattern/
                if (is_string($pattern) && strlen($pattern) > 2 && $pattern[0] === '/' && $pattern[strlen($pattern) - 1] === '/') {
                    // 正则表达式路由
                    self::regexRoute('GET', $pattern, $action);
                } else {
                    // 简单模式路由
                    self::simpleRoute('GET', $pattern, $action);
                }
            }
        }
    }
    
    /**
     * 添加简单模式路由
     * @param string $method HTTP方法
     * @param string $pattern 路由模式
     * @param string $action 控制器路径
     * @return void
     */
    public static function simpleRoute($method, $pattern, $action)
    {
        // 转换简单模式为正则表达式
        $regex = $pattern;
        foreach (self::$simplePatterns as $key => $replacement) {
            $regex = str_replace($key, $replacement, $regex);
        }
        
        // 如果不是正则表达式，将其转换为精确匹配的正则
        if ($regex === $pattern) {
            $regex = '/^' . preg_quote($pattern, '/') . '$/';
        } else {
            $regex = '/^' . str_replace('/', '\/', $regex) . '$/';
        }
        
        self::$routes['config'][] = [
            'type' => 'simple',
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'action' => $action
        ];
    }
    
    /**
     * 添加正则表达式路由
     * @param string $method HTTP方法
     * @param string $regex 正则表达式
     * @param string $action 控制器路径
     * @return void
     */
    public static function regexRoute($method, $regex, $action)
    {
        self::$routes['config'][] = [
            'type' => 'regex',
            'method' => $method,
            'regex' => $regex,
            'action' => $action
        ];
    }
    
    /**
     * 解析路由并执行匹配的操作
     * @return mixed
     * @throws \Exception 未找到路由时抛出异常
     */
    public static function dispatch()
    {
        $uri = $_SERVER['REQUEST_URI'];
        
        // 移除查询字符串
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        // 处理PUT、DELETE请求
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        // 先尝试匹配新的路由格式
        $result = self::match($uri, $method);
        if ($result !== null) {
            list($route, $params) = $result;
            
            // 获取路由中间件
            $routeMiddleware = $route['middleware'] ?? [];
            
            // 创建请求对象
            $request = new Request();
            
            // 应用路由特定的中间件
            $response = Middleware::pipeline($routeMiddleware, $request, function($request) use ($route, $params) {
                // 执行控制器方法或回调
                $action = $route['action'];
                
                if (is_callable($action)) {
                    return call_user_func_array($action, $params);
                }
                
                // 解析控制器和方法
                if (is_string($action)) {
                    list($controller, $method) = explode('@', $action);
                    $controller = 'app\\controllers\\' . $controller;
                    $instance = new $controller();
                    return call_user_func_array([$instance, $method], $params);
                }
            });
            
            return $response;
        }
        
        // 尝试匹配配置文件中的路由
        $configResult = self::matchConfigRoutes($uri);
        if ($configResult !== null) {
            list($target, $params) = $configResult;
            
            // 处理控制器路径
            $parts = explode('/', $target);
            if (count($parts) >= 2) {
                $methodName = array_pop($parts);
                $controllerName = array_pop($parts);
                $namespace = !empty($parts) ? implode('\\', $parts) : 'app\\controllers';
                
                $controllerClass = $namespace . '\\' . ucfirst($controllerName) . 'Controller';
                $controller = new $controllerClass();
                return call_user_func_array([$controller, $methodName], $params);
            } else {
                throw new \Exception("Invalid route target: $target", 500);
            }
        }
        
        // 如果没有匹配到路由，尝试使用默认解析方式
        $parseResult = self::parse($uri);
        if ($parseResult && count($parseResult) >= 3) {
            list($module, $controller, $action, $params) = $parseResult;
            
            // 构建控制器类名
            $controllerClass = 'app\\' . $module . '\\controller\\' . ucfirst($controller) . 'Controller';
            
            // 检查控制器类是否存在
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                
                // 检查方法是否存在
                if (method_exists($controllerInstance, $action)) {
                    return call_user_func_array([$controllerInstance, $action], $params ?: []);
                }
            }
        }
        
        throw new \Exception("Route not found: $uri [$method]", 404);
    }
    
    /**
     * 匹配配置文件中定义的路由
     * @param string $uri 请求URI
     * @return array|null 匹配的目标和参数
     */
    protected static function matchConfigRoutes($uri)
    {
        $uri = trim($uri, '/');
        
        foreach (self::$routes['config'] ?? [] as $route) {
            $pattern = $route['regex'];
            $target = $route['action'];
            
            // 移除分隔符
            if ($route['type'] === 'regex') {
                $pattern = substr($pattern, 1, -1);
            }
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // 移除完整匹配
                
                // 替换目标中的 $1, $2 等为实际参数
                $replacedTarget = preg_replace_callback('/\$(\d+)/', function($m) use ($matches) {
                    $index = intval($m[1]) - 1;
                    return isset($matches[$index]) ? $matches[$index] : '';
                }, $target);
                
                return [$replacedTarget, $matches];
            }
        }
        
        return null;
    }
    
    /**
     * 解析路由规则
     * @param string $uri 请求URI
     * @return array 解析结果
     */
    public static function parse($uri)
    {
        // 移除查询字符串
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        // 移除前后的斜杠
        $uri = trim($uri, '/');
        
        // 获取默认模块配置
        $defaultModule = config('default_module') ?: 'home';
        $defaultController = config('default_controller') ?: 'Index';
        $defaultAction = config('default_action') ?: 'index';
        
        // 如果URI为空，设置为首页
        if (empty($uri)) {
            return [$defaultModule, $defaultController, $defaultAction, []];
        }
        
        // 智能处理URL后缀
        $originalUri = $uri;
        $urlSuffix = config('common.url_suffix') ?: '';
        
        // 如果配置了URL后缀且URI以该后缀结尾，则移除后缀进行路由匹配
        if (!empty($urlSuffix) && strlen($uri) > strlen($urlSuffix)) {
            $suffixPos = strrpos($uri, $urlSuffix);
            if ($suffixPos !== false && $suffixPos == strlen($uri) - strlen($urlSuffix)) {
                $uri = substr($uri, 0, $suffixPos);
            }
        }
        
        // 加载路由配置
        $routes = \startmvc\core\Config::load('route') ?: [];
        
        // 遍历配置的路由规则
        foreach ($routes as $route) {
            if (is_array($route) && count($route) >= 2) {
                $pattern = $route[0];
                $target = $route[1];
                
                // 处理正则表达式路由
                if (is_string($pattern) && strlen($pattern) > 2 && $pattern[0] === '/' && $pattern[strlen($pattern) - 1] === '/') {
                    if (preg_match($pattern, $uri, $matches)) {
                        // 替换目标中的 $1, $2 等为实际参数
                        $target = preg_replace_callback('/\$(\d+)/', function($m) use ($matches) {
                            $index = intval($m[1]);
                            return isset($matches[$index]) ? $matches[$index] : '';
                        }, $target);
                        
                        $parts = explode('/', $target);
                        return [
                            isset($parts[0]) ? strtolower($parts[0]) : $defaultModule,
                            isset($parts[1]) ? ucfirst(strtolower($parts[1])) : $defaultController,
                            isset($parts[2]) ? strtolower($parts[2]) : $defaultAction,
                            array_slice($parts, 3)
                        ];
                    }
                }
                // 处理简单模式路由
                else {
                    $regex = $pattern;
                    foreach (self::$simplePatterns as $key => $replacement) {
                        $regex = str_replace($key, $replacement, $regex);
                    }
                    
                    if (preg_match('#^' . $regex . '$#', $uri, $matches)) {
                        array_shift($matches); // 移除完整匹配
                        $parts = explode('/', $target);
                        
                        // 替换目标中的参数
                        foreach ($matches as $i => $match) {
                            $target = str_replace('$' . ($i + 1), $match, $target);
                        }
                        
                        $parts = explode('/', $target);
                        return [
                            isset($parts[0]) ? strtolower($parts[0]) : $defaultModule,
                            isset($parts[1]) ? ucfirst(strtolower($parts[1])) : $defaultController,
                            isset($parts[2]) ? strtolower($parts[2]) : $defaultAction,
                            array_slice($parts, 3)
                        ];
                    }
                }
            }
        }
        
        // 如果没有匹配的路由规则，使用默认的解析方式
        $parts = explode('/', $uri);
        
        // 智能解析：尝试判断是否省略了默认模块
        if (count($parts) >= 1) {
            // 检查第一个部分是否是已存在的模块
            $possibleModule = strtolower($parts[0]); // 模块名转小写进行检查
            
            // 使用绝对路径检查模块目录
            if (defined('APP_PATH')) {
                $modulePath = APP_PATH . $possibleModule;
            } else {
                // 如果 APP_PATH 未定义，使用相对路径
                $modulePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $possibleModule;
            }
            

            
            // 如果模块目录存在，按正常方式解析
            if (is_dir($modulePath)) {
                return [
                    $possibleModule, // 使用小写的模块名
                    isset($parts[1]) ? ucfirst(strtolower($parts[1])) : $defaultController, // 控制器名首字母大写
                    isset($parts[2]) ? strtolower($parts[2]) : $defaultAction, // 方法名小写
                    array_slice($parts, 3)
                ];
            } else {
                // 如果模块目录不存在，假设省略了默认模块，将第一个部分作为控制器
                return [
                    $defaultModule,
                    isset($parts[0]) ? ucfirst(strtolower($parts[0])) : $defaultController, // 控制器名首字母大写
                    isset($parts[1]) ? strtolower($parts[1]) : $defaultAction, // 方法名小写
                    array_slice($parts, 2)
                ];
            }
        }
        
        return [
            $defaultModule,
            $defaultController,
            $defaultAction,
            []
        ];
    }
} 