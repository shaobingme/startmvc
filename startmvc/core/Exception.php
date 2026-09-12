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
class Exception
{
	/**
	 * 构造函数，注册错误处理和异常处理方法
	 */
	private function __construct()
	{

	}
	public static function init() {
		// 设置错误处理函数
		set_error_handler([__CLASS__, 'handleError']);
		
		// 设置异常处理函数
		set_exception_handler([__CLASS__, 'handleException']);
		
		// 设置致命错误处理
		register_shutdown_function([__CLASS__, 'handleShutdown']);
	}

	/**
	 * 处理错误
	 * @param int $level 错误级别
	 * @param string $message 错误消息
	 * @param string $file 文件
	 * @param int $line 行号
	 * @throws \ErrorException
	 */
	public static function handleError($level, $message, $file, $line)
	{
		if (error_reporting() & $level) {
			throw new \ErrorException($message, 0, $level, $file, $line);
		}
	}

	/**
	 * 记录异常到日志
	 *
	 * 统一经由 Logger（与 DB、中间件等使用同一通道、同一格式），
	 * 不再自建目录与文件名 —— 原先这里用 error_log() 写 {date}_error.log，
	 * 而 Logger 写 {date}.log，同一站点的日志被劈成两个互不相干的通道。
	 *
	 * Logger 内部保证写入失败只返回 false 而不抛异常，调用方再包一层 Throwable 兜底。
	 *
	 * @param \Throwable $exception
	 * @return void
	 */
	protected static function logException(\Throwable $exception)
	{
		$message = sprintf(
			"%s in %s:%d\nStack trace:\n%s",
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$exception->getTraceAsString()
		);

		(new Logger())->error($message);
	}

	/**
	 * 处理异常
	 * @param \Throwable $exception
	 */
	public static function handleException(\Throwable $exception)
	{
		// 响应异常：直接发送其携带的 Response（响应方法在管道之外被调用时的兜底出口）
		if ($exception instanceof HttpResponseException) {
			$exception->getResponse()->send();
			exit;
		}

		try {
			self::logException($exception);
		} catch (\Throwable $e) {
			// 日志记录失败时的兜底。
			// 必须捕获 \Throwable：PHP 8 下 \Error / \TypeError / \ParseError 不是 \Exception
			// 的子类，只写 catch (\Exception) 会让日志环节的致命错误反过来中断异常处理。
		}

		// 404：路由未命中或目标不存在，返回正确的 404 状态码（而非一律 500）
		if ($exception->getCode() === 404) {
			if (self::isAjaxRequest()) {
				(new Response())->json(['error' => 'Not Found', 'code' => 404], 404)
					->withTrace(false)->send();
				exit;
			}

			// 模板渲染到缓冲区后交由 Response 发送：状态码 / 响应头 / 输出收敛到一个出口。
			// withTrace(false) 保持历史行为（错误页不附加调试面板）
			ob_start();
			$notFoundTemplate = __DIR__ . '/tpl/404.php';
			if (file_exists($notFoundTemplate)) {
				include $notFoundTemplate;
			} else {
				echo '<h1>404 Not Found</h1><p>页面不存在</p>';
			}
			(new Response())->html(ob_get_clean(), 404)->withTrace(false)->send();
			exit;
		}

		// 获取调试模式设置
		// 默认为 false：配置文件丢失时按生产环境处理，避免意外开启调试导致信息泄露
		$debug = config('debug', false);

		// AJAX请求处理
		if (self::isAjaxRequest()) {
			// 详细信息（错误消息+堆栈）仅在调试模式返回；
			// 生产环境返回通用提示，防止泄露 SQL、文件路径、数据库账号等敏感内容
			$payload = $debug
				? ['error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]
				: ['error' => '服务器内部错误，请稍后再试'];
			(new Response())->json($payload, 500)->withTrace(false)->send();
			exit;
		}

		// 传递异常对象到错误模板
		$e = $exception; // 为错误模板提供异常对象

		// 包含错误模板（同样渲染到缓冲区后由 Response 统一发送）
		ob_start();
		$errorTemplate = __DIR__ . '/tpl/error.php';
		if (file_exists($errorTemplate)) {
			include $errorTemplate;
		} else {
			echo '<h1>系统错误</h1>';
			echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
			echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
		}
		(new Response())->html(ob_get_clean(), 500)->withTrace(false)->send();
		exit;
	}

	/**
	 * 是否为 AJAX 请求（读取 X-Requested-With 请求头）
	 *
	 * Exception 是纯静态上下文，拿不到已绑定的 Request 实例，故在此统一判断，
	 * 避免同一段判断在 404 与 500 两个分支里各写一遍。
	 * @return bool
	 */
	protected static function isAjaxRequest()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
			&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}

	/**
	 * 处理程序结束时的错误
	 */
	public static function handleShutdown()
	{
		$error = error_get_last();
		
		if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
			self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}
}