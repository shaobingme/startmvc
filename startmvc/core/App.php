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
		// CLI 环境不执行 HTTP 分发（$_SERVER['REQUEST_URI'] 等不可用），
		// 命令行脚本加载框架后可直接使用 Config/Db/Cache 等组件
		if (PHP_SAPI === 'cli') {
			return;
		}

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
		if ($response instanceof Response) {
			// 控制器返回 Response 对象时统一交给它发送（状态码/响应头/内容）
			$response->send();
			if (config('trace')) {
				self::outputTrace();
			}
		} elseif (is_string($response)) {
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

		// 剥离URL后缀（如 .html），规则与 Router::parse 一致，保证路由表匹配不受后缀影响
		$urlSuffix = config('common.url_suffix') ?: '';
		if ($urlSuffix !== '' && strlen($uri) > strlen($urlSuffix)) {
			$suffixPos = strrpos($uri, $urlSuffix);
			if ($suffixPos !== false && $suffixPos === strlen($uri) - strlen($urlSuffix)) {
				$uri = substr($uri, 0, $suffixPos);
			}
		}

		$method = strtoupper($_SERVER['REQUEST_METHOD']);
		// 表单方法伪装：POST + _method 模拟 PUT/DELETE/PATCH
		if ($method === 'POST' && isset($_POST['_method'])) {
			$method = strtoupper((string)$_POST['_method']);
		}

		// 加载路由定义并优先匹配（流式 API 与旧配置数组共用一张路由表）
		Router::loadRoutes();
		$match = Router::match($uri, $method);
		if ($match !== null) {
			list($route, $params) = $match;
			// 路由级中间件包裹控制器执行
			return Middleware::pipeline(
				$route['middleware'] ?? [],
				new Request(),
				function ($request) use ($route, $params) {
					return Router::resolveAction($route['action'], $params);
				}
			);
		}

		// 无路由匹配：按 模块/控制器/方法 结构解析 URL（解析后目标不存在由 resolveAction 抛 404）
		$parseResult = Router::parse($uri);
		if ($parseResult && count($parseResult) >= 3) {
			list($module, $controller, $action, $params) = $parseResult;
			return Router::resolveAction("{$module}/{$controller}/{$action}", $params);
		}

		throw new \Exception('页面不存在: /' . $uri, 404);
	}
}
