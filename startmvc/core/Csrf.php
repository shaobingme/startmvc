<?php
/**
 * StartMVC超轻量级PHP开发框架
 *
 * @author    Shao Bing QQ858292510
 * @copyright Copyright (c) 2020-2022
 * @license   StartMVC 遵循Apache2开源协议发布，需保留开发者信息。
 * @link      http://startmvc.com
 */
 
namespace startmvc\core;

class Csrf
{
	const CSRF_TOKEN_NAME = 'csrf_token';
	public static function token()
	{
		$token = bin2hex(random_bytes(32)); // 更安全的随机令牌生成
		Session::set(self::CSRF_TOKEN_NAME, $token);
		return $token;
	}

	public static function check()
	{
		$postToken = Request::post(self::CSRF_TOKEN_NAME);
		$sessionToken = Session::get(self::CSRF_TOKEN_NAME);
		// 使用严格类型比较
		if ($postToken === null || $postToken !== $sessionToken) {
			return false; // 早期返回简化逻辑
		}

		self::unsetToken(); // 验证后删除令牌
		return true;
	}

	public static function unsetToken()
	{
		Session::delete(self::CSRF_TOKEN_NAME);
	}
}