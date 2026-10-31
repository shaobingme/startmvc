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
	 *
	 * 只构建 common 分组，其余分组由 load() 按需构建。
	 * 先置位 initialized 再构建，避免 load() → initialize() 递归。
	 */
	protected static function init()
	{
		if (self::$initialized) {
			return;
		}
		self::$initialized = true;

		self::$config['common'] = self::build('common');
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
			$first = $parts[0];
			
			// 起点选择：优先「已加载 / 可加载的分组」（config/{name}.php），
			// 其次才是 common 里的嵌套组 —— common 既是分组容器，又装着框架的扁平键，
			// 所以 config('session.cookie_secure') 必须能落到 common.session 上。
			if (isset(self::$config[$first])) {
				$config = self::$config[$first];
			} elseif (isset(self::$config['common'][$first]) && is_array(self::$config['common'][$first])) {
				$config = self::$config['common'][$first];
			} else {
				self::load($first);
				if (!isset(self::$config[$first])) {
					return $default;
				}
				$config = self::$config[$first];
			}
			
			foreach (array_slice($parts, 1) as $part) {
				if (!is_array($config) || !array_key_exists($part, $config)) {
					return $default;
				}
				$config = $config[$part];
			}
			
			return $config;
		}
		
		// 简单键名：先从 common 配置组中查找
		if (isset(self::$config['common'][$key])) {
			return self::$config['common'][$key];
		}
		
		// 如果 common 中没有，尝试自动加载配置文件
		if (!isset(self::$config[$key]) && $key !== 'common') {
			self::load($key);
		}
		
		// 从其他配置组中查找
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
	/**
	 * 加载指定配置文件
	 *
	 * 两层合并（后者覆盖前者）：
	 *   1. 用户配置    config/{file}.php
	 *   2. 环境覆盖    config/{ENV}.php 与 config/local.php 中的同名键
	 *
	 * @param string $file 配置文件名（不含扩展名）
	 * @return array|null 配置数据；配置文件不存在时返回 null
	 */
	public static function load($file)
	{
		// 确保初始化
		self::initialize();

		// 如果配置已经加载过，直接返回
		if (isset(self::$config[$file])) {
			return self::$config[$file];
		}

		if (!self::exists($file)) {
			return null;
		}

		self::$config[$file] = self::build($file);

		return self::$config[$file];
	}

	/**
	 * 判断分组是否存在配置文件
	 * @param string $file 分组名
	 * @return bool
	 */
	private static function exists($file)
	{
		return is_file(self::userPath() . $file . '.php');
	}

	/**
	 * 用户配置目录（统一补末尾分隔符，兼容调用方传入不带分隔符的 CONFIG_PATH）
	 * @return string
	 */
	private static function userPath()
	{
		return rtrim(CONFIG_PATH, '/\\') . DIRECTORY_SEPARATOR;
	}

	/**
	 * 构建一个分组的完整配置：用户配置 → 环境覆盖
	 * @param string $file 分组名
	 * @return array
	 */
	private static function build($file)
	{
		$data = self::read(self::userPath() . $file . '.php');

		return self::deepMerge($data, self::overrides($file));
	}

	/**
	 * 读取一个配置文件，不存在或未返回数组时给空数组
	 * @param string $path 文件路径
	 * @return array
	 */
	private static function read($path)
	{
		if (!is_file($path)) {
			return [];
		}
		$data = require $path;

		return is_array($data) ? $data : [];
	}

	/**
	 * 环境覆盖：config/{ENV}.php 与 config/local.php
	 *
	 * 顶层键即分组名，例如 ['database' => ['connections' => [...]]]；只覆盖写到的键，其余保留。
	 *
	 * @param string $group 分组名
	 * @return array
	 */
	private static function overrides($group)
	{
		$out = [];
		$names = ['local.php'];
		if (defined('ENV')) {
			$names[] = ENV . '.php';
		}

		foreach ($names as $name) {
			$data = self::read(self::userPath() . $name);
			if ($data === []) {
				continue;
			}
			if (isset($data[$group]) && is_array($data[$group])) {
				$out = self::deepMerge($out, $data[$group]);
			}
		}

		return $out;
	}

	/**
	 * 递归合并：关联数组逐键合并，列表（连续数字下标）整体替换
	 * @param array $base 基底
	 * @param array $over 覆盖
	 * @return array
	 */
	private static function deepMerge(array $base, array $over)
	{
		foreach ($over as $key => $value) {
			if (is_array($value) && isset($base[$key]) && is_array($base[$key])
				&& !self::isList($value) && !self::isList($base[$key])) {
				$base[$key] = self::deepMerge($base[$key], $value);
			} else {
				$base[$key] = $value;
			}
		}

		return $base;
	}

	/**
	 * 是否列表（连续数字下标）。PHP 7.2 无 array_is_list
	 * @param array $arr 待判断数组
	 * @return bool
	 */
	private static function isList(array $arr)
	{
		if ($arr === []) {
			return true;
		}

		return array_keys($arr) === range(0, count($arr) - 1);
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