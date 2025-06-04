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
			self::init();
			self::$initialized = true;
		}
	}

	/**
	 * 初始化基础配置
	 * 只加载 common.php 基础配置文件，其他配置文件通过 load() 方法按需加载
	 */
	protected static function init()
	{
		// 只加载基础配置文件 common.php
		$commonFile = CONFIG_PATH . 'common.php';
		if (file_exists($commonFile)) {
			self::$config['common'] = require $commonFile;
		}
		
		// 根据环境加载对应配置文件并合并
		if (file_exists(CONFIG_PATH . ENV . '.php')) {
			$envConfig = require CONFIG_PATH . ENV . '.php';
			foreach ($envConfig as $key => $value) {
				if (isset(self::$config['common'][$key]) && is_array(self::$config['common'][$key]) && is_array($value)) {
					self::$config['common'][$key] = array_merge(self::$config['common'][$key], $value);
				} else {
					self::$config['common'][$key] = $value;
				}
			}
		}
		
		// 加载本地配置（如果存在）
		if (file_exists(CONFIG_PATH . 'local.php')) {
			$localConfig = require CONFIG_PATH . 'local.php';
			foreach ($localConfig as $key => $value) {
				if (isset(self::$config['common'][$key]) && is_array(self::$config['common'][$key]) && is_array($value)) {
					self::$config['common'][$key] = array_merge(self::$config['common'][$key], $value);
				} else {
					self::$config['common'][$key] = $value;
				}
			}
		}
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
			return self::$config;
		}
		
		// 处理点语法
		if (strpos($key, '.') !== false) {
			$parts = explode('.', $key);
			$config = self::$config;
			
			foreach ($parts as $part) {
				if (!is_array($config) || !array_key_exists($part, $config)) {
					return $default;
				}
				$config = $config[$part];
			}
			
			return $config;
		}
		
		// 简单键名直接获取
		return self::$config[$key] ?? $default;
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
			$config = &self::$config;
			
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
			// 简单键名直接设置
			self::$config[$key] = $value;
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
		
		// 如果存在完整的配置组，直接返回
		if (isset(self::$config[$group]) && is_array(self::$config[$group])) {
			return self::$config[$group];
		}
		
		// 否则查找所有以 $group. 开头的配置项
		$result = [];
		foreach (self::$config as $configKey => $configValue) {
			if (is_array($configValue)) {
				foreach ($configValue as $key => $value) {
					if ($configKey === $group || strpos($key, $group . '.') === 0) {
						if ($configKey === $group) {
							$result[$key] = $value;
						} else {
							$subKey = substr($key, strlen($group) + 1);
							$result[$subKey] = $value;
						}
					}
				}
			}
		}
		
		return $result;
	}
}