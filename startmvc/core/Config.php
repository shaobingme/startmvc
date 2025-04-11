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

class Config
{
	/**
	 * 配置缓存
	 * @var array
	 */
	protected static $config = [];

	/**
	 * 加载配置文件
	 * @param string $file 配置文件名(不含扩展名)
	 * @return array
	 */
	public static function load($file = 'app')
	{
		if (!isset(self::$config[$file])) {
			$path = CONFIG_PATH . $file . '.php';
			if (file_exists($path)) {
				self::$config[$file] = require $path;
			} else {
				self::$config[$file] = [];
			}
		}
		
		return self::$config[$file];
	}

	/**
	 * 获取配置项
	 * @param string $key 配置键 (格式: file.key.subkey)
	 * @param mixed $default 默认值
	 * @return mixed
	 */
	public static function get($key, $default = null)
	{
		$keys = explode('.', $key);
		$file = array_shift($keys);
		
		$config = self::load($file);
		
		foreach ($keys as $segment) {
			if (!is_array($config) || !array_key_exists($segment, $config)) {
				return $default;
			}
			$config = $config[$segment];
		}
		
		return $config;
	}

	/**
	 * 设置配置项
	 * @param string $key 配置键
	 * @param mixed $value 配置值
	 * @return void
	 */
	public static function set($key, $value)
	{
		$keys = explode('.', $key);
		$file = array_shift($keys);
		
		if (!isset(self::$config[$file])) {
			self::load($file);
		}
		
		$config = &self::$config[$file];
		
		foreach ($keys as $segment) {
			if (!is_array($config)) {
				$config = [];
			}
			
			if (!array_key_exists($segment, $config)) {
				$config[$segment] = [];
			}
			
			$config = &$config[$segment];
		}
		
		$config = $value;
	}
}