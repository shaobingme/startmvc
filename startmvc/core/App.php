<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author	Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link	  http://startmvc.com
 */
 
namespace startmvc\core;

class App
{
	public $conf;
	public function __construct()
	{
		
	}
	public function run()
	{
		//自定义异常
		//set_error_handler([$this,'errorHandler']);
		//set_exception_handler([$this,'exceptionHandler']);
		Exception::init(); //加载自定义错误及异常处理

		$this->loadFunction();//加载自定义函数
		$this->getRoute();
		//开启调试追踪
		if (config('trace')) {
			include __DIR__.'/tpl/trace.php';
		}


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
		if (!function_exists('mb_convert_encoding')) {  
		    die('mb_convert_encoding() function is not available.');  
		}
		$pathInfo = str_replace('/index.php', '', mb_detect_encoding($pathInfo, 'UTF-8, GBK') === 'GBK' ? mb_convert_encoding($pathInfo, 'UTF-8', 'GBK') : $pathInfo);
		$pathInfo = str_replace(config('url_suffix'), '', substr($pathInfo, 1));
		$route = require_once(CONFIG_PATH.'route.php');
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
		define('VIEW_PATH', APP_PATH.MODULE . DS .'view');

		// 用于运行追踪
		$GLOBALS['traceSql'] = [];

		$argv = array_map(function($arg) {
			return strip_tags(htmlspecialchars(stripslashes($arg)));
		}, array_slice($pathInfo, 3));
		
		self::startApp(MODULE, CONTROLLER, ACTION, $argv);
	}

	/**
	 * 配置控制器的路径
	 */
	private static function startApp($module, $controller, $action, $argv) {
		$controller = APP_NAMESPACE . "\\{$module}\\controller\\{$controller}Controller";
		if (!class_exists($controller)) {
			throw new \Exception($controller.'控制器不存在');
			//die($controller.'控制器不存在');
		}
		$action .= 'Action';		
		Loader::make($controller, $action, $argv);
	}
	/**
	 * 自定义错误处理触发错误
	 */
	 public static function errorHandler($level,$message, $file, $line)
	{
		if (error_reporting() !== 0) {
			$errorMessage = "错误提示：{$message}，文件：{$file}，行号：{$line}";
			throw new \Exception($errorMessage, $level);
		}
	}
	/**
	 * 异常错误处理
	 */
	public static function exceptionHandler($exception)
	{
		// Code is 404 (not found) or 500 (general error)
		$code = $exception->getCode();
		if ($code != 404) {
			$code = 500;
		}
		http_response_code($code);
		if (config('debug')) {
			include 'tpl/debug.php';
			//var_dump($exception);
		} else {
			//$log = new Log();
			//$log->debug($exception->getMessage() . '\n' . $exception->getFile() . '\n' . $exception->getLine());
			return $code;
		}
	}


}
