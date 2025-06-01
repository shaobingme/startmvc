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
	 * 初始化标记
	 */
	private static $initialized = false;
	
	/**
	 * 自动初始化
	 */
	private static function initialize()
	{
		if (!self::$initialized) {
			self::loadCommonConfig();
			self::$initialized = true;
		}
	}

	/**
	 * 加载公共配置
	 */
	protected static function loadCommonConfig()
	{
		// 加载基础配置文件
		$config = require CONFIG_PATH . 'common.php';
		
		// 根据环境加载对应配置文件并合并
		if (file_exists(CONFIG_PATH . ENV . '.php')) {
			$config = array_merge($config, require CONFIG_PATH . ENV . '.php');
		}
		
		// 加载本地配置（如果存在）
		if (file_exists(CONFIG_PATH . 'local.php')) {
			$config = array_merge($config, require CONFIG_PATH . 'local.php');
		}
		
		self::$config['common'] = $config;
	}

	/**
	 * 获取配置项
	 * @param string $key 配置键，支持file.key.subkey格式
	 * @param mixed $default 默认值
	 * @return mixed
	 */
	public static function get($key = null, $default = null)
	{
		// 确保初始化
		self::initialize();
		
		// 不带参数时返回所有配置
		if ($key === null) {
			return self::$config['common'];
		}
		
		// 处理点语法
		if (strpos($key, '.') !== false) {
			$parts = explode('.', $key);
			$file = array_shift($parts);
			
			// 尝试加载配置文件
			if (file_exists(CONFIG_PATH . $file . '.php') && !isset(self::$config[$file])) {
				self::load($file);
			}
			
			// 确定从哪个配置数组中查找
			$config = isset(self::$config[$file]) ? self::$config[$file] : self::$config['common'];
			
			// 逐级查找配置项
			foreach ($parts as $part) {
				if (!is_array($config) || !array_key_exists($part, $config)) {
					return $default;
				}
				$config = $config[$part];
			}
			
			return $config;
		}
		
		// 简单键名直接从common中获取
		return self::$config['common'][$key] ?? $default;
	}

	/**
	 * 设置配置项
	 * @param string $key 配置键
	 * @param mixed $value 配置值
	 * @return bool
	 */
	public static function set($key, $value)
	{
		// 确保初始化
		self::initialize();
		
		if (strpos($key, '.') !== false) {
			$parts = explode('.', $key);
			$file = array_shift($parts);
			
			// 确保配置数组已初始化
			if (!isset(self::$config[$file])) {
				if (file_exists(CONFIG_PATH . $file . '.php')) {
					self::load($file);
				} else {
					self::$config[$file] = [];
				}
			}
			
			// 引用配置数组
			$config = &self::$config[$file];
			
			// 逐级设置配置项
			$count = count($parts);
			foreach ($parts as $i => $part) {
				if ($i === $count - 1) {
					$config[$part] = $value;
				} else {
					if (!isset($config[$part]) || !is_array($config[$part])) {
						$config[$part] = [];
					}
					$config = &$config[$part];
				}
			}
		} else {
			// 简单键名直接设置到common中
			self::$config['common'][$key] = $value;
		}
		
		return true;
	}

	/**
	 * 检查配置项是否存在
	 * @param string $key 配置键
	 * @return bool
	 */
	public static function has($key)
	{
		return self::get($key) !== null;
	}

	/**
	 * 加载指定配置文件
	 * @param string $file 配置文件名（不含扩展名）
	 * @return array|null 配置数据
	 */
	public static function load($file)
	{
		// 确保初始化
		self::initialize();
		
		$filePath = CONFIG_PATH . $file . '.php';
		
		if (!file_exists($filePath)) {
			return null;
		}
		
		// 如果配置已经加载过，直接返回
		if (isset(self::$config[$file])) {
			return self::$config[$file];
		}
		
		// 加载配置文件
		self::$config[$file] = require $filePath;
		
		return self::$config[$file];
	}

	/**
	 * 获取配置分组
	 * @param string $group 配置分组名
	 * @return array
	 */
	public static function group($group)
	{
		// 确保初始化
		self::initialize();
		
		$result = [];
		foreach (self::$config['common'] as $key => $value) {
			if (strpos($key, $group . '.') === 0) {
				$subKey = substr($key, strlen($group) + 1);
				$result[$subKey] = $value;
			}
		}
		
		return $result;
	}
}