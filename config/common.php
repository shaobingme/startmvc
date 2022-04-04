<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
return [
    'debug' => true,	//Debug模式，开发过程中开启，生产环境中请关闭
    'timezone' => 'PRC',	//系统时区
    'url_suffix' => '.html',	//URL后缀
    'muti_module' => true,	//是否多模块，true-多模块，false-单模块
    'default_module' => 'Home',	//默认模块，单模块可以不填
    'default_controller' => 'Index',	//默认控制器
    'default_action' => 'index',	//默认方法
    'urlrewrite' => true,	//是否Url重写，隐藏index.php,需要服务器支持和对应的规则
    'session_prefix' => '',	//Session前缀
    'cookie_prefix' => '',	//Cookie前缀
	'cache_status'=>true,	//false为关闭，true为开启缓存
	'cache_type'=>'file',	//支持类型 : file [文件型],redis[内存型]
    //以下配置内存型redis缓存的必须设置
    'cache_host' => '127.0.0.1',	//主机地址
    'cache_port'   => '6379',	//端口 redis 一般为 6379
    'cache_prefix'  => 'sm_',	//缓存变量前缀
    'locale'  => 'zh_cn',	//指定默认语言，小写
    'db_auto_connect'  => false,	//是否开启数据库自动连接
];