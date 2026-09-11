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
     * 静态路由表（URI 中不含 : 占位符）：[method][uri] => ['action' => mixed, 'middleware' => array]
     *
     * 精确命中走哈希查找，零正则开销——这是绝大多数请求的实际路径。
     * @var array
     */
    protected static $staticRoutes = [];

    /**
     * 动态路由表（URI 中含 : 占位符）：
     * [method][uri] => ['regex' => string, 'action' => mixed, 'middleware' => array]
     *
     * regex 在注册期就编译好（含首尾锚定），匹配时只做 preg_match，不再重复编译。
     * @var array
     */
    protected static $dynamicRoutes = [];

    /**
     * 原生正则路由：[method][] => ['regex' => string, 'action' => mixed, 'middleware' => array]
     * @var array
     */
    protected static $rawRoutes = [];

    /**
     * 路由编译缓存是否启用（null 表示尚未读取配置）
     * @var bool|null
     */
    protected static $cacheEnabled = null;

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

        $record = [
            'action' => $action,
            'middleware' => array_merge(self::$middleware, $middleware),
        ];

        // 静态 / 动态分表：
        //   静态路由进哈希表，请求命中时 O(1)，完全绕开正则；
        //   动态路由在此刻就编译好正则并随记录存下，匹配循环里只剩 preg_match。
        // 两者都用 $uri 作键，重复注册同一 URI 时行为与旧版一致（后者覆盖前者、且保留首次插入位置）。
        if (strpos($uri, ':') === false) {
            self::$staticRoutes[$method][$uri] = $record;
        } else {
            $record['uri'] = $uri;
            $record['regex'] = self::compileToRegex($uri);
            self::$dynamicRoutes[$method][$uri] = $record;
        }
    }

    /**
     * 加载路由定义（每请求一次）
     *
     * config/route.php 是唯一的路由定义文件，同时支持两种写法：
     *   1. 流式 API：在 return 之前直接调用 Router::get()/group()/resource()（推荐）
     *   2. 数组配置：return 一个二维数组（兼容旧版写法）
     * 两种写法可混用，定义结果进入同一张路由表。
     *
     * 编译结果会落盘到 runtime/cache/routes.php：
     * 下次请求直接恢复已编译好的路由表，跳过配置文件执行与正则编译。
     * 缓存以「route.php mtime + 框架版本号」为失效键，改路由或升级框架后自动重建；
     * 路由中含闭包时不写缓存（闭包不可序列化），退回每请求即时编译。
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
        // 注册类别名让配置文件中可以直接写 Router::get(...)。
        // 必须在缓存判断之前执行：缓存命中时同样不 include 配置文件，
        // 但应用代码/后续动态注册仍可能依赖这个全局别名。
        if (!class_exists('Router', false)) {
            class_alias(self::class, 'Router');
        }

        // 优先恢复编译缓存：命中则连 route.php 都不需要 include
        if (self::loadCompiled()) {
            return;
        }

        // 加载路由配置：文件中 return 的数组按旧版格式转译进路由表
        $legacy = Config::load('route');
        if (is_array($legacy)) {
            self::loadLegacyConfig($legacy);
        }

        // 编译结果写盘，供后续请求直接复用
        self::saveCompiled();
    }

    /**
     * 路由编译缓存文件路径
     * @return string
     */
    public static function cacheFile()
    {
        return CACHE_PATH . 'routes.php';
    }

    /**
     * 删除路由编译缓存（部署脚本或调试时可主动调用）
     * @return bool 文件不存在或删除成功均返回 true
     */
    public static function clearCache()
    {
        $file = self::cacheFile();
        return !is_file($file) || @unlink($file);
    }

    /**
     * 是否启用路由编译缓存
     *
     * 取 config('route_cache')，未配置时默认启用——缓存会在路由配置或框架版本
     * 变化时自动失效，不依赖人工清理，因此默认开启是安全的。
     *
     * @return bool
     */
    protected static function cacheEnabled()
    {
        if (self::$cacheEnabled === null) {
            $enabled = config('route_cache');
            self::$cacheEnabled = ($enabled === null) ? true : (bool)$enabled;
        }
        return self::$cacheEnabled;
    }

    /**
     * 路由配置文件的修改时间（缓存失效依据）
     * @return int
     */
    protected static function routesMtime()
    {
        $file = CONFIG_PATH . 'route.php';
        return is_file($file) ? (int)filemtime($file) : 0;
    }

    /**
     * 从编译缓存恢复路由表
     *
     * 缓存损坏（被手工改坏、写入中断）时静默返回 false，由调用方回退到重新编译，
     * 保证缓存问题永远不会演变成整站 500。
     *
     * @return bool 是否成功恢复
     */
    protected static function loadCompiled()
    {
        if (!self::cacheEnabled()) {
            return false;
        }

        $file = self::cacheFile();
        if (!is_file($file)) {
            return false;
        }

        try {
            $cache = include $file;
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($cache)
            || ($cache['version'] ?? null) !== SM_VERSION
            || ($cache['mtime'] ?? null) !== self::routesMtime()
            || !isset($cache['static'], $cache['dynamic'], $cache['raw'])
            || !is_array($cache['static']) || !is_array($cache['dynamic']) || !is_array($cache['raw'])
        ) {
            return false;
        }

        self::$staticRoutes = $cache['static'];
        self::$dynamicRoutes = $cache['dynamic'];
        self::$rawRoutes = $cache['raw'];
        return true;
    }

    /**
     * 将编译好的路由表写入缓存
     *
     * 采用「临时文件 + rename」原子写：rename 在同一分区内是原子操作，
     * 并发请求要么读到完整旧文件、要么读到完整新文件，不会读到半截内容。
     *
     * @return void
     */
    protected static function saveCompiled()
    {
        if (!self::cacheEnabled()) {
            return;
        }

        $file = self::cacheFile();

        // 闭包路由无法 var_export，整体放弃缓存，并清掉可能残留的旧缓存
        if (!self::isCacheable()) {
            if (is_file($file)) {
                @unlink($file);
            }
            return;
        }

        $payload = [
            'version' => SM_VERSION,
            'mtime' => self::routesMtime(),
            'static' => self::$staticRoutes,
            'dynamic' => self::$dynamicRoutes,
            'raw' => self::$rawRoutes,
        ];

        $content = "<?php\n"
            . "// StartMVC 路由编译缓存（由 Router 自动生成，请勿手工编辑）。\n"
            . "// 路由配置或框架版本变化时自动失效重建，也可调用 Router::clearCache() 删除。\n"
            . "return " . var_export($payload, true) . ";\n";

        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return;
        }
        // 纯数据文件，无需 opcache 反复编译
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    /**
     * 路由表是否可缓存
     *
     * 闭包（路由回调）无法序列化，只要路由表中存在闭包就整体不写缓存。
     *
     * @return bool
     */
    protected static function isCacheable()
    {
        foreach ([self::$staticRoutes, self::$dynamicRoutes, self::$rawRoutes] as $group) {
            foreach ($group as $routes) {
                foreach ($routes as $record) {
                    if (($record['action'] ?? null) instanceof \Closure) {
                        return false;
                    }
                }
            }
        }
        return true;
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
     *
     * 三段式匹配，开销从低到高：
     *   1. 静态表哈希精确匹配（零正则）
     *   2. 原生正则路由
     *   3. 占位符动态路由（正则已在注册期编译好）
     *
     * @param string $uri 请求 URI（不含查询串，可含前后斜杠）
     * @param string $method HTTP 方法
     * @return array|null [路由数据, 匹配参数] 或 null；动态路由数据额外含 uri/regex 两个键
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

        // 1) 静态表精确匹配（哈希查找，零正则开销）
        foreach ($candidates as $m) {
            if (isset(self::$staticRoutes[$m][$uri])) {
                return [self::$staticRoutes[$m][$uri], []];
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

        // 3) 占位符模式匹配（正则由 addRoute 预编译，此处不再调用 compileToRegex）
        foreach ($candidates as $m) {
            foreach (self::$dynamicRoutes[$m] ?? [] as $data) {
                if (preg_match($data['regex'], $uri, $matches)) {
                    array_shift($matches);
                    return [$data, $matches];
                }
            }
        }

        return null;
    }

    /**
     * 导出当前路由表（只读）
     *
     * 供 CLI（route:list）等工具读取路由表使用，不触发路由加载——
     * 调用方需自行确保 loadRoutes() 已执行。
     *
     * @return array [HTTP方法 => [['uri' => string, 'action' => mixed,
     *               'middleware' => array, 'type' => string], ...]]
     *               type 取值：static（精确匹配）/ dynamic（占位符）/ raw（原生正则）
     */
    public static function dump()
    {
        $tables = [
            'static' => self::$staticRoutes,
            'dynamic' => self::$dynamicRoutes,
            'raw' => self::$rawRoutes,
        ];

        $result = [];
        foreach ($tables as $type => $table) {
            foreach ($table as $method => $routes) {
                foreach ($routes as $key => $record) {
                    // 三种表的键含义不同：静态表的键就是 URI；动态表另有 uri 键；
                    // 原生正则表是自增键，只能拿编译后的正则代表
                    if (isset($record['uri'])) {
                        $uri = $record['uri'];
                    } elseif (isset($record['regex'])) {
                        $uri = $record['regex'];
                    } else {
                        $uri = (string)$key;
                    }

                    $result[$method][] = [
                        'uri' => $uri,
                        'action' => isset($record['action']) ? $record['action'] : null,
                        'middleware' => isset($record['middleware']) ? $record['middleware'] : [],
                        'type' => $type,
                    ];
                }
            }
        }

        // 输出顺序稳定，便于 diff 与人工核对
        ksort($result);
        return $result;
    }

    /**
     * 将路由 URI 编译为完整匹配正则（占位符替换 + 斜杠转义 + 首尾锚定）
     *
     * 仅在路由注册期调用一次，结果随路由表缓存复用。
     *
     * @param string $uri 含 :占位符 的路由 URI
     * @return string 形如 #^article\/(\d+)$#
     */
    protected static function compileToRegex($uri)
    {
        if (strpos($uri, ':') !== false) {
            // strtr 按最长键优先替换，:alphanum 不会被 :alpha 截断
            $uri = strtr($uri, self::$patterns);
        }
        return '#^' . str_replace('/', '\/', $uri) . '$#';
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
            // 路由闭包同样支持签名注入：类类型参数（如 Request）走容器，其余按位置传匹配参数
            return Loader::invoke($action, $params);
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

        // 把解析结果写入请求上下文（View / lang() / Controller::model() 的统一数据源），
        // 并补绑为容器共享实例：CLI / 单元测试等未经 App::run 绑定的场景
        // 也能读到同一份上下文，控制器构造时注入的也是这个实例
        $container = Container::getInstance();
        $request = $container->make(Request::class);
        if ($request instanceof Request) {
            $request->setRouteContext($module, $controller, $method);
            $container->singleton(Request::class, $request);
        }

        // 兼容层：常量仍供存量代码与模板直接读取。
        // 注意 define() 只在该常量首次定义时生效，无法反映同一进程内的多次路由
        // 解析（CLI 下会残留首次的值），新代码请改用 Request::currentRoute() 读取
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
