<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

namespace startmvc\core;

/**
 * 请求对象
 *
 * 双态调用设计（实例 API 声明为 private，类外经魔术方法分发）：
 *   - 实例调用（推荐）：$request->method() —— 读取构造时捕获的请求快照，
 *     支持测试注入模拟数据、_method 伪装、中间件属性袋；
 *     App::run 创建的唯一实例贯穿中间件管道，并注入控制器/闭包方法签名
 *   - 静态调用（兼容）：Request::method() —— 经 __callStatic 基于当前
 *     超全局变量构造临时实例，行为与历史版本一致，保留给存量代码
 *
 * @method string uri() 原始 REQUEST_URI（含查询串）
 * @method string path() 解析后的路由路径（已剥离查询串/入口文件/URL后缀）
 * @method string method() 请求方法（POST + _method 伪装为 PUT/DELETE/PATCH）
 * @method bool isGet() 是否GET请求
 * @method bool isPost() 是否POST请求
 * @method bool isAjax() 是否AJAX请求
 * @method bool isHttps() 是否HTTPS请求
 * @method string ip() 客户端IP（可信代理规则见 resolveIp）
 * @method array all() 所有输入（GET + POST）
 * @method mixed input(string $key = null, mixed $default = null) 获取输入值
 * @method mixed get(string $key, array $options = []) 获取GET参数
 * @method mixed post(string $key = '', array|mixed $options = []) 获取POST参数
 * @method string postInput() 原始POST输入
 * @method mixed getJson(bool $assoc = true) JSON格式POST数据
 * @method mixed header(string $key = null, mixed $default = null) 获取请求头
 * @method array headers() 所有请求头
 * @method array route(string $key = null, mixed $default = null) 路由上下文（module/controller/action）
 */
class Request
{
    /**
     * 请求快照（构造时捕获，测试时可注入模拟数据，脱离真实超全局）
     * @var array
     */
    protected $server;
    protected $get;
    protected $post;

    /**
     * 中间件属性袋：中间件可通过 $request->foo = 'bar' 附加状态，
     * 随唯一请求实例贯穿管道传递到控制器（同时避免 PHP8.2 动态属性弃用告警）
     * @var array
     */
    protected $attributes = [];

    /**
     * 当前路由上下文：module / controller / action
     *
     * 由 Router::resolveAction() 在解析路由目标时写入，View / lang() /
     * Controller::model() 等组件统一从这里读取。相比被它取代的
     * MODULE / CONTROLLER / ACTION 常量：可重复写入（CLI 下多次分发不会
     * 残留首次的值）、可读取、可注入，常量仅作为兼容层保留。
     *
     * @var array
     */
    protected $routeContext = [];

    /**
     * path()/method() 解析缓存
     * @var string|null
     */
    protected $pathCache;
    protected $methodCache;

    /**
     * 构造请求实例
     * @param array|null $server 缺省捕获 $_SERVER
     * @param array|null $get    缺省捕获 $_GET
     * @param array|null $post   缺省捕获 $_POST
     */
    public function __construct(array $server = null, array $get = null, array $post = null)
    {
        $this->server = $server ?? $_SERVER;
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
    }

    /* ==================== 魔术分发 ==================== */

    /**
     * 静态调用兼容层：Request::isAjax() 等旧式静态调用
     *
     * 每次调用基于当前超全局变量构造临时实例，保持"实时读取"的旧有行为
     * （框架内 Csrf/Cookie/Controller 及存量用户代码依赖此入口）
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     * @throws \BadMethodCallException 方法不存在时
     */
    public static function __callStatic($name, $arguments)
    {
        $request = new static();
        if (!method_exists($request, $name)) {
            throw new \BadMethodCallException('调用不存在的方法: Request::' . $name . '()');
        }
        return $request->{$name}(...$arguments);
    }

    /**
     * 实例调用分发：$request->isAjax() 等
     * （实例 API 声明为 private，类外调用经此分发到快照实现）
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return mixed
     * @throws \BadMethodCallException 方法不存在时
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            throw new \BadMethodCallException('调用不存在的方法: Request::' . $name . '()');
        }
        return $this->{$name}(...$arguments);
    }

    /* ==================== 中间件属性袋 ==================== */

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    /* ==================== 路由上下文 ==================== */

    /**
     * 设置当前路由上下文
     *
     * 由 Router::resolveAction() 在解析出路由目标后写入，是 View / lang() /
     * Controller::model() 的统一数据源，取代原先 define() 全局常量传状态的写法。
     *
     * @param string $module 模块名
     * @param string $controller 控制器名
     * @param string $action 方法名（不含 Action 后缀）
     * @return $this
     */
    public function setRouteContext($module, $controller, $action)
    {
        $this->routeContext = [
            'module' => $module,
            'controller' => $controller,
            'action' => $action,
        ];
        return $this;
    }

