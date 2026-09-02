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
 * HTTP 响应异常
 *
 * 携带一个已构建好的 Response，抛出后由框架统一捕获并发送。
 * 用于替代 Controller 响应方法中的 exit()：既保留"终止后续代码执行"的
 * 语义，又让响应经由统一出口发送，不截断框架的正常收尾流程。
 * 中间件可用 try/catch 包裹 $next() 参与响应后处理。
 */
class HttpResponseException extends \Exception
{
	/**
	 * 待发送的响应对象
	 * @var Response
	 */
	protected $response;

	/**
	 * @param Response $response 已构建好的响应
	 */
	public function __construct(Response $response)
	{
		$this->response = $response;
		parent::__construct('HTTP Response Exception');
	}

	/**
	 * 获取携带的响应对象
	 * @return Response
	 */
	public function getResponse()
	{
		return $this->response;
	}
}
