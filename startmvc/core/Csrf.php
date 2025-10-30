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
	/**
	 * 配置缓存
	 * @var array|null
	 */
	private static $config = null;
	
	/**
	 * 获取配置项
	 * @param string $key 配置键
	 * @param mixed $default 默认值
	 * @return mixed
	 */
	private static function getConfig($key, $default = null)
	{
		// 首次调用时加载配置
		if (self::$config === null) {
			// 从 common.php 的 csrf 配置组读取
			self::$config = Config::get('csrf', []);
		}
		
		return self::$config[$key] ?? $default;
	}
	
	/**
	 * 获取 Token 名称
	 * @return string
	 */
	private static function getTokenName()
	{
		return self::getConfig('token_name', 'csrf_token');
	}
	
	/**
	 * 获取 Token 有效期
	 * @return int
	 */
	private static function getTokenLifetime()
	{
		return (int)self::getConfig('token_lifetime', 3600);
	}
	
	/**
	 * 获取是否自动删除配置
	 * @return bool
	 */
	private static function getAutoDelete()
	{
		return (bool)self::getConfig('auto_delete', false);
	}
	
	/**
	 * 生成或获取 CSRF Token
	 * 如果 token 已存在且未过期，则返回现有 token
	 * @param bool $forceNew 是否强制生成新 token
	 * @param int|null $lifetime 自定义有效期（秒），null 则使用配置值
	 * @return string
	 */
	public static function token($forceNew = false, $lifetime = null)
	{
		$tokenName = self::getTokenName();
		$tokenTimeName = $tokenName . '_time';
		$tokenLifetime = $lifetime ?? self::getTokenLifetime();
		
		$existingToken = Session::get($tokenName);
		$tokenTime = Session::get($tokenTimeName);
		
		// 如果 token 存在且未过期，直接返回
		if (!$forceNew && $existingToken && $tokenTime) {
			if (time() - $tokenTime < $tokenLifetime) {
				return $existingToken;
			}
		}
		
		// 生成新 token
		$token = bin2hex(random_bytes(32));
		Session::set($tokenName, $token);
		Session::set($tokenTimeName, time());
		
		// 如果指定了自定义有效期，也保存到 session
		if ($lifetime !== null) {
			Session::set($tokenName . '_lifetime', $lifetime);
		}
		
		return $token;
	}

	/**
	 * 验证 CSRF Token
	 * @param bool|null $deleteAfterCheck 是否在验证后删除 token（null 则使用配置值）
	 * @return bool
	 */
	public static function check($deleteAfterCheck = null)
	{
		$tokenName = self::getTokenName();
		$tokenTimeName = $tokenName . '_time';
		$tokenLifetimeName = $tokenName . '_lifetime';
		
		$postToken = Request::post($tokenName);
		$sessionToken = Session::get($tokenName);
		$tokenTime = Session::get($tokenTimeName);
		
		// Token 不存在
		if ($postToken === null || $sessionToken === null) {
			return false;
		}
		
		// 获取 token 有效期（优先使用自定义值）
		$tokenLifetime = Session::get($tokenLifetimeName) ?? self::getTokenLifetime();
		
		// Token 已过期
		if ($tokenTime && (time() - $tokenTime > $tokenLifetime)) {
			self::unsetToken();
			return false;
		}
		
		// 使用 hash_equals 防止时序攻击
		if (!hash_equals($sessionToken, $postToken)) {
			return false;
		}

		// 确定是否删除 token（优先使用参数，其次使用配置）
		$shouldDelete = $deleteAfterCheck ?? self::getAutoDelete();
		if ($shouldDelete) {
			self::unsetToken();
		}
		
		return true;
	}

	/**
	 * 删除 CSRF Token
	 */
	public static function unsetToken()
	{
		$tokenName = self::getTokenName();
		Session::delete($tokenName);
		Session::delete($tokenName . '_time');
		Session::delete($tokenName . '_lifetime');
	}
	
	/**
	 * 刷新 Token（生成新的 token）
	 * @param int|null $lifetime 自定义有效期（秒）
	 * @return string
	 */
	public static function refresh($lifetime = null)
	{
		return self::token(true, $lifetime);
	}
	
	/**
	 * 获取当前 Token 的剩余有效时间（秒）
	 * @return int|null 剩余秒数，如果 token 不存在则返回 null
	 */
	public static function getTokenTTL()
	{
		$tokenName = self::getTokenName();
		$tokenTimeName = $tokenName . '_time';
		$tokenLifetimeName = $tokenName . '_lifetime';
		
		$tokenTime = Session::get($tokenTimeName);
		if (!$tokenTime) {
			return null;
		}
		
		$tokenLifetime = Session::get($tokenLifetimeName) ?? self::getTokenLifetime();
		$elapsed = time() - $tokenTime;
		$remaining = $tokenLifetime - $elapsed;
		
		return max(0, $remaining);
	}
}