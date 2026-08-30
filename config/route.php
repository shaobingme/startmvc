<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */

/*
自定义路由配置（唯一的路由定义文件，两种写法可混用，进入同一张路由表）

一、流式 API（推荐）：在 return 之前直接调用 Router 静态方法

Router::get('/', 'Home/Index/index');
Router::post('/login', 'Home/User/login');
Router::put('/user/:id', 'Home/User/update');   // 另有 post/put/patch/delete/any

// 路由组：统一前缀 + 中间件（中间件用 config/middleware.php 中的别名或完整类名）
Router::group(['prefix' => 'admin', 'middleware' => ['auth']], function() {
    Router::get('/dashboard', 'Admin/Index/dashboard');
});

// RESTful资源路由：一行生成 index/create/store/show/edit/update/destroy 7条路由
Router::resource('/article', 'Home/Article');

// 闭包路由，匹配参数按序传入
Router::get('/welcome/:name', function($name) {
    return 'hello ' . $name;
});

// 单条路由应用中间件
Router::get('/admin/setting', 'Admin/Index/setting', ['log']);

二、数组配置（兼容旧版）：目标中的 $1、$2 会被替换为匹配参数

return [
    ['about', 'home/index/about'],                       // 精确匹配
    ['article/(:num)', 'home/article/detail/$1'],        // /article/232
    ['/^blog\/(\w+)\/(\d+)$/', 'home/blog/view/$1/$2'],  // 原生正则
];

说明：
- 占位符：流式API用 :id/:num/:slug/:alpha/:alphanum/:any（Router::pattern 可自定义），
  数组用 (:num)/(:any)/(:alpha)/(:alnum)，也支持 '/^...$/' 原生正则
- 优先级：精确匹配 > 正则路由 > 内置模式
- URL后缀（如 .html）在路由匹配前自动剥离，规则中无需写后缀
*/

return [];
