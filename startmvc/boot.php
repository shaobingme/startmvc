<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */

defined('ENV') or define('ENV', 'development');  // 可以是 development 或 production
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__));

if (version_compare(PHP_VERSION , '7.2', '<')) {
	die('程序要求PHP7+环境版本，当前环境为PHP' . PHP_VERSION . ',请升级服务器环境');
}


//版本号
define('SM_VERSION', '2.5.3');
define('SM_UPDATE', '20250910');
// 应用命名空间（请与应用所在目录名保持一致）
define('APP_NAMESPACE', 'app');
//应用目录
define('APP_PATH', ROOT_PATH . 'app'.DS);
//公共入口目录(web站点目录)
define('BASE_PATH', dirname($_SERVER['SCRIPT_FILENAME']).DS);
//框架目录
define('CORE_PATH', ROOT_PATH . 'startmvc'.DS);
//缓存路径
define('CACHE_PATH', ROOT_PATH . 'runtime'.DS.'cache'.DS);
//临时文件路径
define('TEMP_PATH', ROOT_PATH . 'runtime'.DS.'temp'.DS);
//配置文件路径
define('CONFIG_PATH', ROOT_PATH . 'config'.DS);
define('_STATIC_','/static/');
define('START_MEMORY',  memory_get_usage());
define('START_TIME',  microtime(true));

// 加载函数库
require __DIR__ . '/function.php';

// 注册自动加载
if (is_file(ROOT_PATH . 'vendor'.DS.'autoload.php')) {
    // 优先使用 Composer 自动加载
    require ROOT_PATH . 'vendor'.DS.'autoload.php';
} else {
    // 框架自动加载
    require __DIR__ . '/autoload.php';
    Autoload::register();
}

// 设置默认时区
$config = \startmvc\core\Config::load('common');
$timezone = $config['timezone'] ?? 'Asia/Shanghai';
date_default_timezone_set($timezone);

// 启动会话
\startmvc\core\Session::start();

// 创建应用实例并运行
$app = new \startmvc\core\App();
$app->run();