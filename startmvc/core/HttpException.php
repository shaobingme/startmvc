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

/**
 * HTTP 异常（abort() 助手的载体）
 *
 * 携带 HTTP 状态码与附加响应头，抛出后由 Exception::handleException()
 * 统一捕获：按状态码渲染错误页（404 复用 tpl/404.php，其余用
 * tpl/http_error.php）；Ajax 请求则输出同状态码的 JSON。
 * 与 HttpResponseException 的分工：后者携带"已构建好的 Response"，
 * 本类只携带"状态码 + 消息"，渲染交给框架统一完成。
 *
 * 状态码限定 400~599（客户端/服务端错误段）：abort 的语义是"中断并报错"，
 * 2xx/3xx 应走正常返回或 redirect()。越界直接抛 InvalidArgumentException，
 * 与 Logger 未知级别 fail-fast 同一取舍——静默兜底只会掩盖调用方笔误。
 */
class HttpException extends \Exception
{
	/**
	 * HTTP 状态码（400~599）
	 * @var int
	 */
	protected $statusCode;

	/**
	 * 附加响应头（键名 => 值）
	 * @var array
	 */
	protected $headers;

	/**
	 * 常见状态码的默认短语：abort(403) 不传消息时兜底，
	 * 避免空消息渲染出空白错误页
	 * @var array
	 */
	private static $reasonPhrases = [
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		408 => 'Request Timeout',
		410 => 'Gone',
		422 => 'Unprocessable Entity',
		429 => 'Too Many Requests',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
	];

	/**
	 * @param int         $statusCode HTTP 状态码（400~599，越界抛 InvalidArgumentException）
	 * @param string      $message    错误消息（面向客户端展示）；留空时用状态码默认短语
	 * @param array       $headers    附加响应头（如 ['X-Reason' => 'quota']）
	 * @param \Throwable|null $previous 异常链
	 */
	public function __construct(int $statusCode, string $message = '', array $headers = [], ?\Throwable $previous = null)
	{
		if ($statusCode < 400 || $statusCode > 599) {
			throw new \InvalidArgumentException(sprintf(
				'HttpException 状态码必须在 400~599 之间，收到 %d（2xx/3xx 请走正常返回或 redirect()）',
				$statusCode
			));
		}
		$this->statusCode = $statusCode;
		$this->headers = $headers;
		// 状态码同时写入 \Exception::$code，沿用既有 getCode()===404 的判定习惯
		parent::__construct($message !== '' ? $message : self::reasonPhrase($statusCode), $statusCode, $previous);
	}

	/**
	 * 获取 HTTP 状态码
	 * @return int
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}

	/**
	 * 获取附加响应头
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * 状态码默认短语（表外状态码回退 "HTTP Error {code}"）
	 * @param int $statusCode
	 * @return string
	 */
	public static function reasonPhrase($statusCode)
	{
		return self::$reasonPhrases[$statusCode] ?? ('HTTP Error ' . $statusCode);
	}
}
