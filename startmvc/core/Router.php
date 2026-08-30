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

/**
 * 路由器
 *
 * 统一的路由表 + 两种定义方式（均写在 config/route.php，可混用）：
 *   1. 流式 API（推荐）：直接调用 Router::get()/post()/group()/resource()
 *   2. 兼容旧配置：return 数组，由 loadLegacyConfig() 转译进同一张路由表
 *
 * action 写法：
 *   'Home/User/show'  模块/控制器/方法（方法自动加 Action 后缀）
 *   Closure           闭包，匹配参数按序传入
 *   目标中的 $1/$2 引用会被替换为匹配参数（兼容旧配置写法）
 */
class Router
{
    /**
     * 路由表：[method][uri] => ['action' => mixed, 'middleware' => array]
     * @var array
     */
    protected static $routes = [];

    /**
     * 原生正则路由：[method][] => ['regex' => string, 'action' => mixed, 'middleware' => array]
     * @var array
     */
    protected static $rawRoutes = [];

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
     * 路由是否已加载
     * @var bool
     */
    protected static $routesLoaded = false;

    /**
     * 路由参数占位符
     * @var array
     */
    protected static $patterns = [
        ':id' => '(\d+)',
        ':slug' => '([a-z0-9-]+)',
        ':any' => '(.+)',
        ':num' => '([0-9]+)',
        ':alpha' => '([a-zA-Z]+)',
        ':alphanum' => '([a-zA-Z0-9]+)',
    ];

    /**
     * 旧配置简便占位符 → 内部占位符（兼容 (:num) 写法）
     * @var array
     */
    protected static $legacyPatterns = [
        '(:num)' => ':num',
        '(:any)' => ':any',
        '(:alpha)' => ':alpha',
        '(:alnum)' => ':alphanum',
    ];

    /**
     * 自定义占位符
     * @param string $name 形如 :name
     * @param string $regex 正则（含括号分组）
     * @return void
     */
    public static function pattern($name, $regex)
    {
        self::$patterns[$name] = $regex;
    }

    /**
     * 添加 GET 路由
     * @param string $uri 路由 URI
     * @param mixed $action 控制器方法或闭包
     * @param array $middleware 路由级中间件
     * @return void
     */
    public static function get($uri, $action, $middleware = [])
    {
        self::addRoute('GET', $uri, $action, $middleware);
    }

    /**
     * 添加 POST 路由
     */
    public static function post($uri, $action, $middleware = [])
    {
        self::addRoute('POST', $uri, $action, $middleware);
    }

    /**
     * 添加 PUT 路由
     */
    public static function put($uri, $action, $middleware = [])
    {
        self::addRoute('PUT', $uri, $action, $middleware);
    }

    /**
     * 添加 PATCH 路由
     */
    public static function patch($uri, $action, $middleware = [])
    {
        self::addRoute('PATCH', $uri, $action, $middleware);
    }

    /**
     * 添加 DELETE 路由
     */
    public static function delete($uri, $action, $middleware = [])
    {
        self::addRoute('DELETE', $uri, $action, $middleware);
    }

