<?php
namespace startmvc\core;

/**
 * 中间件基类，所有中间件都应继承此类
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
     * @param string $middleware 中间件类名或别名
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
     * 通过中间件管道发送请求
     * @param array $middleware 中间件数组
     * @param object $request 请求对象
     * @param \Closure $destination 最终目标处理函数
     * @return mixed
     */
    public static function pipeline($middleware, $request, \Closure $destination)
    {
        $firstSlice = function($request) use ($middleware, $destination) {
            if (empty($middleware)) {
                return $destination($request);
            }
            
            // 取出第一个中间件
            $middlewareClass = array_shift($middleware);
            
            // 解析别名
            if (isset(self::$aliases[$middlewareClass])) {
                $middlewareClass = self::$aliases[$middlewareClass];
            }
            
            // 实例化中间件
            $instance = new $middlewareClass();
            
            // 创建下一个中间件的闭包
            $next = function($request) use ($middleware, $destination) {
                return self::pipeline($middleware, $request, $destination);
            };
            
            // 执行当前中间件
            return $instance->handle($request, $next);
        };
        
        return $firstSlice($request);
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
