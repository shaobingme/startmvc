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
	 * @param \Throwable $exception
	 * @return void
	 */
	protected static function logException(\Throwable $exception)
	{
		$logPath = ROOT_PATH . 'runtime/logs';
		
		// 确保日志目录存在
		if (!is_dir($logPath)) {
			@mkdir($logPath, 0777, true);
		}
		
		// 如果目录创建失败，尝试使用系统临时目录
		if (!is_dir($logPath)) {
			$logPath = sys_get_temp_dir();
		}
		
		$message = sprintf(
			"[%s] %s in %s:%d\nStack trace:\n%s\n",
			date('Y-m-d H:i:s'),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$exception->getTraceAsString()
		);
		
		$logFile = $logPath . DIRECTORY_SEPARATOR . date('Y-m-d') . '_error.log';
		
		// 使用错误抑制符，避免因写入失败导致的额外异常
		@error_log($message, 3, $logFile);
	}

	/**
	 * 处理异常
	 * @param \Throwable $exception
	 */
	public static function handleException(\Throwable $exception)
	{
		try {
			self::logException($exception);
		} catch (\Exception $e) {
			// 日志记录失败时的处理
		}

		// 设置HTTP状态码
		http_response_code(500);

		// 获取调试模式设置
		$debug = config('debug', true); // 默认为true，确保在配置不存在时也能看到错误

		// AJAX请求处理
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			header('Content-Type: application/json');
			echo json_encode([
				'error' => $exception->getMessage(),
				'trace' => $exception->getTraceAsString()
			]);
			exit;
		}

		// 传递异常对象到错误模板
		$e = $exception; // 为错误模板提供异常对象
		
		// 包含错误模板
		$errorTemplate = CORE_PATH . 'tpl/error.php';
		if (file_exists($errorTemplate)) {
			include $errorTemplate;
		} else {
			echo '<h1>系统错误</h1>';
			echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
			echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
		}
		exit;
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

	/**
	 * 显示友好的错误页面给用户（在生产环境中使用）
	 */
	private static function errorPage($output)
	{
		// 将错误信息作为 GET 参数传递到错误页面
		//$errorPageURL = '/error-page.php?error=' . urlencode($errorMessage);
		//header("Location: $errorPageURL");
		if(config('debug')){
			include 'tpl/error.php';
		}
		exit;
	}
}

// 创建 CustomErrorHandler 实例，自动注册错误处理和异常处理方法
//$customErrorHandler = new CustomErrorHandler();