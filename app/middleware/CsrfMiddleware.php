<?php
namespace app\middleware;

use startmvc\core\Config;
use startmvc\core\Csrf;
use startmvc\core\MiddlewareBase;
use startmvc\core\Request;

/**
 * CSRF 防护中间件
 *
 * 对所有"非安全"请求方法（POST/PUT/DELETE/PATCH）强制校验 CSRF Token，
 * 校验失败返回 403，请求不会到达控制器。
 *
 * Token 提交方式（按优先级）：
 *   1. 表单隐藏域：<input type="hidden" name="csrf_token" value="...">（可用 csrf_field() 生成）
 *   2. 请求头：X-CSRF-TOKEN 或 X-XSRF-TOKEN（AJAX 请求推荐，可用 csrf_meta() 生成 meta 标签）
 *
 * 无需校验的路径可在 config/common.php 的 csrf.exclude 中配置，支持 * 通配符。
 */
class CsrfMiddleware extends MiddlewareBase
{
    /**
     * 安全请求方法：不改变服务器状态，无需校验
     * @var array
     */
    protected $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * 处理传入的请求
     *
     * @param object $request 请求对象
     * @param \Closure $next 下一个要执行的中间件
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 安全方法：确保 Token 存在（供页面中的表单使用），直接放行
        if (in_array(Request::method(), $this->safeMethods, true)) {
            Csrf::token();
            return $next($request);
        }

        // 排除路径（如第三方支付回调、对外开放 API）跳过校验
        if ($this->inExceptArray()) {
            return $next($request);
        }

        // 校验失败：拒绝请求，不再向下传递
        if (!Csrf::check()) {
            return $this->deny();
        }

        return $next($request);
    }

    /**
     * 判断当前请求 URI 是否在排除列表中
     * @return bool
     */
    protected function inExceptArray()
    {
        $config = Config::get('csrf', []);
        $exclude = $config['exclude'] ?? [];
        if (empty($exclude) || !is_array($exclude)) {
            return false;
        }

        $uri = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        foreach ($exclude as $pattern) {
            $pattern = trim((string)$pattern, '/');
            if ($pattern === '') {
                continue;
            }
            // 精确匹配或前缀匹配（'api/webhook' 同时匹配 'api/webhook/xxx'）
            if ($uri === $pattern || strpos($uri, $pattern . '/') === 0) {
                return true;
            }
            // 通配符匹配（* 匹配任意字符），手工实现以兼容全部平台
            if (strpos($pattern, '*') !== false) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (preg_match($regex, $uri)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 拒绝请求：返回 403
     * AJAX 请求返回 JSON，普通请求返回 HTML 提示页
     * @return string
     */
    protected function deny()
    {
        http_response_code(403);

        if (Request::isAjax() || $this->wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            return json_encode([
                'code' => 403,
                'message' => 'CSRF token 验证失败，请刷新页面后重试',
            ], JSON_UNESCAPED_UNICODE);
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>403 Forbidden</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding-top:60px;">'
            . '<h1>403 Forbidden</h1>'
            . '<p>CSRF token 验证失败，请<a href="javascript:location.reload()">刷新页面</a>后重试。</p>'
            . '</body></html>';
    }

    /**
     * 判断客户端是否期望 JSON 响应
     * @return bool
     */
    protected function wantsJson()
    {
        $accept = (string)Request::header('Accept', '');
        return strpos($accept, 'application/json') !== false;
    }
}