    /**
     * 读取路由上下文
     *
     * 数据源优先级：请求实例上的路由上下文 → 兼容层常量 → 调用方默认值。
     * CLI / 队列 / 单元测试等"无路由"场景因此不再像常量那样直接失效，
     * 而是安全回退到默认值。
     *
     * @param string|null $key 键名（module/controller/action），为 null 时返回全部
     * @param mixed $default 键不存在时的默认值
     * @return mixed
     */
    private function route($key = null, $default = null)
    {
        $context = $this->routeContext;
        if (empty($context)) {
            // 兼容层：常量仍可能被存量代码或模板直接读取，
            // 注意 define() 只在首次定义时生效，无法反映同一进程内的多次路由解析
            $context = [
                'module' => defined('MODULE') ? MODULE : null,
                'controller' => defined('CONTROLLER') ? CONTROLLER : null,
                'action' => defined('ACTION') ? ACTION : null,
            ];
        }

        if ($key === null) {
            return $context;
        }
        return $context[$key] ?? $default;
    }

    /**
     * 读取当前请求的路由上下文（静态入口）
     *
     * 供 lang() 等无法注入 Request 的全局函数使用：优先取容器中绑定的当前
     * 请求实例，未绑定时回退到兼容层常量与调用方默认值。
     *
     * @param string|null $key 键名（module/controller/action），为 null 时返回全部
     * @param mixed $default 键不存在时的默认值
     * @return mixed
     */
    public static function currentRoute($key = null, $default = null)
    {
        $request = Container::getInstance()->make(static::class);
        if (!$request instanceof self) {
            return $default;
        }
        return $request->route($key, $default);
    }

    /* ==================================================================
     * 实例 API（private：保证静态/实例两种调用方式都能经魔术方法分发，
     * 详见类注释。类内部相互调用不受影响）
     * ================================================================== */

    /**
     * 原始 REQUEST_URI（含查询串）
     * @return string
     */
    private function uri()
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * 解析后的路由路径（供路由匹配使用）
     *
     * 清洗规则（原 App::handleRequest 中的逻辑迁入）：
     *   1. 去掉查询字符串
     *   2. 去掉首尾斜杠
     *   3. 过滤入口文件名（如 index.php/user/1 → user/1）
     *   4. 剥离 URL 后缀（如 .html，规则与 Router::parse 一致）
     *
     * @return string
     */
    private function path()
    {
        if ($this->pathCache !== null) {
            return $this->pathCache;
        }

        $uri = $this->uri();

        // 移除查询字符串
        $questionPos = strpos($uri, '?');
        if ($questionPos !== false) {
            $uri = substr($uri, 0, $questionPos);
        }

        // 移除前后的斜杠
        $uri = trim($uri, '/');

        // 过滤入口文件名（如 index.php/user/1 → user/1）
        // 注意：PHP 内置服务器等 SAPI 下 SCRIPT_NAME 等于请求路径本身，
        // 仅当其以 .php 结尾时才视为入口文件名，避免把整个 URI 剥空
        $scriptName = basename($this->server['SCRIPT_NAME'] ?? '');
        if (substr($scriptName, -4) === '.php' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
            $uri = trim($uri, '/');
        }

        // 剥离URL后缀（如 .html），规则与 Router::parse 一致，保证路由表匹配不受后缀影响
        $urlSuffix = Config::get('common.url_suffix') ?: '';
        if ($urlSuffix !== '' && strlen($uri) > strlen($urlSuffix)) {
            $suffixPos = strrpos($uri, $urlSuffix);
            if ($suffixPos !== false && $suffixPos === strlen($uri) - strlen($urlSuffix)) {
                $uri = substr($uri, 0, $suffixPos);
            }
        }

        return $this->pathCache = $uri;
    }

    /**
     * 请求方法（含 _method 表单伪装）
     *
     * POST + _method=PUT/DELETE/PATCH 时返回伪装后的方法（与路由匹配口径一致）；
     * 伪装仅接受 PUT/DELETE/PATCH，不允许伪装成 GET 等安全方法，防止绕过 CSRF 校验
     *
     * @return string
     */
    private function method()
    {
        if ($this->methodCache !== null) {
            return $this->methodCache;
        }

        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($this->post['_method'])) {
            $spoofed = strtoupper((string)$this->post['_method']);
            if (in_array($spoofed, ['PUT', 'DELETE', 'PATCH'], true)) {
                $method = $spoofed;
            }
        }

