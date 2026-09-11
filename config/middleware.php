<?php
// config/middleware.php
// 中间件配置：由 startmvc\core\App::registerMiddleware() 在应用启动时加载
//
// 中间件项支持「别名[:参数]」写法，参数会作为额外实参传给中间件的 handle()：
//   'auth'             → handle($request, $next)
//   'auth:admin'       → handle($request, $next, 'admin')
//   'auth:admin,editor'→ handle($request, $next, 'admin', 'editor')
// 关键：参数不是由配置文件"传"给中间件，而是中间件自己在 handle() 里多声明形参接收，
//      例如 public function handle($request, \Closure $next, $role = null)
// 这样「同一套逻辑、不同参数」无需复制多个中间件类。
return [
    // 中间件别名（注册后可用别名代替完整类名）
    'aliases' => [
        'csrf' => 'app\\middleware\\CsrfMiddleware',
        'auth' => 'app\\middleware\\AuthMiddleware',
        'log'  => 'app\\middleware\\LogMiddleware',
    ],

    // 全局中间件（每个请求都会执行，按注册顺序）
    // 同样支持带参数写法，例如 'app\\middleware\\FooMiddleware:bar'
    'global' => [
        'app\\middleware\\CsrfMiddleware',  // CSRF 防护：所有 POST/PUT/DELETE/PATCH 请求强制校验
    ],
];
