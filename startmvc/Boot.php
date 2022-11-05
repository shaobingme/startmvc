<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
 
namespace Startmvc;
class Boot
{
	public $conf;
	public function __construct()
	{
		$this->conf = include CONFIG_PATH.'common.php';
	}
	public function run()
	{
		//版本号
		define('SM_VERSION', '1.2.6');
		define('SM_UPDATE', '20221102');
		if (phpversion() < 7) {
			die('程序要求PHP7+环境版本，当前环境为PHP' . phpversion() . ',请升级服务器环境');			
		}
		
		session_start();
		date_default_timezone_set($this->conf['timezone']);
		if ($this->conf['debug']) {
			error_reporting(E_ALL);
		} else {
			error_reporting(0);
		}
		require_once(CORE_PATH . 'Function.php');//加载系统内置函数
		$this->loadFunction();//加载自定义函数
		$this->getRoute();

	}

	
	private function loadFunction($dirPath = ROOT_PATH.'function/')
	{
		if ($dir = opendir($dirPath)) {
			while ($file = readdir($dir)) {
				if ($file != '.' && $file != '..') {
					$filePath = $dirPath . $file;
					if (is_file($filePath)) {
						require_once($filePath);
					} else {
						$this->loadFunction($filePath . '/');
					}
				}
			}
		}
	}
	private function getRoute()
	{
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
		$pathInfo = str_replace('/index.php', '', mb_convert_encoding($pathInfo, 'UTF-8', 'GBK'));
		$pathInfo = str_replace($this->conf['url_suffix'], '', substr($pathInfo, 1));
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
		$pathInfo[0] = isset($pathInfo[0]) && $pathInfo[0] != '' ? $pathInfo[0] : $this->conf['default_module'];
		$pathInfo[1] = isset($pathInfo[1]) && $pathInfo[1] != '' ? $pathInfo[1] : $this->conf['default_controller'];
		$pathInfo[2] = isset($pathInfo[2]) && $pathInfo[2] != '' ? $pathInfo[2] : $this->conf['default_action'];
		define('MODULE', ucfirst($pathInfo[0]));
		define('CONTROLLER', ucfirst($pathInfo[1]));
		define('ACTION', $pathInfo[2]);
		define('VIEW_PATH', APP_PATH.DS.MODULE . DS .'View');
		$argv = array_slice($pathInfo, 3);
		for ($i = 0; $i < count($argv); $i++) {
			$argv[$i] = strip_tags(htmlspecialchars(stripslashes($argv[$i])));
		}
		
		$this->startApp(MODULE, CONTROLLER, ACTION, $argv);
	}
	private function startApp($module, $controller, $action, $argv) {
		$controller = APP_NAMESPACE.'\\' .$module . '\\' . 'Controller\\' . $controller . 'Controller';
		if (!class_exists($controller)) {
			header("HTTP/1.1 404 Not Found");  
			header("Status: 404 Not Found");
			die();
		}
		$action .= 'Action';
		Lib\Loader::make($controller, $action, $argv);
	}



}
