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
		
		try {
			Exception::init(); 
			$this->loadFunction();
			
			// 创建请求对象
			$request = new Request();
			
			// 通过中间件管道处理请求
			$response = Middleware::run($this, function() {
				return $this->handleRequest();
			});
			
			// 记录结束时间和内存
			$endTime = microtime(true);
			$endMem = memory_get_usage();
			
			// 计算运行时间和内存使用
			self::$trace = [
				'beginTime' => $beginTime,
				'endTime' => $endTime,
				'runtime' => number_format(($endTime - $beginTime) * 1000, 2) . 'ms',
				'memory' => number_format(($endMem - $beginMem) / 1024, 2) . 'KB',
				'files' => get_included_files(),  // 添加加载的文件列表
				'uri' => $_SERVER['REQUEST_URI'],
				'request_method' => $_SERVER['REQUEST_METHOD']
			];
			
			// 输出响应内容
			if (is_string($response)) {
				echo $response;
			} elseif (is_array($response)) {
				header('Content-Type: application/json');
				echo json_encode($response);
			}
			
			// 在页面最后输出追踪信息
			if (config('trace')) {
				echo "\n<!-- Trace Info Start -->\n";
				include __DIR__ . '/tpl/trace.php';
				echo "\n<!-- Trace Info End -->\n";
			}
			
		} catch (\Exception $e) {
			throw $e;
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

	/**
	 * 注册默认中间件
	 */
	protected function registerMiddleware()
	{
		// 从配置文件加载中间件
		$middleware = config('middleware') ?? [];
		
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
		try {
			// 获取当前URI
			$uri = $_SERVER['REQUEST_URI'];
			
			// 移除查询字符串
			if (strpos($uri, '?') !== false) {
				$uri = substr($uri, 0, strpos($uri, '?'));
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
			
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * 显示追踪信息
	 */
	protected static function showTrace()
	{
		// 确保输出在页面最后
		register_shutdown_function(function() {
			// 包含trace模板
			include __DIR__ . '/tpl/trace.php';
		});
	}
}
