<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
// 系统目录分隔符
define('DS', DIRECTORY_SEPARATOR);
// 项目根目录
define('ROOT_PATH', realpath(__DIR__.DS.'..'.DS).DS);    // 入口文件在 public 中
//define('ROOT_PATH', dirname(__FILE__).DS);    // 入口文件在项目根目录
// 应用命名空间（请与应用所在目录名保持一致）
define('APP_NAMESPACE', 'App');
//应用目录
define('APP_PATH', ROOT_PATH . 'app'.DS);
// 公共入口目录
define('BASE_PATH', dirname(__FILE__) .DS);
//框架目录
define('CORE_PATH', ROOT_PATH . 'startmvc'.DS);
// 缓存路径
define('CACHE_PATH', ROOT_PATH . 'runtime'.DS.'cache'.DS);
// 临时文件路径
define('TEMP_PATH', ROOT_PATH . 'runtime'.DS.'temp'.DS);
// 配置文件路径
define('CONFIG_PATH', ROOT_PATH . 'config'.DS);
define('_STATIC_','/static/');
//版本号
define('SM_VERSION', '1.1.4');
require(ROOT_PATH .'vendor'.DS.'autoload.php');
$boot = new Startmvc\Boot;
$boot->run();
