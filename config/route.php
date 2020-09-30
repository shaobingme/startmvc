<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
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
	['/^(.*?)$/','home/$1'],//隐藏home模块url(适用于单模块)
	['/^(\d+)(.*?)$/','home/goods/index/$1'],
	['/^category\/(\d+)$/','home/category/index/$1'],

	//简便方法
	[(:any)','home/$1'],//隐藏home模块url(适用于单模块)
	['article_(:num)','article/detail/$1'],
	['category/(:num)','home/category/index/$1'],
];

*/

return [

	
];