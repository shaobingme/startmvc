<?php
namespace startmvc\core;

/**
 * 中间件基类，所有中间件都应继承此类
 *
 * 带参数的中间件：注册时可写成「别名:参数」或「完整类名:参数」，参数会作为额外实参
 * 传给 handle()，无需为不同参数复制多个中间件类：
 *
 *     Router::get('/admin', 'Admin/Index/index', ['auth:admin']);
 *     Router::get('/editor', 'Admin/Index/edit', ['auth:admin,editor']);   // 多参数用英文逗号分隔
 *
 *     class AuthMiddleware extends MiddlewareBase
 *     {
 *         public function handle($request, \Closure $next, $role = null)
 *         {
 *             // $role === 'admin'
 *             return $next($request);
 *         }
 *     }
 *
 * 不使用参数的中间件**无需改动**：只声明 $request 与 $next 两个形参即可，
 * 多传的实参会被 PHP 忽略（可用 func_get_args() 取到）。
 */
abstract class MiddlewareBase
{
    /**
     * 处理传入的请求
     * 
     * @param object $request 请求对象
     * @param \Closure $next 下一个要执行的中间件
     * @return mixed 响应对象
     */
    abstract public function handle($request, \Closure $next);
}

/**
 * 中间件管理器，负责中间件的注册和执行
 *
 * 执行模型为「洋葱模型」：先注册的中间件最先进入、最后返回。
 *
 * pipeline() 用 array_reduce 逆序把中间件列表组装成一条单层闭包链，
 * 不再递归调用自身；但**实例化仍发生在真正执行到该中间件的那一刻**，
 * 所以前置中间件短路（不调用 $next）时，后面的中间件不会被创建。
 */
class Middleware
{
    /**
     * 已注册的全局中间件
     * @var array
     */
    protected static $middleware = [];
    
    /**
     * 中间件别名
     * @var array
     */
    protected static $aliases = [];
    
    /**
     * 注册全局中间件
     * @param string $middleware 中间件类名或别名，支持「别名:参数」写法（如 'auth:admin'）
     * @return void
     */
    public static function register($middleware)
    {
        if (!in_array($middleware, self::$middleware)) {
            self::$middleware[] = $middleware;
        }
    }
    
    /**
     * 注册中间件别名
     * @param string $alias 别名
     * @param string $class 中间件类名
     * @return void
     */
    public static function alias($alias, $class)
    {
        self::$aliases[$alias] = $class;
    }
    
    /**
     * 解析单个中间件项，得到 [类名, 参数数组]
     *
     * 支持四种写法：
     *   'auth'                       别名，无参数
     *   'auth:admin'                 别名 + 单个参数
     *   'auth:admin,editor'          别名 + 多个参数（英文逗号分隔，自动去空白）
     *   'app\middleware\Foo:bar'     完整类名 + 参数
     *
     * 细节：只按**第一个**冒号切分，故 'a:b:c' 的参数是 'b:c'；
     *       'auth:' 视为无参数；别名解析不了时按类名原样返回，由实例化阶段报错。
     *
     * @param string $item 中间件项
     * @return array [类名（别名已解析）, 参数数组]
     * @throws \RuntimeException 传入数组/对象等非字符串项时
     */
    public static function parse($item)
    {
        if (!is_string($item)) {
            if (is_scalar($item)) {
                $item = (string)$item;
            } else {
                throw new \RuntimeException(
                    '中间件配置无效：应为「类名」或「别名[:参数]」字符串，实际为 ' . gettype($item)
                );
            }
        }
        
        $item = trim($item);
        $name = $item;
        $params = [];
        
        $pos = strpos($item, ':');
        if ($pos !== false) {
            $name = trim(substr($item, 0, $pos));
            $raw = substr($item, $pos + 1);
            // 'auth:' 这类空参数视为无参数
            if (trim($raw) !== '') {
                foreach (explode(',', $raw) as $param) {
                    $param = trim($param);
                    if ($param !== '') {
                        $params[] = $param;
                    }
                }
            }
        }
        
        if (isset(self::$aliases[$name])) {
            $name = self::$aliases[$name];
        }
        
        return [$name, $params];
    }
    
    /**
     * 创建中间件实例
     *
     * 走容器解析，因此中间件构造函数里的类类型依赖会被自动注入；
     * 中间件未注册为共享绑定，每次都是新实例（与旧实现直接 new 的语义一致）。
     *
     * @param string $class 中间件类名
     * @return object
     * @throws \RuntimeException 类名为空或类不存在时
     */
    protected static function instantiate($class)
    {
        if (!is_string($class) || $class === '') {
            throw new \RuntimeException('中间件配置无效：类名为空');
        }
        if (!class_exists($class)) {
            throw new \RuntimeException("中间件不存在：{$class}（别名未注册或类未定义）");
        }
        return Container::getInstance()->make($class);
    }
    
    /**
     * 通过中间件管道发送请求
     * @param array|string $middleware 中间件数组（也接受单个中间件项）
     * @param object $request 请求对象
     * @param \Closure $destination 最终目标处理函数
     * @return mixed
     */
    public static function pipeline($middleware, $request, \Closure $destination)
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }
        
        // 逆序组装：先注册的中间件包在最外层（最先进入、最后返回）
        $pipeline = array_reduce(
            array_reverse($middleware),
            function (\Closure $next, $item) {
                return function ($request) use ($next, $item) {
                    list($class, $params) = self::parse($item);
                    $instance = self::instantiate($class);
                    // 参数以额外实参传入；不使用参数的中间件只声明两个形参即可
                    return $instance->handle($request, $next, ...$params);
                };
            },
            $destination
        );
        
        return $pipeline($request);
    }
    
    /**
     * 执行所有全局中间件
     * @param object $request 请求对象
     * @param \Closure $destination 最终目标处理函数
     * @return mixed
     */
    public static function run($request, \Closure $destination)
    {
        return self::pipeline(self::$middleware, $request, $destination);
    }
}
