<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
 
namespace startmvc\lib;
class App
{
	public $conf;
	public function __construct()
	{
		
	}
	public function run()
	{
		self::loadFunction();//加载自定义函数
		self::getRoute();

	}

	/**
	 * 加载自定义函数
	 */
	private static function loadFunction($dirPath = ROOT_PATH.'function'.DS.'*.php')
	{
		$files=glob($dirPath);
		if (is_array($files)) {
			foreach ($files as $v) {
				if(is_file($v)) require_once($v);
			}
		}
	}
	/**
	 * 获取路由
	 */
	private static function getRoute()
	{
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
		$pathInfo = str_replace('/index.php', '', mb_convert_encoding($pathInfo, 'UTF-8', 'GBK'));
		$pathInfo = str_replace(config('url_suffix'), '', substr($pathInfo, 1));
		$route = include(CONFIG_PATH.'route.php');
		$rule = array(
			':any' => '.*?',
			':num' => '[0-9]+'
		);
		foreach ($route as $r) {
			if(strpos($r[0],'(:any)') !== false||strpos($r[0],'(:num)') !== false){ 
				$pattern = '#^' . strtr($r[0], $rule) . '$#';//过滤参数
			}else{
				$pattern=$r[0];
			}
			$pathInfo = preg_replace($pattern, $r[1], $pathInfo);
		}
		$pathInfo = explode('/', $pathInfo);
		$pathInfo[0] = isset($pathInfo[0]) && $pathInfo[0] != '' ? $pathInfo[0] : config('default_module');
		$pathInfo[1] = isset($pathInfo[1]) && $pathInfo[1] != '' ? $pathInfo[1] : config('default_controller');
		$pathInfo[2] = isset($pathInfo[2]) && $pathInfo[2] != '' ? $pathInfo[2] : config('default_action');
		define('MODULE', $pathInfo[0]);
		define('CONTROLLER', ucfirst($pathInfo[1]));
		define('ACTION', $pathInfo[2]);
		define('VIEW_PATH', APP_PATH.DS.MODULE . DS .'view');
		$argv = array_slice($pathInfo, 3);
		for ($i = 0; $i < count($argv); $i++) {
			$argv[$i] = strip_tags(htmlspecialchars(stripslashes($argv[$i])));
		}
		
		self::startApp(MODULE, CONTROLLER, ACTION, $argv);
	}

	/**
	 * 配置控制器的路径
	 */
	private static function startApp($module, $controller, $action, $argv) {
		$controller = APP_NAMESPACE.'\\' .$module . '\\' . 'controller\\' . $controller . 'Controller';
		if (!class_exists($controller)) {
			header("HTTP/1.1 404 Not Found");  
			header("Status: 404 Not Found");
			die();
		}
		$action .= 'Action';
		Loader::make($controller, $action, $argv);
	}


}