        return $this->methodCache = $method;
    }

    /**
     * 判断是否为GET请求
     * @return bool
     */
    private function isGet()
    {
        return $this->method() === 'GET';
    }

    /**
     * 判断是否为POST请求
     * @return bool
     */
    private function isPost()
    {
        return $this->method() === 'POST';
    }

    /**
     * 判断是否为AJAX请求
     * @return bool
     */
    private function isAjax()
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 判断是否为HTTPS请求
     * @return bool
     */
    private function isHttps()
    {
        return self::resolveHttps($this->server);
    }

    /**
     * 获取客户端IP地址（可信代理规则见 resolveIp 说明）
     * @return string
     */
    private function ip()
    {
        return self::resolveIp($this->server);
    }

    /**
     * 获取所有输入（GET + POST）
     * @return array
     */
    private function all()
    {
        return array_merge($this->get, $this->post);
    }

    /**
     * 获取输入值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    private function input($key = null, $default = null)
    {
        $data = $this->all();
        return $key ? ($data[$key] ?? $default) : $data;
    }

    /**
     * 获取GET参数
     * @param string $key 键名
     * @param array $options 处理选项
     * @return mixed
     */
    private function get($key, $options = [])
    {
        $val = isset($this->get[$key]) ? $this->get[$key] : null;
        return Http::handling($val, $options);
    }

    /**
     * 获取POST参数
     * @param string $key 键名(为空则返回所有POST数据)
     * @param array|mixed $options 处理选项；传入标量时视为默认值 default
     * @return mixed
     */
    private function post($key = '', $options = [])
    {
        // 支持 post('age', 0) 简写：标量 options 视为默认值
        if (!is_array($options)) {
            $options = ['default' => $options];
        }

        // 不传 key 时返回所有 POST 数据；传了 key 但不存在时返回 null（交由 handling 走默认值逻辑）
        if ($key === '' || $key === null) {
            $val = $this->post ?: null;
        } else {
            $val = array_key_exists($key, $this->post) ? $this->post[$key] : null;
        }

        return Http::handling($val, $options);
    }

    /**
     * 获取原始POST输入
     * @return string
     */
    private function postInput()
    {
        return file_get_contents('php://input');
    }

    /**
     * 获取JSON格式的POST数据
     * @param bool $assoc 是否转换为关联数组
     * @return mixed
     */
    private function getJson($assoc = true)
    {
        return json_decode($this->postInput(), $assoc);
    }

    /**
     * 获取请求头（基于请求快照解析，测试中构造的模拟头同样生效）
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    private function header($key = null, $default = null)
    {
        return self::findHeader($this->headers(), $key, $default);
    }

    /**
     * 获取所有请求头
     * @return array
     */
    private function headers()
    {
        return self::buildHeadersFromServer($this->server);
    }

    /* ==================== 共享解析逻辑（静态/实例复用） ==================== */

    /**
     * 从 server 数组构建请求头映射
     * @param array $server
     * @return array
     */
    protected static function buildHeadersFromServer(array $server)
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if ('HTTP_' == substr($key, 0, 5)) {
                $headers[ucfirst(strtolower(str_replace('_', '-', substr($key, 5))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 在请求头数组中查找指定头（键名不区分大小写）
     * @param array $headers
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function findHeader(array $headers, $key, $default)
    {
        if ($key) {
            $key = strtolower($key);
            foreach ($headers as $headerKey => $value) {
                if (strtolower($headerKey) === $key) {
                    return $value;
                }
            }
            return $default;
        }
        return $headers;
    }

    /**
     * 判断是否为HTTPS请求
     * @param array $server
     * @return bool
     */
    protected static function resolveHttps(array $server)
    {
        return isset($server['HTTPS']) && ($server['HTTPS'] === 'on' || $server['HTTPS'] == 1)
            || isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] === 'https';
    }

    /**
     * 解析客户端真实IP
     *
     * 仅当 REMOTE_ADDR 命中可信代理列表（config: trusted_proxies）时才解析 X-Forwarded-For，
     * 且从右向左取第一个非可信代理的地址（最右侧由最近的代理追加，客户端无法伪造），
     * 否则一律返回 REMOTE_ADDR，防止通过伪造请求头绕过登录日志、限流、审计。
     *
     * @param array $server
     * @return string
     */
    protected static function resolveIp(array $server)
    {
        $remoteAddr = $server['REMOTE_ADDR'] ?? '0.0.0.0';

        $trustedProxies = (array)Config::get('trusted_proxies', []);
        if (empty($trustedProxies) || !self::isTrustedProxy($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        // 请求来自可信代理：从 X-Forwarded-For 最右侧向左找第一个非可信代理的有效IP
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $server['HTTP_X_FORWARDED_FOR']));
            for ($i = count($ips) - 1; $i >= 0; $i--) {
                $ip = $ips[$i];
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    continue;
                }
                if (!self::isTrustedProxy($ip, $trustedProxies)) {
                    return $ip;
                }
            }
        }

        // X-Forwarded-For 无有效值时回退到 REMOTE_ADDR
        return $remoteAddr;
    }

    /**
     * 判断IP是否命中可信代理列表（支持精确IP和IPv4 CIDR）
     * @param string $ip 待检查的IP
     * @param array $trustedProxies 可信代理列表
     * @return bool
     */
    private static function isTrustedProxy($ip, array $trustedProxies)
    {
        foreach ($trustedProxies as $proxy) {
            $proxy = trim($proxy);
            if ($proxy === '') {
                continue;
            }
            if (strpos($proxy, '/') !== false) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && self::ipv4InCidr($ip, $proxy)) {
                    return true;
                }
            } elseif (strcasecmp($proxy, $ip) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断IPv4是否落在指定CIDR网段内
     * @param string $ip IPv4地址
     * @param string $cidr CIDR网段，如 10.0.0.0/8
     * @return bool
     */
    private static function ipv4InCidr($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        $bits = (int)$bits;
        if ($bits < 0 || $bits > 32
            || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
}
