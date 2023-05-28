<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

if (version_compare(PHP_VERSION , '7.2', '<')) {
	die('程序要求PHP7+环境版本，当前环境为PHP' . PHP_VERSION . ',请升级服务器环境');
}
session_start();
//版本号
define('SM_VERSION', '1.3.3');
define('SM_UPDATE', '20221226');
// 应用命名空间（请与应用所在目录名保持一致）
define('APP_NAMESPACE', 'app');
//应用目录
define('APP_PATH', ROOT_PATH . 'app'.DS);
//公共入口目录
define('BASE_PATH', dirname(__FILE__) .DS);
//框架目录
define('CORE_PATH', ROOT_PATH . 'startmvc'.DS);
//缓存路径
define('CACHE_PATH', ROOT_PATH . 'runtime'.DS.'cache'.DS);
//临时文件路径
define('TEMP_PATH', ROOT_PATH . 'runtime'.DS.'temp'.DS);
//配置文件路径
define('CONFIG_PATH', ROOT_PATH . 'config'.DS);
define('_STATIC_','/static/');
if (file_exists(ROOT_PATH.'vendor'.DS.'autoload.php')) {
	require_once ROOT_PATH.'vendor'.DS.'autoload.php'; //composer自动加载
} else {
	require_once CORE_PATH.'autoload.php'; //框架自动加载
}
require_once(CORE_PATH . 'function.php');//加载系统内置函数
date_default_timezone_set(config('timezone'));
error_reporting(config('debug') ? E_ALL : 0);

$app = new startmvc\lib\App;
$app->run();