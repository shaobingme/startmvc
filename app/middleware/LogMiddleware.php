<?php
namespace app\middleware;

use startmvc\core\MiddlewareBase;

/**
 * 日志中间件
 * 用于记录请求和响应信息
 */
class LogMiddleware extends MiddlewareBase
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
        // 记录请求开始时间
        $startTime = microtime(true);
        
        // 记录请求信息
        $requestInfo = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'time' => date('Y-m-d H:i:s'),
        ];
        
        // 输出请求信息（实际应用中应写入日志文件）
        echo "<!-- 请求日志：" . json_encode($requestInfo, JSON_UNESCAPED_UNICODE) . " -->";
        
        // 调用下一个中间件
        $response = $next($request);
        
        // 计算执行时间
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        // 输出执行时间信息
        echo "<!-- 请求执行时间：{$executionTime}ms -->";
        
        return $response;
    }
} 