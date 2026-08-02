<?php
// config/middleware.php
// 中间件配置：由 startmvc\core\App::registerMiddleware() 在应用启动时加载
return [
    // 中间件别名（注册后可用别名代替完整类名）
    'aliases' => [
        'csrf' => 'app\\middleware\\CsrfMiddleware',
        'auth' => 'app\\middleware\\AuthMiddleware',
        'log'  => 'app\\middleware\\LogMiddleware',
    ],

    // 全局中间件（每个请求都会执行，按注册顺序）
    'global' => [
        'app\\middleware\\CsrfMiddleware',  // CSRF 防护：所有 POST/PUT/DELETE/PATCH 请求强制校验
    ],

    // 路由中间件（可应用到特定路由）
    'route' => [
        'auth' => 'app\\middleware\\AuthMiddleware',
        'log'  => 'app\\middleware\\LogMiddleware',
    ],
];
