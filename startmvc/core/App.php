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

use startmvc\core\Router;

class App
{
	public $conf;
	public static $trace = [];
	
	public function __construct()
	{
		// 注册默认中间件
		$this->registerMiddleware();
	}
	public function run()
	{
		// 记录开始时间和内存
		$beginTime = microtime(true);
		$beginMem = memory_get_usage();
		
		// 初始化 trace 数据
		self::$trace = [
			'beginTime' => $beginTime,
			'beginMem' => $beginMem,
			'uri' => $_SERVER['REQUEST_URI'],
			'request_method' => $_SERVER['REQUEST_METHOD']
		];
		
		Exception::init();
		$this->loadFunction();

		// 创建请求对象
		$request = new Request();

		// 通过中间件管道处理请求
		$response = Middleware::run($request, function($request) {
			return $this->handleRequest();
		});

		// 输出响应内容
		if (is_string($response)) {
			echo $response;
			// 对于字符串响应，在末尾添加 trace 信息
			if (config('trace')) {
				self::outputTrace();
			}
		} elseif (is_array($response)) {
			header('Content-Type: application/json');
			echo json_encode($response);
		}
		// 注意：如果 $response 为 null（控制器直接输出了内容），trace 会在 Controller::display 中处理
	}
	
	/**
	 * 输出 trace 信息
	 */
	public static function outputTrace()
	{
		// 记录结束时间和内存
		$endTime = microtime(true);
		$endMem = memory_get_usage();
		
		// 计算运行时间和内存使用
		self::$trace['endTime'] = $endTime;
		self::$trace['endMem'] = $endMem;
		self::$trace['runtime'] = number_format((self::$trace['endTime'] - self::$trace['beginTime']) * 1000, 2) . 'ms';
		self::$trace['memory'] = number_format((self::$trace['endMem'] - self::$trace['beginMem']) / 1024, 2) . 'KB';
		self::$trace['files'] = get_included_files();
		
		echo "\n<!-- Trace Info Start -->\n";
		include __DIR__ . '/tpl/trace.php';
		echo "\n<!-- Trace Info End -->\n";
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
	private static function startApp($module, $controller, $action, $argv)
	{
		// 先定义常量，因为 View 类的构造函数需要用到
		if (!defined('MODULE')) define('MODULE', $module);
		if (!defined('CONTROLLER')) define('CONTROLLER', $controller);
		if (!defined('ACTION')) define('ACTION', $action);
		
		$controller = APP_NAMESPACE . "\\{$module}\\controller\\{$controller}Controller";
		if (!class_exists($controller)) {
			throw new \Exception($controller.'控制器不存在');
		}
		$action .= 'Action';
		return Loader::make($controller, $action, $argv);
	}

	/**
	 * 注册默认中间件
	 */
	protected function registerMiddleware()
	{
		// 从配置文件加载中间件（config/middleware.php 全注释时 require 返回 1，需做数组校验）
		$middleware = config('middleware');
		$middleware = is_array($middleware) ? $middleware : [];

		// 注册中间件别名
		$aliases = $middleware['aliases'] ?? [];
		foreach ($aliases as $alias => $class) {
			Middleware::alias($alias, $class);
		}
		
		// 注册全局中间件
		$global = $middleware['global'] ?? [];
		foreach ($global as $middlewareClass) {
			Middleware::register($middlewareClass);
		}
	}

	/**
	 * 处理请求
	 */
	private function handleRequest()
	{
		// 获取当前URI
		$uri = $_SERVER['REQUEST_URI'];

		// 移除查询字符串
		$questionPos = strpos($uri, '?');
		if ($questionPos !== false) {
			$uri = substr($uri, 0, $questionPos);
		}

		// 移除前后的斜杠
		$uri = trim($uri, '/');

		// 过滤入口文件名（如index.php）
		$scriptName = basename($_SERVER['SCRIPT_NAME']);
		if (strpos($uri, $scriptName) === 0) {
			$uri = substr($uri, strlen($scriptName));
			$uri = trim($uri, '/');
		}

		// 使用Router类的parse方法解析URI（Router会自动处理URL后缀）
		$parseResult = Router::parse($uri);

		if ($parseResult && count($parseResult) >= 3) {
			$module = $parseResult[0];
			$controller = $parseResult[1];
			$action = $parseResult[2];
			$params = isset($parseResult[3]) ? $parseResult[3] : [];
		} else {
			// 如果解析失败，使用默认值
			$module = Config::get('common.default_module', 'home');
			$controller = Config::get('common.default_controller', 'Index');
			$action = Config::get('common.default_action', 'index');
			$params = [];
		}

		// 使用原有的startApp方法
		return self::startApp($module, $controller, $action, $params);
	}
}
