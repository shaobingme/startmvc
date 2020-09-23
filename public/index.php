<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2021
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
// 应用命名空间（请与应用所在目录名保持一致）
define('APP_NAMESPACE', 'App');
define('ROOT_PATH', dirname(__FILE__) . '/..');
define('APP_PATH', dirname(__FILE__) . '/../app');
// 项目根目录
define('BASE_PATH', dirname(__DIR__) .'/');
// 公共入口目录
define('PUBLIC_PATH', BASE_PATH . 'public' . '/');
define('_STATIC_','/static/');
define('DB_AUTO_CONNECT', false);//数据库自动连接
define('SM_VERSION', '1.0.5');//版本号

require(ROOT_PATH . '/vendor/autoload.php');
$boot = new Startmvc\Boot;
$boot->run();