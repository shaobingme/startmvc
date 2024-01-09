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
		// 注册错误处理方法
		set_error_handler([__CLASS__, 'error'], E_ALL | E_STRICT);

		// 注册异常处理方法
		set_exception_handler([__CLASS__, 'exception']);
	}

	/**
	 * 错误处理方法
	 */
	public static function error($errno, $errstr, $errfile, $errline)
	{
		// 格式化错误信息
		//$msg = sprintf(
		//	"错误:\nType: %d\n信息: %s\n文件: %s\nLine: %s",
		//	$errno,
		//	$errstr,
		//	$errfile,
		//	$errline
		//);
		// 如果错误被 @ 符号抑制，则不处理错误
		if (error_reporting() === 0) {
			return;
		}
		$output=['类型:'.$errno,'错误:'.$errstr,'文件:'.$errfile,'行号:'.$errline];
		// 输出或记录格式化后的错误信息
		//$this->logError($msg);

		// 传递错误信息到错误页面
		self::errorPage($output);

		// 继续执行默认的错误处理
		return false;
	}

	/**
	 * 异常处理方法
	 */
	public static function exception(\Throwable $exception)
	{
		// 格式化异常信息
		$output=['异常:'.$exception->getMessage(),'文件:'.$exception->getFile(),'行号:'.$exception->getLine(),'跟踪:'.str_replace("#", "<br>#", $exception->getTraceAsString())];
		//$msg = sprintf(
		//	"异常:\n
		//	信息: %s\n文件: %s\n行: %s\n跟踪:\n%s",
		//	$exception->getMessage(),
		//	$exception->getFile(),
		//	$exception->getLine(),
		//	$exception->getTraceAsString()
		//);

		// 输出或记录格式化后的异常信息
		//$this->logError($msg);

		// 传递异常信息到错误页面
		self::errorPage($output);

		// 终止脚本执行
		exit(1);
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