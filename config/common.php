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
    'trace' => true,	//是否开启调试追踪，生产环境中请关闭
    'timezone' => 'PRC',	//系统时区
    'url_suffix' => '.html',	//URL后缀
    'default_module' => 'home',	//默认模块
    'default_controller' => 'Index',	//默认控制器
    'default_action' => 'index',	//默认方法
    'urlrewrite' => true,	//是否Url重写，隐藏index.php,需要服务器支持和对应的规则
    'session_prefix' => '',	//Session前缀
    'cookie_prefix' => '',	//Cookie前缀
    'locale'  => 'zh_cn',	//指定默认语言，小写
    'db_auto_connect'  => false,	//是否开启数据库自动连接
    'theme'  => '',	//指定模板子目录，方便多风格使用，为空时模板文件在view下
];