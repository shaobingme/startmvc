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
		Exception::init(); 
		$this->loadFunction();
		
		// 加载Router类
		$router = Router::getInstance();
		$route = $router->parse();
		
		// 启动应用
		self::startApp($route['module'], $route['controller'], $route['action'], $route['params']);
		
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