    /**
     * 添加支持任意 HTTP 方法的路由
     */
    public static function any($uri, $action, $middleware = [])
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            self::addRoute($method, $uri, $action, $middleware);
        }
    }

    /**
     * 创建路由组（支持嵌套）
     * @param array|string $attributes 路由组属性或前缀
     * @param callable $callback 路由定义回调
     * @return void
     */
    public static function group($attributes, callable $callback)
    {
        $previousPrefix = self::$prefix;
        $previousMiddleware = self::$middleware;

        if (is_string($attributes)) {
            self::$prefix .= '/' . trim($attributes, '/');
        } else {
            if (isset($attributes['prefix'])) {
                self::$prefix .= '/' . trim($attributes['prefix'], '/');
            }
            if (isset($attributes['middleware'])) {
                self::$middleware = array_merge(self::$middleware, (array)$attributes['middleware']);
            }
        }

        $callback();

        self::$prefix = $previousPrefix;
        self::$middleware = $previousMiddleware;
    }

    /**
     * 添加 RESTful 资源路由（一行生成 7 条路由）
     * @param string $name 资源 URI，如 '/article'
     * @param string $controller 控制器目标，如 'Home/Article'
     * @param array $middleware 路由级中间件
     * @return void
     */
    public static function resource($name, $controller, $middleware = [])
    {
        $name = trim($name, '/');
        self::get("/{$name}", "{$controller}/index", $middleware);
        self::get("/{$name}/create", "{$controller}/create", $middleware);
        self::post("/{$name}", "{$controller}/store", $middleware);
        self::get("/{$name}/:id", "{$controller}/show", $middleware);
        self::get("/{$name}/:id/edit", "{$controller}/edit", $middleware);
        self::put("/{$name}/:id", "{$controller}/update", $middleware);
        self::delete("/{$name}/:id", "{$controller}/destroy", $middleware);
    }

    /**
     * 添加路由规则
     * @param string $method HTTP 方法，或 'ANY'（旧配置兼容，不限方法）
     * @param string $uri 路由 URI；以 '^' 开头时视为原生正则（兼容旧 '/^...$/' 写法）
     * @param mixed $action 控制器方法或闭包
     * @param array $middleware 路由级中间件
     * @return void
     */
    protected static function addRoute($method, $uri, $action, $middleware = [])
    {
        $uri = trim((string)$uri, '/');

        // 原生正则路由：不套前缀，按正则整串匹配
        if (strpos($uri, '^') === 0) {
            self::$rawRoutes[$method][] = [
                'regex' => $uri,
                'action' => $action,
                'middleware' => $middleware,
            ];
            return;
        }

        $uri = trim(self::$prefix . '/' . $uri, '/');
        if ($uri === '') {
            $uri = '/';
        }
        // 统一旧简便占位符写法
        $uri = strtr($uri, self::$legacyPatterns);

        self::$routes[$method][$uri] = [
            'action' => $action,
            'middleware' => array_merge(self::$middleware, $middleware),
        ];
    }

    /**
     * 加载路由定义（每请求一次）
     *
     * config/route.php 是唯一的路由定义文件，同时支持两种写法：
     *   1. 流式 API：在 return 之前直接调用 Router::get()/group()/resource()（推荐）
     *   2. 数组配置：return 一个二维数组（兼容旧版写法）
     * 两种写法可混用，定义结果进入同一张路由表。
     *
     * @return void
     */
    public static function loadRoutes()
    {
        if (self::$routesLoaded) {
            return;
        }
        self::$routesLoaded = true;

        // 路由配置文件在全局命名空间执行（include 不继承宿主命名空间），
        // 注册类别名让配置文件中可以直接写 Router::get(...)
        if (!class_exists('Router', false)) {
            class_alias(self::class, 'Router');
        }

        // 加载路由配置：文件中 return 的数组按旧版格式转译进路由表
        $legacy = Config::load('route');
        if (is_array($legacy)) {
            self::loadLegacyConfig($legacy);
        }
    }

    /**
     * 将旧版 config/route.php 数组转译进统一路由表
     *
     * 旧格式：['pattern', 'home/article/index/$1']
     * pattern 支持简便占位符 (:num) 和原生正则 '/^...$/' 两种写法；
     * 旧配置不限请求方法，按 ANY 注册以保持行为一致。
     *
     * @param array $routes
     * @return void
     */
    protected static function loadLegacyConfig(array $routes)
    {
        foreach ($routes as $route) {
            if (!is_array($route) || count($route) < 2) {
                continue;
            }
            list($pattern, $target) = $route;
            $pattern = (string)$pattern;

            // 原生正则写法 '/^...$/'：去掉两侧分隔符后注册
            if (strlen($pattern) > 2 && $pattern[0] === '/' && substr($pattern, -1) === '/') {
                self::addRoute('ANY', substr($pattern, 1, -1), $target);
                continue;
            }

            self::addRoute('ANY', $pattern, $target);
        }
    }

    /**
     * 根据 URI 和 HTTP 方法匹配路由
     * @param string $uri 请求 URI（不含查询串，可含前后斜杠）
     * @param string $method HTTP 方法
     * @return array|null [路由数据, 匹配参数] 或 null
     */
    public static function match($uri, $method)
    {
        $uri = trim((string)$uri, '/');
        if ($uri === '') {
            $uri = '/';
        }
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        // 候选方法：精确方法优先，ANY 兜底（旧配置不限方法）
        $candidates = $method === 'GET' ? ['GET', 'ANY'] : [$method, 'ANY'];

        // 1) 精确匹配（静态表，零正则开销）
        foreach ($candidates as $m) {
            if (isset(self::$routes[$m][$uri])) {
                return [self::$routes[$m][$uri], []];
            }
        }

        // 2) 原生正则路由（优先级高于内置占位符模式）
        foreach ($candidates as $m) {
            foreach (self::$rawRoutes[$m] ?? [] as $data) {
                if (preg_match('#' . $data['regex'] . '#', $uri, $matches)) {
                    array_shift($matches);
                    return [$data, $matches];
                }
            }
        }

        // 3) 占位符模式匹配
        foreach ($candidates as $m) {
            foreach (self::$routes[$m] ?? [] as $route => $data) {
                if (preg_match('#^' . self::compileRoute($route) . '$#', $uri, $matches)) {
                    array_shift($matches);
                    return [$data, $matches];
                }
            }
        }

        return null;
    }

    /**
     * 将路由 URI 编译为正则（占位符替换 + 斜杠转义）
     * @param string $route
     * @return string
     */
    protected static function compileRoute($route)
    {
        if (strpos($route, ':') !== false) {
            // strtr 按最长键优先替换，:alphanum 不会被 :alpha 截断
            $route = strtr($route, self::$patterns);
        }
        return str_replace('/', '\/', $route);
    }

    /**
     * 解析路由目标并执行（唯一的控制器解析入口）
     *
     * @param mixed $action '模块/控制器/方法' 字符串或闭包
     * @param array $params 路由匹配参数
     * @return mixed 控制器方法返回值
     * @throws \Exception 目标控制器或方法不存在时抛出 404 异常
     */
    public static function resolveAction($action, array $params = [])
    {
        if ($action instanceof \Closure) {
            return call_user_func_array($action, $params);
        }

        $action = trim((string)$action, '/');

        // 目标中的 $1/$2 引用替换为匹配参数（兼容旧配置写法）
        if ($params && strpos($action, '$') !== false) {
            $action = preg_replace_callback('/\$(\d+)/', function ($m) use ($params) {
                $i = (int)$m[1] - 1;
                return isset($params[$i]) ? $params[$i] : '';
            }, $action);
            $params = [];
        }

        $parts = array_values(array_filter(explode('/', $action), 'strlen'));
        $defaultModule = config('default_module') ?: 'home';
        $defaultController = config('default_controller') ?: 'Index';
        $defaultAction = config('default_action') ?: 'index';

        $module = strtolower($parts[0] ?? $defaultModule);
        $controller = self::convertController($parts[1] ?? $defaultController);
        $method = self::convertAction($parts[2] ?? $defaultAction);
        $args = array_slice($parts, 3);

        // View 等组件依赖这些常量
        if (!defined('MODULE')) define('MODULE', $module);
        if (!defined('CONTROLLER')) define('CONTROLLER', $controller);
        if (!defined('ACTION')) define('ACTION', $method);

        $class = APP_NAMESPACE . "\\{$module}\\controller\\{$controller}Controller";
        if (!class_exists($class)) {
            throw new \Exception("控制器不存在: {$class}", 404);
        }

        $method .= 'Action';
        return Loader::make($class, $method, array_merge($args, $params));
    }

    /**
     * 解析路由规则（URL 结构直解析：模块/控制器/方法/参数）
     *
     * 仅负责 URL 结构解析；路由表匹配（流式 API 与旧配置数组）由 match() 负责。
     *
     * @param string $uri 请求 URI
     * @return array [module, controller, action, params]
     */
    public static function parse($uri)
    {
        // 移除查询字符串
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // 移除前后的斜杠
        $uri = trim($uri, '/');

        $defaultModule = config('default_module') ?: 'home';
        $defaultController = config('default_controller') ?: 'Index';
        $defaultAction = config('default_action') ?: 'index';

        // 如果URI为空，设置为首页
        if (empty($uri)) {
            return [$defaultModule, $defaultController, $defaultAction, []];
        }

        // 智能处理URL后缀
        $urlSuffix = config('common.url_suffix') ?: '';
        if (!empty($urlSuffix) && strlen($uri) > strlen($urlSuffix)) {
            $suffixPos = strrpos($uri, $urlSuffix);
            if ($suffixPos !== false && $suffixPos == strlen($uri) - strlen($urlSuffix)) {
                $uri = substr($uri, 0, $suffixPos);
            }
        }

        $parts = explode('/', $uri);
        $possibleModule = strtolower($parts[0]);

        // 如果模块目录存在，按正常方式解析
        if (is_dir(APP_PATH . $possibleModule)) {
            return [
                $possibleModule,
                isset($parts[1]) ? self::convertController($parts[1]) : $defaultController,
                isset($parts[2]) ? self::convertAction($parts[2]) : $defaultAction,
                array_slice($parts, 3),
            ];
        }

        // 模块目录不存在，假设省略了默认模块，将第一个部分作为控制器
        return [
            $defaultModule,
            isset($parts[0]) ? self::convertController($parts[0]) : $defaultController,
            isset($parts[1]) ? self::convertAction($parts[1]) : $defaultAction,
            array_slice($parts, 2),
        ];
    }

    /**
     * 转换URL片段为控制器名称 (StudlyCase)
     * @param string $part
     * @return string
     */
    protected static function convertController($part)
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($part))));
    }

    /**
     * 转换URL片段为方法名称 (camelCase)
     * @param string $part
     * @return string
     */
    protected static function convertAction($part)
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($part))));
        return lcfirst($studly);
    }
}
