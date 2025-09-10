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
路由配置说明：
支持两种路由规则格式：
1. 简便方法：使用占位符 (:num)、(:any)、(:alpha) 等
2. 正则表达式：使用完整的正则表达式模式

占位符说明：
- (:num)     匹配数字
- (:any)     匹配任意字符
- (:alpha)   匹配字母
- (:alnum)   匹配字母和数字

注意：框架会自动处理 .html 后缀，无需在路由中指定

    // ==================== 简便方法路由规则 ====================
    
    // 1. 数字参数路由
    ['article_(:num)', 'home/article/index/$1'],              // article_123 -> home/article/index/123
    ['category/(:num)', 'home/category/index/$1'],            // category/123 -> home/category/index/123
    ['news/(:num)', 'home/news/detail/$1'],                   // news/123 -> home/news/detail/123
    ['user/(:num)', 'home/user/profile/$1'],                  // user/123 -> home/user/profile/123
    
    // 2. 多级路径路由
    ['article/detail/(:num)', 'home/article/detail/$1'],      // article/detail/123 -> home/article/detail/123
    ['product/(:alpha)/(:num)', 'home/product/show/$1/$2'],   // product/phone/123 -> home/product/show/phone/123
    ['blog/(:any)/(:num)', 'home/blog/detail/$1/$2'],         // blog/tech/123 -> home/blog/detail/tech/123
    
    // 3. 字母参数路由
    ['tag/(:alpha)', 'home/tag/index/$1'],                    // tag/tech -> home/tag/index/tech
    ['lang/(:alpha)', 'home/index/lang/$1'],                  // lang/en -> home/index/lang/en
    
    // 4. 任意字符路由
    ['search/(:any)', 'home/search/index/$1'],                // search/keyword -> home/search/index/keyword
    ['page/(:any)', 'home/page/show/$1'],                     // page/about -> home/page/show/about
    
    // 5. 隐藏默认模块路由（将所有请求映射到home模块）
    // ['(:any)', 'home/$1'],                                 // 任意路径 -> home/任意路径
    
    
    // ==================== 正则表达式路由规则 ====================
    
    // 1. 精确匹配
    ['/^about$/', 'home/index/about'],                        // 精确匹配 about
    ['/^contact$/', 'home/index/contact'],                    // 精确匹配 contact
    
    // 2. 数字参数匹配
    ['/^article_(\d+)$/', 'home/article/index/$1'],           // article_123
    ['/^column\/(\d+)$/', 'home/column/index/$1'],            // column/123
    ['/^category\/(\d+)$/', 'home/category/index/$1'],        // category/123
    
    // 3. 多参数匹配
    ['/^product\/([a-zA-Z]+)\/(\d+)$/', 'home/product/detail/$1/$2'],  // product/phone/123
    ['/^blog\/([^\/]+)\/(\d+)$/', 'home/blog/detail/$1/$2'],           // blog/tech/123
    
    // 4. 可选参数匹配
    ['/^list\/(\d+)?$/', 'home/list/index/$1'],               // list 或 list/123
    ['/^archive\/(\d{4})\/(\d{1,2})?$/', 'home/archive/index/$1/$2'], // archive/2023 或 archive/2023/12
    
    // 5. 复杂路径匹配
    ['/^([^\/]+)\/([^\/]+)\/(.+)$/', 'home/$1/$2/$3'],        // 三级路径映射
    ['/^([^\/]+)\/(\d+)$/', 'home/$1/index/$2'],              // 控制器/数字ID
    ['/^([^\/]+)$/', 'home/$1/index'],                        // 单级路径映射
    
    // 6. 特殊格式匹配
    ['/^api\/v(\d+)\/([^\/]+)$/', 'api/v$1/$2'],              // api/v1/users -> api/v1/users
    ['/^(\d+)(.*?)$/', 'home/goods/index/$1'],                // 数字开头的路径
    ['/^download\/([^\/]+)\.([a-z]+)$/', 'home/download/file/$1/$2'], // download/file.pdf
    
    
*/

return [
    
    // 文章系统
    // ['article', 'home/article/index'],                     // 文章列表
    // ['article/(:num)', 'home/article/detail/$1'],          // 文章详情
    // ['article/add', 'home/article/add'],                   // 添加文章
    // ['article/edit/(:num)', 'home/article/edit/$1'],       // 编辑文章
    
    // 用户系统
    // ['login', 'home/user/login'],                          // 登录页面
    // ['register', 'home/user/register'],                    // 注册页面
    // ['profile/(:num)', 'home/user/profile/$1'],            // 用户资料
    
    // 商品系统
    // ['goods', 'home/goods/index'],                         // 商品列表
    // ['goods/(:num)', 'home/goods/detail/$1'],              // 商品详情
    // ['cart', 'home/cart/index'],                           // 购物车
    // ['order/(:num)', 'home/order/detail/$1'],              // 订单详情
];