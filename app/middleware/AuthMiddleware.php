<?php
namespace app\middleware;

use startmvc\core\MiddlewareBase;

/**
 * 认证中间件
 * 用于验证用户是否已登录
 */
class AuthMiddleware extends MiddlewareBase
{
    /**
     * 处理传入的请求
     * 
     * @param object $request 请求对象
     * @param \Closure $next 下一个要执行的中间件
     * @return mixed 响应对象
     */
    public function handle($request, \Closure $next)
    {
        // 在这里进行身份验证
        // 例如：检查用户是否登录
        if (!isset($_SESSION['user_id'])) {
            // 用户未登录，可以重定向到登录页面
            // 但这里为了示例，我们只是设置一个标志
            $request->authenticated = false;
            echo "用户未登录，但允许继续访问。<br>";
        } else {
            $request->authenticated = true;
            echo "用户已登录。<br>";
        }
        
        // 调用下一个中间件或控制器
        $response = $next($request);
        
        // 在响应返回前可以进行一些后处理
        // 例如：添加响应头、修改响应内容等
        
        return $response;
    }
} 