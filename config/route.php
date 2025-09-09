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
配置路由
示例：
return
[
	//正则表达式替换替换方法
	['/^about$/', 'home/index/about'],
	['/^column\/(\d+)$/', 'home/index/column/$1']
	['/^(.*?)$/','home/$1'],//隐藏home模块url
	['/^(\d+)(.*?)$/','home/goods/index/$1'],
	['/^category\/(\d+)$/','home/category/index/$1'],

	//简便方法
	[(:any)','home/$1'],//隐藏home模块url
	['article_(:num)','article/detail/$1'],
	['category/(:num)','home/category/index/$1'],
];

*/

return [
    // 隐藏默认模块home的路由规则示例
    // 注意：这些规则是可选的，新的智能解析已经可以自动处理默认模块省略
    
    // 支持 article_数字 格式的URL路由（框架会自动处理.html后缀）
    ['article_(:num)', 'home/article/index/$1'],
    
    // 更多路由示例（都不需要手动添加后缀）
    ['article/detail/(:num)', 'home/article/detail/$1'],
    ['category/(:num)', 'home/category/index/$1'],
    ['news/(:num)', 'home/news/detail/$1'],
    
    // 正则表达式路由示例
    // ['/^article_(\d+)$/', 'home/article/index/$1'],
    
    // 示例：将 /article/detail/123 映射到 home/article/detail/123
    // ['/^([^\/]+)\/([^\/]+)\/(.+)$/', 'home/$1/$2/$3'],
    
    // 示例：将 /article/123 映射到 home/article/index/123  
    // ['/^([^\/]+)\/(\d+)$/', 'home/$1/index/$2'],
    
    // 示例：将 /article 映射到 home/article/index
    // ['/^([^\/]+)$/', 'home/$1/index'],
];